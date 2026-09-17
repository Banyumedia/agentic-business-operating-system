<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ModuleSetting;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_store_and_retrieve_json_array()
    {
        $company = Company::factory()->create();

        $featuresData = [
            'has_inventory' => true,
            'max_users' => 10,
            'enabled_addons' => ['cashier', 'report'],
        ];

        $setting = ModuleSetting::create([
            'company_id' => $company->id,
            'module_name' => 'features',
            'settings_json' => $featuresData,
        ]);

        $this->assertDatabaseHas('module_settings', [
            'id' => $setting->id,
            'company_id' => $company->id,
            'module_name' => 'features',
        ]);

        $retrieved = ModuleSetting::find($setting->id);

        $this->assertIsArray($retrieved->settings_json);
        $this->assertEquals($featuresData, $retrieved->settings_json);
        $this->assertTrue($retrieved->settings_json['has_inventory']);
        $this->assertCount(2, $retrieved->settings_json['enabled_addons']);
    }

    public function test_module_name_is_unique_per_company()
    {
        $company = Company::factory()->create();

        ModuleSetting::create([
            'company_id' => $company->id,
            'module_name' => 'features',
            'settings_json' => ['a' => 1],
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23000'); // Integrity constraint violation

        ModuleSetting::create([
            'company_id' => $company->id,
            'module_name' => 'features',
            'settings_json' => ['b' => 2],
        ]);
    }
}
