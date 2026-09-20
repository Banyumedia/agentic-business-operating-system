<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\MembershipPlan;
use App\Services\Billing\TokenQuotaGate;
use App\Services\Billing\WaGroupQuotaGate;
use App\Services\PlanCapabilityGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for D-60: Free tier for company without paket.
 *
 * Test that:
 * 1. Free tier opens all operational capabilities + system.ai_agent
 * 2. Free tier limits WA groups to 1 (exceeding raises error)
 * 3. Free tier limits token quota (~500, configurable)
 * 4. Company with active paket uses plan quotas, not free tier
 */
class FreeTierBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_without_paket_gets_free_tier_capabilities(): void
    {
        // D-60: Tier gratis terbuka untuk SEMUA kapabilitas operasional + system.ai_agent
        $company = Company::factory()->create();

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new PlanCapabilityGate($context);
        $capabilities = $gate->allowedCapabilities();

        // Verify free tier capabilities include all operational + AI
        $this->assertNotEmpty($capabilities);
        $this->assertContains('contacts', $capabilities);
        $this->assertContains('system.ai_agent', $capabilities);
    }

    public function test_free_tier_max_wa_groups_is_one(): void
    {
        // D-60: max_wa_groups = 1 untuk tier gratis
        $company = Company::factory()->create();

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $quotaGate = new WaGroupQuotaGate($context);

        // First group should be allowed
        $this->assertTrue($quotaGate->canAddGroup(0));

        // Second group should be rejected (fail-closed)
        $this->assertFalse($quotaGate->canAddGroup(1));
    }

    public function test_free_tier_second_wa_group_throws_exception(): void
    {
        // Negative test: attempting 2nd WA group throws exception
        $company = Company::factory()->create();

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $quotaGate = new WaGroupQuotaGate($context);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Kuota grup WhatsApp tercapai');

        $quotaGate->assertCanAddGroup(1); // Already have 1 group
    }

    public function test_free_tier_token_quota_from_config(): void
    {
        // D-60: Kuota token dari config (~500, bisa diubah Bos)
        $company = Company::factory()->create();

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $tokenGate = new TokenQuotaGate($context);

        $expected = (int) config('billing.free_tier.token_quota', 500);
        $this->assertSame($expected, $tokenGate->getMonthlyTokenQuota());
    }

    public function test_company_with_active_paket_uses_plan_capabilities(): void
    {
        // D-52: Company dengan paket aktif → kuota paket berlaku, bukan tier gratis
        $plan = MembershipPlan::factory()->create([
            'features' => ['contacts', 'deals'], // Limited features
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
        $capabilities = $gate->allowedCapabilities();

        // Should return plan features, not free tier
        $this->assertSame(['contacts', 'deals'], $capabilities);
        $this->assertNotContains('system.ai_agent', $capabilities);
    }

    public function test_company_with_active_paket_uses_plan_wa_groups_quota(): void
    {
        // D-53: Company dengan paket → max_wa_groups dari paket, bukan free tier
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

        $quotaGate = new WaGroupQuotaGate($context);

        // Should allow up to 5, not 1
        $this->assertTrue($quotaGate->canAddGroup(4));
        $this->assertFalse($quotaGate->canAddGroup(5));
    }

    public function test_company_with_active_paket_uses_plan_token_quota(): void
    {
        // D-52: Company dengan paket → token_quota dari paket, bukan free tier
        $plan = MembershipPlan::factory()->create([
            'monthly_token_quota' => 10000,
        ]);

        $company = Company::factory()->create();

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'monthly_token_quota' => 10000,
            'current_token_balance' => 9000,
        ]);

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $tokenGate = new TokenQuotaGate($context);

        // Should use plan quota (10000), not free tier (500)
        $this->assertSame(10000, $tokenGate->getMonthlyTokenQuota());
        $this->assertSame(9000, $tokenGate->getCurrentTokenBalance());
    }
}
