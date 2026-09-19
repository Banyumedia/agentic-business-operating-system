<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Models\Company;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\FeatureResolver;
use App\Services\TerminologyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresetCompositionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
        $this->app->scoped(CompanyContext::class, EloquentCompanyContext::class);
        $this->app->scoped(CompanySettingsStore::class, EloquentCompanySettingsStore::class);
        $this->artisan('db:seed', ['--class' => 'BusinessPresetSeeder']);
    }

    public function test_new_presets_are_rendered_without_hardcoding()
    {
        $presets = [
            'bengkel', 'laundry', 'kursus', 'kos_coworking', 'katering',
            'bakery_preorder', 'travel_umroh', 'gym', 'praktek_dokter',
            'cuci_mobil', 'barbershop', 'kedai_kopi', 'fotografi',
            'warnet_gaming', 'cuci_sepatu', 'percetakan', 'service_ac',
            'toko_bangunan', 'cleaning_service',
        ];

        $user = User::factory()->create();

        foreach ($presets as $preset) {
            $company = Company::factory()->create([
                'owner_user_id' => $user->id,
                'business_preset' => $preset,
            ]);

            app(CompanyContext::class)->setCurrent($company->id);
            app(FeatureResolver::class)->flushCache();
            app(TerminologyResolver::class)->flushCache();

            $response = $this->actingAs($user)->get('/app/dashboard');
            $response->assertStatus(200);

            if ($company->feature('quotations')) {
                $this->actingAs($user)->get('/app/quotations')->assertStatus(200);
            }

            // Access contacts to test terminology applies
            if ($company->feature('contacts')) {
                $response = $this->actingAs($user)->get('/app/contacts');
                $response->assertStatus(200);
            }
        }
    }
}
