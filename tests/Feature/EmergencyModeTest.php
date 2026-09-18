<?php

namespace Tests\Feature;

use App\Models\AiModelPricing;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\MembershipPlan;
use App\Services\Token\EmergencyModeResolver;
use App\Services\Token\ModelSelector;
use App\Services\Token\TokenLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmergencyModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_emergency_mode_is_active_when_balance_zero_and_emergency_positive()
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();

        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 0,
            'emergency_balance' => 1000,
        ]);

        $resolver = new EmergencyModeResolver;

        $this->assertTrue($resolver->isEmergencyModeActive($membership));
        $this->assertFalse($resolver->isDepleted($membership));
    }

    public function test_emergency_mode_is_not_active_when_balance_positive()
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();

        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 100,
            'emergency_balance' => 1000,
        ]);

        $resolver = new EmergencyModeResolver;

        $this->assertFalse($resolver->isEmergencyModeActive($membership));
        $this->assertFalse($resolver->isDepleted($membership));
    }

    public function test_depleted_when_both_balances_zero()
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();

        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 0,
            'emergency_balance' => 0,
        ]);

        $resolver = new EmergencyModeResolver;

        $this->assertFalse($resolver->isEmergencyModeActive($membership));
        $this->assertTrue($resolver->isDepleted($membership));
    }

    public function test_model_selector_returns_null_when_depleted()
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();

        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 0,
            'emergency_balance' => 0,
        ]);

        $selector = new ModelSelector(new EmergencyModeResolver);

        $this->assertNull($selector->selectModelForInference($membership));
    }

    public function test_model_selector_forces_cheapest_model_in_emergency_mode()
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();

        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 0,
            'emergency_balance' => 1000,
        ]);

        AiModelPricing::factory()->create([
            'model_name' => 'expensive-model',
            'input_multiplier' => 3.0,
            'output_multiplier' => 10.0,
        ]);

        $cheapModel = AiModelPricing::factory()->create([
            'model_name' => 'cheap-fallback-model',
            'input_multiplier' => 0.5,
            'output_multiplier' => 1.5,
        ]);

        $selector = new ModelSelector(new EmergencyModeResolver);

        // Even if we prefer the expensive model, we should get the cheap one
        $selected = $selector->selectModelForInference($membership, 'expensive-model');

        $this->assertNotNull($selected);
        $this->assertEquals($cheapModel->id, $selected->id);
        $this->assertEquals('cheap-fallback-model', $selected->model_name);
    }

    public function test_token_ledger_deducts_from_emergency_balance_in_emergency_mode()
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();

        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 0,
            'emergency_balance' => 1000,
        ]);

        $service = new TokenLedgerService;

        $entry = $service->recordTransaction(
            membership: $membership,
            direction: 'debit',
            amount: 200,
            source: 'inference',
            idempotencyKey: 'debit-emergency-1'
        );

        $this->assertEquals(800, $entry->balance_after);

        $membership->refresh();
        $this->assertEquals(0, $membership->current_token_balance);
        $this->assertEquals(800, $membership->emergency_balance);
    }

    public function test_topup_restores_normal_mode_immediately()
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();

        // Start in emergency mode
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 0,
            'emergency_balance' => 500,
        ]);

        $resolver = new EmergencyModeResolver;
        $this->assertTrue($resolver->isEmergencyModeActive($membership));

        // Topup
        $service = new TokenLedgerService;
        $service->recordTransaction(
            membership: $membership,
            direction: 'credit',
            amount: 100000,
            source: 'topup',
            idempotencyKey: 'topup-1'
        );

        $membership->refresh();

        // Mode hemat mati seketika
        $this->assertFalse($resolver->isEmergencyModeActive($membership));
        $this->assertEquals(100000, $membership->current_token_balance);
    }

    public function test_web_app_does_not_fail_when_bot_dead()
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();

        // Start depleted
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 0,
            'emergency_balance' => 0,
        ]);

        // Assert bot says null but web doesn't die.
        $selector = new ModelSelector(new EmergencyModeResolver);
        $selected = $selector->selectModelForInference($membership);
        $this->assertNull($selected);
    }
}
