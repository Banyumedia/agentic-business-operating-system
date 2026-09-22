<?php

namespace Tests\Feature\Api\TenantBot;

use App\Contracts\CompanyContext;
use App\Contracts\PresetSource;
use App\Models\Company;
use App\Models\HermesNode;
use App\Models\HermesProfile;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Preset\EloquentPresetSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * QA-08: token bot tenant tidak boleh tersimpan dalam bentuk yang bisa dipakai.
 *
 * Kolomnya bernama `webhook_secret_reference`, tetapi `AuthenticateTenantBot`
 * mencocokkannya **verbatim** dengan bearer token. Artinya isinya bukan referensi
 * melainkan **kredensial sungguhan yang tersimpan plaintext** - berbeda dari pola
 * yang dianut untuk rahasia node (`api_secret_reference` hanya menyimpan nama, dan
 * nilainya dipetakan dari environment).
 *
 * Akibatnya, siapa pun yang bisa membaca satu baris basis data - backup yang bocor,
 * dump untuk debugging, akses baca ke replika - bisa langsung menyamar sebagai bot
 * tenant dan memanggil TenantBot API atas namanya. Tidak ada yang perlu dipecahkan
 * lebih dulu.
 *
 * Yang diubah: basis data menyimpan **SHA-256** dari token, dan plaintextnya hanya
 * pernah ada satu kali di terminal operator.
 *
 * **Kenapa SHA-256 telanjang dan bukan bcrypt/argon.** Token ini dicari berdasarkan
 * nilainya (satu query, bukan verifikasi terhadap satu baris yang sudah diketahui),
 * jadi hash bersalt tidak bisa dipakai tanpa memindai seluruh tabel. Yang membuat
 * SHA-256 cukup di sini adalah **entropi tokennya sendiri**: 40 karakter acak dari
 * `Str::random()`, bukan kata sandi buatan manusia. Tidak ada kamus yang bisa
 * menebaknya, sehingga salt dan work factor tidak menambah perlindungan apa pun -
 * keduanya hanya ada untuk melawan rendahnya entropi kata sandi manusia.
 */
class BotTokenAtRestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pola yang sama dengan `DocumentStandardTest`: jalur bot adalah jalur
        // Eloquent, dan preset harus ter-seed supaya `system.ai_agent` resolvable.
        config(['datasource.driver' => 'eloquent']);
        $this->app->scoped(CompanyContext::class, EloquentCompanyContext::class);
        $this->app->bind(PresetSource::class, EloquentPresetSource::class);
        $this->artisan('db:seed', ['--class' => 'BusinessPresetSeeder']);
    }

    public function test_negative_the_database_never_stores_the_token_that_was_printed(): void
    {
        [$company, $token] = $this->provisionedTenant();

        $stored = (string) HermesProfile::query()->value('webhook_secret_reference');

        $this->assertNotSame($token, $stored, 'Token tersimpan apa adanya - satu baris DB yang bocor sudah cukup untuk menyamar.');
        $this->assertSame(hash('sha256', $token), $stored);
        $this->assertNotEmpty($company->id);
    }

    public function test_the_printed_token_still_authenticates(): void
    {
        // Mengamankan penyimpanan tidak boleh mematikan jalurnya: kalau token hasil
        // provisioning tidak bisa dipakai, operator akan mencari jalan pintas.
        [$company, $token] = $this->provisionedTenant();

        $this->botRequest($company, $token)->assertOk();
    }

    public function test_negative_a_tampered_token_is_refused(): void
    {
        [$company, $token] = $this->provisionedTenant();

        $this->botRequest($company, $token.'x')->assertUnauthorized();
        $this->botRequest($company, strtoupper($token))->assertUnauthorized();
    }

    public function test_negative_a_legacy_plaintext_row_can_no_longer_authenticate(): void
    {
        // Baris lama menyimpan token plaintext. Setelah perubahan ini pencocokan
        // dilakukan terhadap hash, jadi baris semacam itu **berhenti bekerja** - dan
        // itu memang yang diinginkan: fail-closed, bukan menerima keduanya. Menerima
        // plaintext sebagai cadangan berarti tidak mengamankan apa pun.
        $owner = User::factory()->create(['wa_number' => '628111', 'wa_is_verified' => true]);
        $company = Company::factory()->create(['owner_user_id' => $owner->id, 'business_preset' => 'bengkel']);

        $profile = HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'type' => 'primary',
            'status' => 'paired',
            'webhook_secret_reference' => 'sec_token_lama_plaintext',
        ]);
        $profile->companies()->attach($company->id, ['is_default' => true, 'created_at' => now()]);

        $this->botRequest($company, 'sec_token_lama_plaintext', '628111')->assertUnauthorized();
    }

    public function test_reissuing_a_token_replaces_the_old_one(): void
    {
        // Konsekuensi langsung dari menyimpan hash: token yang hilang **tidak bisa**
        // ditampilkan ulang. Tanpa jalan menerbitkan ulang, operator yang kehilangan
        // token akan terjebak pada profil yang tidak bisa dipakai - dan jalan keluar
        // yang tersedia (menghapus profil) akan memutus peta companyâ†”profil.
        [$company, $token] = $this->provisionedTenant();

        $baru = $this->runProvision([
            '--company' => $company->id,
            '--node' => (string) HermesNode::query()->value('id'),
            '--reissue' => true,
        ]);

        $this->assertNotSame($token, $baru);
        $this->assertSame(1, HermesProfile::query()->count(), 'Penerbitan ulang tidak boleh membuat profil kedua.');

        $this->botRequest($company, $baru)->assertOk();
        $this->botRequest($company, $token)->assertUnauthorized();
    }

    public function test_negative_the_token_is_not_shown_again_on_a_repeat_run(): void
    {
        [$company] = $this->provisionedTenant();

        $keluaran = $this->provisionOutput([
            '--company' => $company->id,
            '--node' => (string) HermesNode::query()->value('id'),
        ]);

        // Perintah harus mengatakan apa adanya: yang tersimpan hanya hash, jadi tidak
        // ada token untuk ditampilkan. Menampilkan sesuatu yang tampak seperti token
        // padahal bukan akan membuat operator memasang nilai yang tidak pernah bekerja.
        $this->assertStringNotContainsString('sec_', $keluaran);
        $this->assertStringContainsString('--reissue', $keluaran);
    }

    /**
     * @return array{0: Company, 1: string}
     */
    private function provisionedTenant(): array
    {
        $owner = User::factory()->create(['wa_number' => '628111', 'wa_is_verified' => true]);
        $company = Company::factory()->create(['owner_user_id' => $owner->id, 'business_preset' => 'bengkel']);

        $node = HermesNode::factory()->create([
            'api_url' => 'http://127.0.0.1:3000',
            'api_secret_reference' => 'none',
            'status' => 'active',
        ]);

        $token = $this->runProvision(['--company' => $company->id, '--node' => $node->id]);

        HermesProfile::query()->update(['status' => 'paired']);

        return [$company, $token];
    }

    /**
     * Menjalankan perintah provisioning dan memungut token dari keluaran terminal -
     * satu-satunya tempat ia pernah muncul.
     *
     * @param  array<string, mixed>  $options
     */
    private function runProvision(array $options): string
    {
        $output = $this->provisionOutput($options);

        preg_match('/(sec_[A-Za-z0-9]+)/', $output, $matches);

        $this->assertNotEmpty($matches, "Perintah tidak mencetak token. Keluaran:\n".$output);

        return $matches[1];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function provisionOutput(array $options): string
    {
        Artisan::call('bos:hermes-profile', $options);

        return Artisan::output();
    }

    private function botRequest(Company $company, string $token, string $wa = '628111'): TestResponse
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Caller-Wa-Number' => $wa,
        ])->getJson('/api/bot/tenant/capabilities?company_id='.$company->id);
    }
}
