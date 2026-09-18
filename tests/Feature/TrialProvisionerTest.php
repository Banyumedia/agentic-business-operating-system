<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Billing\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_trial_success(): void
    {
        $user = User::factory()->create(['wa_number' => '08123456789']);
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $plan = MembershipPlan::factory()->create([
            'monthly_token_quota' => 1000,
            'emergency_token_quota' => 100,
            'trial_token_quota' => 500,
        ]);

        $provisioner = new TrialProvisioner;
        $membership = $provisioner->provision($company, $plan, 14);

        $this->assertSame('active', $membership->status);
        $this->assertEquals(500, $membership->current_token_balance);
        $this->assertNotNull($membership->trial_ends_at);
        $this->assertTrue($membership->trial_ends_at->isFuture());
    }

    public function test_provision_trial_prevent_duplicate_by_email(): void
    {
        $user1 = User::factory()->create(['email' => 'test@example.com', 'wa_number' => '08111']);
        $company1 = Company::factory()->create(['owner_user_id' => $user1->id]);

        $plan = MembershipPlan::factory()->create(['trial_token_quota' => 500]);

        $provisioner = new TrialProvisioner;
        $provisioner->provision($company1, $plan, 14);

        // Attempt second trial with same email (simulated by same user or different user with same email)
        // Here we just test same user first
        $company2 = Company::factory()->create(['owner_user_id' => $user1->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Anda sudah pernah menikmati masa trial pada akun lain.');

        $provisioner->provision($company2, $plan, 14);
    }

    public function test_provision_trial_prevent_duplicate_by_wa_number(): void
    {
        $user1 = User::factory()->create(['email' => 'u1@example.com', 'wa_number' => '08123456789']);
        $company1 = Company::factory()->create(['owner_user_id' => $user1->id]);

        $plan = MembershipPlan::factory()->create(['trial_token_quota' => 500]);

        $provisioner = new TrialProvisioner;
        $provisioner->provision($company1, $plan, 14);

        // Different user, same wa_number
        $user2 = User::factory()->create(['email' => 'u2@example.com', 'wa_number' => '08123456789']);
        $company2 = Company::factory()->create(['owner_user_id' => $user2->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Anda sudah pernah menikmati masa trial pada akun lain.');

        $provisioner->provision($company2, $plan, 14);
    }
}
