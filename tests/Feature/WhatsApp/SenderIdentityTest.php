<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Company;
use App\Models\HermesProfile;
use App\Models\User;
use App\Services\CompanyRoleResolver;
use App\Services\WhatsApp\WhatsAppInteractionFilter;
use App\Services\WhatsApp\WhatsAppSenderIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-58 (D-66): bot mengenali siapa manusia di balik sebuah nomor WA.
 *
 * Yang dijaga lebih dulu adalah penolakannya. Japri staf memperluas permukaan
 * otorisasi WA dari satu nomor menjadi banyak, jadi setiap jalur yang tidak
 * boleh lolos diuji eksplisit: nomor belum terverifikasi, bukan anggota,
 * keanggotaan dicabut, dan satu nomor di dua usaha.
 */
class SenderIdentityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    private HermesProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'wa_number' => '628110000001',
            'wa_is_verified' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Usaha Uji',
            'slug' => 'usaha-uji',
            'owner_user_id' => $this->owner->id,
            'business_preset' => 'bengkel',
            'module_settings' => [],
        ]);

        $this->profile = HermesProfile::create([
            'owner_user_id' => $this->owner->id,
            'type' => 'primary',
            'status' => 'connected',
            'instance_id' => 'inst-uji-1',
            'webhook_secret_reference' => 'secret-ref-uji',
        ]);
        $this->profile->companies()->attach($this->company->id, ['role' => 'owner', 'is_default' => true]);
    }

    public function test_owner_is_recognised_with_the_owner_role(): void
    {
        $identity = $this->resolve('628110000001');

        $this->assertTrue($identity['known']);
        $this->assertSame(CompanyRoleResolver::ROLE_OWNER, $identity['role']);
        $this->assertSame($this->company->id, $identity['company']->id);
    }

    public function test_staff_member_is_recognised_with_the_staff_role(): void
    {
        $staff = $this->staff('628110000002');

        $identity = $this->resolve('0811-0000-002');

        $this->assertTrue($identity['known']);
        $this->assertSame(CompanyRoleResolver::ROLE_STAFF, $identity['role']);
        $this->assertSame($staff->id, $identity['user']->id);
    }

    public function test_negative_unverified_number_is_not_recognised(): void
    {
        $this->staff('628110000003', verified: false);

        $identity = $this->resolve('628110000003');

        $this->assertFalse($identity['known']);
        // Dibedakan dari orang asing supaya pesannya dapat menuntun verifikasi.
        $this->assertStringContainsString('belum terverifikasi', $identity['reason']);
    }

    public function test_negative_stranger_is_not_recognised(): void
    {
        $identity = $this->resolve('628999999999');

        $this->assertFalse($identity['known']);
        $this->assertStringContainsString('tidak dikenali', $identity['reason']);
    }

    public function test_negative_verified_user_who_is_not_a_member_is_rejected(): void
    {
        User::factory()->create(['wa_number' => '628777777777', 'wa_is_verified' => true]);

        $identity = $this->resolve('628777777777');

        $this->assertFalse($identity['known']);
        $this->assertStringContainsString('bukan anggota usaha ini', $identity['reason']);
    }

    public function test_negative_revoking_membership_closes_wa_access_immediately(): void
    {
        $staff = $this->staff('628110000004');
        $this->assertTrue($this->resolve('628110000004')['known']);

        $this->company->members()->detach($staff->id);

        // Tidak ada yang perlu disentuh di sisi WA: keanggotaan adalah sumber
        // kebenarannya.
        $this->assertFalse($this->resolve('628110000004')['known']);
    }

    public function test_negative_number_belonging_to_two_companies_is_fail_closed(): void
    {
        $staff = $this->staff('628110000005');

        $second = Company::create([
            'name' => 'Usaha Kedua',
            'slug' => 'usaha-kedua',
            'owner_user_id' => $this->owner->id,
            'business_preset' => 'bengkel',
            'module_settings' => [],
        ]);
        $second->members()->attach($staff->id, [
            'role' => CompanyRoleResolver::ROLE_STAFF,
            'accepted_at' => now(),
        ]);
        $this->profile->companies()->attach($second->id, ['role' => 'owner']);

        $identity = $this->resolve('628110000005');

        // Menebak company berarti berisiko menjawab dengan data usaha yang salah.
        $this->assertFalse($identity['known']);
        $this->assertTrue($identity['ambiguous']);
        $this->assertNull($identity['company']);
        $this->assertStringContainsString('lebih dari satu usaha', $identity['reason']);
    }

    public function test_negative_member_of_a_company_this_bot_does_not_serve_is_rejected(): void
    {
        $otherOwner = User::factory()->create(['wa_number' => '628660000001', 'wa_is_verified' => true]);
        $otherCompany = Company::create([
            'name' => 'Usaha Lain',
            'slug' => 'usaha-lain',
            'owner_user_id' => $otherOwner->id,
            'business_preset' => 'bengkel',
            'module_settings' => [],
        ]);
        $otherCompany->members()->attach($otherOwner->id, [
            'role' => CompanyRoleResolver::ROLE_OWNER,
            'accepted_at' => now(),
        ]);

        // Bot ini tidak melayani usaha itu, jadi ia tidak boleh menjawab
        // atas namanya walau nomornya sah di tempat lain.
        $identity = $this->resolve('628660000001');

        $this->assertFalse($identity['known']);
    }

    public function test_staff_dm_is_allowed_but_marked_read_only(): void
    {
        $this->staff('628110000006');

        $eval = $this->evaluateDm('628110000006');

        $this->assertTrue($eval['allow']);
        $this->assertSame(CompanyRoleResolver::ROLE_STAFF, $eval['role']);
        // Inti D-66: japri staf tidak boleh menulis.
        $this->assertTrue($eval['read_only']);
    }

    public function test_owner_dm_is_not_read_only(): void
    {
        $eval = $this->evaluateDm('628110000001');

        $this->assertTrue($eval['allow']);
        $this->assertSame(CompanyRoleResolver::ROLE_OWNER, $eval['role']);
        $this->assertFalse($eval['read_only']);
    }

    public function test_negative_stranger_dm_is_still_refused(): void
    {
        $eval = $this->evaluateDm('628999999999');

        $this->assertFalse($eval['allow']);
        $this->assertStringContainsString('anggota tim terdaftar', $eval['reason']);
    }

    public function test_negative_ambiguous_number_dm_is_refused_with_its_own_reason(): void
    {
        $staff = $this->staff('628110000007');
        $second = Company::create([
            'name' => 'Usaha Kedua',
            'slug' => 'usaha-kedua-2',
            'owner_user_id' => $this->owner->id,
            'business_preset' => 'bengkel',
            'module_settings' => [],
        ]);
        $second->members()->attach($staff->id, [
            'role' => CompanyRoleResolver::ROLE_STAFF,
            'accepted_at' => now(),
        ]);
        $this->profile->companies()->attach($second->id, ['role' => 'owner']);

        $eval = $this->evaluateDm('628110000007');

        $this->assertFalse($eval['allow']);
        $this->assertStringContainsString('lebih dari satu usaha', $eval['reason']);
    }

    public function test_group_rules_are_not_loosened_by_per_person_identity(): void
    {
        // D-66 butir (e): aturan grup WA-04 tetap berlaku apa adanya.
        $eval = app(WhatsAppInteractionFilter::class)->evaluate(
            $this->profile,
            '120363012345678@g.us',
            '628110000001',
            'halo tim',
            $this->company,
            false,
        );

        $this->assertFalse($eval['allow']);
        $this->assertStringContainsString('di-tag', $eval['reason']);
    }

    /** @return array<string, mixed> */
    private function resolve(string $number): array
    {
        return app(WhatsAppSenderIdentity::class)->resolve($this->profile, $number);
    }

    /** @return array<string, mixed> */
    private function evaluateDm(string $number): array
    {
        return app(WhatsAppInteractionFilter::class)->evaluate(
            $this->profile,
            $number.'@s.whatsapp.net',
            $number,
            'Berapa omzet hari ini?',
            $this->company,
            false,
        );
    }

    private function staff(string $number, bool $verified = true): User
    {
        $staff = User::factory()->create([
            'wa_number' => $number,
            'wa_is_verified' => $verified,
        ]);

        $this->company->members()->attach($staff->id, [
            'role' => CompanyRoleResolver::ROLE_STAFF,
            'accepted_at' => now(),
        ]);

        return $staff;
    }
}
