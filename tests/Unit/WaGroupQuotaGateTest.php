<?php

namespace Tests\Unit;

use App\Contracts\CompanyContext;
use App\Exceptions\Billing\WaGroupQuotaExceededException;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\MembershipPlan;
use App\Services\Billing\WaGroupQuotaGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaGroupQuotaGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_tier_allows_only_one_wa_group(): void
    {
        $company = Company::factory()->create();

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new WaGroupQuotaGate($context);

        // D-60: Free tier should allow max 1 WA group
        $this->assertSame(1, $gate->maxAllowedGroups());
        $this->assertTrue($gate->canAddGroup(0));
        $this->assertFalse($gate->canAddGroup(1)); // Already 1, cannot add more
    }

    public function test_company_without_membership_uses_free_tier_quota(): void
    {
        $company = Company::factory()->create();

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new WaGroupQuotaGate($context);

        // D-60: No membership = free tier quota = 1
        $this->assertSame(1, $gate->maxAllowedGroups());
    }

    public function test_company_with_active_membership_uses_plan_quota(): void
    {
        $plan = MembershipPlan::factory()->create([
            'max_wa_groups' => 5,
        ]);

        $company = Company::factory()->create();

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'max_wa_groups' => 5,
        ]);

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new WaGroupQuotaGate($context);

        // D-53: Active membership should use plan's max_wa_groups, not free tier
        $this->assertSame(5, $gate->maxAllowedGroups());
        $this->assertTrue($gate->canAddGroup(4));
        $this->assertFalse($gate->canAddGroup(5)); // Already at quota
    }

    public function test_assert_can_add_group_throws_on_quota_exceeded(): void
    {
        $company = Company::factory()->create();

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new WaGroupQuotaGate($context);

        // D-60: Should throw when trying to exceed free tier quota
        $this->expectException(WaGroupQuotaExceededException::class);
        $this->expectExceptionMessage('Kuota grup WhatsApp tercapai');

        $gate->assertCanAddGroup(1); // Free tier max = 1, already have 1
    }

    public function test_negative_case_free_tier_second_wa_group_rejected(): void
    {
        // D-60: Negative test - company without paket tries to add 2nd WA group → ditolak
        $company = Company::factory()->create();

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new WaGroupQuotaGate($context);

        $this->assertTrue($gate->canAddGroup(0), 'Should allow 1st group');
        $this->assertFalse($gate->canAddGroup(1), 'Should reject 2nd group for free tier');
    }

    public function test_negative_membership_ai_suspended_returns_zero_quota(): void
    {
        // D-49: Negative test - membership in ai_suspended status → return 0 quota (fail-closed)
        $plan = MembershipPlan::factory()->create([
            'max_wa_groups' => 5,
        ]);

        $company = Company::factory()->create();

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'ai_suspended',
            'max_wa_groups' => 5,
        ]);

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new WaGroupQuotaGate($context);

        // D-49: Status ai_suspended → return 0 quota
        $this->assertSame(0, $gate->maxAllowedGroups());
        $this->assertFalse($gate->canAddGroup(0));
    }

    public function test_negative_membership_read_only_returns_zero_quota(): void
    {
        // D-49: Negative test - membership in read_only status → return 0 quota (fail-closed)
        $plan = MembershipPlan::factory()->create([
            'max_wa_groups' => 5,
        ]);

        $company = Company::factory()->create();

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'read_only',
            'max_wa_groups' => 5,
        ]);

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new WaGroupQuotaGate($context);

        // D-49: Status read_only → return 0 quota
        $this->assertSame(0, $gate->maxAllowedGroups());
        $this->assertFalse($gate->canAddGroup(0));
    }

    public function test_negative_membership_frozen_returns_zero_quota(): void
    {
        // D-49: Negative test - membership in frozen status → return 0 quota (fail-closed)
        $plan = MembershipPlan::factory()->create([
            'max_wa_groups' => 5,
        ]);

        $company = Company::factory()->create();

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'frozen',
            'max_wa_groups' => 5,
        ]);

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new WaGroupQuotaGate($context);

        // D-49: Status frozen → return 0 quota
        $this->assertSame(0, $gate->maxAllowedGroups());
        $this->assertFalse($gate->canAddGroup(0));
    }
}
