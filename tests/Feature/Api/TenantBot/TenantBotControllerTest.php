<?php

namespace Tests\Feature\Api\TenantBot;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Models\Company;
use App\Models\Contact;
use App\Models\HermesProfile;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\Preset\EloquentPresetSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantBotControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
        $this->app->scoped(CompanyContext::class, EloquentCompanyContext::class);
        $this->app->scoped(CompanySettingsStore::class, EloquentCompanySettingsStore::class);
        $this->app->bind(PresetSource::class, EloquentPresetSource::class);

        $this->artisan('db:seed', ['--class' => 'BusinessPresetSeeder']);
    }

    public function test_mcp_configure_modules_omits_sensitive_capabilities_without_privacy_consent()
    {
        $user = User::factory()->create(['wa_number' => '12345']);

        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'privacy_accepted_at' => null,
            'business_preset' => 'klinik',
        ]);

        $profile = HermesProfile::factory()->create([
            'webhook_secret_reference' => HermesProfile::hashBotToken('secret-token'),
        ]);
        $profile->companies()->attach($company->id);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer secret-token',
            'X-Caller-Wa-Number' => '12345',
        ])->getJson("/api/bot/tenant/capabilities?company_id={$company->id}");

        $response->assertStatus(200);

        // Use PHP array syntax instead of assertJsonPath to avoid dot notation parsing issues with keys that have dots
        $data = $response->json();
        $this->assertFalse($data['capabilities']['pharmacy.prescription']);
        $this->assertFalse($data['privacy_accepted']);
    }

    public function test_tenant_bot_reports_unavailable_if_capability_disabled()
    {
        $user = User::factory()->create(['wa_number' => '12345']);

        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'bengkel',
        ]);

        $company->settings()->create([
            'module_name' => 'features',
            'settings_json' => ['contacts' => false],
        ]);

        $profile = HermesProfile::factory()->create([
            'webhook_secret_reference' => HermesProfile::hashBotToken('secret-token'),
        ]);
        $profile->companies()->attach($company->id);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer secret-token',
            'X-Caller-Wa-Number' => '12345',
        ])->postJson('/api/bot/tenant/contacts', [
            'company_id' => $company->id,
            'name' => 'Pelanggan X',
            'phone' => '08111111',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Capability contacts is disabled or requires privacy consent.']);
    }

    public function test_mcp_blocks_all_payloads_when_ai_agent_disabled()
    {
        $user = User::factory()->create(['wa_number' => '12345']);
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'bengkel',
        ]);

        $company->settings()->create([
            'module_name' => 'features',
            'settings_json' => ['system.ai_agent' => false],
        ]);

        $profile = HermesProfile::factory()->create([
            'webhook_secret_reference' => HermesProfile::hashBotToken('secret-token'),
        ]);
        $profile->companies()->attach($company->id);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer secret-token',
            'X-Caller-Wa-Number' => '12345',
        ])->postJson('/api/bot/tenant/contacts', [
            'company_id' => $company->id,
            'name' => 'Pelanggan Y',
            'phone' => '08222222',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'AI Agent capability is disabled for this company']);
    }

    public function test_create_deal_rejects_contact_from_another_tenant_without_mutation()
    {
        $user = User::factory()->create(['wa_number' => '12345']);

        // Preset klinik punya kapabilitas deals; bengkel tidak.
        $companyA = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'klinik',
        ]);
        $companyB = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'klinik',
        ]);

        // Contact dimiliki company B; payload menunjuk company A. ID boleh
        // sama-sama kecil - bentrokan data tenant nyata.
        $foreignContact = Contact::factory()->create([
            'company_id' => $companyB->id,
            'name' => 'Kontak Tenant Lain',
        ]);

        $profile = HermesProfile::factory()->create([
            'webhook_secret_reference' => HermesProfile::hashBotToken('secret-token'),
        ]);
        $profile->companies()->attach($companyA->id);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer secret-token',
            'X-Caller-Wa-Number' => '12345',
        ])->postJson('/api/bot/tenant/deals', [
            'company_id' => $companyA->id,
            'contact_id' => $foreignContact->id,
            'title' => 'Deal Ilegal',
            'amount' => 100000,
            'stage' => 'baru',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Contact not found for this company']);

        $this->assertDatabaseMissing('deals', ['company_id' => $companyA->id]);
    }

    public function test_create_deal_accepts_contact_owned_by_the_same_company()
    {
        $user = User::factory()->create(['wa_number' => '12345']);

        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'klinik',
        ]);

        $contact = Contact::factory()->create([
            'company_id' => $company->id,
            'name' => 'Kontak Milik Sendiri',
        ]);

        $profile = HermesProfile::factory()->create([
            'webhook_secret_reference' => HermesProfile::hashBotToken('secret-token'),
        ]);
        $profile->companies()->attach($company->id);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer secret-token',
            'X-Caller-Wa-Number' => '12345',
        ])->postJson('/api/bot/tenant/deals', [
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'title' => 'Deal Sah',
            'amount' => 50000,
            'stage' => 'baru',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('deals', [
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'title' => 'Deal Sah',
        ]);
    }
}
