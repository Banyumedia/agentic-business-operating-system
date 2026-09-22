<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\PresetSource;
use App\Livewire\MobileQuickNav;
use App\Services\DynamicMenuRegistry;
use App\Services\FeatureResolver;
use App\Services\TerminologyResolver;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Navigasi bawah ponsel tidak boleh menawarkan modul yang tidak dimiliki usaha.
 *
 * Versi pertamanya menanam tab Kasir (`/app/pos`) dan Buku Kas
 * (`/app/accounting`) di layout. Preset `klinik` tidak punya kapabilitas `pos`,
 * jadi klinik yang membuka aplikasi dari ponsel melihat tab Kasir dan mendapat
 * 403 saat menekannya. Isinya kini diturunkan dari `DynamicMenuRegistry`.
 */
class MobileQuickNavTest extends TestCase
{
    public function test_negative_a_business_without_pos_is_not_offered_a_cashier_tab(): void
    {
        app(CompanyContext::class)->setCurrent('klinik-sehat');

        // Bukti prasyarat: modul pos memang tidak terjangkau klinik.
        $this->assertNotContains(
            'pos',
            array_column(app(DynamicMenuRegistry::class)->visibleModules(), 'slug'),
        );

        Livewire::test(MobileQuickNav::class)
            ->assertDontSee('Layar Kasir')
            ->assertDontSeeHtml('href="/app/pos"');
    }

    public function test_a_business_with_pos_is_offered_it(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        Livewire::test(MobileQuickNav::class)
            ->assertSeeHtml('href="/app/pos"');
    }

    public function test_every_tab_points_at_a_module_the_business_can_reach(): void
    {
        foreach (['bengkel-arka', 'klinik-sehat', 'salon-ayu', 'laundry-bersih'] as $company) {
            app(CompanyContext::class)->setCurrent($company);

            $registry = app(DynamicMenuRegistry::class);
            $reachable = array_column($registry->visibleModules(), 'slug');

            foreach (Livewire::test(MobileQuickNav::class)->instance()->tabs as $tab) {
                $this->assertContains(
                    $tab['slug'],
                    $reachable,
                    "Tab {$tab['slug']} pada {$company} menunjuk modul yang tidak terjangkau.",
                );
            }
        }
    }

    public function test_negative_an_unresolvable_company_yields_no_tabs_instead_of_a_broken_page(): void
    {
        // Halaman bisa dibuka dengan company yang presetnya belum terkonfigurasi.
        // Fail-closed untuk sebuah bilah navigasi berarti tidak menawarkan tab -
        // bukan menjatuhkan seluruh halaman yang sudah dirender.
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $this->app->bind(DynamicMenuRegistry::class, fn (): DynamicMenuRegistry => new class(app(FeatureResolver::class), app(TerminologyResolver::class), app(CompanyContext::class), app(PresetSource::class)) extends DynamicMenuRegistry
        {
            public function visibleModules(): array
            {
                throw new InvalidArgumentException('Preset company belum dikonfigurasi: bengkel-arka');
            }
        });

        $component = Livewire::test(MobileQuickNav::class);

        $this->assertSame([], $component->instance()->tabs);
        $component->assertSee('Beranda');
    }

    public function test_settings_is_left_out_so_the_bar_keeps_room_for_daily_work(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $tabs = array_column(Livewire::test(MobileQuickNav::class)->instance()->tabs, 'slug');

        $this->assertNotContains('settings', $tabs);
        $this->assertLessThanOrEqual(3, count($tabs));
    }
}
