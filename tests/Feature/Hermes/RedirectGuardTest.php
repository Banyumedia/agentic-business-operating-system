<?php

namespace Tests\Feature\Hermes;

use App\Models\HermesNode;
use App\Services\Hermes\BridgeGateway;
use App\Services\Hermes\ControlPlaneException;
use App\Services\Hermes\HermesControlPlaneClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * QA-03: aturan "tanpa autentikasi hanya sah untuk loopback" dijaga oleh
 * `isLoopback()`, dan `isLoopback()` hanya melihat **host yang terkonfigurasi**.
 *
 * Guzzle secara bawaan mengikuti sampai lima redirect. Artinya bridge atau control
 * plane loopback yang disusupi - atau sekadar salah konfigurasi di belakang proxy -
 * bisa menjawab `302` ke host luar, dan klien HTTP dengan patuh mengulang
 * permintaannya ke sana, badan permintaan ikut serta. Untuk bridge badan itu memuat
 * **nomor tujuan dan isi pesan**; untuk control plane ia memuat isi SOUL dan
 * keputusan pairing. Pemeriksaan loopback dengan begitu hanya menjaga hop pertama,
 * dan justru hop keduanya yang berbahaya.
 *
 * Karena itu kedua kelas mematikan pengikutan redirect. Yang diuji di sini bukan
 * "redirect ditangani dengan baik" melainkan **permintaan kedua tidak pernah
 * terjadi**, plus kegagalannya terlihat oleh pemanggil - redirect yang diam-diam
 * dianggap sukses akan membuat pesan tampak terkirim padahal tidak.
 */
class RedirectGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Host luar yang tidak boleh pernah menerima satu byte pun dari kita.
     */
    private const ATTACKER = 'https://penyerang.uji.test/send';

    public function test_negative_the_bridge_does_not_follow_a_redirect_to_an_external_host(): void
    {
        Http::fake([
            '127.0.0.1:3000/*' => Http::response('', 302, ['Location' => self::ATTACKER]),
            'penyerang.uji.test/*' => Http::response(['success' => true], 200),
        ]);

        try {
            app(BridgeGateway::class)->sendText(
                'http://127.0.0.1:3000',
                'none',
                '081234567890',
                'rahasia yang tidak boleh singgah di host lain',
            );

            $this->fail('Redirect dari bridge seharusnya menggagalkan pengiriman, bukan diikuti.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('redirect', mb_strtolower($exception->getMessage()));
        }

        // Inti temuannya: badan permintaan memuat nomor tujuan dan isi pesan, jadi satu
        // permintaan yang lolos ke host luar sudah merupakan kebocoran - tidak ada yang
        // bisa ditarik kembali sesudahnya.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'penyerang.uji.test'));
        Http::assertSentCount(1);
    }

    public function test_negative_the_bridge_ping_does_not_follow_a_redirect_to_an_external_host(): void
    {
        // `ping()` memang tidak melempar - ia melaporkan. Yang diuji tetap sama:
        // permintaan kedua tidak pernah terjadi, dan node tidak dilaporkan sehat hanya
        // karena ada host lain yang bersedia menjawab 200 untuknya.
        Http::fake([
            '127.0.0.1:3000/*' => Http::response('', 301, ['Location' => 'https://penyerang.uji.test/health']),
            'penyerang.uji.test/*' => Http::response(['status' => 'connected'], 200),
        ]);

        $result = app(BridgeGateway::class)->ping('http://127.0.0.1:3000', 'none');

        $this->assertFalse($result['ok']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'penyerang.uji.test'));
        Http::assertSentCount(1);
    }

    public function test_negative_the_control_plane_does_not_follow_a_redirect_to_an_external_host(): void
    {
        Config::set('hermes.control_secrets', ['kontrol_uji' => 'token-kontrol-uji']);

        Http::fake([
            '127.0.0.1:8765/*' => Http::response('', 302, ['Location' => self::ATTACKER]),
            'penyerang.uji.test/*' => Http::response(['ok' => true], 200),
        ]);

        $node = HermesNode::factory()->create([
            'api_url' => 'http://127.0.0.1:3000',
            'api_secret_reference' => 'none',
            'control_url' => 'http://127.0.0.1:8765',
            'control_secret_reference' => 'kontrol_uji',
            'status' => 'active',
        ]);

        try {
            app(HermesControlPlaneClient::class)->call(
                $node,
                'PUT',
                '/api/profiles/{name}/soul',
                ['name' => 'tenant-3'],
                payload: ['soul' => 'isi SOUL yang tidak boleh singgah di host lain'],
            );

            $this->fail('Redirect dari control plane seharusnya melempar, bukan diikuti.');
        } catch (ControlPlaneException $exception) {
            $this->assertStringContainsString('redirect', mb_strtolower($exception->getMessage()));
        }

        // Token bearer control plane setara eksekusi kode di host Hermes, jadi mengirim
        // header `Authorization` ke host yang ditunjuk `Location` menyerahkan host itu
        // sekaligus.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'penyerang.uji.test'));
        Http::assertSentCount(1);
    }
}
