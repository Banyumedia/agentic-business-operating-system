<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Models\BusinessPreset;
use App\Models\Company;
use App\Models\User;
use App\Services\DynamicMenuRegistry;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\Preset\EloquentPresetSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class EloquentDynamicMenuRegistryTest extends TestCase
{
    use RefreshDatabase;

    private DynamicMenuRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('datasource.driver', 'eloquent');
        $this->app->scoped(CompanyContext::class, EloquentCompanyContext::class);
        $this->app->scoped(PresetSource::class, EloquentPresetSource::class);
        $this->app->scoped(CompanySettingsStore::class, EloquentCompanySettingsStore::class);

        BusinessPreset::create([
            'key' => 'klinik-sehat-preset',
            'name' => 'Klinik Sehat',
            'tier' => 'professional',
            'definition' => [
                'key' => 'klinik-sehat-preset',
                'name' => 'Klinik Sehat',
                'tier' => 'professional',
                'capabilities' => [
                    'contacts' => true,
                    'projects' => false,
                    'deals' => true,
                ],
                'terminology' => [
                    'contacts' => 'Pasien',
                    'deals' => 'Kunjungan',
                ],
                'menus' => [
                    'order' => ['contacts'],
                ],
                'workflows' => [
                    'deals' => ['draft', 'done'],
                ],
            ],
        ]);

        $user = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'klinik-sehat-preset',
        ]);

        $this->actingAs($user);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        $this->registry = app(DynamicMenuRegistry::class);
    }

    public function test_registry_resolves_labels_and_visibility_from_eloquent_preset(): void
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
    }
}
