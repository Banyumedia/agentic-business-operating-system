<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Livewire\Dashboard;
use App\Services\Dashboard\DashboardComposer;
use App\Services\DynamicMenuRegistry;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardQuickActionsTest extends TestCase
{
    public function test_dashboard_composer_generates_contextual_quick_actions(): void
    {
        $context = app(CompanyContext::class);
        $context->setCurrent('laundry-bersih');

        $composer = app(DashboardComposer::class);
        $data = $composer->compose();

        $this->assertArrayHasKey('quick_actions', $data);
        $this->assertNotEmpty($data['quick_actions']);
        $this->assertLessThanOrEqual(3, count($data['quick_actions']));

        // First action should be marked as primary
        $this->assertTrue($data['quick_actions'][0]['primary']);

        // Laundry has pos capability -> should have Buka Kasir
        $keys = array_column($data['quick_actions'], 'key');
        $this->assertContains('pos', $keys);
        $this->assertContains('cashbook', $keys);
        $this->assertContains('contacts', $keys);
    }

    public function test_negative_every_quick_action_points_at_a_registered_menu_path(): void
    {
        // Quick action menuliskan path-nya sendiri, jadi ia bisa menyimpang dari
        // menu tanpa ada yang tahu. Itu yang terjadi pada kandidat `orders`:
        // memeriksa kapabilitas yang tidak ada dan menunjuk `/app/orders` yang
        // bukan rute terdaftar - tombolnya tidak pernah muncul, dan kalau
        // kapabilitasnya pernah ada, tombolnya akan menuju halaman yang tiada.
        foreach (['bengkel-arka', 'klinik-sehat', 'salon-ayu', 'laundry-bersih'] as $company) {
            app(CompanyContext::class)->setCurrent($company);

            $registry = app(DynamicMenuRegistry::class);
            $registered = [];
            foreach ($registry->visibleModules() as $module) {
                foreach ($registry->menusFor($module['slug']) as $menu) {
                    $registered[] = $menu['route'];
                }
            }

            foreach (app(DashboardComposer::class)->compose()['quick_actions'] as $action) {
                $path = parse_url($action['url'], PHP_URL_PATH);

                $this->assertContains(
                    $path,
                    $registered,
                    "Quick action {$action['key']} pada {$company} menunjuk {$path} yang tidak ada di menu.",
                );
            }
        }
    }

    public function test_dashboard_renders_quick_actions_bar_in_dom(): void
    {
        $context = app(CompanyContext::class);
        $context->setCurrent('bengkel-arka');

        Livewire::test(Dashboard::class)
            ->assertSee('Aksi cepat')
            ->assertSee('Catat Kas')
            ->assertSeeHtml('href="'.url('/app/accounting').'"');
    }
}
