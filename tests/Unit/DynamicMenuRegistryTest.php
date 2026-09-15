<?php

namespace Tests\Unit;

use App\Services\DynamicMenuRegistry;
use Tests\TestCase;

class DynamicMenuRegistryTest extends TestCase
{
    private DynamicMenuRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new DynamicMenuRegistry;
    }

    public function test_known_module_returns_its_own_menu(): void
    {
        $menus = $this->registry->menusFor('hrd');

        $this->assertNotEmpty($menus);
        $this->assertSame('Data Karyawan', $menus[1]['label']);
    }

    public function test_each_module_menu_is_isolated_from_other_modules(): void
    {
        $hrdLabels = array_column($this->registry->menusFor('hrd'), 'label');
        $crmLabels = array_column($this->registry->menusFor('crm'), 'label');

        $this->assertNotEquals($hrdLabels, $crmLabels);
        $this->assertEmpty(
            array_intersect(['Data Karyawan', 'Payroll'], $crmLabels),
            'Menu HRD tidak boleh bocor ke modul CRM.'
        );
    }

    public function test_every_menu_item_has_label_icon_and_route(): void
    {
        foreach ($this->registry->modules() as $module) {
            foreach ($this->registry->menusFor($module) as $item) {
                $this->assertArrayHasKey('label', $item);
                $this->assertArrayHasKey('icon', $item);
                $this->assertArrayHasKey('route', $item);
                $this->assertNotSame('', trim((string) $item['label']));
                $this->assertStringStartsWith('/app/', $item['route']);
            }
        }
    }

    public function test_unknown_module_returns_empty_menu_instead_of_placeholder(): void
    {
        $menus = $this->registry->menusFor('modul-tidak-dikenal');

        $this->assertSame(
            [],
            $menus,
            'Modul tak dikenal harus menghasilkan menu kosong (zero-bloat), bukan placeholder.'
        );
    }

    public function test_null_module_returns_empty_menu(): void
    {
        $this->assertSame([], $this->registry->menusFor(null));
    }

    public function test_accent_color_is_module_specific_with_safe_fallback(): void
    {
        $this->assertSame('bg-blue-600', $this->registry->accentFor('hrd'));
        $this->assertSame('bg-emerald-600', $this->registry->accentFor('crm'));
        $this->assertSame('bg-slate-600', $this->registry->accentFor('entah-apa'));
    }

    public function test_registry_knows_whether_a_module_exists(): void
    {
        $this->assertTrue($this->registry->hasModule('pos'));
        $this->assertFalse($this->registry->hasModule('tidak-ada'));
    }
}
