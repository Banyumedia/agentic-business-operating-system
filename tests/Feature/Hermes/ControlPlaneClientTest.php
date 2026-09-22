<?php

namespace Tests\Feature\Hermes;

use App\Models\HermesNode;
use App\Services\Hermes\ControlPlaneNotFound;
use App\Services\Hermes\ControlPlanePathDenied;
use App\Services\Hermes\ControlPlanePaths;
use App\Services\Hermes\ControlPlaneRateLimited;
use App\Services\Hermes\ControlPlaneSessionExpired;
use App\Services\Hermes\ControlPlaneUnauthorized;
use App\Services\Hermes\ControlPlaneUnavailable;
use App\Services\Hermes\HermesControlPlaneClient;
use App\Services\Hermes\NodeHasNoControlPlane;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Throwable;

/**
 * T-82: satu-satunya kelas yang boleh memanggil dashboard API Hermes (D-72).
 *
 * Kenapa dikurung dalam satu kelas: rute `/api/*` dashboard Hermes **tidak
 * berversi** dan sebagian besar masih di dalam `web_server.py`, jadi pembaruan
 * Hermes bisa memindahkannya tanpa peringatan. Dengan satu pintu, kerusakan itu
 * muncul sebagai **satu test merah**, bukan sebagai fitur yang diam-diam mati.
 *
 * Kenapa daftar-putih path, bukan proxy umum: port yang sama menyajikan
 * `/api/fs/write-text`, `/api/files/upload`, `/api/tools/terminal/*`, `/api/git/*`,
 * dan `/api/profiles/{name}/open-terminal`. Token dashboard karena itu **setara
 * eksekusi kode** di host Hermes. Tanpa daftar-putih, `EnforceBotToolScoping` dan
 * seluruh D-69 menjadi hiasan.
 *
 * Fakta yang dipakai di sini dibaca langsung dari
 * `%LOCALAPPDATA%\hermes\hermes-agent\hermes_cli\web_server.py`, bukan dari
 * dokumen: nama parameter onboarding adalah `{pairing_id}`, dan **tempat** profil
 * dikirim berbeda per endpoint — query untuk `/api/pairing`, `/api/status`,
 * `/api/messaging/platforms`, `clear-pending`, dan `apply`; **body** untuk
 * `pairing/approve`, `pairing/revoke`, dan `onboarding/start` (`_pairing_store(body.profile)`).
 */
class ControlPlaneClientTest extends TestCase
{
    use RefreshDatabase;

    private HermesControlPlaneClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('hermes.control_secrets', ['kontrol_uji' => 'token-kontrol-uji']);

        $this->client = app(HermesControlPlaneClient::class);
    }

    public function test_it_calls_a_whitelisted_path_with_a_bearer_token(): void
    {
        Http::fake(['*' => Http::response(['pending' => [], 'approved' => []], 200)]);

        $node = $this->node();

        $body = $this->client->call($node, 'GET', '/api/pairing', profile: 'tenant-3');

        $this->assertSame(['pending' => [], 'approved' => []], $body);
        Http::assertSent(fn ($request) => $request->url() === 'https://kontrol.uji.test/api/pairing?profile=tenant-3'
            && $request->header('Authorization') === ['Bearer token-kontrol-uji']);
    }

    public function test_path_parameters_are_filled_and_encoded(): void
    {
        Http::fake(['*' => Http::response(['soul' => '...'], 200)]);

        $this->client->call($this->node(), 'GET', '/api/profiles/{name}/soul', ['name' => 'bot cs/1']);

        Http::assertSent(fn ($request) => $request->url() === 'https://kontrol.uji.test/api/profiles/bot%20cs%2F1/soul');
    }

    public function test_a_body_scoped_endpoint_carries_the_profile_in_the_body(): void
    {
        // `POST /api/pairing/approve` membaca `body.profile` (`_pairing_store(body.profile)`).
        // Mengirimnya sebagai query akan diabaikan Hermes dan permintaannya jatuh ke
        // profil yang sedang aktif - persis yang dilarang D-72 butir (c).
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $this->client->call(
            $this->node(),
            'POST',
            '/api/pairing/approve',
            profile: 'tenant-3',
            payload: ['platform' => 'whatsapp', 'request_id' => 'req_1'],
        );

        Http::assertSent(fn ($request) => $request->url() === 'https://kontrol.uji.test/api/pairing/approve'
            && $request['profile'] === 'tenant-3'
            && $request['platform'] === 'whatsapp');
    }

    public function test_negative_a_path_outside_the_whitelist_never_reaches_the_network(): void
    {
        Http::fake();

        $this->expectException(ControlPlanePathDenied::class);

        try {
            $this->client->call($this->node(), 'GET', '/api/tools/terminal/run');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_negative_every_permanently_forbidden_path_is_refused(): void
    {
        Http::fake();

        // Delapan keluarga path yang dilarang permanen (D-72 butir 1). Masing-masing
        // memberi penguasaan atas host Hermes, bukan sekadar data.
        $forbidden = [
            ['POST', '/api/fs/write-text'],
            ['POST', '/api/files/upload'],
            ['POST', '/api/tools/terminal/run'],
            ['POST', '/api/git/commit'],
            ['POST', '/api/profiles/bot-cs/open-terminal'],
            ['GET', '/api/env/reveal'],
            ['POST', '/api/ops/restart'],
            ['GET', '/api/dashboard/plugins'],
        ];

        foreach ($forbidden as [$method, $path]) {
            try {
                $this->client->call($this->node(), $method, $path, profile: 'tenant-3');
                $this->fail("Path terlarang lolos: {$method} {$path}");
            } catch (ControlPlanePathDenied) {
                $this->assertTrue(true);
            }
        }

        Http::assertNothingSent();
    }

    public function test_negative_the_whitelist_and_the_denylist_can_never_overlap(): void
    {
        // Penjaga terhadap kesalahan manusia di berkas konstanta: satu baris baru di
        // daftar-putih yang kebetulan cocok dengan keluarga terlarang akan membuka
        // jalan tanpa ada yang menyadarinya.
        foreach (array_keys(ControlPlanePaths::ALLOWED) as $entry) {
            [, $path] = explode(' ', $entry, 2);

            $this->assertFalse(
                ControlPlanePaths::isForbidden($path),
                "Path {$path} ada di daftar-putih sekaligus daftar terlarang.",
            );
        }
    }

    public function test_negative_a_profile_scoped_endpoint_without_a_profile_is_refused(): void
    {
        // Dashboard punya konsep "profil aktif" yang merupakan state bersama dan bisa
        // berubah. Permintaan tanpa profil akan mengonfigurasi profil siapa pun yang
        // sedang aktif - pada armada multi-tenant itu berarti menyentuh tenant yang salah.
        Http::fake();

        $profileScoped = [
            ['GET', '/api/pairing'],
            ['GET', '/api/status'],
            ['GET', '/api/messaging/platforms'],
            ['POST', '/api/pairing/clear-pending'],
            ['POST', '/api/pairing/approve'],
            ['POST', '/api/messaging/whatsapp/onboarding/start'],
        ];

        foreach ($profileScoped as [$method, $path]) {
            try {
                $this->client->call($this->node(), $method, $path);
                $this->fail("Endpoint profile-scoped lolos tanpa profil: {$method} {$path}");
            } catch (ControlPlanePathDenied) {
                $this->assertTrue(true);
            }
        }

        Http::assertNothingSent();
    }

    public function test_negative_a_node_without_a_control_plane_address_is_refused(): void
    {
        // `api_url` adalah alamat **bridge** (loopback, tanpa auth, per nomor - T-81).
        // Memakainya sebagai alamat control plane akan mengirim token ke port yang
        // tidak memintanya, dan membuat node tanpa control plane tampak siap.
        Http::fake();

        $node = HermesNode::factory()->create([
            'api_url' => 'http://127.0.0.1:3000',
            'api_secret_reference' => 'none',
            'control_url' => null,
            'control_secret_reference' => null,
        ]);

        $this->expectException(NodeHasNoControlPlane::class);

        try {
            $this->client->call($node, 'GET', '/api/health');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_negative_a_missing_secret_is_refused_by_reference_name_not_by_value(): void
    {
        Http::fake();

        Config::set('hermes.control_secrets', []);

        try {
            $this->client->call($this->node(), 'GET', '/api/health');
            $this->fail('Rahasia yang belum dipasang seharusnya menolak panggilan.');
        } catch (ControlPlaneUnauthorized $exception) {
            $this->assertStringContainsString('kontrol_uji', $exception->getMessage());
            $this->assertStringNotContainsString('token-kontrol-uji', $exception->getMessage());
            Http::assertNothingSent();
        }
    }

    public function test_negative_a_public_control_plane_without_a_secret_is_refused(): void
    {
        // Aturan yang sama dengan bridge: ketiadaan autentikasi harus dinyatakan, dan
        // hanya sah untuk loopback. Control plane tanpa token di alamat publik berarti
        // siapa pun bisa menulis SOUL dan menyetujui pairing di host kita.
        Http::fake();

        $node = $this->node(controlUrl: 'https://kontrol.publik.test', secretReference: 'none');

        $this->expectException(ControlPlaneUnauthorized::class);

        try {
            $this->client->call($node, 'GET', '/api/health');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_a_loopback_control_plane_may_declare_it_has_no_authentication(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $node = $this->node(controlUrl: 'http://127.0.0.1:8765', secretReference: 'none');

        $this->client->call($node, 'GET', '/api/health');

        Http::assertSent(fn ($request) => $request->header('Authorization') === []);
    }

    public function test_status_codes_map_to_exceptions_that_say_different_things(): void
    {
        // Menyatukan semuanya menjadi "galat tak dikenal" membuat layar berbohong: QR
        // kedaluwarsa (410) dan lockout pairing (429) adalah keadaan yang wajar dan
        // punya jalan keluar sendiri, bukan kerusakan.
        $cases = [
            401 => ControlPlaneUnauthorized::class,
            404 => ControlPlaneNotFound::class,
            410 => ControlPlaneSessionExpired::class,
            429 => ControlPlaneRateLimited::class,
            503 => ControlPlaneUnavailable::class,
        ];

        // `Http::fake()` **menambah** stub, tidak menggantinya: memanggilnya ulang di
        // dalam loop membuat stub pertama menang untuk seluruh iterasi, sehingga test
        // tampak hijau padahal hanya satu kode status yang pernah diuji. Urutan di
        // `fakeSequence()` harus sama dengan urutan `$cases`.
        $sequence = Http::fakeSequence();

        foreach (array_keys($cases) as $status) {
            $sequence->push(['detail' => 'x'], $status);
        }

        foreach ($cases as $status => $expected) {
            try {
                $this->client->call($this->node(), 'GET', '/api/health');
                $this->fail("HTTP {$status} seharusnya melempar.");
            } catch (Throwable $exception) {
                $this->assertInstanceOf($expected, $exception, "HTTP {$status} salah dipetakan.");
            }
        }
    }

    public function test_negative_an_unreachable_control_plane_is_not_reported_as_a_bad_request(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->expectException(ControlPlaneUnavailable::class);

        $this->client->call($this->node(), 'GET', '/api/health');
    }

    public function test_negative_the_qr_payload_and_other_secrets_never_reach_the_log(): void
    {
        // `qr_payload` adalah kredensial sesi WhatsApp: siapa pun yang memilikinya bisa
        // memasang perangkat sebagai nomor itu. Isi SOUL dan nilai env juga bukan bahan
        // log.
        Log::spy();
        Http::fake(['*' => Http::response([
            'qr_payload' => '2@rahasia-qr-yang-tidak-boleh-tercatat',
            'soul' => 'isi SOUL rahasia',
            'token' => 'token-bocor',
            'detail' => 'gagal',
        ], 500)]);

        try {
            $this->client->call($this->node(), 'GET', '/api/health');
        } catch (ControlPlaneUnavailable) {
            // Yang diuji adalah isi log, bukan pengecualiannya.
        }

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context = []) {
            $flat = $message.json_encode($context);

            return ! str_contains($flat, 'rahasia-qr-yang-tidak-boleh-tercatat')
                && ! str_contains($flat, 'isi SOUL rahasia')
                && ! str_contains($flat, 'token-bocor')
                && ! str_contains($flat, 'token-kontrol-uji');
        });
    }

    public function test_the_control_ping_command_reports_the_node_without_sending_secrets(): void
    {
        Http::fake(['*' => Http::response(['status' => 'ok', 'version' => '0.9.1'], 200)]);

        $node = $this->node();

        $this->artisan('bos:hermes-control-ping', ['--node' => $node->id])
            ->expectsOutputToContain('0.9.1')
            ->assertSuccessful();
    }

    public function test_negative_the_control_ping_command_fails_when_the_node_has_no_control_plane(): void
    {
        Http::fake();

        $node = HermesNode::factory()->create([
            'control_url' => null,
            'control_secret_reference' => null,
        ]);

        $this->artisan('bos:hermes-control-ping', ['--node' => $node->id])->assertFailed();

        Http::assertNothingSent();
    }

    // Penjaga "satu pintu" dipindah ke `Tests\Architecture\ControlPlaneBoundaryTest`
    // (T-86): versi di sana memindai `app/`, `routes/`, dan `config/` secara rekursif,
    // sedangkan versi lama di berkas ini memakai glob berlapis yang melewatkan
    // direktori yang lebih dalam. Dua penjaga untuk satu aturan pasti menyimpang.

    private function node(
        string $controlUrl = 'https://kontrol.uji.test',
        string $secretReference = 'kontrol_uji',
    ): HermesNode {
        return HermesNode::factory()->create([
            'api_url' => 'http://127.0.0.1:3000',
            'api_secret_reference' => 'none',
            'control_url' => $controlUrl,
            'control_secret_reference' => $secretReference,
            'status' => 'active',
        ]);
    }
}
