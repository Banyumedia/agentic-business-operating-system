<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\HermesNode;
use App\Models\HermesProfile;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanupExpiredTrialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_expired_trials(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $plan = MembershipPlan::factory()->create();

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'read_only',
            'trial_ends_at' => now()->subDays(31),
        ]);

        HermesProfile::create([
            'owner_user_id' => $user->id,
            'instance_id' => 'test-instance',
            'webhook_secret_reference' => 'secret',
            'status' => 'paired',
        ]);

        $this->artisan('app:cleanup-expired-trials')
            ->expectsOutputToContain('Cleaned up 1 expired trial companies.')
            ->assertExitCode(0);

        $this->assertSoftDeleted($company);

        $profile = HermesProfile::where('owner_user_id', $user->id)->first();
        $this->assertSame('unpaired', $profile->status);
        $this->assertStringStartsWith('revoked_', $profile->webhook_secret_reference);
    }

    public function test_negative_revoking_a_profile_must_not_leave_the_node_counter_inflated(): void
    {
        // QA-02: pencabutan menulis `node_id => null` tanpa menyentuh penghitung node
        // lamanya. Karena `active_profiles` adalah satu-satunya angka yang dipakai
        // penjaga kapasitas di `bos:hermes-profile`, setiap trial yang kedaluwarsa
        // membuat node itu sedikit lebih "penuh" secara palsu sampai akhirnya ia
        // menolak profil yang sah - dan `max_capacity` jadi dekorasi.
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $plan = MembershipPlan::factory()->create();

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'read_only',
            'trial_ends_at' => now()->subDays(31),
        ]);

        $node = HermesNode::create([
            'name' => 'Node Lokal',
            'api_url' => 'http://127.0.0.1:3000',
            'api_secret_reference' => 'none',
            'max_capacity' => 1,
            'active_profiles' => 1,
            'status' => 'active',
        ]);

        $profile = HermesProfile::create([
            'owner_user_id' => $user->id,
            'node_id' => $node->id,
            'instance_id' => 'test-instance',
            'webhook_secret_reference' => 'secret',
            'status' => 'paired',
        ]);

        $this->artisan('app:cleanup-expired-trials')->assertExitCode(0);

        $this->assertNull($profile->fresh()->node_id);
        $this->assertSame(0, (int) $node->fresh()->active_profiles);
    }
}
