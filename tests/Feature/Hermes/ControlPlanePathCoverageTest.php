<?php

namespace Tests\Feature\Hermes;

use App\Models\HermesNode;
use App\Services\Hermes\ControlPlanePaths;
use App\Services\Hermes\HermesControlPlaneClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Satu kasus uji untuk **setiap** path di daftar-putih control plane (T-86 penjaga c).
 *
 * Kenapa literal dan berulang, bukan satu loop atas `ControlPlanePaths::ALLOWED`:
 * loop akan otomatis "meliputi" path apa pun yang kelak ditambahkan, sehingga izin
 * baru masuk tanpa ada yang pernah memeriksa bentuk pemakaiannya. Dengan literal,
 * menambah satu path menuntut satu baris uji baru yang terlihat di diff.
 *
 * Yang dipatok di sini bukan sekadar "boleh dipanggil", melainkan **dua hal yang
 * mudah salah dan tidak menimbulkan galat apa pun saat salah**:
 *
 * 1. URL akhirnya - termasuk nama placeholder. Template `{id}` untuk sesi onboarding
 *    tidak akan pernah cocok, karena Hermes menamainya `{pairing_id}`.
 * 2. **Di mana** nama profil mendarat. Query untuk sebagian endpoint, body untuk
 *    `pairing/approve`, `pairing/revoke`, dan `onboarding/start`. Mengirimnya di
 *    tempat yang salah membuat Hermes mengabaikannya, lalu permintaan jatuh ke profil
 *    yang sedang aktif: tenant yang salah dikonfigurasi, tanpa satu galat pun.
 */
class ControlPlanePathCoverageTest extends TestCase
{
    use RefreshDatabase;

    private HermesControlPlaneClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('hermes.control_secrets', ['kontrol_uji' => 'token-kontrol-uji']);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $this->client = app(HermesControlPlaneClient::class);
    }

    public function test_profile_endpoints(): void
    {
        $this->assertUrl('GET', '/api/profiles', [], null, 'https://kontrol.uji.test/api/profiles');
        $this->assertUrl('POST', '/api/profiles', [], null, 'https://kontrol.uji.test/api/profiles');
        $this->assertUrl('PATCH', '/api/profiles/{name}', ['name' => 'tenant-3'], null, 'https://kontrol.uji.test/api/profiles/tenant-3');
        $this->assertUrl('DELETE', '/api/profiles/{name}', ['name' => 'tenant-3'], null, 'https://kontrol.uji.test/api/profiles/tenant-3');
        $this->assertUrl('GET', '/api/profiles/{name}/soul', ['name' => 'tenant-3'], null, 'https://kontrol.uji.test/api/profiles/tenant-3/soul');
        $this->assertUrl('PUT', '/api/profiles/{name}/soul', ['name' => 'tenant-3'], null, 'https://kontrol.uji.test/api/profiles/tenant-3/soul');
        $this->assertUrl('PUT', '/api/profiles/{name}/model', ['name' => 'tenant-3'], null, 'https://kontrol.uji.test/api/profiles/tenant-3/model');
    }

    public function test_pairing_endpoints_and_where_the_profile_lands(): void
    {
        // `GET /api/pairing` membaca profil dari query.
        $this->assertUrl('GET', '/api/pairing', [], 'tenant-3', 'https://kontrol.uji.test/api/pairing?profile=tenant-3');

        // `clear-pending` juga query: `async def clear_pending_pairing(profile=None)`.
        $this->assertUrl('POST', '/api/pairing/clear-pending', [], 'tenant-3', 'https://kontrol.uji.test/api/pairing/clear-pending?profile=tenant-3');

        // `approve` dan `revoke` membaca `body.profile` - bukan query.
        $this->assertProfileInBody('POST', '/api/pairing/approve');
        $this->assertProfileInBody('POST', '/api/pairing/revoke');
    }

    public function test_whatsapp_onboarding_endpoints(): void
    {
        // `onboarding/start` membaca `body.profile`.
        $this->assertProfileInBody('POST', '/api/messaging/whatsapp/onboarding/start');

        // Sesi dicari berdasarkan `pairing_id`, jadi tidak ter-scope profil. Nama
        // placeholder-nya `{pairing_id}`, bukan `{id}`.
        $this->assertUrl('GET', '/api/messaging/whatsapp/onboarding/{pairing_id}', ['pairing_id' => 'sesi_1'], null, 'https://kontrol.uji.test/api/messaging/whatsapp/onboarding/sesi_1');
        $this->assertUrl('DELETE', '/api/messaging/whatsapp/onboarding/{pairing_id}', ['pairing_id' => 'sesi_1'], null, 'https://kontrol.uji.test/api/messaging/whatsapp/onboarding/sesi_1');

        // `apply` menerima profil dari query.
        $this->assertUrl('POST', '/api/messaging/whatsapp/onboarding/{pairing_id}/apply', ['pairing_id' => 'sesi_1'], 'tenant-3', 'https://kontrol.uji.test/api/messaging/whatsapp/onboarding/sesi_1/apply?profile=tenant-3');
    }

    public function test_monitoring_endpoints(): void
    {
        $this->assertUrl('GET', '/api/messaging/platforms', [], 'tenant-3', 'https://kontrol.uji.test/api/messaging/platforms?profile=tenant-3');
        $this->assertUrl('GET', '/api/status', [], 'tenant-3', 'https://kontrol.uji.test/api/status?profile=tenant-3');
        $this->assertUrl('GET', '/api/health', [], null, 'https://kontrol.uji.test/api/health');
        $this->assertUrl('GET', '/api/system/stats', [], null, 'https://kontrol.uji.test/api/system/stats');
    }

    public function test_the_whitelist_has_no_entry_left_untested(): void
    {
        // Penjaga terhadap berkas ini sendiri: kalau `ControlPlanePaths::ALLOWED`
        // bertambah tanpa kasus uji baru, jumlahnya tidak lagi cocok. Angkanya
        // disengaja literal - ia harus berubah bersama daftarnya, di satu commit yang
        // sama, sehingga terlihat di review.
        $this->assertCount(19, ControlPlanePaths::ALLOWED);
    }

    /**
     * @param  array<string, string>  $pathParams
     */
    private function assertUrl(string $method, string $template, array $pathParams, ?string $profile, string $expected): void
    {
        $this->client->call($this->node(), $method, $template, $pathParams, $profile);

        Http::assertSent(fn ($request) => $request->url() === $expected && $request->method() === $method);
    }

    private function assertProfileInBody(string $method, string $template): void
    {
        $this->client->call($this->node(), $method, $template, [], 'tenant-3', ['platform' => 'whatsapp']);

        // Closure ini diuji terhadap **seluruh** permintaan yang tercatat di test,
        // termasuk yang tidak punya badan - jadi aksesnya harus aman, bukan langsung
        // `$request['profile']`.
        Http::assertSent(function ($request) use ($method, $template) {
            $expectedPath = parse_url($request->url(), PHP_URL_PATH);

            return $request->method() === $method
                && $expectedPath === $template
                && data_get($request->data(), 'profile') === 'tenant-3'
                && ! str_contains($request->url(), 'profile=');
        });
    }

    private function node(): HermesNode
    {
        return HermesNode::factory()->create([
            'api_url' => 'http://127.0.0.1:3000',
            'api_secret_reference' => 'none',
            'control_url' => 'https://kontrol.uji.test',
            'control_secret_reference' => 'kontrol_uji',
            'status' => 'active',
        ]);
    }
}
