<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\MembershipPlan;
use App\Services\Token\TokenLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class TokenLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_transaction_updates_balance_and_creates_entry(): void
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 1000,
        ]);

        $service = new TokenLedgerService;

        // Test Credit
        $entry = $service->recordTransaction(
            membership: $membership,
            direction: 'credit',
            amount: 500,
            source: 'topup',
            idempotencyKey: 'credit-1'
        );

        $this->assertEquals(1500, $entry->balance_after);
        $this->assertEquals('credit', $entry->direction);
        $this->assertEquals(500, $entry->amount);

        $membership->refresh();
        $this->assertEquals(1500, $membership->current_token_balance);

        // Test Debit
        $entry2 = $service->recordTransaction(
            membership: $membership,
            direction: 'debit',
            amount: 200,
            source: 'inference',
            idempotencyKey: 'debit-1'
        );

        $this->assertEquals(1300, $entry2->balance_after);
        $this->assertEquals('debit', $entry2->direction);
        $this->assertEquals(200, $entry2->amount);

        $membership->refresh();
        $this->assertEquals(1300, $membership->current_token_balance);
    }

    public function test_ledger_transaction_is_idempotent(): void
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 1000,
        ]);

        $service = new TokenLedgerService;

        // First call
        $entry1 = $service->recordTransaction(
            membership: $membership,
            direction: 'credit',
            amount: 500,
            source: 'topup',
            idempotencyKey: 'same-key'
        );

        // Second call with same key
        $entry2 = $service->recordTransaction(
            membership: $membership,
            direction: 'credit',
            amount: 500,
            source: 'topup',
            idempotencyKey: 'same-key'
        );

        $this->assertEquals($entry1->id, $entry2->id);

        $membership->refresh();
        // Balance should only increase once
        $this->assertEquals(1500, $membership->current_token_balance);
    }

    public function test_ledger_rejects_negative_amounts(): void
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 1000,
        ]);

        $service = new TokenLedgerService;

        $this->expectException(LogicException::class);
        $service->recordTransaction(
            membership: $membership,
            direction: 'credit',
            amount: -500,
            source: 'topup',
            idempotencyKey: 'neg-1'
        );
    }
}
