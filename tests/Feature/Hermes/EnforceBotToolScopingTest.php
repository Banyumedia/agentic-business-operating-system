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

        // Owner default-nya terverifikasi: jalur bahagia yang sudah ada
        // memang mewakili pemanggil bot yang sah, dan D-66 mensyaratkan
        // `wa_is_verified = true` agar nomor dianggap bukti identitas.
        $this->owner = User::factory()->create([
            'email' => 'owner_bot@example.com',
            'wa_number' => '6281234567890',
            'wa_is_verified' => true,
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

    /**
     * D-66: nomor cocok tapi `wa_is_verified = false` BUKAN bukti identitas —
     * nomor WA berpindah tangan. Middleware ini sempat lebih longgar daripada
     * `WhatsAppSenderIdentity`/`WhatsAppInteractionFilter` yang sudah menegakkan
     * flag verifikasi, sehingga bearer sah + nomor kebetulan cocok tetap lolos.
     * Rute mutasi harus menolak dengan 403, bukan meloloskan.
     */
    public function test_unverified_number_is_rejected_on_mutation_route(): void
    {
        $this->owner->forceFill(['wa_is_verified' => false])->save();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer token_primary_secret',
            'X-Caller-Wa-Number' => '6281234567890',
        ])->putJson('/api/bot/tenant/settings', [
            'company_id' => $this->company->id,
            'features' => ['contacts' => true],
        ]);

        $response->assertStatus(403);
    }

    /**
     * Aturan verifikasi berlaku sama untuk rute baca: tidak boleh ada celah di
     * mana pembacaan lolos sementara mutasi ditolak (atau sebaliknya).
     */
    public function test_unverified_number_is_rejected_on_read_route(): void
    {
        $this->owner->forceFill(['wa_is_verified' => false])->save();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer token_primary_secret',
            'X-Caller-Wa-Number' => '6281234567890',
        ])->getJson('/api/bot/tenant/capabilities?company_id='.$this->company->id);

        $response->assertStatus(403);
    }

    /**
     * Nomor disimpan `628...` tetapi pemanggil mengirim format lokal `08...`.
     * Consumer WA lain menormalkan `08`→`628` sebelum membandingkan; middleware
     * ini harus konsisten agar nomor sah tidak ditolak hanya karena beda format.
     */
    public function test_local_format_number_is_recognized_via_normalization(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer token_primary_secret',
            'X-Caller-Wa-Number' => '081234567890',
        ])->putJson('/api/bot/tenant/settings', [
            'company_id' => $this->company->id,
            'features' => ['contacts' => true],
        ]);

        $response->assertOk();
    }

    /**
     * Bearer sah tetapi nomor tidak dimiliki user mana pun → 403. Token yang
     * benar tidak boleh cukup sendirian; identitas pemanggil tetap wajib.
     */
    public function test_unknown_number_is_rejected(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer token_primary_secret',
            'X-Caller-Wa-Number' => '6289999999999',
        ])->putJson('/api/bot/tenant/settings', [
            'company_id' => $this->company->id,
            'features' => ['contacts' => true],
        ]);

        $response->assertStatus(403);
    }
}
