<?php

namespace Tests\Feature\Api\TenantBot;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Models\Company;
use App\Models\HermesProfile;
use App\Models\ModuleSetting;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\Preset\EloquentPresetSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-65: standar dokumen per tenant, disimpan sebagai **data** dan dibaca bot
 * lewat TenantBot API.
 *
 * D-69 mewajibkan **satu skill melayani banyak tenant**: perbedaan per tenant
 * datang sebagai data dari API kita, bukan berkas skill yang dipecah per
 * industri. Supaya itu mungkin, skill harus bisa menanyakan "standar proposal
 * usaha ini apa". Sebelum task ini TenantBot API tidak punya jalur untuk itu -
 * hanya `context`, `capabilities`, `settings`, `contacts`, `deals`, dan
 * `destructive-action`.
 *
 * Disimpan di `module_settings` (D-31: data, bukan kolom baru per jenis dokumen).
 */
class DocumentStandardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pola yang sama dengan TenantBotControllerTest: jalur bot memang jalur
        // Eloquent, dan preset harus ter-seed supaya `system.ai_agent` resolvable.
        config(['datasource.driver' => 'eloquent']);
        $this->app->scoped(CompanyContext::class, EloquentCompanyContext::class);
        $this->app->scoped(CompanySettingsStore::class, EloquentCompanySettingsStore::class);
        $this->app->bind(PresetSource::class, EloquentPresetSource::class);

        $this->artisan('db:seed', ['--class' => 'BusinessPresetSeeder']);
    }

    public function test_bot_can_read_the_document_standard_of_its_company(): void
    {
        [$company, $headers] = $this->tenantBot();

        $this->storeStandard($company, [
            'proposal' => [
                'sections' => ['Ringkasan', 'Lingkup', 'Harga', 'Ketentuan'],
                'tone' => 'formal',
            ],
        ]);

        $response = $this->getJson('/api/bot/tenant/document-standards?company_id='.$company->id, $headers);

        $response->assertOk();
        $response->assertJsonPath('company_id', $company->id);
        $response->assertJsonPath('standards.proposal.tone', 'formal');
        $response->assertJsonPath('standards.proposal.sections.0', 'Ringkasan');
    }

    public function test_an_empty_standard_returns_a_documented_default_instead_of_failing(): void
    {
        // Tenant baru belum punya standar. Mengembalikan galat akan membuat skill
        // gagal pada tenant yang justru paling banyak - yang belum menyetel apa pun.
        [$company, $headers] = $this->tenantBot();

        $response = $this->getJson('/api/bot/tenant/document-standards?company_id='.$company->id, $headers);

        $response->assertOk();
        $response->assertJsonPath('is_default', true);
        $this->assertNotEmpty($response->json('standards.proposal.sections'));
    }

    public function test_negative_a_bot_cannot_read_another_companys_standard(): void
    {
        [$company, $headers] = $this->tenantBot();
        $other = Company::factory()->create();

        $this->storeStandard($other, ['proposal' => ['tone' => 'santai']]);

        $response = $this->getJson('/api/bot/tenant/document-standards?company_id='.$other->id, $headers);

        $response->assertForbidden();
        $this->assertStringNotContainsString('santai', $response->getContent());
    }

    public function test_negative_an_unauthenticated_request_is_refused(): void
    {
        [$company] = $this->tenantBot();

        $this->getJson('/api/bot/tenant/document-standards?company_id='.$company->id)
            ->assertUnauthorized();
    }

    public function test_two_tenants_return_their_own_standard_from_one_endpoint(): void
    {
        // Bukti bahwa satu skill bisa melayani dua tenant: endpoint yang sama
        // mengembalikan isi yang berbeda.
        [$first, $firstHeaders] = $this->tenantBot();
        [$second, $secondHeaders] = $this->tenantBot();

        $this->storeStandard($first, ['proposal' => ['tone' => 'formal']]);
        $this->storeStandard($second, ['proposal' => ['tone' => 'santai']]);

        $this->getJson('/api/bot/tenant/document-standards?company_id='.$first->id, $firstHeaders)
            ->assertJsonPath('standards.proposal.tone', 'formal');

        $this->getJson('/api/bot/tenant/document-standards?company_id='.$second->id, $secondHeaders)
            ->assertJsonPath('standards.proposal.tone', 'santai');
    }

    public function test_negative_the_endpoint_is_read_only_for_a_bot(): void
    {
        // Menyetel standar adalah keputusan owner di web, bukan sesuatu yang bot
        // boleh ubah sendiri.
        [$company, $headers] = $this->tenantBot();

        $this->putJson('/api/bot/tenant/document-standards', [
            'company_id' => $company->id,
            'standards' => ['proposal' => ['tone' => 'apa saja']],
        ], $headers)->assertStatus(405);
    }

    /** @return array{0: Company, 1: array<string, string>} */
    private function tenantBot(): array
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
            'type' => 'primary',
            'status' => 'paired',
            // Yang tersimpan adalah hash token (QA-08); bearer yang dikirim tetap
            // plaintext. Test yang menyimpan plaintext akan gagal autentikasi, dan
            // itu memang jaminan yang kita inginkan.
            'webhook_secret_reference' => HermesProfile::hashBotToken($secret),
        ]);
        $profile->companies()->attach($company->id, ['is_default' => true, 'created_at' => now()]);

        return [$company, [
            'Authorization' => 'Bearer '.$secret,
            'X-Caller-Wa-Number' => $waNumber,
        ]];
    }

    /** @param array<string, mixed> $standards */
    private function storeStandard(Company $company, array $standards): void
    {
        ModuleSetting::create([
            'company_id' => $company->id,
            'module_name' => 'documents',
            'settings_json' => ['standards' => $standards],
        ]);
    }
}
