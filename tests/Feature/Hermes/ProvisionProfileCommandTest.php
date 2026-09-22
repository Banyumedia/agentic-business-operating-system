<?php

namespace Tests\Feature\Hermes;

use App\Models\Company;
use App\Models\HermesNode;
use App\Models\HermesProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-80: jalur pembuatan profil Hermes.
 *
 * `HermesProfileProvisioner::ensurePrimaryProfile()` sudah ada sejak lama tetapi
 * **tidak pernah dipanggil dari mana pun** - onboarding tidak memakainya, admin
 * tidak punya tombolnya. Akibatnya tidak ada satu cara pun membuat baris
 * `hermes_profiles`, dan tanpa baris itu:
 *
 * - `HermesNodeClient` selalu menolak (tidak ada profil yang melayani company)
 * - bot tenant tidak punya `webhook_secret_reference` untuk memanggil TenantBot API
 * - bot CS platform tidak bisa didaftarkan sama sekali
 *
 * Pola yang sama dengan `hermes_nodes` sebelum T-70: skema siap, jalurnya tidak ada.
 */
class ProvisionProfileCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_provisions_a_tenant_profile_and_links_it_to_the_company(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $node = $this->node();

        $this->artisan('bos:hermes-profile', [
            '--company' => $company->id,
            '--node' => $node->id,
        ])->assertSuccessful();

        $profile = HermesProfile::query()->firstWhere('owner_user_id', $owner->id);

        $this->assertNotNull($profile);
        $this->assertSame('primary', $profile->type);
        $this->assertFalse((bool) $profile->is_platform_provided);
        $this->assertSame((int) $node->id, (int) $profile->node_id);
        $this->assertTrue($profile->companies->contains($company->id));
    }

    public function test_the_secret_reference_is_generated_and_never_reused(): void
    {
        // `webhook_secret_reference` adalah token yang dipakai bot untuk memanggil
        // TenantBot API (`AuthenticateTenantBot` mencarinya apa adanya). Dua tenant
        // dengan token sama berarti bot yang satu bisa menyamar sebagai yang lain.
        $first = $this->provisionTenant();
        $second = $this->provisionTenant();

        $this->assertNotSame($first->webhook_secret_reference, $second->webhook_secret_reference);
        $this->assertNotSame($first->instance_id, $second->instance_id);
        $this->assertGreaterThanOrEqual(24, strlen((string) $first->webhook_secret_reference));
    }

    public function test_running_it_twice_does_not_create_a_second_profile(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $node = $this->node();

        $this->artisan('bos:hermes-profile', ['--company' => $company->id, '--node' => $node->id])->assertSuccessful();
        $this->artisan('bos:hermes-profile', ['--company' => $company->id, '--node' => $node->id])->assertSuccessful();

        $this->assertSame(1, HermesProfile::query()->count());
        $this->assertSame(1, HermesProfile::query()->first()->companies()->count());
    }

    public function test_it_provisions_a_platform_cs_profile_without_a_billing_addon(): void
    {
        // Bot CS platform: `addon` tanpa `billing_addon_id`, melayani nol company.
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $node = $this->node();

        $this->artisan('bos:hermes-profile', [
            '--platform' => true,
            '--owner' => $admin->id,
            '--node' => $node->id,
            '--type' => 'addon',
            '--label' => 'Bot CS Platform',
        ])->assertSuccessful();

        $profile = HermesProfile::query()->firstWhere('is_platform_provided', true);

        $this->assertNotNull($profile);
        $this->assertSame('addon', $profile->type);
        $this->assertNull($profile->billing_addon_id);
        $this->assertSame(0, $profile->companies()->count());
    }

    public function test_running_the_platform_path_twice_does_not_create_a_second_profile(): void
    {
        // QA-01: jalur tenant sudah idempoten, jalur platform tidak. Profil platform
        // membawa nomor yang mewakili perusahaan, dan `PlatformHermesNodeClient`
        // memilih pengirim dengan `orderBy('id')->first()` - jadi profil kedua tidak
        // dipakai sama sekali, hanya menggantung dan membingungkan operasi.
        // `instance_id` acak per jalankan, jadi tidak ada kendala basis data yang
        // menahan duplikat ini.
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $node = $this->node();

        $options = [
            '--platform' => true,
            '--owner' => $admin->id,
            '--node' => $node->id,
            '--type' => 'addon',
            '--label' => 'Bot CS Platform',
        ];

        $this->artisan('bos:hermes-profile', $options)->assertSuccessful();
        $this->artisan('bos:hermes-profile', $options)->assertSuccessful();

        $this->assertSame(1, HermesProfile::query()->count());
        $this->assertSame(1, (int) $node->fresh()->active_profiles);
    }

    public function test_running_the_platform_path_twice_with_a_primary_type_also_stays_at_one(): void
    {
        // Tipe `primary` punya penjaga lain (hook `saving` di `HermesProfile`
        // melarang dua primary untuk satu owner), tetapi penjaga itu melempar
        // pengecualian - artinya jalankan kedua akan **gagal** alih-alih diam-diam
        // menemukan profil yang sudah ada. Idempoten berarti jalankan kedua sukses
        // dan tidak menambah apa pun.
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $node = $this->node();

        $options = [
            '--platform' => true,
            '--owner' => $admin->id,
            '--node' => $node->id,
            '--type' => 'primary',
        ];

        $this->artisan('bos:hermes-profile', $options)->assertSuccessful();
        $this->artisan('bos:hermes-profile', $options)->assertSuccessful();

        $this->assertSame(1, HermesProfile::query()->count());
        $this->assertSame(1, (int) $node->fresh()->active_profiles);
    }

    public function test_negative_a_platform_profile_owner_must_be_a_platform_admin(): void
    {
        // Profil platform membawa nomor yang mewakili kita. Menautkannya ke user
        // sembarang berarti pemilik nomor itu bukan pihak yang berwenang.
        $outsider = User::factory()->create(['is_platform_admin' => false]);
        $node = $this->node();

        $this->artisan('bos:hermes-profile', [
            '--platform' => true,
            '--owner' => $outsider->id,
            '--node' => $node->id,
            '--type' => 'addon',
        ])->assertFailed();

        $this->assertSame(0, HermesProfile::query()->count());
    }

    public function test_negative_an_unknown_company_or_node_fails_without_writing(): void
    {
        $this->artisan('bos:hermes-profile', ['--company' => 999, '--node' => 999])->assertFailed();

        $this->assertSame(0, HermesProfile::query()->count());
    }

    public function test_a_company_can_never_lack_an_owner_so_the_profile_link_cannot_dangle(): void
    {
        // Rencana awal saya menguji "company tanpa owner ditolak". Skenario itu
        // **mustahil**: `companies.owner_user_id` NOT NULL, dan basis data
        // menolaknya lebih dulu. Jadi yang diuji di sini adalah jaminan yang
        // sebenarnya berlaku, bukan cabang kode yang tidak akan pernah tercapai.
        // Guard di dalam perintah tetap ada sebagai lapis kedua.
        $company = Company::factory()->create();

        $this->expectException(QueryException::class);

        Company::query()->whereKey($company->id)->update(['owner_user_id' => null]);
    }

    public function test_negative_a_node_at_capacity_is_refused(): void
    {
        // `max_capacity` ada supaya satu node tidak kelebihan profil. Mengabaikannya
        // membuat kolom itu dekorasi. Node di sini **benar-benar** penuh: satu profil
        // nyata menempel padanya, bukan hanya kolom penghitung yang bilang begitu.
        $node = $this->node();
        $this->provisionTenant($node);
        $node->update(['max_capacity' => 1]);

        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $this->artisan('bos:hermes-profile', ['--company' => $company->id, '--node' => $node->id])
            ->assertFailed();

        $this->assertSame(1, HermesProfile::query()->count());
    }

    public function test_negative_a_stale_counter_must_not_make_an_empty_node_look_full(): void
    {
        // QA-02: `active_profiles` hanya pernah dinaikkan, tidak pernah diturunkan,
        // jadi ia menggelembung ke atas seiring waktu. Kalau penjaga kapasitas
        // mempercayai kolom itu, node yang lowong menolak profil yang sah - dan
        // kegagalannya muncul jauh dari penyebabnya. Kebenarannya adalah jumlah
        // profil yang benar-benar menempel pada node, bukan kolomnya.
        $node = $this->node();
        $node->update(['max_capacity' => 100, 'active_profiles' => 100]);

        $this->provisionTenant($node);

        $this->assertSame(1, (int) $node->fresh()->active_profiles);
    }

    public function test_the_node_profile_counter_follows_the_profiles_it_holds(): void
    {
        $node = $this->node();

        $this->provisionTenant($node);
        $this->provisionTenant($node);

        $this->assertSame(2, (int) $node->fresh()->active_profiles);
    }

    public function test_a_profile_can_be_given_its_own_bridge_address(): void
    {
        // Satu bridge WhatsApp = satu nomor = satu port. Kalau alamat itu hanya bisa
        // disimpan di node, nomor kedua pada host yang sama memaksa baris node kedua
        // dan `max_capacity` kehilangan arti.
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $node = $this->node();

        $this->artisan('bos:hermes-profile', [
            '--company' => $company->id,
            '--node' => $node->id,
            '--api-url' => 'http://127.0.0.1:3002',
        ])->assertSuccessful();

        $this->assertSame('http://127.0.0.1:3002', HermesProfile::query()->first()->api_url);
    }

    public function test_negative_a_bridge_address_without_a_scheme_is_refused(): void
    {
        // `HermesNodeClient::endpoint()` menolak alamat tanpa skema saat mengirim.
        // Menerimanya di sini hanya menunda kegagalan sampai pesan pertama.
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $node = $this->node();

        $this->artisan('bos:hermes-profile', [
            '--company' => $company->id,
            '--node' => $node->id,
            '--api-url' => '127.0.0.1:3002',
        ])->assertFailed();

        $this->assertSame(0, HermesProfile::query()->count());
    }

    private function provisionTenant(?HermesNode $node = null): HermesProfile
    {
        $node ??= $this->node();
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $this->artisan('bos:hermes-profile', ['--company' => $company->id, '--node' => $node->id])
            ->assertSuccessful();

        return HermesProfile::query()->firstWhere('owner_user_id', $owner->id);
    }

    private function node(): HermesNode
    {
        return HermesNode::create([
            'name' => 'Node Lokal',
            'api_url' => 'http://127.0.0.1:3000',
            'api_secret_reference' => 'none',
            'max_capacity' => 100,
            'active_profiles' => 0,
            'status' => 'active',
        ]);
    }
}
