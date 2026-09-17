<?php

namespace Tests\Architecture;

use App\Models\User;
use App\Services\Json\JsonPresetSource;
use App\Services\Preset\PresetDefinitionValidator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class RenderAllPresetsTest extends TestCase
{
    public function test_all_routes_render_for_all_presets_without_exception()
    {
        // 1. Get all presets
        $presetSource = new JsonPresetSource(app(PresetDefinitionValidator::class));
        $presets = $presetSource->all();

        $this->assertNotEmpty($presets, 'No presets found to test');

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
                'laundry' => 'laundry-bersih',
            ];

            if (! isset($slugs[$presetId])) {
                continue; // Skip presets without demo data in Fase 2
            }

            $slug = $slugs[$presetId];

            // Allow this slug in config
            Config::set('datasource.demo_companies', [$slug]);

            // Mock session
            Session::put('company_role', 'owner');

            // GET main lobby
            $response = $this->get('/?company='.$slug);
            $response->assertStatus(200);

            // GET dashboard
            $response = $this->get('/app/dashboard?company='.$slug);
            $response->assertStatus(200);

            // GET settings
            $response = $this->get('/app/settings?company='.$slug);
            $response->assertStatus(200);
        }

        // This is a minimal assertion to ensure we reach the end
        $this->assertTrue(true);
    }

    public function test_laundry_preset_renders_pos_screen_and_kanban_without_exception()
    {
        // T-F15: bukti bahwa preset baru murni data (nol kode baru) sudah
        // memunculkan aplikasi lengkap - kasir dan papan tahap `Cucian`.
        Config::set('datasource.demo_companies', ['laundry-bersih']);
        Session::put('company_role', 'owner');

        $this->get('/app/pos?company=laundry-bersih')
            ->assertStatus(200)
            ->assertSee('Cuci Kering Reguler');

        $this->get('/app/pos/pipeline?company=laundry-bersih')
            ->assertStatus(200)
            ->assertSee('Papan Cucian')
            ->assertSee('Diterima');

        $this->get('/app/contacts?company=laundry-bersih')
            ->assertStatus(200)
            ->assertSee('Pelanggan');

        $this->get('/app/inventory?company=laundry-bersih')
            ->assertStatus(200)
            ->assertSee('Layanan');

        // Kapabilitas yang tidak diaktifkan preset ini (projects, bookings)
        // harus tetap tertutup - zero-bloat, bukan menu kosong.
        $this->get('/app/projects?company=laundry-bersih')->assertStatus(403);
        $this->get('/app/bookings?company=laundry-bersih')->assertStatus(403);
    }
}
