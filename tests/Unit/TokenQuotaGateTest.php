<?php

namespace Tests\Unit;

use App\Contracts\CompanyContext;
use App\Exceptions\Billing\InsufficientTokenQuotaException;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\MembershipPlan;
use App\Services\Billing\TokenQuotaGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenQuotaGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_tier_has_default_token_quota(): void
    {
        $company = Company::factory()->create();

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new TokenQuotaGate($context);

        // D-60: Free tier should have token quota from config (default 500)
        $this->assertSame(
            (int) config('billing.free_tier.token_quota', 500),
            $gate->getMonthlyTokenQuota()
        );
    }

    public function test_company_without_membership_uses_free_tier_quota(): void
    {
        $company = Company::factory()->create();

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new TokenQuotaGate($context);

        // D-60: No membership = free tier quota
        $expected = (int) config('billing.free_tier.token_quota', 500);
        $this->assertSame($expected, $gate->getMonthlyTokenQuota());
    }

    public function test_company_with_active_membership_uses_plan_quota(): void
    {
        $plan = MembershipPlan::factory()->create([
            'monthly_token_quota' => 10000,
        ]);

        $company = Company::factory()->create();

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'monthly_token_quota' => 10000,
            'current_token_balance' => 5000,
        ]);

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new TokenQuotaGate($context);

        // D-52: Active membership should use plan's monthly_token_quota, not free tier
        $this->assertSame(10000, $gate->getMonthlyTokenQuota());
    }

    public function test_get_current_token_balance(): void
    {
        $plan = MembershipPlan::factory()->create();

        $company = Company::factory()->create();

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_token_balance' => 250,
        ]);

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new TokenQuotaGate($context);

        $this->assertSame(250, $gate->getCurrentTokenBalance());
    }

    public function test_has_enough_tokens_returns_true_when_balance_sufficient(): void
    {
        $plan = MembershipPlan::factory()->create();

        $company = Company::factory()->create();

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_token_balance' => 100,
        ]);

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new TokenQuotaGate($context);

        $this->assertTrue($gate->hasEnoughTokens(50));
        $this->assertTrue($gate->hasEnoughTokens(100));
        $this->assertFalse($gate->hasEnoughTokens(101));
    }

    public function test_assert_has_enough_tokens_throws_on_insufficient_balance(): void
    {
        $plan = MembershipPlan::factory()->create();

        $company = Company::factory()->create();

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_token_balance' => 50,
        ]);

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new TokenQuotaGate($context);

        $this->expectException(InsufficientTokenQuotaException::class);
        $this->expectExceptionMessage('Kuota token AI tidak mencukupi');

        $gate->assertHasEnoughTokens(100); // Require 100 but only have 50
    }

    public function test_negative_case_free_tier_token_balance_exhausted(): void
    {
        // D-60: company tanpa paket memakai saldo awal tier gratis dari config (default 500),
        // sehingga langsung bisa memakai AI. Kuota itu sekaligus batas atasnya.
        $company = Company::factory()->create();

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new TokenQuotaGate($context);

        $expected = (int) config('billing.free_tier.token_quota', 500);
        $this->assertSame($expected, $gate->getCurrentTokenBalance());
        $this->assertTrue($gate->hasEnoughTokens(1));
        // Melebihi saldo awal -> ditolak fail-closed.
        $this->assertFalse($gate->hasEnoughTokens($expected + 1));
    }

    public function test_negative_membership_ai_suspended_returns_zero_quota(): void
    {
        // D-49: Negative test - membership in ai_suspended status → return 0 quota (fail-closed)
        $plan = MembershipPlan::factory()->create([
            'monthly_token_quota' => 10000,
        ]);

        $company = Company::factory()->create();

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'ai_suspended',
            'monthly_token_quota' => 10000,
            'current_token_balance' => 5000,
        ]);

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new TokenQuotaGate($context);

        // D-49: Status ai_suspended → return 0 quota
        $this->assertSame(0, $gate->getMonthlyTokenQuota());
        $this->assertSame(0, $gate->getCurrentTokenBalance());
        $this->assertFalse($gate->hasEnoughTokens(1));
    }

    public function test_negative_membership_read_only_returns_zero_quota(): void
    {
        // D-49: Negative test - membership in read_only status → return 0 quota (fail-closed)
        $plan = MembershipPlan::factory()->create([
            'monthly_token_quota' => 10000,
        ]);

        $company = Company::factory()->create();

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'read_only',
            'monthly_token_quota' => 10000,
            'current_token_balance' => 5000,
        ]);

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new TokenQuotaGate($context);

        // D-49: Status read_only → return 0 quota
        $this->assertSame(0, $gate->getMonthlyTokenQuota());
        $this->assertSame(0, $gate->getCurrentTokenBalance());
        $this->assertFalse($gate->hasEnoughTokens(1));
    }

    public function test_negative_membership_frozen_returns_zero_quota(): void
    {
        // D-49: Negative test - membership in frozen status → return 0 quota (fail-closed)
        $plan = MembershipPlan::factory()->create([
            'monthly_token_quota' => 10000,
        ]);

        $company = Company::factory()->create();

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'frozen',
            'monthly_token_quota' => 10000,
            'current_token_balance' => 5000,
        ]);

        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);

        $gate = new TokenQuotaGate($context);

        // D-49: Status frozen → return 0 quota
        $this->assertSame(0, $gate->getMonthlyTokenQuota());
        $this->assertSame(0, $gate->getCurrentTokenBalance());
        $this->assertFalse($gate->hasEnoughTokens(1));
    }
}
