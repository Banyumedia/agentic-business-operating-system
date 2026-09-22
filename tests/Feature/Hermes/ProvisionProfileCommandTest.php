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
        // membuat kolom itu dekorasi.
        $node = $this->node();
        $node->update(['max_capacity' => 1, 'active_profiles' => 1]);

        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $this->artisan('bos:hermes-profile', ['--company' => $company->id, '--node' => $node->id])
            ->assertFailed();

        $this->assertSame(0, HermesProfile::query()->count());
    }

    public function test_the_node_profile_counter_follows_the_profiles_it_holds(): void
    {
        $node = $this->node();

        $this->provisionTenant($node);
        $this->provisionTenant($node);

        $this->assertSame(2, (int) $node->fresh()->active_profiles);
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
