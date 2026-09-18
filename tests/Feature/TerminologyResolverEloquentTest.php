<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Models\BusinessPreset;
use App\Models\Company;
use App\Models\ModuleSetting;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\Preset\EloquentPresetSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TerminologyResolverEloquentTest extends TestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('datasource.driver', 'eloquent');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Re-bind to ensure Eloquent implementations are used
        app()->scoped(CompanyContext::class, EloquentCompanyContext::class);
        app()->scoped(CompanySettingsStore::class, EloquentCompanySettingsStore::class);
        app()->bind(PresetSource::class, EloquentPresetSource::class);

        BusinessPreset::create([
            'key' => 'rental',
            'name' => 'Persewaan',
            'tier' => 'A',
            'definition' => [
                'terminology' => [
                    'contact' => 'Penyewa',
                    'booking' => 'Booking',
                ],
            ],
        ]);

        BusinessPreset::create([
            'key' => 'pharmacy',
            'name' => 'Apotek',
            'tier' => 'B',
            'definition' => [
                'terminology' => [
                    'contact' => 'Pasien',
                ],
            ],
        ]);

        $user = User::factory()->create();

        $company = Company::create([
            'name' => 'Rental Mobil',
            'slug' => 'rental-mobil',
            'owner_user_id' => $user->id,
            'business_preset' => 'rental',
        ]);

        ModuleSetting::create([
            'company_id' => $company->id,
            'module_name' => 'terminology',
            'settings_json' => ['project' => 'Sewa'],
        ]);

        $company2 = Company::create([
            'name' => 'Apotek Sehat',
            'slug' => 'apotek-sehat',
            'owner_user_id' => $user->id,
            'business_preset' => 'pharmacy',
        ]);

        ModuleSetting::create([
            'company_id' => $company2->id,
            'module_name' => 'terminology',
            'settings_json' => ['contact' => 'Pelanggan'],
        ]);
    }

    public function test_rental_preset_terminology(): void
    {
        $company = Company::where('slug', 'rental-mobil')->first();
        $this->withSession(['active_company' => $company->id]);
        $this->actingAs(User::find($company->owner_user_id));

        $this->assertSame('Penyewa', term('contact'));
        $this->assertSame('Sewa', term('project'));
        $this->assertSame('Staf', term('staff')); // default
    }

    public function test_pharmacy_preset_terminology_with_override(): void
    {
        $company2 = Company::where('slug', 'apotek-sehat')->first();
        $this->withSession(['active_company' => $company2->id]);
        $this->actingAs(User::find($company2->owner_user_id));

        $this->assertSame('Pelanggan', term('contact')); // override menang
        $this->assertSame('Kontak', term('contacts')); // default
    }
}
