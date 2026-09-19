<?php

namespace Tests\Feature;

use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Livewire\Settings;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsCapabilityTabsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('company-json');
        Storage::disk('company-json')->put(
            'json/bengkel-arka/business_identity.json',
            json_encode(['id' => 1, 'name' => 'Bengkel Arka', 'preset' => 'bengkel', 'tax_mode' => 'non_taxable'], JSON_THROW_ON_ERROR),
        );
    }

    public function test_tabs_come_from_registry_and_team_tab_is_absent_from_dom_for_staff(): void
    {
        $staffHtml = $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'staff'])
            ->get('/app/settings')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="tab-team"', $staffHtml);
        $this->assertStringNotContainsString('Tim & Akses', $staffHtml);

        $ownerHtml = $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'owner'])
            ->get('/app/settings')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="tab-team"', $ownerHtml);
    }

    public function test_erasure_tab_is_deep_linkable_and_mounted_for_owner_but_absent_for_staff(): void
    {
        $ownerHtml = $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'owner'])
            ->get('/app/settings/erasure')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="tab-erasure"', $ownerHtml);
        $this->assertStringContainsString('Hapus Data', $ownerHtml);

        $staffHtml = $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'staff'])
            ->get('/app/settings')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="tab-erasure"', $staffHtml);
        $this->assertStringNotContainsString('Penghapusan Pelanggan', $staffHtml);
    }

    public function test_preset_dropdown_lists_a_fake_preset_from_preset_source_not_hardcoded_names(): void
    {
        $this->app->bind(PresetSource::class, fn () => new class implements PresetSource
        {
            public function all(): array
            {
                return [[
                    'key' => 'zz_fixture',
                    'name' => 'Preset Fixture Uji ZZ',
                    'tier' => 'A',
                    'capabilities' => ['contacts' => true],
                    'terminology' => [],
                    'workflows' => [],
                    'dashboard' => ['industry_zone' => []],
                    'menus' => ['order' => []],
                ]];
            }

            public function find(string $key): ?array
            {
                return collect($this->all())->firstWhere('key', $key);
            }
        });

        $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'owner'])
            ->get('/app/settings?tab=features')
            ->assertOk()
            ->assertSee('Preset Fixture Uji ZZ')
            ->assertSee('value="zz_fixture"', false);
    }

    public function test_settings_source_files_do_not_hardcode_a_fixed_list_of_preset_names(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/settings.blade.php'));
        $component = file_get_contents(app_path('Livewire/Settings.php'));

        foreach (['bengkel', 'klinik', 'salon'] as $presetKey) {
            $this->assertStringNotContainsString("'{$presetKey}'", $blade);
            $this->assertStringNotContainsString("'{$presetKey}'", $component);
        }
    }

    public function test_owner_can_update_terminology_and_render_reflects_it_immediately(): void
    {
        $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'owner']);

        Livewire::test(Settings::class)
            ->assertSet('terminologyForm.contact', 'Pelanggan')
            ->set('terminologyForm.contact', 'Jemaah')
            ->call('updateTerminology', 'contact')
            ->assertSet('terminologyForm.contact', 'Jemaah')
            ->assertSee('Jemaah');

        $settings = json_decode(
            Storage::disk('company-json')->get('json/bengkel-arka/settings.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->assertSame('Jemaah', $settings['terminology']['contact']);
        $this->assertSame('Jemaah', $settings['terminology']['contacts']);
    }

    public function test_updating_terminology_rejects_empty_value_without_writing(): void
    {
        $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'owner']);

        Livewire::test(Settings::class)
            ->set('terminologyForm.contact', '   ')
            ->call('updateTerminology', 'contact')
            ->assertSee('tidak boleh kosong');

        Storage::disk('company-json')->assertMissing('json/bengkel-arka/settings.json');
    }

    public function test_staff_cannot_update_terminology_or_preset(): void
    {
        $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'staff']);

        Livewire::test(Settings::class)
            ->call('updateTerminology', 'contact')
            ->assertStatus(403);

        Livewire::test(Settings::class)
            ->call('updatePreset', 'klinik')
            ->assertStatus(403);

        Storage::disk('company-json')->assertMissing('json/bengkel-arka/settings.json');
    }

    public function test_terminology_update_revalidates_active_company_and_role_server_side(): void
    {
        $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'owner']);
        $component = Livewire::test(Settings::class);

        session(['active_company' => 'klinik-sehat']);
        $component->call('updateTerminology', 'contact')->assertStatus(403);
        Storage::disk('company-json')->assertMissing('json/bengkel-arka/settings.json');
    }

    public function test_updating_terminology_with_unknown_key_is_not_found(): void
    {
        $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'owner']);

        Livewire::test(Settings::class)
            ->set('terminologyForm.unknown_key', 'x')
            ->call('updateTerminology', 'unknown_key')
            ->assertStatus(404);
    }

    public function test_owner_can_change_preset_and_unknown_preset_is_rejected(): void
    {
        $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'owner']);

        Livewire::test(Settings::class)
            ->call('updatePreset', 'klinik')
            ->assertSet('selectedPreset', 'klinik')
            ->assertSee('Kunjungan');

        $settings = json_decode(
            Storage::disk('company-json')->get('json/bengkel-arka/settings.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->assertSame('klinik', $settings['preset']);

        app(CompanySettingsStore::class)->update('bengkel-arka', fn (array $settings) => [...$settings, 'preset' => 'bengkel']);

        Livewire::test(Settings::class)
            ->call('updatePreset', 'preset_hantu')
            ->assertStatus(404);
    }

    public function test_flow_subsection_shows_stages_and_backward_transition_notes_from_active_preset(): void
    {
        $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'owner']);

        $this->get('/app/settings?tab=features')
            ->assertOk()
            ->assertSee('Pengerjaan')
            ->assertSee('Wajib catatan')
            ->assertSee('Tahap akhir');
    }
}
