<?php

namespace Tests\Feature\Ai;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Models\Company;
use App\Models\Contact;
use App\Models\HermesProfile;
use App\Models\ModuleSetting;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Ai\AiDataSharingPolicy;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\Preset\EloquentPresetSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiContextControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
        $this->app->scoped(CompanyContext::class, EloquentCompanyContext::class);
        $this->app->scoped(PresetSource::class, EloquentPresetSource::class);
        $this->app->scoped(CompanySettingsStore::class, EloquentCompanySettingsStore::class);
        $this->artisan('db:seed', ['--class' => 'BusinessPresetSeeder']);
    }

    private function setupBotAccess(Company $company, User $owner): HermesProfile
    {
        $profile = HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'webhook_secret_reference' => 'test-token',
        ]);

        $profile->companies()->attach($company->id);

        $owner->update(['wa_number' => '1234567890']);

        return $profile;
    }

    public function test_sensitive_capability_withheld_by_default(): void
    {
        config(['datasource.driver' => 'eloquent']);

        $owner = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $owner->id,
            'business_preset' => 'pharmacy',
            'privacy_accepted_at' => now(),
            'privacy_accepted_by_user_id' => $owner->id,
            'privacy_policy_version' => '1.0',
        ]);

        $this->setupBotAccess($company, $owner);

        ModuleSetting::create([
            'company_id' => $company->id,
            'module_name' => 'features',
            'settings_json' => [
                'approval_flow' => true,
                'system.ai_agent' => true,
                'pharmacy.prescription' => true,
            ],
        ]);

        $contact = Contact::factory()->create(['company_id' => $company->id]);

        Prescription::factory()->create([
            'company_id' => $company->id,
            'doctor_name' => 'Dr. Budi',
            'patient_contact_id' => $contact->id,
        ]);

        $response = $this->getJson("/api/bot/tenant/context?company_id={$company->id}", [
            'Authorization' => 'Bearer test-token',
            'X-Caller-Wa-Number' => '1234567890',
        ]);

        $response->assertStatus(200);

        $this->assertFalse($response->json()['ai_data_sharing']['pharmacy.prescription']);

        $this->assertArrayNotHasKey('prescriptions', $response->json('data'));

        $response->assertJsonPath('withheld.0.capability', 'pharmacy.prescription');
        $response->assertJsonPath('withheld.0.entity', 'prescriptions');

        $policy = new AiDataSharingPolicy;
        $this->assertEquals($policy->withheldReason('pharmacy.prescription'), $response->json('withheld.0.message'));
    }

    public function test_sensitive_capability_shared_when_opted_in(): void
    {
        config(['datasource.driver' => 'eloquent']);

        $owner = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $owner->id,
            'business_preset' => 'pharmacy',
            'privacy_accepted_at' => now(),
            'privacy_accepted_by_user_id' => $owner->id,
            'privacy_policy_version' => '1.0',
        ]);

        $this->setupBotAccess($company, $owner);

        ModuleSetting::create([
            'company_id' => $company->id,
            'module_name' => 'features',
            'settings_json' => [
                'approval_flow' => true,
                'system.ai_agent' => true,
                'pharmacy.prescription' => true,
            ],
        ]);

        // Opt in via endpoint
        $optInResponse = $this->putJson('/api/bot/tenant/context/opt-in', [
            'company_id' => $company->id,
            'ai_data_sharing' => [
                'pharmacy.prescription' => true,
            ],
        ], [
            'Authorization' => 'Bearer test-token',
            'X-Caller-Wa-Number' => '1234567890',
        ]);

        $optInResponse->assertStatus(200);
        $this->assertTrue($optInResponse->json()['ai_data_sharing']['pharmacy.prescription']);

        $contact = Contact::factory()->create(['company_id' => $company->id]);

        $prescription = Prescription::factory()->create([
            'company_id' => $company->id,
            'doctor_name' => 'Dr. Budi',
            'patient_contact_id' => $contact->id,
        ]);

        $response = $this->getJson("/api/bot/tenant/context?company_id={$company->id}", [
            'Authorization' => 'Bearer test-token',
            'X-Caller-Wa-Number' => '1234567890',
        ]);

        $response->assertStatus(200);

        $this->assertTrue($response->json()['ai_data_sharing']['pharmacy.prescription']);

        $this->assertEquals($prescription->id, $response->json('data.prescriptions.0.id'));

        $this->assertArrayHasKey('patient_contact_id', $response->json('data.prescriptions.0'));
        $this->assertArrayHasKey('doctor_name', $response->json('data.prescriptions.0'));
    }
}
