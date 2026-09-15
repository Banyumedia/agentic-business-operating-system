<?php

namespace Tests\Feature;

use Tests\TestCase;

class ModuleSidebarTest extends TestCase
{
    public function test_module_screen_renders_its_own_menu(): void
    {
        $this->get(route('app.module', ['module' => 'hrd']))
            ->assertOk()
            ->assertSee('Data Karyawan')
            ->assertSee('Payroll');
    }

    public function test_module_screen_does_not_leak_other_module_menu(): void
    {
        $this->get(route('app.module', ['module' => 'hrd']))
            ->assertOk()
            ->assertDontSee('Follow Up')
            ->assertDontSee('Riwayat Transaksi');
    }

    public function test_switching_module_switches_menu_completely(): void
    {
        $this->get(route('app.module', ['module' => 'crm']))
            ->assertOk()
            ->assertSee('Data Klien')
            ->assertDontSee('Data Karyawan');
    }

    public function test_unknown_module_renders_no_menu_items(): void
    {
        $html = $this->get(route('app.module', ['module' => 'modul-hantu']))
            ->assertOk()
            ->getContent();

        // Zero-bloat (UX_UI_SPEC §3.1): modul tanpa menu tidak boleh menghasilkan
        // DOM item apa pun — bukan placeholder, bukan pesan kosong.
        $this->assertStringNotContainsString('Modul ini belum memiliki menu aktif', $html);
        $this->assertStringNotContainsString('Menu 1', $html);

        preg_match('/<nav[^>]*aria-label="Menu modul[^"]*"[^>]*>(.*?)<\/nav>/s', $html, $nav);
        $this->assertNotEmpty($nav, 'Sidebar <nav> harus tetap ada sebagai landmark.');
        $this->assertSame(
            '',
            trim(strip_tags($nav[1])),
            'Isi <nav> untuk modul tak dikenal harus benar-benar kosong.'
        );
    }
}
