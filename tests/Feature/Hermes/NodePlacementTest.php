<?php

namespace Tests\Feature\Hermes;

use App\Models\Company;
use App\Models\HermesNode;
use App\Models\HermesProfile;
use App\Models\User;
use App\Services\Hermes\BridgeGateway;
use App\Services\Hermes\HermesProfileProvisioner;
use App\Services\Hermes\NodePlacement;
use App\Services\HermesNodeClient;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * T-105: penempatan tenant ke node + alokasi port + kapasitas yang tidak bisa berbohong.
 *
 * Tiga cacat yang ditutup di sini, ditulis sebagai test **negatif lebih dulu**:
 *
 * (a) `ensurePrimaryProfile()` dulu tidak pernah mengisi `node_id`. Profil lahir
 *     tanpa node, lalu `HermesNodeClient` menolak "belum ditempatkan" - tenant baru
 *     mendapat bot yang tidak akan pernah bisa mengirim, dan penyebabnya jauh dari
 *     kejadian. Sekarang penempatan otomatis, dan **menolak** kalau tidak ada node
 *     layak - gagal di depan lebih baik daripada tenant bisu.
 *
 * (b) Kapasitas dihitung dari kenyataan (`attachedProfileCount()`), bukan dari
 *     kolom `active_profiles` yang bisa menyimpang. Menurunkan `max_capacity` di
 *     bawah jumlah profil yang sudah ada tidak menendang yang sudah jalan.
 *
 * (c) Port bridge menjadi sumber daya yang dialokasikan: unik per node, dipilih
 *     dari rentang di config. Dua profil pada satu node tidak bisa memakai port
 *     yang sama - ditolak saat simpan, bukan saat pesan pertama.
 */
class NodePlacementTest extends TestCase
{
    use RefreshDatabase;

    private function activeNode(array $overrides = []): HermesNode
    {
        return HermesNode::create(array_merge([
            'name' => 'Node Lokal '.bin2hex(random_bytes(3)),
            'api_url' => 'http://127.0.0.1:3000',
            'api_secret_reference' => 'none',
            'max_capacity' => 100,
            'active_profiles' => 0,
            'status' => 'active',
        ], $overrides));
    }

    private function provisioner(): HermesProfileProvisioner
    {
        return new HermesProfileProvisioner(new NodePlacement);
    }

    // (a) — negatif lebih dulu: tanpa node layak, TIDAK ada profil tanpa node.

    public function test_negative_ensure_primary_profile_without_an_eligible_node_fails_and_writes_nothing(): void
    {
        $owner = User::factory()->create();

        $this->expectException(RuntimeException::class);

        try {
            $this->provisioner()->ensurePrimaryProfile($owner);
        } finally {
            // Yang paling penting: tidak ada profil menggantung tanpa node. Profil
            // tanpa node adalah bot bisu yang gagal jauh dari sini.
            $this->assertSame(0, HermesProfile::query()->count());
        }
    }

    public function test_negative_a_node_that_is_maintenance_down_or_draining_is_never_chosen_for_new_placement(): void
    {
        // Tiga keadaan yang bukan 'active' semuanya tidak layak untuk penempatan
        // baru. draining khususnya: ia masih melayani yang lama, tetapi tidak
        // menerima yang baru.
        $this->activeNode(['status' => 'maintenance']);
        $this->activeNode(['status' => 'down']);
        $this->activeNode(['status' => 'draining']);

        $owner = User::factory()->create();

        $this->expectException(RuntimeException::class);

        try {
            $this->provisioner()->ensurePrimaryProfile($owner);
        } finally {
            $this->assertSame(0, HermesProfile::query()->count());
        }
    }

    public function test_it_places_a_new_primary_profile_on_an_active_node_with_a_bridge_port(): void
    {
        $node = $this->activeNode();
        $owner = User::factory()->create();

        $profile = $this->provisioner()->ensurePrimaryProfile($owner);

        $this->assertSame((int) $node->id, (int) $profile->node_id);
        // Port dialokasikan dari rentang config (bawaan 3000-3099).
        $this->assertNotNull($profile->bridge_port);
        $this->assertGreaterThanOrEqual(3000, (int) $profile->bridge_port);
        $this->assertLessThanOrEqual(3099, (int) $profile->bridge_port);
    }

    // (c) — negatif: port yang sama tidak boleh dipakai dua profil di satu node.

    public function test_negative_two_profiles_on_one_node_never_share_a_port(): void
    {
        $node = $this->activeNode();

        $first = $this->provisioner()->ensurePrimaryProfile(User::factory()->create());
        $second = $this->provisioner()->ensurePrimaryProfile(User::factory()->create());

        $this->assertNotSame((int) $first->bridge_port, (int) $second->bridge_port);
    }

    public function test_negative_a_duplicate_port_on_a_node_is_refused_at_save_not_at_first_message(): void
    {
        // Uji kendala basis data secara langsung: alokasi bisa keliru, tetapi
        // unique-per-node adalah jaring terakhir yang menolak sebelum baris tersimpan
        // - bukan menunggu pesan pertama gagal senyap.
        $node = $this->activeNode();
        $owner = User::factory()->create();

        HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'node_id' => $node->id,
            'bridge_port' => 3000,
        ]);

        $this->expectException(QueryException::class);

        HermesProfile::factory()->create([
            'owner_user_id' => User::factory()->create()->id,
            'node_id' => $node->id,
            'bridge_port' => 3000,
        ]);
    }

    // (b) — kapasitas nyata; menurunkan max_capacity tidak menendang yang sudah jalan.

    public function test_max_capacity_is_honoured_using_the_real_count(): void
    {
        $node = $this->activeNode(['max_capacity' => 1]);

        $this->provisioner()->ensurePrimaryProfile(User::factory()->create());

        // Node sudah penuh menurut hitungan nyata; profil kedua tidak punya node layak.
        $this->expectException(RuntimeException::class);
        $this->provisioner()->ensurePrimaryProfile(User::factory()->create());
    }

    public function test_lowering_max_capacity_below_existing_count_does_not_evict_running_profiles(): void
    {
        $node = $this->activeNode(['max_capacity' => 5]);

        $a = $this->provisioner()->ensurePrimaryProfile(User::factory()->create());
        $b = $this->provisioner()->ensurePrimaryProfile(User::factory()->create());

        // Operator menurunkan kapasitas di bawah jumlah yang sudah ada.
        $node->update(['max_capacity' => 1]);

        // Yang sudah jalan tetap menempel: menendang berarti mematikan tenant yang
        // sedang bekerja.
        $this->assertNotNull($a->fresh()->node_id);
        $this->assertNotNull($b->fresh()->node_id);
        $this->assertSame(2, $node->fresh()->attachedProfileCount());
    }

    // (a/idempoten) — profil primary yang sudah punya node tidak dipindah/ditempatkan ulang.

    public function test_idempotent_primary_profile_is_not_re_placed_when_called_again(): void
    {
        $node = $this->activeNode();
        $owner = User::factory()->create();

        $first = $this->provisioner()->ensurePrimaryProfile($owner);
        $originalNode = $first->node_id;
        $originalPort = $first->bridge_port;

        // Tambah node kedua yang juga layak. Panggilan kedua tidak boleh memindah
        // profil ke sana - firstOrCreate menemukan yang lama apa adanya.
        $this->activeNode();

        $second = $this->provisioner()->ensurePrimaryProfile($owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($originalNode, $second->node_id);
        $this->assertSame($originalPort, $second->bridge_port);
        $this->assertSame(1, HermesProfile::query()->where('owner_user_id', $owner->id)->count());
    }

    // (draining) — node draining tetap melayani profil yang sudah tertempel.

    public function test_a_draining_node_still_serves_profiles_already_attached_to_it(): void
    {
        // Node draining tidak menerima penempatan baru (diuji di atas), tetapi
        // profil yang sudah di sana harus tetap bisa mengirim. Kalau
        // HermesNodeClient menolak semua kecuali 'active', draining akan mematikan
        // tenant yang sudah jalan - itu yang tidak boleh terjadi.
        $node = $this->activeNode();
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $profile = HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'type' => 'primary',
            'node_id' => $node->id,
            'status' => 'paired',
            'api_url' => 'http://127.0.0.1:3000',
            'is_platform_provided' => false,
        ]);
        $profile->companies()->attach($company->id, ['is_default' => true, 'created_at' => now()]);

        // Node beralih ke draining setelah profil menempel.
        $node->update(['status' => 'draining']);

        // Gateway dipalsukan supaya test tidak benar-benar menyentuh jaringan;
        // yang diuji adalah bahwa HermesNodeClient TIDAK menolak karena status
        // 'draining'. Bila ia menolak, exception "tidak aktif" muncul sebelum
        // gateway dipanggil.
        $gateway = new class extends BridgeGateway
        {
            public function sendText(string $apiUrl, string $secretReference, string $to, string $message): void
            {
                // Sengaja tidak melakukan apa-apa: sukses.
            }
        };

        $client = new HermesNodeClient($gateway);

        $client->sendWhatsAppMessage((string) $company->id, '08123456789', 'halo');

        // Tidak ada exception = draining tidak mematikan pengiriman yang sudah jalan.
        $this->assertTrue(true);
    }

    public function test_negative_a_maintenance_node_still_refuses_to_send_for_attached_profiles(): void
    {
        // Kelonggaran draining tidak boleh merembet ke maintenance/down: keduanya
        // tetap menolak. Ini menjaga agar "draining tetap melayani" tidak diam-diam
        // melonggarkan penolakan yang sah.
        $node = $this->activeNode();
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $profile = HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'type' => 'primary',
            'node_id' => $node->id,
            'status' => 'paired',
            'api_url' => 'http://127.0.0.1:3000',
            'is_platform_provided' => false,
        ]);
        $profile->companies()->attach($company->id, ['is_default' => true, 'created_at' => now()]);

        $node->update(['status' => 'maintenance']);

        $client = new HermesNodeClient;

        $this->expectException(RuntimeException::class);
        $client->sendWhatsAppMessage((string) $company->id, '08123456789', 'halo');
    }
}
