<?php

namespace Tests\Feature\Hermes;

use App\Models\Company;
use App\Models\HermesNode;
use App\Models\HermesProfile;
use App\Models\User;
use App\Services\HermesNodeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * `HermesNodeClient` dulu **selalu melempar** ("not configured for actual
 * delivery in this environment"), jadi pengingat piutang (T-49), undangan staf
 * (T-51), dan efek workflow `notify_owner_wa` hanya pernah terbukti lewat mock.
 *
 * Test ini mengunci rantai fail-closed-nya dengan node yang dipalsukan pada
 * lapisan HTTP - bukan dengan memalsukan kelasnya sendiri, karena justru kelas
 * inilah yang diuji.
 */
class HermesNodeClientTest extends TestCase
{
    use RefreshDatabase;

    private HermesNodeClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('hermes.node_secrets', ['ref_uji' => 'rahasia-uji']);

        $this->client = new HermesNodeClient;
    }

    public function test_message_is_sent_using_the_real_bridge_contract(): void
    {
        // Kontrak nyata bridge WhatsApp Hermes (dibaca dari
        // scripts/whatsapp-bridge/bridge.js): POST /send dengan {chatId, message},
        // sukses ditandai `success: true`. Bentuk `/api/wa/send` dengan
        // `instance_id` yang dipakai sebelumnya adalah karangan dan tidak pernah
        // ada di Hermes.
        Http::fake(['*' => Http::response(['success' => true, 'messageId' => 'ABC'], 200)]);

        $company = $this->companyWithProfile();

        $this->client->sendWhatsAppMessage((string) $company->id, '0812-3456-7890', 'Tagihan jatuh tempo besok.');

        Http::assertSent(function ($request) {
            // Nomor dinormalkan lalu dijadikan JID WhatsApp.
            return $request->url() === 'https://node.uji.test/send'
                && $request['chatId'] === '6281234567890@s.whatsapp.net'
                && $request['message'] === 'Tagihan jatuh tempo besok.';
        });
    }

    public function test_negative_a_bridge_that_is_not_connected_fails_closed(): void
    {
        // Bridge menjawab 503 {"error":"Not connected to WhatsApp"} saat sesi
        // WhatsApp terputus. Itu keadaan nyata: `/health` pada instalasi ini
        // melaporkan `disconnected` sekarang.
        Http::fake(['*' => Http::response(['error' => 'Not connected to WhatsApp'], 503)]);

        $company = $this->companyWithProfile();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 503');

        $this->client->sendWhatsAppMessage((string) $company->id, '6281234567890', 'Halo');
    }

    public function test_negative_ping_reports_a_disconnected_bridge_as_unhealthy(): void
    {
        // Jebakan yang ditutup di sini: `/health` menjawab **HTTP 200** walau
        // WhatsApp-nya terputus. Memeriksa kode HTTP saja akan melaporkan bridge
        // mati sebagai sehat, dan itu justru kebohongan yang paling merugikan -
        // operator akan mencari masalah di tempat lain.
        Http::fake(['*' => Http::response(['status' => 'disconnected', 'queueLength' => 0], 200)]);

        $result = $this->client->ping('https://node.uji.test', 'ref_uji');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('disconnected', $result['detail']);
    }

    public function test_ping_reports_a_connected_bridge_as_healthy(): void
    {
        Http::fake(['*' => Http::response(['status' => 'connected', 'queueLength' => 0], 200)]);

        $result = $this->client->ping('https://node.uji.test', 'ref_uji');

        $this->assertTrue($result['ok']);
    }

    public function test_a_loopback_node_may_declare_it_has_no_authentication(): void
    {
        // Bridge WhatsApp Hermes **tidak punya autentikasi** sama sekali di port
        // 3000 - tidak ada token, tidak ada header. Memaksa referensi rahasia
        // palsu hanya supaya lolos aturan kita adalah kebohongan yang tersimpan
        // di basis data. Jadi ketiadaan itu dinyatakan **eksplisit** dengan
        // referensi `none`.
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $company = $this->companyWithProfile(apiUrl: 'http://127.0.0.1:3000', secretReference: 'none');

        $this->client->sendWhatsAppMessage((string) $company->id, '6281234567890', 'Halo');

        Http::assertSent(fn ($request) => $request->header('X-Hermes-Secret') === []);
    }

    public function test_negative_a_no_auth_node_is_refused_when_it_is_not_loopback(): void
    {
        // Node tanpa autentikasi hanya boleh di loopback. Mengarahkannya ke alamat
        // publik berarti siapa pun di internet bisa mengirim WhatsApp sebagai
        // nomor tenant.
        Http::fake();

        $company = $this->companyWithProfile(apiUrl: 'https://node.publik.test', secretReference: 'none');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tanpa autentikasi');

        try {
            $this->client->sendWhatsAppMessage((string) $company->id, '6281234567890', 'Halo');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_negative_company_without_a_serving_profile_is_refused(): void
    {
        // Bot satu usaha tidak boleh mengirim atas nama usaha lain yang kebetulan
        // satu pemilik, jadi yang menentukan adalah pivot - bukan kepemilikan.
        Http::fake();

        $company = Company::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tidak ada profil Hermes yang melayani company');

        try {
            $this->client->sendWhatsAppMessage((string) $company->id, '6281234567890', 'Halo');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_negative_unpaired_profile_is_refused(): void
    {
        Http::fake();

        $company = $this->companyWithProfile(profileStatus: 'unpaired');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Profil Hermes belum siap mengirim');

        try {
            $this->client->sendWhatsAppMessage((string) $company->id, '6281234567890', 'Halo');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_negative_node_that_is_not_active_is_refused(): void
    {
        Http::fake();

        $company = $this->companyWithProfile(nodeStatus: 'maintenance');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Node Hermes tidak aktif');

        try {
            $this->client->sendWhatsAppMessage((string) $company->id, '6281234567890', 'Halo');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_negative_missing_secret_reference_is_refused_without_leaking_the_value(): void
    {
        Http::fake();

        Config::set('hermes.node_secrets', []);
        $company = $this->companyWithProfile();

        try {
            $this->client->sendWhatsAppMessage((string) $company->id, '6281234567890', 'Halo');
            $this->fail('Rahasia yang belum dipasang seharusnya menolak pengiriman.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ref_uji', $exception->getMessage());
            $this->assertStringNotContainsString('rahasia-uji', $exception->getMessage());
            Http::assertNothingSent();
        }
    }

    public function test_negative_non_success_response_is_a_failure(): void
    {
        Http::fake(['*' => Http::response(['error' => 'instance offline'], 503)]);

        $company = $this->companyWithProfile();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Node Hermes menolak pengiriman (HTTP 503)');

        $this->client->sendWhatsAppMessage((string) $company->id, '6281234567890', 'Halo');
    }

    public function test_negative_a_200_that_does_not_confirm_delivery_is_a_failure(): void
    {
        // Ini yang mencegah pengingat tercatat terkirim padahal node gagal:
        // tanpa pemeriksaan badan respons, HTTP 200 saja sudah dianggap sukses
        // dan pengiriman tidak akan pernah dicoba ulang.
        Http::fake(['*' => Http::response(['ok' => false, 'error' => 'not paired'], 200)]);

        $company = $this->companyWithProfile();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak mengonfirmasi pesan terkirim');

        $this->client->sendWhatsAppMessage((string) $company->id, '6281234567890', 'Halo');
    }

    public function test_negative_empty_recipient_or_message_never_reaches_the_node(): void
    {
        Http::fake();

        $company = $this->companyWithProfile();

        foreach ([['', 'Halo'], ['6281234567890', '   ']] as [$to, $message]) {
            try {
                $this->client->sendWhatsAppMessage((string) $company->id, $to, $message);
                $this->fail('Tujuan atau pesan kosong seharusnya ditolak.');
            } catch (RuntimeException) {
                $this->assertTrue(true);
            }
        }

        Http::assertNothingSent();
    }

    public function test_negative_a_non_http_node_address_is_refused(): void
    {
        Http::fake();

        $company = $this->companyWithProfile(apiUrl: 'node.uji.test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Alamat node Hermes tidak valid.');

        try {
            $this->client->sendWhatsAppMessage((string) $company->id, '6281234567890', 'Halo');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_ping_reports_health_without_sending_any_message(): void
    {
        Http::fake(['*' => Http::response(['status' => 'connected'], 200)]);

        $result = $this->client->ping('https://node.uji.test', 'ref_uji');

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['status']);
        Http::assertSent(fn ($request) => $request->url() === 'https://node.uji.test/health'
            && $request->method() === 'GET');
    }

    public function test_negative_ping_on_a_missing_secret_fails_without_a_request(): void
    {
        Http::fake();

        $result = $this->client->ping('https://node.uji.test', 'ref_tidak_ada');

        $this->assertFalse($result['ok']);
        $this->assertNull($result['status']);
        Http::assertNothingSent();
    }

    private function companyWithProfile(
        string $profileStatus = 'paired',
        string $nodeStatus = 'active',
        string $apiUrl = 'https://node.uji.test',
        string $secretReference = 'ref_uji',
    ): Company {
        $owner = User::factory()->create(['wa_number' => '6281234567890', 'wa_is_verified' => true]);
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $node = HermesNode::factory()->create([
            'api_url' => $apiUrl,
            'api_secret_reference' => $secretReference,
            'status' => $nodeStatus,
        ]);

        $profile = HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'node_id' => $node->id,
            'type' => 'primary',
            'status' => $profileStatus,
        ]);

        $profile->companies()->attach($company->id, ['is_default' => true, 'created_at' => now()]);

        return $company;
    }
}
