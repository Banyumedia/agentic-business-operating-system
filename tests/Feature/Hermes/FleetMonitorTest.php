<?php

namespace Tests\Feature\Hermes;

use App\Models\HermesNode;
use App\Models\HermesProfile;
use App\Models\User;
use App\Services\Hermes\FleetMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * T-84: keadaan gateway dan platform per profil, dibaca dari control plane.
 *
 * (a) **HTTP 200 bukan bukti sehat** - pelajaran yang sama dengan `/health` bridge di
 *     T-81, dan kali ini buktinya ada di instalasi ini: `gateway_state.json` memuat
 *     `whatsapp: fatal whatsapp_not_paired` dan `whatsapp_cloud: fatal
 *     whatsapp_cloud_unconfigured` sementara servernya menjawab normal. Keputusan
 *     diambil dari isi badan respons. Daftar keadaan mati yang dipakai di sini
 *     **bukan karangan**: ia diambil dari `_PLATFORM_DEAD_STATES` milik Hermes sendiri
 *     (`fatal`, `disconnected`, `stopped`), supaya penilaian kita dan penilaian
 *     dashboard-nya tidak pernah berbeda.
 *
 * (b) **Tiga keadaan dibedakan, tidak diringkas jadi "gagal"**: node tidak terjangkau
 *     / node hidup tetapi platform mati / sehat. Menyatukannya membuat operator
 *     mencari masalah di tempat yang salah - yang pertama soal proses dan jaringan,
 *     yang kedua soal konfigurasi dan pairing di dalam profil.
 *
 * (c) **Tidak menulis `hermes_profiles.status`.** Kolom itu sudah punya penulis
 *     tunggal (T-81, dari bridge WhatsApp). Menambah penulis kedua dari sumber yang
 *     berbeda menghasilkan dua kebenaran yang saling menimpa tiap sepuluh menit, dan
 *     tidak ada yang bisa tahu mana yang benar. Keadaan dari control plane adalah
 *     informasi runtime yang ditampilkan, bukan disimpan.
 */
class FleetMonitorTest extends TestCase
{
    use RefreshDatabase;

    private FleetMonitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('hermes.control_secrets', ['kontrol_uji' => 'token-kontrol-uji']);

        $this->monitor = app(FleetMonitor::class);
    }

    public function test_negative_a_platform_that_is_fatal_on_http_200_is_reported_as_broken(): void
    {
        // Inti butir (a). Kalau ini lolos sebagai sehat, seluruh lajur pemantauan
        // menjadi hiasan: keadaan yang paling sering terjadi justru inilah.
        Http::fake(['*' => Http::response([
            'platforms' => [
                ['id' => 'whatsapp', 'enabled' => true, 'state' => 'fatal', 'detail' => 'whatsapp_not_paired'],
                ['id' => 'telegram', 'enabled' => true, 'state' => 'running'],
            ],
        ], 200)]);

        $hasil = $this->monitor->forProfile($this->node(), 'inst_tenant');

        $this->assertSame('platform_bermasalah', $hasil['keadaan']);
        $this->assertContains('whatsapp', array_column($hasil['platform_bermasalah'], 'id'));
        $this->assertNotContains('telegram', array_column($hasil['platform_bermasalah'], 'id'));
    }

    public function test_negative_every_dead_state_hermes_itself_recognises_is_treated_as_dead(): void
    {
        // Daftar ini disalin dari `_PLATFORM_DEAD_STATES` Hermes. Kalau kita hanya
        // mengenali `fatal`, platform yang `disconnected` akan dilaporkan sehat -
        // padahal dashboard Hermes sendiri menandainya mati.
        $urutan = Http::fakeSequence();

        foreach (['fatal', 'disconnected', 'stopped'] as $state) {
            $urutan->push(['platforms' => [['id' => 'whatsapp', 'enabled' => true, 'state' => $state]]], 200);
        }

        foreach (['fatal', 'disconnected', 'stopped'] as $state) {
            $hasil = $this->monitor->forProfile($this->node(), 'inst_tenant');

            $this->assertSame('platform_bermasalah', $hasil['keadaan'], "State {$state} seharusnya dianggap mati.");
        }
    }

    public function test_a_platform_that_is_disabled_is_not_a_failure(): void
    {
        // Platform yang sengaja dimatikan bukan kerusakan. Melaporkannya sebagai
        // masalah membuat setiap profil selalu merah - dan armada yang selalu merah
        // sama tidak bergunanya dengan armada yang selalu hijau.
        Http::fake(['*' => Http::response([
            'platforms' => [
                ['id' => 'whatsapp', 'enabled' => true, 'state' => 'running'],
                ['id' => 'whatsapp_cloud', 'enabled' => false, 'state' => 'fatal', 'detail' => 'unconfigured'],
            ],
        ], 200)]);

        $hasil = $this->monitor->forProfile($this->node(), 'inst_tenant');

        $this->assertSame('sehat', $hasil['keadaan']);
        $this->assertSame([], $hasil['platform_bermasalah']);
    }

    public function test_negative_an_unreachable_node_is_a_different_state_from_a_broken_platform(): void
    {
        // Butir (b): dua keadaan ini mengirim operator ke tempat yang berbeda.
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $hasil = $this->monitor->forProfile($this->node(), 'inst_tenant');

        $this->assertSame('node_tidak_terjangkau', $hasil['keadaan']);
        $this->assertNotSame('platform_bermasalah', $hasil['keadaan']);
    }

    public function test_negative_a_401_says_we_are_not_authorised_yet_not_that_the_node_is_dead(): void
    {
        // Keadaan hari ini sampai H-05 mendarat.
        Http::fake(['*' => Http::response(['detail' => 'Unauthorized'], 401)]);

        $hasil = $this->monitor->forProfile($this->node(), 'inst_tenant');

        $this->assertSame('belum_berwenang', $hasil['keadaan']);
    }

    public function test_negative_monitoring_never_writes_the_profile_status(): void
    {
        // Butir (c) dikunci di sini. `hermes_profiles.status` punya satu penulis:
        // `ProfileStatusRefresher`, dari bridge. Kalau pemantauan ikut menulisnya,
        // dua sumber akan saling menimpa tiap sepuluh menit.
        $node = $this->node();
        $profile = HermesProfile::factory()->create([
            'owner_user_id' => User::factory(),
            'node_id' => $node->id,
            'instance_id' => 'inst_tenant',
            'status' => 'paired',
        ]);

        Http::fake(['*' => Http::response([
            'platforms' => [['id' => 'whatsapp', 'enabled' => true, 'state' => 'fatal']],
        ], 200)]);

        $this->monitor->forNode($node);

        // Platform mati, tetapi status profil **tidak** berubah menjadi unpaired.
        $this->assertSame('paired', $profile->fresh()->status);
        $this->assertNull($profile->fresh()->last_ping_at);
    }

    public function test_negative_no_secret_or_env_value_reaches_the_result(): void
    {
        // Jawaban node memuat `env_path` dan nilai env yang sudah diredaksi Hermes.
        // Keduanya tidak dibutuhkan untuk menilai sehat/tidak, dan setiap field yang
        // diteruskan adalah satu peluang lagi membocorkan sesuatu ke layar.
        Http::fake(['*' => Http::response([
            'env_path' => 'C:/Users/rahasia/.hermes/.env',
            'gateway_start_command' => 'hermes gateway start --token rahasia-sekali',
            'platforms' => [[
                'id' => 'whatsapp',
                'enabled' => true,
                'state' => 'running',
                'env' => [['key' => 'WHATSAPP_TOKEN', 'is_set' => true, 'redacted_value' => 'sk-…abcd']],
            ]],
        ], 200)]);

        $serial = json_encode($this->monitor->forProfile($this->node(), 'inst_tenant'));

        $this->assertStringNotContainsString('C:/Users/rahasia', $serial);
        $this->assertStringNotContainsString('rahasia-sekali', $serial);
        $this->assertStringNotContainsString('sk-', $serial);
        $this->assertStringNotContainsString('token-kontrol-uji', $serial);
    }

    public function test_the_fleet_command_exits_non_zero_when_something_is_wrong(): void
    {
        // Supaya bisa dipakai penjadwal atau probe: perintah yang selalu keluar 0
        // tidak bisa membangunkan siapa pun.
        $node = $this->node();
        HermesProfile::factory()->create([
            'owner_user_id' => User::factory(),
            'node_id' => $node->id,
            'instance_id' => 'inst_tenant',
        ]);

        Http::fake(['*' => Http::response([
            'platforms' => [['id' => 'whatsapp', 'enabled' => true, 'state' => 'fatal']],
        ], 200)]);

        $this->artisan('bos:hermes-fleet-status')->assertExitCode(1);
    }

    public function test_the_fleet_command_survives_a_dead_node_and_still_reports(): void
    {
        $node = $this->node();
        HermesProfile::factory()->create([
            'owner_user_id' => User::factory(),
            'node_id' => $node->id,
            'instance_id' => 'inst_tenant',
        ]);

        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        // Tidak melempar, tidak menjatuhkan perintah - hanya melaporkan.
        $this->artisan('bos:hermes-fleet-status')->assertExitCode(1);
    }

    public function test_the_fleet_command_is_healthy_when_every_platform_is_alive(): void
    {
        $node = $this->node();
        HermesProfile::factory()->create([
            'owner_user_id' => User::factory(),
            'node_id' => $node->id,
            'instance_id' => 'inst_tenant',
        ]);

        Http::fake(['*' => Http::response([
            'platforms' => [['id' => 'whatsapp', 'enabled' => true, 'state' => 'running']],
        ], 200)]);

        $this->artisan('bos:hermes-fleet-status')->assertExitCode(0);
    }

    public function test_negative_a_node_without_a_control_plane_is_reported_not_crashed(): void
    {
        Http::fake();

        $node = HermesNode::factory()->create([
            'api_url' => 'http://127.0.0.1:3000',
            'api_secret_reference' => 'none',
            'control_url' => null,
            'control_secret_reference' => null,
        ]);

        $hasil = $this->monitor->forProfile($node, 'inst_tenant');

        $this->assertSame('tanpa_control_plane', $hasil['keadaan']);
        Http::assertNothingSent();
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
