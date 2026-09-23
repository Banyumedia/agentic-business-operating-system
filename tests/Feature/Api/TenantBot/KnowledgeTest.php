<?php

namespace Tests\Feature\Api\TenantBot;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Models\BusinessNote;
use App\Models\Company;
use App\Models\HermesProfile;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\Preset\EloquentPresetSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-107: basis pengetahuan usaha per tenant (catatan + SOP), dibaca lewat
 * pencarian dan ditambah bot - tidak pernah menimpa tulisan manusia.
 */
class KnowledgeTest extends TestCase
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

    public function test_bot_can_create_a_new_note(): void
    {
        [$company, $headers] = $this->tenantBot();

        $response = $this->postJson('/api/bot/tenant/knowledge', [
            'company_id' => $company->id,
            'title' => 'SOP Penerimaan Barang',
            'content' => 'Barang diterima dicek dulu kondisinya sebelum ditandatangani.',
        ], $headers);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('author_type', 'bot');

        $note = BusinessNote::find($response->json('id'));
        $this->assertNotNull($note);
        $this->assertSame($company->id, $note->company_id);
        $this->assertSame('bot', $note->author_type);
        $this->assertNull($note->created_by_user_id);
        $this->assertFalse($note->sensitive);
    }

    public function test_bot_can_search_notes_and_gets_a_capped_snippet_not_the_full_dump(): void
    {
        [$company, $headers] = $this->tenantBot();

        $longContent = str_repeat('Ini kalimat panjang tentang kebijakan retur barang rusak. ', 50);
        BusinessNote::create([
            'company_id' => $company->id,
            'title' => 'Kebijakan Retur',
            'content' => $longContent,
            'author_type' => 'user',
            'created_by_user_id' => $company->owner_user_id,
        ]);

        $response = $this->getJson('/api/bot/tenant/knowledge?company_id='.$company->id.'&q=retur', $headers);

        $response->assertOk();
        $results = $response->json('results');
        $this->assertNotEmpty($results);
        $this->assertLessThan(mb_strlen($longContent), mb_strlen($results[0]['snippet']));
        $this->assertArrayNotHasKey('sensitive', $results[0]);
    }

    public function test_negative_bot_cannot_overwrite_an_existing_note_only_append(): void
    {
        // T-107(b): satu salah tafsir bot tidak boleh menghapus SOP yang
        // disusun manusia. Menulis dengan note_id harus MENAMBAH, isi lama
        // tetap ada di dalam content sesudahnya.
        [$company, $headers] = $this->tenantBot();

        $note = BusinessNote::create([
            'company_id' => $company->id,
            'title' => 'SOP Kasir',
            'content' => 'Kasir wajib menghitung ulang uang kembalian di depan pelanggan.',
            'author_type' => 'user',
            'created_by_user_id' => $company->owner_user_id,
        ]);

        $response = $this->postJson('/api/bot/tenant/knowledge', [
            'company_id' => $company->id,
            'note_id' => $note->id,
            'content' => 'Tambahan: struk wajib diserahkan bersamaan dengan kembalian.',
        ], $headers);

        $response->assertOk();

        $note->refresh();
        $this->assertStringContainsString('Kasir wajib menghitung ulang', $note->content);
        $this->assertStringContainsString('struk wajib diserahkan', $note->content);
        $this->assertSame('bot', $note->author_type);
    }

    public function test_negative_note_over_the_length_limit_is_rejected_before_saving(): void
    {
        [$company, $headers] = $this->tenantBot();
        config(['billing.business_notes.max_content_length' => 100]);

        $response = $this->postJson('/api/bot/tenant/knowledge', [
            'company_id' => $company->id,
            'title' => 'Terlalu Panjang',
            'content' => str_repeat('x', 200),
        ], $headers);

        $response->assertStatus(422);
        $this->assertSame(0, BusinessNote::where('company_id', $company->id)->count());
    }

    public function test_negative_full_quota_rejects_write_and_leaves_no_partial_row(): void
    {
        [$company, $headers] = $this->tenantBot();
        config(['billing.business_notes.max_notes_per_company' => 1]);

        BusinessNote::create([
            'company_id' => $company->id,
            'title' => 'Catatan Pertama',
            'content' => 'Isi.',
            'author_type' => 'user',
            'created_by_user_id' => $company->owner_user_id,
        ]);

        $response = $this->postJson('/api/bot/tenant/knowledge', [
            'company_id' => $company->id,
            'title' => 'Catatan Kedua',
            'content' => 'Isi kedua.',
        ], $headers);

        $response->assertStatus(422);
        $this->assertSame(1, BusinessNote::where('company_id', $company->id)->count());
    }

    public function test_negative_addon_profile_is_rejected_on_both_routes_despite_valid_token(): void
    {
        // T-107(d): catatan internal bukan bahan jawaban ke orang asing.
        [$company, $headers] = $this->tenantBot(profileType: 'addon');

        $this->getJson('/api/bot/tenant/knowledge?company_id='.$company->id, $headers)
            ->assertForbidden();

        $this->postJson('/api/bot/tenant/knowledge', [
            'company_id' => $company->id,
            'title' => 'Coba Tulis',
            'content' => 'Ini tidak boleh tersimpan.',
        ], $headers)->assertForbidden();

        $this->assertSame(0, BusinessNote::where('company_id', $company->id)->count());
    }

    public function test_negative_bot_cannot_search_another_companys_notes(): void
    {
        [$company, $headers] = $this->tenantBot();
        $other = Company::factory()->create();

        BusinessNote::create([
            'company_id' => $other->id,
            'title' => 'Rahasia Usaha Lain',
            'content' => 'Kata sandi gudang usaha tetangga.',
            'author_type' => 'user',
            'created_by_user_id' => $other->owner_user_id,
        ]);

        $response = $this->getJson('/api/bot/tenant/knowledge?company_id='.$other->id.'&q=Rahasia', $headers);

        $response->assertForbidden();
        $this->assertStringNotContainsString('Rahasia Usaha Lain', $response->getContent());
    }

    public function test_negative_sensitive_note_is_withheld_without_owner_opt_in(): void
    {
        [$company, $headers] = $this->tenantBot();

        BusinessNote::create([
            'company_id' => $company->id,
            'title' => 'Catatan Sensitif',
            'content' => 'Detail yang tidak boleh sembarang dijawab bot.',
            'author_type' => 'user',
            'created_by_user_id' => $company->owner_user_id,
            'sensitive' => true,
        ]);

        $response = $this->getJson('/api/bot/tenant/knowledge?company_id='.$company->id.'&q=Sensitif', $headers);

        $response->assertOk();
        $this->assertSame([], $response->json('results'));
        $this->assertSame(1, $response->json('withheld_sensitive_count'));
    }

    public function test_negative_route_without_authentication_is_refused(): void
    {
        [$company] = $this->tenantBot();

        $this->getJson('/api/bot/tenant/knowledge?company_id='.$company->id)->assertUnauthorized();
        $this->postJson('/api/bot/tenant/knowledge', ['company_id' => $company->id, 'title' => 't', 'content' => 'c'])
            ->assertUnauthorized();
    }

    /** @return array{0: Company, 1: array<string, string>} */
    private function tenantBot(string $profileType = 'primary'): array
    {
        $waNumber = '628'.random_int(100000000, 999999999);
        $owner = User::factory()->create(['wa_number' => $waNumber, 'wa_is_verified' => true]);
        $company = Company::factory()->create([
            'owner_user_id' => $owner->id,
            'business_preset' => 'bengkel',
        ]);

        $secret = 'sec_'.bin2hex(random_bytes(8));
        $profile = HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'type' => $profileType,
            'billing_addon_id' => $profileType === 'addon' ? 999 : null,
            'status' => 'paired',
            'webhook_secret_reference' => HermesProfile::hashBotToken($secret),
        ]);
        $profile->companies()->attach($company->id, ['is_default' => true, 'created_at' => now()]);

        return [$company, [
            'Authorization' => 'Bearer '.$secret,
            'X-Caller-Wa-Number' => $waNumber,
        ]];
    }
}
