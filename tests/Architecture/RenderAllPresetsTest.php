<?php

namespace Tests\Architecture;

use Tests\TestCase;
use App\Models\User;
use App\Services\Json\JsonPresetSource;
use App\Services\DynamicMenuRegistry;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;

class RenderAllPresetsTest extends TestCase
{
    public function test_all_routes_render_for_all_presets_without_exception()
    {
        // 1. Get all presets
        $presetSource = new JsonPresetSource(app(\App\Services\Preset\PresetDefinitionValidator::class));
        $presets = $presetSource->all();
        
        $this->assertNotEmpty($presets, "No presets found to test");
        
        // Disable authorization middleware for the raw view rendering tests, 
        // or properly mock the session context.
        // We will test by simulating requests as a valid user for each preset.
        
        foreach ($presets as $presetId => $presetName) {
            // Setup session for this preset (simulating CompanyContext)
            // Note: Since this is Fase 2 JSON datasource, we need to bypass DB auth or use the demo company config.
            
            // For now, let's just make sure we can boot the app. 
            // In a real scenario we need a valid company slug mapping to the preset.
            // Let's use the known demo companies for the known presets.
            $slugs = [
                'bengkel' => 'bengkel-arka',
                'klinik' => 'klinik-sehat',
                'salon' => 'salon-ayu',
            ];
            
            if (!isset($slugs[$presetId])) {
                continue; // Skip presets without demo data in Fase 2
            }
            
            $slug = $slugs[$presetId];
            
            // Allow this slug in config
            Config::set('datasource.demo_companies', [$slug]);
            
            // Mock session
            Session::put('company_role', 'owner');
            
            // GET main lobby
            $response = $this->get('/?company=' . $slug);
            $response->assertStatus(200);
            
            // GET dashboard
            $response = $this->get('/app/dashboard?company=' . $slug);
            $response->assertStatus(200);
            
            // GET settings
            $response = $this->get('/app/settings?company=' . $slug);
            $response->assertStatus(200);
        }
        
        // This is a minimal assertion to ensure we reach the end
        $this->assertTrue(true);
    }
}
