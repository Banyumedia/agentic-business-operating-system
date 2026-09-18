<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
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
}
