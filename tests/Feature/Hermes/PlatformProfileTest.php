<?php

namespace Tests\Feature\Hermes;

use App\Models\Company;
use App\Models\HermesNode;
use App\Models\HermesProfile;
use App\Models\User;
use App\Services\HermesNodeClient;
use App\Services\WhatsApp\WhatsAppSenderIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/**
 * T-68: dua profil milik platform (bot dev dan bot CS kita) melayani **nol**
 * company, dan itu bertabrakan dengan tiga tempat yang semuanya company-scoped.
 *
 * (a) `HermesProfile::booted()` mewajibkan `addon` punya `billing_addon_id`. Bot
 *     CS platform tidak punya add-on berbayar, dan memalsukan baris billing
 *     adalah jebakan - bukan solusi.
 * (b) `HermesNodeClient::profileFor()` mensyaratkan profil yang melayani company.
 * (c) `WhatsAppSenderIdentity::companiesOf()` membatasi hasil ke company yang
 *     dilayani profil, sehingga profil platform akan menolak semuanya.
 *
 * Yang dijaga di sini: kelonggaran hanya berlaku untuk profil platform, dan
 * **tidak** melonggarkan aturan yang benar untuk profil tenant.
 */
class PlatformProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_platform_addon_profile_may_exist_without_a_billing_addon(): void
    {
        $profile = HermesProfile::create([
            'owner_user_id' => User::factory()->create()->id,
            'type' => 'addon',
            'label' => 'Bot CS Platform',
            'instance_id' => 'inst_platform_cs',
            'webhook_secret_reference' => 'sec_platform_cs',
            'status' => 'unpaired',
            'is_platform_provided' => true,
        ]);

        $this->assertTrue($profile->exists);
        $this->assertNull($profile->billing_addon_id);
    }

    public function test_negative_a_tenant_addon_profile_still_requires_a_billing_addon(): void
    {
        // Aturan ini benar dan tidak boleh ikut longgar: add-on yang dijual wajib
        // tertaut ke baris billing-nya.
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('billing_addon_id');

        HermesProfile::create([
            'owner_user_id' => User::factory()->create()->id,
            'type' => 'addon',
            'label' => 'Bot CS Tenant',
            'instance_id' => 'inst_tenant_cs',
            'webhook_secret_reference' => 'sec_tenant_cs',
            'status' => 'unpaired',
            'is_platform_provided' => false,
        ]);
    }

    public function test_negative_one_owner_still_cannot_have_two_primary_profiles(): void
    {
        // D-37 tidak dilonggarkan oleh penanda platform.
        $owner = User::factory()->create();

        HermesProfile::factory()->create(['owner_user_id' => $owner->id, 'type' => 'primary']);

        $this->expectException(LogicException::class);

        HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'type' => 'primary',
            'is_platform_provided' => true,
        ]);
    }

    public function test_negative_the_tenant_lane_refuses_a_platform_profile(): void
    {
        // Profil platform tidak melayani company, jadi lajur tenant tidak boleh
        // menemukannya - walau seseorang memaksa menautkannya lewat pivot.
        Http::fake();
        Config::set('hermes.node_secrets', ['ref_uji' => 'rahasia-uji']);

        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $node = HermesNode::factory()->create([
            'api_url' => 'https://node.uji.test',
            'api_secret_reference' => 'ref_uji',
            'status' => 'active',
        ]);

        $profile = HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'node_id' => $node->id,
            'type' => 'primary',
            'status' => 'paired',
            'is_platform_provided' => true,
        ]);
        $profile->companies()->attach($company->id, ['is_default' => true, 'created_at' => now()]);

        $this->expectException(RuntimeException::class);

        try {
            (new HermesNodeClient)->sendWhatsAppMessage((string) $company->id, '6281234567890', 'Halo');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_negative_a_platform_profile_never_resolves_a_tenant_identity(): void
    {
        // Pengirim yang nomornya terverifikasi tetap tidak boleh dikenali sebagai
        // anggota usaha lewat profil platform.
        $owner = User::factory()->create(['wa_number' => '6281234567890', 'wa_is_verified' => true]);
        Company::factory()->create(['owner_user_id' => $owner->id]);

        $profile = HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'type' => 'primary',
            'status' => 'paired',
            'is_platform_provided' => true,
        ]);

        $identity = app(WhatsAppSenderIdentity::class)->resolve($profile, '6281234567890');

        $this->assertFalse($identity['known']);
        $this->assertNull($identity['company']);
    }

    public function test_a_tenant_profile_still_resolves_its_owner(): void
    {
        // Pembanding: jalur tenant yang sah tidak ikut rusak.
        $owner = User::factory()->create(['wa_number' => '6281234567890', 'wa_is_verified' => true]);
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $profile = HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'type' => 'primary',
            'status' => 'paired',
            'is_platform_provided' => false,
        ]);
        $profile->companies()->attach($company->id, ['is_default' => true, 'created_at' => now()]);

        $identity = app(WhatsAppSenderIdentity::class)->resolve($profile, '6281234567890');

        $this->assertTrue($identity['known']);
        $this->assertSame((int) $company->id, (int) $identity['company']->id);
    }
}
