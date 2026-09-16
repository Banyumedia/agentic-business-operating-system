<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Livewire\CommandPalette;
use App\Livewire\DummyModule;
use App\Services\CompanySettingsStore;
use App\Services\DynamicMenuRegistry;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class ModuleSidebarTest extends TestCase
{
    public function test_disabled_module_is_absent_from_dom_and_direct_route_is_forbidden(): void
    {
        $html = $this->get('/?company=salon-ayu')->assertOk()->getContent();

        $this->assertStringNotContainsString('/app/projects', $html);
        $this->get('/app/projects?company=salon-ayu')->assertForbidden();
    }

    public function test_company_terminology_is_used_in_sidebar_and_route_content(): void
    {
        $this->get('/app/contacts?company=klinik-sehat')
            ->assertOk()
            ->assertSee('Pasien')
            ->assertSee('Daftar Pasien');

        $this->get('/app/hrd/employees?company=salon-ayu')
            ->assertOk()
            ->assertSee('Data Terapis');
    }

    public function test_route_maps_module_path_to_screen_pattern_and_entity(): void
    {
        $this->get('/app/contacts/deals?company=klinik-sehat')
            ->assertOk()
            ->assertSee('Pipeline Kunjungan')
            ->assertSee('pipeline')
            ->assertSee('deals');
    }

    public function test_unknown_module_and_unknown_subpath_are_not_found(): void
    {
        $this->get('/app/modul-hantu?company=bengkel-arka')->assertNotFound();
        $this->get('/app/contacts/tidak-ada?company=bengkel-arka')->assertNotFound();
    }

    public function test_disabled_nested_capability_route_is_forbidden(): void
    {
        $this->get('/app/contacts/deals?company=bengkel-arka')->assertForbidden();
        $this->get('/app/pos?company=klinik-sehat')->assertForbidden();
    }

    public function test_settings_deep_links_are_not_captured_by_the_generic_module_route(): void
    {
        $html = $this->get('/app/settings/features?company=bengkel-arka')
            ->assertOk()
            ->assertSee('id="tab-features"', false)
            ->assertSee('aria-selected="true"', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
    }

    public function test_universal_dashboard_and_settings_routes_remain_available(): void
    {
        $this->get('/app/dashboard?company=salon-ayu')->assertOk();
        $this->get('/app/settings?company=salon-ayu')->assertOk();
    }

    public function test_command_palette_hides_routes_for_disabled_capabilities(): void
    {
        app(CompanyContext::class)->setCurrent('salon-ayu');

        Livewire::test(CommandPalette::class)
            ->set('search', 'Pekerjaan')
            ->assertDontSee('/app/projects');
    }

    public function test_command_palette_uses_company_terminology(): void
    {
        app(CompanyContext::class)->setCurrent('klinik-sehat');

        Livewire::test(CommandPalette::class)
            ->set('search', 'Pasien')
            ->assertSee('/app/contacts')
            ->set('search', 'Pengaturan')
            ->assertSee('/app/settings');
    }

    public function test_sidebar_has_single_current_page_and_mobile_navigation_controls(): void
    {
        $html = $this->get('/app/projects?company=bengkel-arka')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertStringContainsString('aria-controls="module-sidebar"', $html);
        $this->assertStringContainsString('x-on:keydown.escape.window', $html);
        $this->assertStringContainsString('x-trap.noscroll', $html);
        $this->assertStringContainsString('x-bind:inert', $html);
        $this->assertStringContainsString('aria-label="Buka pencarian universal"', $html);
        $this->assertStringContainsString('aria-label="Tutup pencarian"', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('<html lang="id"', $html);
    }

    public function test_route_derived_livewire_properties_cannot_be_tampered(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(DummyModule::class, ['module' => 'projects'])
            ->set('module', 'contacts');
    }

    public function test_livewire_render_rechecks_capability_after_revocation(): void
    {
        Storage::fake('company-json');
        Storage::disk('company-json')->put(
            'json/bengkel-arka/business_identity.json',
            json_encode(['preset' => 'bengkel'], JSON_THROW_ON_ERROR),
        );
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $component = Livewire::test(DummyModule::class, ['module' => 'projects'])
            ->assertOk();

        app(CompanySettingsStore::class)->update('bengkel-arka', static function (array $settings): array {
            $settings['features']['projects'] = false;

            return $settings;
        });

        $component->call('$refresh')->assertForbidden();
    }

    public function test_menu_labels_resolve_dictionary_terms_instead_of_hardcoded_defaults(): void
    {
        Storage::fake('company-json');
        Storage::disk('company-json')->put(
            'json/bengkel-arka/business_identity.json',
            json_encode(['preset' => 'bengkel'], JSON_THROW_ON_ERROR),
        );
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        app(CompanySettingsStore::class)->update('bengkel-arka', static function (array $settings): array {
            $settings['features']['pos.tables'] = true;

            return $settings;
        });

        $labels = array_column(app(DynamicMenuRegistry::class)->menusFor('pos'), 'label');

        $this->assertContains('Meja & Work Order', $labels);
        $this->assertNotContains('Meja & Pesanan', $labels);
    }
}
