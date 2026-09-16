<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Services\CompanySettingsStore;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsThemeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_settings_theme_tab_uses_wai_aria_contract_and_registry_cards(): void
    {
        $this->withSession(['active_company' => 'usaha-demo', 'company_role' => 'staff'])
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

        $this->withSession(['active_company' => 'usaha-demo', 'company_role' => 'staff'])
            ->get('/app/settings?tab=profile')
            ->assertOk()
            ->assertSee('id="tab-profile"', false)
            ->assertSee('aria-selected="true"', false);
    }

    public function test_theme_selection_is_persisted_per_company_and_seen_by_another_session(): void
    {
        $this->withSession(['company_role' => 'owner']);

        Livewire::withQueryParams(['company' => 'usaha-bersama'])
            ->test(Settings::class)
            ->call('selectTheme', 'c')
            ->assertSet('selectedTheme', 'c');

        Storage::disk('local')->assertExists('json/usaha-bersama/settings.json');
        $this->assertSame(
            'c',
            json_decode(Storage::disk('local')->get('json/usaha-bersama/settings.json'), true, flags: JSON_THROW_ON_ERROR)['theme'],
        );

        $this->app['session']->flush();
        $this->withSession(['active_company' => 'usaha-bersama', 'company_role' => 'staff']);

        Livewire::test(Settings::class)
            ->assertSet('selectedTheme', 'c')
            ->assertSet('canManageTheme', false)
            ->call('selectTheme', 'd')
            ->assertStatus(403);

        $this->assertSame(
            'c',
            json_decode(Storage::disk('local')->get('json/usaha-bersama/settings.json'), true, flags: JSON_THROW_ON_ERROR)['theme'],
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

        Livewire::withQueryParams(['company' => 'usaha-demo'])
            ->test(Settings::class)
            ->call('selectTheme', 'tema-asing')
            ->assertStatus(404);

        Storage::disk('local')->assertMissing('json/usaha-demo/settings.json');
    }

    public function test_owner_cannot_switch_company_by_tampering_with_the_query_string(): void
    {
        $this->withSession(['active_company' => 'usaha-a', 'company_role' => 'owner']);

        Livewire::withQueryParams(['company' => 'usaha-b'])
            ->test(Settings::class)
            ->assertStatus(403);

        Storage::disk('local')->assertMissing('json/usaha-b/settings.json');
    }

    public function test_theme_update_preserves_other_settings_and_rejects_corrupt_json(): void
    {
        $path = 'json/usaha-aman/settings.json';
        Storage::disk('local')->put($path, json_encode(['tax_mode' => 'inclusive'], JSON_THROW_ON_ERROR));

        $this->withSession(['active_company' => 'usaha-aman', 'company_role' => 'owner']);
        Livewire::test(Settings::class)->call('selectTheme', 'b')->assertOk();

        $settings = json_decode(Storage::disk('local')->get($path), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('inclusive', $settings['tax_mode']);
        $this->assertSame('b', $settings['theme']);

        Storage::disk('local')->put($path, '{corrupt');

        try {
            app(CompanySettingsStore::class)->update('usaha-aman', fn (array $value): array => $value);
            $this->fail('JSON rusak harus ditolak.');
        } catch (JsonException) {
            $this->assertSame('{corrupt', Storage::disk('local')->get($path));
        }
    }
}
