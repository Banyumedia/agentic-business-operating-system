<?php

namespace Tests\Unit;

use App\Contracts\CompanyContext;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\MembershipPlan;
use App\Services\PlanCapabilityGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanCapabilityGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_free_tier_capabilities_when_no_active_membership(): void
    {
        $company = Company::factory()->create();

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new PlanCapabilityGate($context);

        $capabilities = $gate->allowedCapabilities();

        // D-60: Free tier should return capabilities from config (all operational + system.ai_agent)
        $this->assertNotEmpty($capabilities);
        $this->assertContains('contacts', $capabilities);
        $this->assertContains('system.ai_agent', $capabilities);
        $this->assertSame(config('billing.free_tier.capabilities'), $capabilities);
    }

    public function test_it_returns_plan_features_when_membership_is_active(): void
    {
        $plan = MembershipPlan::factory()->create([
            'features' => ['contacts', 'deals'],
        ]);

        $company = Company::factory()->create();

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new PlanCapabilityGate($context);

        $this->assertSame(['contacts', 'deals'], $gate->allowedCapabilities());
    }

    public function test_it_returns_empty_array_when_membership_is_not_active(): void
    {
        $plan = MembershipPlan::factory()->create([
            'features' => ['contacts', 'deals', 'projects'],
        ]);

        $company = Company::factory()->create();

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'ai_suspended', // Not active (D-49)
        ]);

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new PlanCapabilityGate($context);

        // D-49: Should return empty array when status is not 'active' (fail-closed)
        $this->assertEmpty($gate->allowedCapabilities());
    }
}
