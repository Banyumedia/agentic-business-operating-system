<?php

namespace Tests\Feature\Hermes;

use App\Contracts\CompanyContext;
use App\Contracts\PresetSource;
use App\Models\Company;
use App\Models\HermesProfile;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Preset\EloquentPresetSource;
use Database\Seeders\BusinessPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnforceBotToolScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    private HermesProfile $primaryProfile;

    private HermesProfile $addonProfile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->scoped(CompanyContext::class, EloquentCompanyContext::class);
        $this->app->bind(PresetSource::class, EloquentPresetSource::class);
        $this->seed(BusinessPresetSeeder::class);

        $this->owner = User::factory()->create([
            'email' => 'owner_bot@example.com',
            'wa_number' => '6281234567890',
        ]);

        $this->company = Company::factory()->create([
            'name' => 'Usaha Maju',
            'slug' => 'usaha-maju',
            'business_preset' => 'klinik',
            'owner_user_id' => $this->owner->id,
        ]);

        // Primary Bot Profile
        $this->primaryProfile = HermesProfile::create([
            'owner_user_id' => $this->owner->id,
            'type' => 'primary',
            'instance_id' => 'inst_p_1',
            'webhook_secret_reference' => HermesProfile::hashBotToken('token_primary_secret'),
            'status' => 'connected',
        ]);
        $this->primaryProfile->companies()->attach($this->company->id);

        // Addon CS Bot Profile
        $this->addonProfile = HermesProfile::create([
            'owner_user_id' => $this->owner->id,
            'type' => 'addon',
            'billing_addon_id' => 999,
            'instance_id' => 'inst_a_1',
            'webhook_secret_reference' => HermesProfile::hashBotToken('token_addon_secret'),
            'status' => 'connected',
        ]);
        $this->addonProfile->companies()->attach($this->company->id);
    }

    public function test_primary_profile_can_access_mutation_endpoints(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer token_primary_secret',
            'X-Caller-Wa-Number' => '6281234567890',
        ])->putJson('/api/bot/tenant/settings', [
            'company_id' => $this->company->id,
            'features' => ['contacts' => true],
        ]);

        $response->assertOk();
    }

    public function test_addon_profile_is_blocked_from_mutation_endpoints(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer token_addon_secret',
            'X-Caller-Wa-Number' => '6281234567890',
        ])->putJson('/api/bot/tenant/settings', [
            'company_id' => $this->company->id,
            'features' => ['contacts' => true],
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'error' => "Aksi 'update_settings' tidak diizinkan untuk profil bot tipe 'addon'.",
        ]);
    }

    public function test_addon_profile_is_blocked_from_destructive_actions(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer token_addon_secret',
            'X-Caller-Wa-Number' => '6281234567890',
        ])->postJson('/api/bot/tenant/destructive-action', [
            'company_id' => $this->company->id,
            'action' => 'delete_orders',
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'error' => "Aksi 'destructive_action' tidak diizinkan untuk profil bot tipe 'addon'.",
        ]);
    }

    public function test_addon_profile_can_read_capabilities(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer token_addon_secret',
            'X-Caller-Wa-Number' => '6281234567890',
        ])->getJson('/api/bot/tenant/capabilities?company_id='.$this->company->id);

        $response->assertOk();
    }
}
