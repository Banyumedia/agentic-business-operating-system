<?php

namespace Tests\Feature;

use App\Livewire\Settings\TeamAccess;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Providers\DataSourceServiceProvider;
use App\Services\Billing\UserQuotaGate;
use App\Services\CompanyRoleResolver;
use App\Services\HermesNodeClient;
use App\Services\Team\TeamInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * T-51 (D-65): keanggotaan tim, undangan lewat WA, dan kuota pengguna.
 *
 * Ini jalur akses, jadi yang dijaga lebih dulu perilaku menolaknya: staf tidak
 * bisa mengundang, kode bocor tidak bisa dipakai orang lain, pencabutan berlaku
 * langsung, dan kuota penuh menutup pintu.
 */
class TeamAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Suite default memakai driver JSON; keanggotaan hidup di Eloquent,
        // jadi binding-nya didaftarkan ulang seperti pola tes Eloquent lain.
        config(['datasource.driver' => 'eloquent']);
        (new DataSourceServiceProvider($this->app))->register();

        // Pengiriman WA dipalsukan: yang diuji keputusan siapa boleh apa,
        // bukan transport Hermes.
        $this->app->bind(HermesNodeClient::class, fn (): HermesNodeClient => new class extends HermesNodeClient
        {
            public function sendWhatsAppMessage(string $companyId, string $to, string $message): void {}
        });

        $this->owner = User::factory()->create(['wa_number' => '628110000001', 'wa_is_verified' => true]);
        $this->company = Company::create([
            'name' => 'Usaha Uji',
            'slug' => 'usaha-uji',
            'owner_user_id' => $this->owner->id,
            'business_preset' => 'bengkel',
            'module_settings' => [],
        ]);
        $this->owner->update(['current_company_id' => $this->company->id]);
        $this->company->members()->attach($this->owner->id, [
            'role' => CompanyRoleResolver::ROLE_OWNER,
            'accepted_at' => now(),
        ]);

        $this->plan(maxUsers: 5);
    }

    public function test_owner_invites_staff_and_staff_claims_the_code(): void
    {
        $this->actingAs($this->owner);

        $result = app(TeamInvitationService::class)->invite($this->company, '0812-3456-7890', $this->owner);

        $this->assertSame('6281234567890', $result['invitation']->wa_number);
        $this->assertSame(CompanyRoleResolver::ROLE_STAFF, $result['invitation']->role);
        // Kode tidak pernah disimpan sebagai teks terang.
        $this->assertNotSame($result['code'], $result['invitation']->code_hash);
        $this->assertTrue(Hash::check($result['code'], $result['invitation']->code_hash));

        $staff = User::factory()->create(['wa_number' => '6281234567890', 'wa_is_verified' => true]);
        app(TeamInvitationService::class)->claim($staff, $result['code']);

        $this->assertTrue($this->company->fresh()->members->contains($staff->id));

        $staff->update(['current_company_id' => $this->company->id]);
        $this->actingAs($staff);
        $resolver = app(CompanyRoleResolver::class);
        $this->assertTrue($resolver->isMemberOfCompany($this->company->id));
        $this->assertFalse($resolver->isOwnerOfActiveCompany());
        $this->assertSame(CompanyRoleResolver::ROLE_STAFF, $resolver->roleForActiveCompany());
    }

    public function test_negative_staff_cannot_invite_anyone(): void
    {
        $staff = $this->staffMember();
        $this->actingAs($staff);

        Livewire::test(TeamAccess::class)
            ->set('waNumber', '628999999999')
            ->call('invite')
            ->assertForbidden();

        $this->assertSame(0, CompanyInvitation::count());
    }

    public function test_negative_staff_cannot_remove_a_member(): void
    {
        $staff = $this->staffMember();
        $other = $this->staffMember('628110000009');

        $this->actingAs($staff);

        Livewire::test(TeamAccess::class)
            ->call('removeMember', $other->id)
            ->assertForbidden();

        $this->assertTrue($this->company->fresh()->members->contains($other->id));
    }

    public function test_negative_code_cannot_be_claimed_by_a_different_number(): void
    {
        $this->actingAs($this->owner);
        $result = app(TeamInvitationService::class)->invite($this->company, '628222222222', $this->owner);

        // Kode bocor ke orang lain: kecocokan kode saja tidak cukup.
        $stranger = User::factory()->create(['wa_number' => '628999999999', 'wa_is_verified' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kode undangan tidak berlaku');
        app(TeamInvitationService::class)->claim($stranger, $result['code']);
    }

    public function test_negative_code_cannot_be_used_twice(): void
    {
        $this->actingAs($this->owner);
        $result = app(TeamInvitationService::class)->invite($this->company, '628222222222', $this->owner);

        $staff = User::factory()->create(['wa_number' => '628222222222', 'wa_is_verified' => true]);
        app(TeamInvitationService::class)->claim($staff, $result['code']);

        $this->expectException(RuntimeException::class);
        app(TeamInvitationService::class)->claim($staff, $result['code']);
    }

    public function test_negative_expired_code_is_rejected(): void
    {
        $this->actingAs($this->owner);
        $result = app(TeamInvitationService::class)->invite($this->company, '628222222222', $this->owner);

        $result['invitation']->update(['expires_at' => now()->subMinute()]);

        $staff = User::factory()->create(['wa_number' => '628222222222', 'wa_is_verified' => true]);

        $this->expectException(RuntimeException::class);
        app(TeamInvitationService::class)->claim($staff, $result['code']);
    }

    public function test_negative_revoked_code_is_rejected(): void
    {
        $this->actingAs($this->owner);
        $result = app(TeamInvitationService::class)->invite($this->company, '628222222222', $this->owner);

        app(TeamInvitationService::class)->revoke($result['invitation']);

        $staff = User::factory()->create(['wa_number' => '628222222222', 'wa_is_verified' => true]);

        $this->expectException(RuntimeException::class);
        app(TeamInvitationService::class)->claim($staff, $result['code']);
    }

    public function test_negative_invitation_is_refused_when_the_user_quota_is_full(): void
    {
        $this->plan(maxUsers: 1);
        $this->actingAs($this->owner);

        // Owner sudah memakai satu-satunya kursi.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kuota pengguna paket ini sudah penuh');
        app(TeamInvitationService::class)->invite($this->company, '628222222222', $this->owner);
    }

    public function test_negative_revoking_access_takes_effect_immediately(): void
    {
        $staff = $this->staffMember();
        $this->actingAs($this->owner);

        Livewire::test(TeamAccess::class)->call('removeMember', $staff->id)->assertOk();

        $this->assertFalse($this->company->fresh()->members->contains($staff->id));

        // Company aktif yang menggantung ikut dibersihkan supaya sesi
        // berikutnya tidak mencoba masuk ke usaha yang aksesnya sudah dicabut.
        $this->assertNull($staff->fresh()->current_company_id);

        $this->actingAs($staff->fresh());
        $this->assertFalse(app(CompanyRoleResolver::class)->isMemberOfCompany($this->company->id));
    }

    public function test_negative_owner_cannot_be_removed(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(TeamAccess::class)
            ->call('removeMember', $this->owner->id)
            ->assertSet('failure', 'Pemilik usaha tidak dapat dikeluarkan.');

        $this->assertTrue($this->company->fresh()->members->contains($this->owner->id));
    }

    public function test_negative_invitation_cannot_grant_owner_role(): void
    {
        $this->actingAs($this->owner);

        $this->expectExceptionMessage('Undangan hanya dapat memberi peran staf.');
        app(TeamInvitationService::class)->invite(
            $this->company,
            '628222222222',
            $this->owner,
            CompanyRoleResolver::ROLE_OWNER,
        );
    }

    public function test_negative_existing_member_number_is_not_invited_again(): void
    {
        $this->staffMember('628333333333');
        $this->actingAs($this->owner);

        $this->expectExceptionMessage('Nomor ini sudah menjadi anggota usaha.');
        app(TeamInvitationService::class)->invite($this->company, '0833-3333-333', $this->owner);
    }

    public function test_quota_falls_back_to_the_free_tier_without_a_membership(): void
    {
        $this->company->memberships()->delete();
        config(['billing.free_tier.max_users' => 1]);

        $this->assertSame(1, app(UserQuotaGate::class)->quota((string) $this->company->id));
        $this->assertFalse(app(UserQuotaGate::class)->canAddUser((string) $this->company->id));
    }

    public function test_reinviting_the_same_number_revokes_the_previous_code(): void
    {
        $this->actingAs($this->owner);
        $service = app(TeamInvitationService::class);

        $first = $service->invite($this->company, '628222222222', $this->owner);
        $second = $service->invite($this->company, '628222222222', $this->owner);

        // Tidak boleh ada dua kode hidup untuk satu orang.
        $this->assertNotNull($first['invitation']->fresh()->revoked_at);
        $this->assertNull($second['invitation']->fresh()->revoked_at);

        $staff = User::factory()->create(['wa_number' => '628222222222', 'wa_is_verified' => true]);

        try {
            $service->claim($staff, $first['code']);
            $this->fail('Kode lama seharusnya sudah tidak berlaku.');
        } catch (RuntimeException) {
            // diharapkan
        }

        $service->claim($staff, $second['code']);
        $this->assertTrue($this->company->fresh()->members->contains($staff->id));
    }

    private function staffMember(string $number = '628110000002'): User
    {
        $staff = User::factory()->create([
            'wa_number' => $number,
            'wa_is_verified' => true,
            'current_company_id' => $this->company->id,
        ]);

        $this->company->members()->attach($staff->id, [
            'role' => CompanyRoleResolver::ROLE_STAFF,
            'accepted_at' => now(),
        ]);

        return $staff;
    }

    private function plan(int $maxUsers): void
    {
        $plan = MembershipPlan::create([
            'name' => 'Uji',
            'slug' => 'uji-'.$maxUsers,
            'monthly_price' => 1000,
            'annual_price' => 10000,
            'max_wa_groups' => 1,
            'max_users' => $maxUsers,
            'monthly_token_quota' => 1000,
            'features' => ['contacts'],
        ]);

        $this->company->memberships()->delete();
        $this->company->memberships()->create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'max_wa_groups' => 1,
            'max_users' => $maxUsers,
            'monthly_token_quota' => 1000,
        ]);
    }
}
