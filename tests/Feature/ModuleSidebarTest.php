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
        $this->get(route('app.module', ['module' => 'modul-hantu']))
            ->assertOk()
            ->assertDontSee('Menu 1')
            ->assertDontSee('Menu 2');
    }
}
