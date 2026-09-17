<?php

namespace Tests\Feature;

use App\Models\BusinessPreset;
use Database\Seeders\BusinessPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessPresetSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_loads_all_presets_into_database(): void
    {
        $this->assertDatabaseCount('business_presets', 0);
        $this->seed(BusinessPresetSeeder::class);

        $files = glob(database_path('presets/*.json'));
        $expectedCount = count($files);

        $this->assertDatabaseCount('business_presets', $expectedCount);

        $bengkel = BusinessPreset::find('bengkel');
        $this->assertNotNull($bengkel);
        $this->assertSame('Bengkel', $bengkel->name);
        $this->assertSame('A', $bengkel->tier);
        $this->assertIsArray($bengkel->definition);
        $this->assertTrue($bengkel->definition['capabilities']['pos']);
    }
}
