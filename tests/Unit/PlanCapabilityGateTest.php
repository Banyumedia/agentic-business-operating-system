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

    public function test_it_returns_empty_array_when_no_active_membership(): void
    {
        $company = Company::factory()->create();

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new PlanCapabilityGate($context);

        $this->assertSame([], $gate->allowedCapabilities());
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
}
