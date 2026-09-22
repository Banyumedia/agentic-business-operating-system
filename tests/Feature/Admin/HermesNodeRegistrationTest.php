<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\HermesNodeManager;
use App\Models\HermesNode;
use App\Models\HermesProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T-70: super admin harus bisa **mendaftarkan** node Hermes, bukan hanya
 * menyuntingnya.
 *
 * Sebelum task ini `HermesNodeManager::saveNode()` hanya berjalan bila
 * `editingNodeId` terisi, sehingga tabel `hermes_nodes` yang kosong **tidak bisa
 * diisi dari UI sama sekali**. Itu jalan buntu nyata: seluruh integrasi Hermes
 * bergantung pada satu baris yang tidak punya cara dibuat, dan
 * `bos:hermes-ping` hanya bisa melaporkan "belum ada node terdaftar".
 */
class HermesNodeRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_platform_admin' => true]);
        Config::set('hermes.node_secrets', ['ref_uji' => 'rahasia-uji']);
    }

    public function test_super_admin_can_register_the_first_node(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(HermesNodeManager::class)
            ->set('name', 'Node Lokal')
            ->set('apiUrl', 'http://127.0.0.1:8642')
            ->set('apiSecretReference', 'ref_uji')
            ->set('maxCapacity', 100)
            ->set('status', 'active')
            ->call('saveNode')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('hermes_nodes', [
            'name' => 'Node Lokal',
            'api_url' => 'http://127.0.0.1:8642',
            'api_secret_reference' => 'ref_uji',
            'status' => 'active',
        ]);
    }

    public function test_editing_an_existing_node_still_works(): void
    {
        $node = $this->node();
        $this->actingAs($this->admin);

        Livewire::test(HermesNodeManager::class)
            ->call('editNode', $node->id)
            ->set('name', 'Node Lokal Baru')
            ->call('saveNode')
            ->assertHasNoErrors();

        $this->assertSame('Node Lokal Baru', $node->fresh()->name);
        $this->assertSame(1, HermesNode::query()->count());
    }

    public function test_negative_a_non_admin_cannot_register_a_node(): void
    {
        $outsider = User::factory()->create(['is_platform_admin' => false]);
        $this->actingAs($outsider);

        $this->get(route('admin.hermes-nodes'))->assertForbidden();
        $this->assertSame(0, HermesNode::query()->count());
    }

    public function test_negative_a_non_http_api_url_is_refused(): void
    {
        // Alamat tanpa skema akan ditolak `HermesNodeClient` saat mengirim, jadi
        // menolaknya di sini mencegah baris yang tampak sah tapi mati.
        $this->actingAs($this->admin);

        Livewire::test(HermesNodeManager::class)
            ->set('name', 'Node Salah')
            ->set('apiUrl', 'node.tanpa.skema')
            ->set('apiSecretReference', 'ref_uji')
            ->call('saveNode')
            ->assertHasErrors('apiUrl');

        $this->assertSame(0, HermesNode::query()->count());
    }

    public function test_negative_a_node_without_a_secret_reference_is_refused(): void
    {
        // Tanpa referensi rahasia, node tidak akan pernah bisa dipanggil - dan
        // kegagalannya baru terlihat jauh di belakang, saat mengirim.
        $this->actingAs($this->admin);

        Livewire::test(HermesNodeManager::class)
            ->set('name', 'Node Tanpa Rahasia')
            ->set('apiUrl', 'http://127.0.0.1:8642')
            ->set('apiSecretReference', '')
            ->call('saveNode')
            ->assertHasErrors('apiSecretReference');

        $this->assertSame(0, HermesNode::query()->count());
    }

    public function test_unreachable_node_is_reported_as_failed_not_as_an_error_page(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->node();
        $this->actingAs($this->admin);

        $component = Livewire::test(HermesNodeManager::class)->call('checkHealth');

        $component->assertOk();
        $this->assertFalse($component->get('health')[$this->firstNodeId()]['ok']);
    }

    public function test_reachable_node_is_reported_as_healthy(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $this->node();
        $this->actingAs($this->admin);

        $component = Livewire::test(HermesNodeManager::class)->call('checkHealth');

        $this->assertTrue($component->get('health')[$this->firstNodeId()]['ok']);
    }

    public function test_platform_profiles_are_listed_apart_from_tenant_profiles(): void
    {
        // Profil platform (bot dev dan bot CS kita) melayani nol company.
        // Mencampurnya dengan profil tenant membuat daftar itu menyesatkan.
        $node = $this->node();

        HermesProfile::factory()->create([
            'node_id' => $node->id,
            'type' => 'primary',
            'label' => 'Bot Dev Platform',
            'is_platform_provided' => true,
        ]);

        HermesProfile::factory()->create([
            'node_id' => $node->id,
            'type' => 'primary',
            'label' => 'Bot Tenant',
            'is_platform_provided' => false,
        ]);

        $this->actingAs($this->admin);

        $component = Livewire::test(HermesNodeManager::class);

        $this->assertCount(1, $component->get('platformProfiles'));
        $this->assertCount(1, $component->get('profiles'));
    }

    public function test_the_page_shows_the_profile_mirror_and_the_fleet_state(): void
    {
        // T-83 + T-84 bertemu di satu halaman: cermin menjawab "profil mana yang
        // ada di node", pemantauan menjawab "kanalnya hidup atau tidak".
        Http::fake(['*' => Http::response(['profiles' => [['name' => 'bot-liar']]], 200)]);

        $node = $this->node();
        $node->update(['control_url' => 'http://127.0.0.1:9119', 'control_secret_reference' => 'none']);

        $this->actingAs($this->admin);

        $component = Livewire::test(HermesNodeManager::class)->call('loadMirror');

        $component->assertOk();

        $cermin = $component->get('mirror')[$node->id];
        $this->assertTrue($cermin['ok']);
        $this->assertSame(['bot-liar'], array_column($cermin['yatim'], 'nama_profil_node'));
    }

    public function test_negative_an_unreachable_control_plane_does_not_break_the_page(): void
    {
        // Pola `checkHealth()` yang sudah terbukti: laman pemantauan yang mati justru
        // menghilangkan satu-satunya cara melihat bahwa ada node bermasalah.
        Http::fake(['*' => Http::response(['detail' => 'Unauthorized'], 401)]);

        $node = $this->node();
        $node->update(['control_url' => 'http://127.0.0.1:9119', 'control_secret_reference' => 'none']);

        $this->actingAs($this->admin);

        $component = Livewire::test(HermesNodeManager::class)->call('loadMirror');

        $component->assertOk();

        $cermin = $component->get('mirror')[$node->id];
        $this->assertFalse($cermin['ok']);
        // "Belum berwenang" harus tetap bisa dibedakan dari "node mati" sampai ke layar.
        $this->assertSame('belum_berwenang', $cermin['sebab']);
    }

    public function test_negative_the_mirror_is_not_loaded_until_it_is_asked_for(): void
    {
        // Memuat cermin di `mount()` berarti setiap kunjungan halaman menembak seluruh
        // armada - dan pada node yang mati, menunggu seluruh timeout sebelum satu
        // piksel pun tampil.
        Http::fake();

        $this->node()->update(['control_url' => 'http://127.0.0.1:9119', 'control_secret_reference' => 'none']);

        $this->actingAs($this->admin);

        Livewire::test(HermesNodeManager::class)->assertOk();

        Http::assertNothingSent();
    }

    public function test_super_admin_can_record_the_control_plane_address(): void
    {
        // T-82/D-72: alamat control plane **terpisah** dari alamat bridge. Tanpa kolom
        // sendiri, satu-satunya cara mengisinya adalah lewat basis data.
        $this->actingAs($this->admin);

        Livewire::test(HermesNodeManager::class)
            ->set('name', 'Host Lokal')
            ->set('apiUrl', 'http://127.0.0.1:3000')
            ->set('apiSecretReference', 'none')
            ->set('controlUrl', 'https://kontrol.uji.test')
            ->set('controlSecretReference', 'control_lokal')
            ->call('saveNode')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('hermes_nodes', [
            'name' => 'Host Lokal',
            'api_url' => 'http://127.0.0.1:3000',
            'control_url' => 'https://kontrol.uji.test',
            'control_secret_reference' => 'control_lokal',
        ]);
    }

    public function test_a_node_may_run_only_a_bridge_without_a_control_plane(): void
    {
        // Keadaan hari ini: bridge ada, dashboard API belum bisa dipanggil mesin
        // (menunggu H-05). Node seperti itu tetap sah.
        $this->actingAs($this->admin);

        Livewire::test(HermesNodeManager::class)
            ->set('name', 'Bridge Saja')
            ->set('apiUrl', 'http://127.0.0.1:3000')
            ->set('apiSecretReference', 'none')
            ->set('controlUrl', '')
            ->set('controlSecretReference', '')
            ->call('saveNode')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('hermes_nodes', ['name' => 'Bridge Saja', 'control_url' => null]);
    }

    public function test_negative_a_public_control_plane_without_a_secret_reference_is_refused(): void
    {
        // Aturan yang sama dengan yang ditegakkan `HermesControlPlaneClient`. Menolaknya
        // di sini mencegah baris yang tampak sah tetapi selalu gagal saat dipakai -
        // dan yang lebih buruk, mencegah operator menyangka control plane publik tanpa
        // token itu keadaan yang wajar. Token dashboard setara terminal di host Hermes.
        $this->actingAs($this->admin);

        Livewire::test(HermesNodeManager::class)
            ->set('name', 'Kontrol Telanjang')
            ->set('apiUrl', 'http://127.0.0.1:3000')
            ->set('apiSecretReference', 'none')
            ->set('controlUrl', 'https://kontrol.publik.test')
            ->set('controlSecretReference', '')
            ->call('saveNode')
            ->assertHasErrors('controlSecretReference');

        $this->assertSame(0, HermesNode::query()->count());
    }

    public function test_negative_a_control_plane_address_without_a_scheme_is_refused(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(HermesNodeManager::class)
            ->set('name', 'Kontrol Salah')
            ->set('apiUrl', 'http://127.0.0.1:3000')
            ->set('apiSecretReference', 'none')
            ->set('controlUrl', 'kontrol.tanpa.skema')
            ->set('controlSecretReference', 'control_lokal')
            ->call('saveNode')
            ->assertHasErrors('controlUrl');

        $this->assertSame(0, HermesNode::query()->count());
    }

    public function test_super_admin_can_refresh_profile_status_from_the_page(): void
    {
        // Status profil tidak boleh hanya bisa diubah lewat basis data atau lewat
        // penjadwal: operator yang baru memasang nomor perlu membuktikannya saat itu
        // juga, dari halaman yang sama tempat ia melihat armadanya.
        Http::fake(['*' => Http::response(['status' => 'connected'], 200)]);

        $node = $this->node();
        HermesProfile::factory()->create([
            'node_id' => $node->id,
            'api_url' => 'http://127.0.0.1:3000',
            'status' => 'unpaired',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(HermesNodeManager::class)
            ->call('refreshProfileStatus')
            ->assertOk();

        $this->assertSame('paired', HermesProfile::query()->first()->status);
    }

    public function test_negative_refreshing_a_profile_without_a_bridge_does_not_break_the_page(): void
    {
        Http::fake();

        HermesProfile::factory()->create(['node_id' => null, 'api_url' => null, 'status' => 'unpaired']);

        $this->actingAs($this->admin);

        Livewire::test(HermesNodeManager::class)
            ->call('refreshProfileStatus')
            ->assertOk();

        $this->assertSame('unpaired', HermesProfile::query()->first()->status);
        Http::assertNothingSent();
    }

    private function node(): HermesNode
    {
        return HermesNode::create([
            'name' => 'Node Lokal',
            'api_url' => 'http://127.0.0.1:8642',
            'api_secret_reference' => 'ref_uji',
            'max_capacity' => 100,
            'active_profiles' => 0,
            'status' => 'active',
        ]);
    }

    private function firstNodeId(): int
    {
        return (int) HermesNode::query()->value('id');
    }
}
