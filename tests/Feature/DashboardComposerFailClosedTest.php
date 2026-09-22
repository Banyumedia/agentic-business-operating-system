<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Contracts\PresetSource;
use App\Services\Dashboard\CashFlowCalculator;
use App\Services\Dashboard\DashboardComposer;
use App\Services\Dashboard\WidgetRegistry;
use App\Services\FeatureResolver;
use App\Services\TerminologyResolver;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Membuktikan dua cabang fail-closed di `DashboardComposer::compose()` benar-benar
 * melempar apa yang mereka janjikan.
 *
 * Keduanya tidak pernah diuji, dan `InvalidArgumentException` tidak pernah
 * di-import di berkasnya - jadi begitu data runtime menyimpang dari preset,
 * yang muncul bukan pesan fail-closed melainkan "Class not found". Kesalahan
 * seperti ini hanya terlihat saat produksi sedang bermasalah.
 */
class DashboardComposerFailClosedTest extends TestCase
{
    public function test_non_string_widget_declaration_fails_closed_with_a_clear_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Deklarasi widget dashboard tidak valid.');

        $this->composerWithIndustryZone([['bukan_widget' => 'x']])->compose();
    }

    public function test_unavailable_widget_fails_closed_instead_of_rendering_a_partial_dashboard(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Widget dashboard tidak tersedia untuk company aktif: widget_tidak_ada');

        $this->composerWithIndustryZone([['widget' => 'widget_tidak_ada']])->compose();
    }

    /** @param list<array<string, mixed>> $industryZone */
    private function composerWithIndustryZone(array $industryZone): DashboardComposer
    {
        $context = app(CompanyContext::class);
        $context->setCurrent('bengkel-arka');

        $real = app(PresetSource::class);
        $presetKey = $context->preset();
        $preset = $real->find($presetKey) ?? [];
        $preset['dashboard']['industry_zone'] = $industryZone;

        $presets = new class($preset, $presetKey) implements PresetSource
        {
            /** @param array<string, mixed> $preset */
            public function __construct(private readonly array $preset, private readonly string $key) {}

            public function all(): array
            {
                return [$this->preset];
            }

            public function find(string $key): ?array
            {
                return $key === $this->key ? $this->preset : null;
            }
        };

        return new DashboardComposer(
            $context,
            app(EntityRepository::class),
            $presets,
            app(FeatureResolver::class),
            app(TerminologyResolver::class),
            app(WidgetRegistry::class),
            app(CashFlowCalculator::class),
        );
    }
}
