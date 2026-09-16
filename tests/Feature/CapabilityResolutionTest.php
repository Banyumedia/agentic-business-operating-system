<?php

namespace Tests\Feature;

use App\Services\FeatureResolver;
use App\Services\TerminologyResolver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class CapabilityResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('company-json');
        $this->writeSettings('klinik-sehat', ['preset' => 'klinik']);
        $this->writeSettings('bengkel-arka', ['preset' => 'bengkel']);
        $this->writeSettings('salon-ayu', ['preset' => 'salon']);
    }

    public function test_terminology_resolves_company_override_then_preset_then_global_default(): void
    {
        $this->withSession(['active_company' => 'klinik-sehat']);

        $resolver = app(TerminologyResolver::class);
        $this->assertSame('Pasien', $resolver->resolve('contact'));
        $this->assertSame('Vendor', $resolver->resolve('vendor'));
        $this->assertSame('Pasien', term('contact'));
        $this->assertSame('Pasien', Blade::render('@term("contact")'));

        $this->writeSettings('klinik-sehat', [
            'preset' => 'klinik',
            'terminology' => ['contact' => 'Anggota'],
        ]);
        $resolver->flushCache();

        $this->assertSame('Anggota', $resolver->resolve('contact'));
    }

    public function test_company_switch_does_not_leak_cached_terms_or_features(): void
    {
        $this->writeSettings('klinik-sehat', [
            'preset' => 'klinik',
            'features' => ['projects' => true],
            'terminology' => ['contact' => 'Anggota'],
        ]);
        $this->withSession(['active_company' => 'klinik-sehat']);

        $terms = app(TerminologyResolver::class);
        $features = app(FeatureResolver::class);
        $this->assertSame('Anggota', $terms->resolve('contact'));
        $this->assertTrue($features->enabled('projects'));

        session(['active_company' => 'bengkel-arka']);

        $this->assertSame('Pelanggan', $terms->resolve('contact'));
        $this->assertFalse($features->enabled('bookings'));
        $this->assertTrue($features->enabled('projects'));
    }

    public function test_feature_override_wins_over_preset_and_missing_capability_is_disabled(): void
    {
        $this->writeSettings('bengkel-arka', [
            'preset' => 'bengkel',
            'features' => ['projects' => false, 'bookings' => true],
        ]);
        $this->withSession(['active_company' => 'bengkel-arka']);

        $resolver = app(FeatureResolver::class);

        $this->assertFalse($resolver->enabled('projects'));
        $this->assertTrue($resolver->enabled('bookings'));
        $this->assertFalse($resolver->enabled('pos.tables'));
        $this->assertFalse($resolver->enabled('unknown'));
        $this->assertTrue($resolver->hasAny(['unknown', 'bookings']));
    }

    public function test_unknown_term_key_throws_in_local_environment(): void
    {
        $this->withSession(['active_company' => 'klinik-sehat']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Kunci istilah tidak terdaftar');

        app(TerminologyResolver::class)->resolve('unknown');
    }

    public function test_unknown_preset_and_invalid_overrides_fail_closed(): void
    {
        $this->writeSettings('salon-ayu', [
            'preset' => 'tidak-ada',
            'features' => ['contacts' => true],
        ]);
        $this->withSession(['active_company' => 'salon-ayu']);

        try {
            app(FeatureResolver::class)->enabled('contacts');
            $this->fail('Preset asing seharusnya ditolak.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Preset company tidak tersedia', $exception->getMessage());
        }

        $this->writeSettings('salon-ayu', [
            'preset' => 'salon',
            'terminology' => ['contact' => ''],
        ]);
        app(TerminologyResolver::class)->flushCache();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Override istilah tidak valid');
        app(TerminologyResolver::class)->resolve('contact');
    }

    public function test_malformed_settings_and_broken_capability_dependencies_fail_closed(): void
    {
        Storage::disk('company-json')->put('json/bengkel-arka/settings.json', '[]');
        $this->withSession(['active_company' => 'bengkel-arka']);

        try {
            app(FeatureResolver::class)->enabled('contacts');
            $this->fail('Settings berbentuk list seharusnya ditolak.');
        } catch (\JsonException $exception) {
            $this->assertStringContainsString('harus berupa object', $exception->getMessage());
        }

        $this->writeSettings('bengkel-arka', [
            'preset' => 'bengkel',
            'features' => ['approval_flow' => false],
        ]);
        app(FeatureResolver::class)->flushCache();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('system.ai_agent membutuhkan approval_flow');
        app(FeatureResolver::class)->enabled('system.ai_agent');
    }

    /** @param array<string, mixed> $settings */
    private function writeSettings(string $company, array $settings): void
    {
        Storage::disk('company-json')->put(
            "json/{$company}/settings.json",
            json_encode($settings, JSON_THROW_ON_ERROR),
        );
    }
}
