<?php

namespace Tests\Feature;

use App\Contracts\CompanySettingsStore;
use App\Livewire\Settings;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsThemeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('company-json');
    }

    public function test_settings_theme_tab_uses_wai_aria_contract_and_registry_cards(): void
    {
        $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'staff'])
            ->get('/app/settings')
            ->assertOk()
            ->assertSee('role="tablist"', false)
            ->assertSee('aria-orientation="horizontal"', false)
            ->assertSee('role="tab"', false)
            ->assertSee('aria-controls="panel-theme"', false)
            ->assertSee('role="tabpanel"', false)
            ->assertSee('A — Slate + Emerald')
            ->assertSee('E — Terang')
            ->assertDontSee('localStorage', false);

        $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'staff'])
            ->get('/app/settings?tab=profile')
            ->assertOk()
            ->assertSee('id="tab-profile"', false)
            ->assertSee('aria-selected="true"', false);
    }

    public function test_theme_selection_is_persisted_per_company_and_seen_by_another_session(): void
    {
        Storage::disk('company-json')->put(
            'json/klinik-sehat/business_identity.json',
            json_encode(['preset' => 'klinik'], JSON_THROW_ON_ERROR),
        );
        $this->withSession(['company_role' => 'owner']);

        Livewire::withQueryParams(['company' => 'klinik-sehat'])
            ->test(Settings::class)
            ->call('selectTheme', 'c')
            ->assertSet('selectedTheme', 'c');

        Storage::disk('company-json')->assertExists('json/klinik-sehat/settings.json');
        $this->assertSame(
            'c',
            json_decode(Storage::disk('company-json')->get('json/klinik-sehat/settings.json'), true, flags: JSON_THROW_ON_ERROR)['theme'],
        );

        $this->app['session']->flush();
        $this->withSession(['active_company' => 'klinik-sehat', 'company_role' => 'staff']);

        Livewire::test(Settings::class)
            ->assertSet('selectedTheme', 'c')
            ->assertSet('canManageTheme', false)
            ->call('selectTheme', 'd')
            ->assertStatus(403);

        $this->assertSame(
            'c',
            json_decode(Storage::disk('company-json')->get('json/klinik-sehat/settings.json'), true, flags: JSON_THROW_ON_ERROR)['theme'],
        );

        $this->get('/app/hrd')
            ->assertOk()
            ->assertSee('data-theme="c"', false);
    }

    public function test_invalid_company_path_and_unknown_theme_fail_closed(): void
    {
        Livewire::withQueryParams(['company' => '../usaha-lain'])
            ->test(Settings::class)
            ->assertStatus(404);

        $this->withSession(['company_role' => 'owner']);

        Livewire::withQueryParams(['company' => 'bengkel-arka'])
            ->test(Settings::class)
            ->call('selectTheme', 'tema-asing')
            ->assertStatus(404);

        Storage::disk('company-json')->assertMissing('json/bengkel-arka/settings.json');
    }

    public function test_demo_query_can_switch_only_to_an_allowlisted_company(): void
    {
        $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'owner']);

        Livewire::withQueryParams(['company' => 'salon-ayu'])
            ->test(Settings::class)
            ->assertSet('companySlug', 'salon-ayu');

        $this->assertSame('salon-ayu', session('active_company'));
    }

    public function test_theme_action_revalidates_company_context_and_role(): void
    {
        $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'owner']);
        $component = Livewire::test(Settings::class);

        session(['active_company' => 'klinik-sehat']);
        $component->call('selectTheme', 'b')->assertStatus(403);
        Storage::disk('company-json')->assertMissing('json/bengkel-arka/settings.json');

        session(['active_company' => 'bengkel-arka', 'company_role' => 'owner']);
        $roleComponent = Livewire::test(Settings::class);
        session(['company_role' => 'staff']);
        $roleComponent
            ->call('selectTheme', 'b')
            ->assertStatus(403);
        Storage::disk('company-json')->assertMissing('json/bengkel-arka/settings.json');
    }

    public function test_theme_update_preserves_other_settings_and_rejects_corrupt_json(): void
    {
        $path = 'json/salon-ayu/settings.json';
        Storage::disk('company-json')->put($path, json_encode(['tax_mode' => 'inclusive'], JSON_THROW_ON_ERROR));

        $this->withSession(['active_company' => 'salon-ayu', 'company_role' => 'owner']);
        Livewire::test(Settings::class)->call('selectTheme', 'b')->assertOk();

        $settings = json_decode(Storage::disk('company-json')->get($path), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('inclusive', $settings['tax_mode']);
        $this->assertSame('b', $settings['theme']);

        Storage::disk('company-json')->put($path, '{corrupt');

        try {
            app(CompanySettingsStore::class)->update('salon-ayu', fn (array $value): array => $value);
            $this->fail('JSON rusak harus ditolak.');
        } catch (JsonException) {
            $this->assertSame('{corrupt', Storage::disk('company-json')->get($path));
        }
    }
}
