<?php

namespace Tests\Unit;

use App\Contracts\CompanyContext;
use App\Services\DynamicMenuRegistry;
use Tests\TestCase;

class DynamicMenuRegistryTest extends TestCase
{
    private DynamicMenuRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        app(CompanyContext::class)->setCurrent('klinik-sehat');
        $this->registry = app(DynamicMenuRegistry::class);
    }

    public function test_registry_resolves_labels_and_visibility_from_company_configuration(): void
    {
        $menus = $this->registry->menusFor('contacts');

        $this->assertSame('Pasien', $this->registry->titleFor('contacts'));
        $this->assertSame('Daftar Pasien', $menus[0]['label']);
        $this->assertTrue($this->registry->isModuleVisible('contacts'));
        $this->assertFalse($this->registry->isModuleVisible('projects'));
    }

    public function test_every_visible_menu_has_a_screen_pattern_and_entity(): void
    {
        foreach ($this->registry->visibleModules() as $module) {
            foreach ($this->registry->menusFor($module['slug']) as $item) {
                $this->assertArrayHasKey('label', $item);
                $this->assertArrayHasKey('route', $item);
                $this->assertArrayHasKey('screen', $item);
                $this->assertArrayHasKey('entity', $item);
                $this->assertNotSame('', trim($item['label']));
                $this->assertStringStartsWith('/app/', $item['route']);
            }
        }
    }

    public function test_nested_route_definition_maps_to_capability_screen_and_entity(): void
    {
        $definition = $this->registry->routeDefinition('contacts', 'deals');

        $this->assertSame('pipeline', $definition['screen']);
        $this->assertSame('deals', $definition['entity']);
        $this->assertSame('Pipeline Kunjungan', $definition['label']);
    }

    public function test_unknown_module_and_path_fail_closed_in_registry(): void
    {
        $this->assertFalse($this->registry->hasModule('tidak-ada'));
        $this->assertSame([], $this->registry->menusFor('tidak-ada'));
        $this->assertNull($this->registry->routeDefinition('contacts', 'tidak-ada'));
    }

    public function test_registry_contains_every_canonical_capability_path(): void
    {
        foreach ([
            '/app/projects/billing',
            '/app/projects/retention',
            '/app/bookings/checkin',
            '/app/inventory/bom',
            '/app/pos/tables',
            '/app/pos/prescriptions',
            '/app/accounting/coa',
            '/app/accounting/journals',
        ] as $path) {
            [, , $module, $submodule] = explode('/', $path);
            $this->assertTrue($this->registry->hasPath($module, $submodule), "Path kanonik tidak terdaftar: $path");
        }

        $this->assertTrue($this->registry->hasPath('quotations', null));
    }

    public function test_module_order_is_composed_from_the_active_preset(): void
    {
        $this->assertSame(
            ['dashboard', 'bookings', 'contacts', 'hrd', 'accounting', 'settings'],
            array_slice($this->registry->modules(), 0, 6),
        );
    }
}
