<?php

namespace Tests\Feature\Hermes;

use App\Models\Company;
use App\Models\HermesNode;
use App\Models\HermesProfile;
use App\Models\User;
use App\Services\Hermes\ProfileMirror;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * T-83: cermin profil node Hermes + rekonsiliasi dua arah.
 *
 * Kenapa lapisan ini ada, dan kenapa bentuknya seperti ini:
 *
 * (a) **Read-through, tanpa tabel bayangan** (D-72 butir 2). Salinan daftar profil
 *     akan menyimpang persis saat node tidak terjangkau - yaitu saat operator paling
 *     butuh angka yang benar. Halaman yang menampilkan salinan basi tanpa mengatakannya
 *     lebih berbahaya daripada halaman yang jujur kosong, karena ia membuat operator
 *     yakin tidak ada masalah.
 *
 * (b) **Rekonsiliasi harus terlihat, bukan dirapikan.** Profil yang ada di node tetapi
 *     tidak ada di `hermes_profiles` berarti **ada bot berjalan di luar pembukuan**:
 *     tidak ada company yang menanggungnya, tidak ada kuota yang dihitung, dan tidak
 *     ada yang berwenang atasnya. Sebaliknya, baris kita yang menunjuk profil tak ada
 *     di node adalah pemetaan mati. Keduanya ditampilkan; **tidak ada** yang dihapus
 *     otomatis, karena memusnahkan pemetaan company↔profil hanya karena node sedang
 *     sakit jauh lebih merugikan daripada membiarkan satu baris bertanda merah.
 *
 * (c) **Tidak ada rahasia di hasil.** `webhook_secret_reference` menyimpan **hash**
 *     token bot (QA-08). Hash pun tidak dirender: ia cukup untuk mencocokkan token
 *     kalau seseorang bisa menebak kandidatnya, dan tidak ada satu pun keputusan
 *     operator yang membutuhkannya di layar.
 *
 * (d) **SOUL tidak diambil untuk daftar.** Satu panggilan per profil berarti N+1 ke
 *     node, dan isi SOUL bukan tontonan daftar - ia memuat aturan bisnis dan identitas
 *     white-label (D-68).
 *
 * (e) **Node bermasalah tidak menjatuhkan pemanggil.** Pola `checkHealth()` yang sudah
 *     ada: kegagalan dikembalikan sebagai hasil, bukan dilempar, supaya laman
 *     pemantauan tetap hidup. Sebab kegagalan **dibedakan** - "belum berwenang" dan
 *     "node mati" mengirim operator ke tempat yang berbeda, dan menyatukannya membuat
 *     ia mencari masalah di tempat yang salah.
 *
 * Bentuk respons node yang dipakai di sini dibaca dari kode Hermes
 * (`hermes_cli/web_routers/profiles.py`), bukan ditebak: `GET /api/profiles` menjawab
 * `{"profiles": [{"name", "is_default", "gateway_running", "description", ...}]}` dan
 * `GET /api/profiles/{name}/soul` menjawab `{"content", "exists"}`.
 */
class HermesProfileMirrorTest extends TestCase
{
    use RefreshDatabase;

    private ProfileMirror $mirror;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('hermes.control_secrets', ['kontrol_uji' => 'token-kontrol-uji']);

        $this->mirror = app(ProfileMirror::class);
    }

    // ---------------------------------------------------------------------
    // Negatif lebih dulu: tiga sebab kegagalan yang tidak boleh disatukan.
    // ---------------------------------------------------------------------

    public function test_negative_a_node_without_a_control_plane_fails_without_touching_the_network(): void
    {
        // Node yang hanya menjalankan bridge adalah keadaan **sah** hari ini. Ia harus
        // dilaporkan sebagai konfigurasi yang belum diisi, bukan sebagai node sakit -
        // dan tidak boleh ada satu permintaan pun yang dibuat, karena alamat bridge
        // bukan penggantinya.
        Http::fake();

        $node = HermesNode::factory()->create([
            'api_url' => 'http://127.0.0.1:3000',
            'api_secret_reference' => 'none',
            'control_url' => null,
            'control_secret_reference' => null,
        ]);

        $hasil = $this->mirror->forNode($node);

        $this->assertFalse($hasil['ok']);
        $this->assertSame('tanpa_control_plane', $hasil['sebab']);
        $this->assertStringContainsString('control plane', $hasil['pesan']);
        $this->assertNotSame('', $hasil['diambil_pada']);

        // Tidak ada kelompok yang diisi saat node tidak bisa dibaca: mengklaim
        // "hilang di node" berdasarkan jawaban yang tidak pernah datang adalah
        // kebohongan yang menuntun operator menghapus baris yang sehat.
        $this->assertSame([], $hasil['cocok']);
        $this->assertSame([], $hasil['yatim']);
        $this->assertSame([], $hasil['hilang_di_node']);

        Http::assertNothingSent();
    }

    public function test_negative_a_401_is_reported_as_not_yet_authorized_not_as_a_dead_node(): void
    {
        // Ini keadaan **hari ini**: `GET /api/profiles` menjawab 401 sampai H-05
        // mendarat di repo Hermes. Kalau ia dilaporkan sebagai "node tidak terjangkau",
        // operator akan memeriksa proses dan jaringan di host yang sebenarnya sehat.
        Http::fake(['*' => Http::response(['detail' => 'Unauthorized'], 401)]);

        $hasil = $this->mirror->forNode($this->node());

        $this->assertFalse($hasil['ok']);
        $this->assertSame('belum_berwenang', $hasil['sebab']);
        $this->assertNotSame('tidak_terjangkau', $hasil['sebab']);
        $this->assertStringContainsString('berwenang', $hasil['pesan']);

        // Pesan tidak boleh menyalahkan node: itu yang membuat operator mencari
        // masalah di tempat yang salah.
        $this->assertStringNotContainsString('tidak terjangkau', $hasil['pesan']);

        // Dan nilai rahasianya tidak ikut, walau ia ada di konfigurasi.
        $this->assertStringNotContainsString('token-kontrol-uji', json_encode($hasil));
    }

    public function test_negative_an_unreachable_node_is_reported_as_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $hasil = $this->mirror->forNode($this->node());

        $this->assertFalse($hasil['ok']);
        $this->assertSame('tidak_terjangkau', $hasil['sebab']);
        $this->assertNotSame('belum_berwenang', $hasil['sebab']);
    }

    public function test_negative_a_failing_node_never_throws_to_the_caller(): void
    {
        // Penjaga eksplisit terhadap regresi yang paling mudah terjadi: seseorang
        // "merapikan" lapisan ini dengan melempar ulang exception, dan satu node sakit
        // menjatuhkan seluruh halaman pemantauan - menghilangkan satu-satunya cara
        // melihat bahwa node itu sakit.
        $kasus = [
            403 => 'belum_berwenang',
            404 => 'tidak_ada_di_node',
            429 => 'dibatasi_laju',
            500 => 'tidak_terjangkau',
        ];

        // `Http::fake()` **menambah** stub, tidak menggantinya: memanggilnya ulang di
        // dalam loop membuat stub pertama menang untuk seluruh iterasi, sehingga test
        // tampak hijau padahal hanya satu kode status yang pernah diuji.
        $urutan = Http::fakeSequence();

        foreach (array_keys($kasus) as $status) {
            $urutan->push(['detail' => 'x'], $status);
        }

        foreach ($kasus as $status => $sebab) {
            // Cache dilewati supaya setiap iterasi benar-benar memanggil node; tanpa
            // ini iterasi kedua dan seterusnya hanya membaca jawaban pertama.
            $hasil = $this->mirror->forNode($this->node(), fresh: true);

            $this->assertFalse($hasil['ok'], "HTTP {$status} seharusnya gagal.");
            $this->assertSame($sebab, $hasil['sebab'], "HTTP {$status} salah dipetakan.");
        }
    }

    // ---------------------------------------------------------------------
    // Rekonsiliasi dua arah.
    // ---------------------------------------------------------------------

    public function test_an_orphan_profile_on_the_node_shows_up_in_the_result(): void
    {
        $node = $this->node();
        $milik = $this->profile($node, 'inst_tenant_resmi', 'Asisten Toko');

        Http::fake(['*' => Http::response(['profiles' => [
            ['name' => 'inst_tenant_resmi', 'gateway_running' => true],
            // Profil yang tidak pernah kita catat: ada bot berjalan di luar pembukuan.
            ['name' => 'bot-liar', 'gateway_running' => true],
        ]], 200)]);

        $hasil = $this->mirror->forNode($node);

        $this->assertTrue($hasil['ok']);
        $this->assertSame(['inst_tenant_resmi'], array_column($hasil['cocok'], 'nama_profil_node'));
        $this->assertSame(['bot-liar'], array_column($hasil['yatim'], 'nama_profil_node'));
        $this->assertSame([], $hasil['hilang_di_node']);

        // Baris yatim tidak punya id kita - justru itu keluhannya.
        $this->assertNull($hasil['yatim'][0]['profil_id']);
        $this->assertSame($milik->id, $hasil['cocok'][0]['profil_id']);
    }

    public function test_a_row_missing_on_the_node_shows_up_and_is_not_deleted(): void
    {
        $node = $this->node();
        $hantu = $this->profile($node, 'inst_tenant_hantu', 'Asisten Lama');

        Http::fake(['*' => Http::response(['profiles' => [
            ['name' => 'default', 'is_default' => true],
        ]], 200)]);

        $hasil = $this->mirror->forNode($node);

        $this->assertSame(['inst_tenant_hantu'], array_column($hasil['hilang_di_node'], 'instance_id'));

        // Inti butir (b): pemetaan company↔profil **tetap ada**. Menghapusnya karena
        // node menjawab tidak lengkap - atau karena profilnya sedang dipindah - memutus
        // riwayat yang tidak bisa dibangun ulang.
        $this->assertDatabaseHas('hermes_profiles', [
            'id' => $hantu->id,
            'instance_id' => 'inst_tenant_hantu',
        ]);
        $this->assertSame('Asisten Lama', $hantu->fresh()->label);
    }

    public function test_a_row_not_placed_on_any_node_is_not_blamed_on_this_node(): void
    {
        // Profil dengan `node_id` kosong belum pernah mengklaim tinggal di mana pun -
        // `CleanupExpiredTrials` menulis `node_id => null` saat mencabut. Melaporkannya
        // sebagai "hilang di node" mengubah pencabutan yang normal menjadi alarm palsu,
        // dan alarm palsu yang rutin adalah cara tercepat membuat kelompok ini
        // diabaikan.
        $node = $this->node();
        $this->profile($node, 'inst_menempel', 'Menempel');
        $lepas = $this->profile(null, 'inst_lepas', 'Tanpa Node');

        Http::fake(['*' => Http::response(['profiles' => [
            ['name' => 'inst_menempel'],
        ]], 200)]);

        $hasil = $this->mirror->forNode($node);

        $this->assertSame([], $hasil['hilang_di_node']);
        $this->assertNotContains('inst_lepas', array_column($hasil['cocok'], 'instance_id'));
        $this->assertDatabaseHas('hermes_profiles', ['id' => $lepas->id]);
    }

    public function test_the_node_default_profile_is_marked_as_the_nodes_own_not_silently_dropped(): void
    {
        // Setiap Hermes punya profil `default`. Menyaringnya dari kelompok yatim akan
        // menyembunyikan bot dev yang nyata berjalan; membiarkannya tanpa penanda
        // membuat kelompok yatim selalu berisi satu baris yang wajar, dan kelompok yang
        // selalu merah akan diabaikan. Jadi ia tetap tampil, tetapi **bertanda**.
        Http::fake(['*' => Http::response(['profiles' => [
            ['name' => 'default', 'is_default' => true],
        ]], 200)]);

        $hasil = $this->mirror->forNode($this->node());

        $this->assertCount(1, $hasil['yatim']);
        $this->assertTrue($hasil['yatim'][0]['bawaan_node']);
    }

    // ---------------------------------------------------------------------
    // Rahasia, SOUL, dan pemisahan profil platform.
    // ---------------------------------------------------------------------

    public function test_negative_no_secret_value_ever_reaches_the_result(): void
    {
        $node = $this->node();
        $token = 'sec_token-bot-yang-tidak-boleh-bocor';
        $hash = HermesProfile::hashBotToken($token);

        $profile = $this->profile($node, 'inst_rahasia', 'Asisten Rahasia');
        $profile->forceFill(['webhook_secret_reference' => $hash])->save();

        Http::fake(['*' => Http::response(['profiles' => [
            ['name' => 'inst_rahasia'],
            // Node pun bisa mengembalikan berkas yang bukan tontonan daftar; kalau
            // lapisan ini meneruskan apa saja, isi SOUL dan letak berkas di host ikut
            // terbawa ke layar.
            ['name' => 'bot-liar', 'path' => '/home/hermes/profiles/bot-liar', 'soul' => 'isi SOUL rahasia'],
        ]], 200)]);

        $serial = json_encode($this->mirror->forNode($node));

        // Hash cukup untuk menguji kandidat token secara offline; ia tidak pernah
        // dibutuhkan di layar, jadi ia tidak pernah dirender - bahkan terpotong.
        $this->assertStringNotContainsString($hash, $serial);
        $this->assertStringNotContainsString(substr($hash, 0, 12), $serial);
        $this->assertStringNotContainsString($token, $serial);
        $this->assertStringNotContainsString('isi SOUL rahasia', $serial);
        $this->assertStringNotContainsString('/home/hermes/profiles', $serial);
    }

    public function test_negative_the_soul_is_not_fetched_while_loading_the_list(): void
    {
        $node = $this->node();
        $this->profile($node, 'inst_satu', 'Satu');
        $this->profile(null, 'inst_dua', 'Dua');

        Http::fake(['*' => Http::response(['profiles' => [
            ['name' => 'inst_satu'],
            ['name' => 'inst_dua'],
            ['name' => 'inst_tiga'],
        ]], 200)]);

        $this->mirror->forNode($node);

        // Satu panggilan SOUL per profil adalah N+1 ke node - pada armada penuh itu
        // ratusan permintaan untuk satu render halaman.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/soul'));

        // Tepatnya: **satu** permintaan untuk seluruh daftar.
        Http::assertSentCount(1);
    }

    public function test_the_soul_is_fetched_only_when_one_profile_is_opened(): void
    {
        Http::fake(['*' => Http::response(['content' => 'Kamu adalah asisten.', 'exists' => true], 200)]);

        $hasil = $this->mirror->soulFor($this->node(), 'inst_satu');

        $this->assertTrue($hasil['ok']);
        $this->assertTrue($hasil['ada']);
        $this->assertSame('Kamu adalah asisten.', $hasil['isi']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/soul'));
    }

    public function test_negative_a_soul_fetch_from_a_broken_node_also_returns_instead_of_throwing(): void
    {
        // Alasan yang sama dengan daftar: layar yang membuka satu profil tidak boleh
        // mati karena node sedang sakit.
        Http::fake(['*' => Http::response(['detail' => 'Unauthorized'], 401)]);

        $hasil = $this->mirror->soulFor($this->node(), 'inst_satu');

        $this->assertFalse($hasil['ok']);
        $this->assertSame('belum_berwenang', $hasil['sebab']);
        $this->assertSame('', $hasil['isi']);
    }

    public function test_platform_profiles_stay_distinguishable_from_tenant_profiles(): void
    {
        // T-68 tidak dilonggarkan: profil milik platform melayani **nol** company.
        // Mencampurnya dengan profil tenant membuat daftar itu menyesatkan - yang satu
        // menandakan pelanggan, yang lain tidak.
        $node = $this->node();

        $tenant = $this->profile($node, 'inst_tenant', 'Asisten Tenant');
        $company = Company::factory()->create(['name' => 'PT Uji Coba']);
        $tenant->companies()->syncWithoutDetaching([$company->id => ['is_default' => true, 'created_at' => now()]]);

        $admin = User::factory()->create(['is_platform_admin' => true]);
        HermesProfile::factory()->create([
            'owner_user_id' => $admin->id,
            'node_id' => $node->id,
            'type' => 'primary',
            'label' => 'Bot CS Platform',
            'instance_id' => 'inst_platform',
            'is_platform_provided' => true,
        ]);

        Http::fake(['*' => Http::response(['profiles' => [
            ['name' => 'inst_tenant'],
            ['name' => 'inst_platform'],
        ]], 200)]);

        $hasil = $this->mirror->forNode($node);

        $baris = collect($hasil['cocok'])->keyBy('instance_id');

        $this->assertFalse($baris['inst_tenant']['milik_platform']);
        $this->assertTrue($baris['inst_platform']['milik_platform']);

        // Company hanya namanya - id maupun slug tidak dibutuhkan untuk menilai
        // "siapa yang dilayani profil ini".
        $this->assertSame(['PT Uji Coba'], $baris['inst_tenant']['companies']);
        $this->assertSame([], $baris['inst_platform']['companies']);
    }

    // ---------------------------------------------------------------------
    // Read-through: cache pendek, stempel waktu, dan cara memaksa segar.
    // ---------------------------------------------------------------------

    public function test_every_result_carries_the_moment_it_was_fetched(): void
    {
        // Tanpa stempel ini, halaman tidak bisa membedakan "baru saja benar" dari
        // "benar beberapa menit lalu", dan cache pendek berubah menjadi kebohongan
        // kecil yang tidak terdeteksi.
        Http::fake(['*' => Http::response(['profiles' => []], 200)]);

        $hasil = $this->mirror->forNode($this->node());

        $this->assertNotSame('', $hasil['diambil_pada']);
        $this->assertNotFalse(strtotime($hasil['diambil_pada']));
    }

    public function test_a_second_read_is_served_from_the_short_cache_and_fresh_forces_a_new_call(): void
    {
        $node = $this->node();

        Http::fake(['*' => Http::response(['profiles' => []], 200)]);

        $this->mirror->forNode($node);
        $this->mirror->forNode($node);

        // Satu render halaman bisa memanggil cermin beberapa kali; tanpa cache pendek
        // setiap klik menjadi beberapa perjalanan ke node.
        Http::assertSentCount(1);

        $this->mirror->forNode($node, fresh: true);

        // Dan harus ada cara memaksa segar, kalau tidak tombol "segarkan" berbohong.
        Http::assertSentCount(2);
    }

    public function test_the_cache_is_per_node_so_one_node_never_answers_for_another(): void
    {
        // Kunci cache yang tidak menyebut node membuat jawaban satu host dipakai untuk
        // host lain - armada yang tampak sehat karena satu node sehat.
        $satu = $this->node();
        $dua = $this->node();

        Http::fake(['*' => Http::response(['profiles' => []], 200)]);

        $this->mirror->forNode($satu);
        $this->mirror->forNode($dua);

        Http::assertSentCount(2);
    }

    // ---------------------------------------------------------------------

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

    private function profile(?HermesNode $node, string $instanceId, string $label): HermesProfile
    {
        return HermesProfile::factory()->create([
            'owner_user_id' => User::factory(),
            'node_id' => $node?->id,
            'type' => 'primary',
            'label' => $label,
            'instance_id' => $instanceId,
        ]);
    }
}
