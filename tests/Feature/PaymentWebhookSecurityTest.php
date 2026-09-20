<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Invoice;
use App\Models\MembershipPlan;
use App\Services\Payment\MidtransSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Payment Webhook Security Tests — D-08, D-49 fail-closed
 * 
 * Requirements:
 * - Invalid signature must be rejected (403)
 * - Only settlement/accepted capture statuses credit tokens
 * - Duplicate webhook (replay) must be idempotent (not double-credit)
 * - Membership data loss: invoice without member must fail gracefully (422)
 * - Already paid invoice must not re-credit (double-payment protection)
 */
class PaymentWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private string $serverKey = 'test-server-key-12345';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.midtrans.server_key' => $this->serverKey]);
    }

    /**
     * CRITICAL: Webhook with invalid signature must be rejected (403).
     * Fail-closed: never process payment with bad signature.
     */
    public function test_webhook_rejects_invalid_signature(): void
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 0,
        ]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'company_membership_id' => $membership->id,
            'order_id' => 'ORDER-123',
            'payment_status' => 'pending',
            'token_amount_granted' => 1000,
        ]);

        // Send webhook with WRONG signature
        $response = $this->postJson('/api/webhooks/payment/midtrans', [
            'order_id' => 'ORDER-123',
            'status_code' => '200',
            'gross_amount' => '100000',
            'signature_key' => 'wrong-signature-xyz',  // Invalid!
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Invalid signature']);

        // Invoice must remain unpaid
        $invoice->refresh();
        $this->assertEquals('pending', $invoice->payment_status);

        // Balance must NOT change
        $membership->refresh();
        $this->assertEquals(0, $membership->current_token_balance);
    }

    /**
     * Webhook with correct signature must process payment.
     */
    public function test_webhook_processes_settlement_with_valid_signature(): void
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 0,
        ]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'company_membership_id' => $membership->id,
            'order_id' => 'ORDER-123',
            'payment_status' => 'pending',
            'token_amount_granted' => 1000,
        ]);

        // Calculate CORRECT signature per Midtrans spec: SHA512(order_id + status_code + gross_amount + server_key)
        $verifier = new MidtransSignatureVerifier;
        $correctSignature = hash('sha512', 'ORDER-123' . '200' . '100000' . $this->serverKey);

        $response = $this->postJson('/api/webhooks/payment/midtrans', [
            'order_id' => 'ORDER-123',
            'status_code' => '200',
            'gross_amount' => '100000',
            'signature_key' => $correctSignature,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        // Invoice must be paid
        $invoice->refresh();
        $this->assertEquals('paid', $invoice->payment_status);
        $this->assertNotNull($invoice->paid_at);

        // Balance MUST be credited
        $membership->refresh();
        $this->assertEquals(1000, $membership->current_token_balance);
    }

    /**
     * CRITICAL: Duplicate webhook (replay attack) must be idempotent.
     * Sending same webhook twice must NOT double-credit.
     */
    public function test_webhook_idempotent_prevents_double_credit(): void
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 0,
        ]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'company_membership_id' => $membership->id,
            'order_id' => 'ORDER-REPLAY',
            'payment_status' => 'pending',
            'token_amount_granted' => 5000,
        ]);

        $correctSignature = hash('sha512', 'ORDER-REPLAY' . '200' . '100000' . $this->serverKey);

        $payload = [
            'order_id' => 'ORDER-REPLAY',
            'status_code' => '200',
            'gross_amount' => '100000',
            'signature_key' => $correctSignature,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
        ];

        // First webhook
        $response1 = $this->postJson('/api/webhooks/payment/midtrans', $payload);
        $response1->assertStatus(200);

        $membership->refresh();
        $balance1 = $membership->current_token_balance;
        $this->assertEquals(5000, $balance1, 'First webhook must credit 5000 tokens');

        // REPLAY: Send same webhook again
        $response2 = $this->postJson('/api/webhooks/payment/midtrans', $payload);
        $response2->assertStatus(200);
        $response2->assertJson(['status' => 'success', 'message' => 'Already paid']);

        // Balance must NOT increase again
        $membership->refresh();
        $this->assertEquals(5000, $membership->current_token_balance, 'Replay must NOT double-credit');

        // Only ONE ledger entry for this order
        $entries = $this->app->make('App\Models\TokenLedgerEntry')
            ->where('company_id', $company->id)
            ->whereJsonContains('metadata->order_id', 'ORDER-REPLAY')
            ->get();
        $this->assertCount(1, $entries, 'Only one ledger entry must exist for this order');
    }

    /**
     * When invoice payment_status is already 'paid', webhook must not re-process.
     * This is the double-payment guard at invoice level.
     */
    public function test_webhook_rejects_already_paid_invoice(): void
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 5000,  // Already paid once
        ]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'company_membership_id' => $membership->id,
            'order_id' => 'ORDER-PAID',
            'payment_status' => 'paid',  // Already paid
            'paid_at' => now(),
            'token_amount_granted' => 2000,
        ]);

        $correctSignature = hash('sha512', 'ORDER-PAID' . '200' . '100000' . $this->serverKey);

        $response = $this->postJson('/api/webhooks/payment/midtrans', [
            'order_id' => 'ORDER-PAID',
            'status_code' => '200',
            'gross_amount' => '100000',
            'signature_key' => $correctSignature,
            'transaction_status' => 'settlement',
        ]);

        // Must return success (not error) to avoid webhook retry loops
        $response->assertStatus(200);
        $response->assertJson(['status' => 'success', 'message' => 'Already paid']);

        // Balance must remain unchanged
        $membership->refresh();
        $this->assertEquals(5000, $membership->current_token_balance, 'Already paid invoice must not re-credit');
    }

    /**
     * CRITICAL: Invoice without membership (data loss) must fail gracefully (422).
     * Fail-closed: never credit when membership is unknown.
     */
    public function test_webhook_fails_when_membership_missing(): void
    {
        $company = Company::factory()->create();
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'company_membership_id' => null,  // Data loss: no membership
            'order_id' => 'ORDER-LOST',
            'payment_status' => 'pending',
        ]);

        $correctSignature = hash('sha512', 'ORDER-LOST' . '200' . '100000' . $this->serverKey);

        $response = $this->postJson('/api/webhooks/payment/midtrans', [
            'order_id' => 'ORDER-LOST',
            'status_code' => '200',
            'gross_amount' => '100000',
            'signature_key' => $correctSignature,
            'transaction_status' => 'settlement',
        ]);

        // MUST fail with 422 (data corruption)
        $response->assertStatus(422);
        $response->assertJson(['error' => 'Company membership not found']);

        // Invoice must remain unpaid
        $invoice->refresh();
        $this->assertEquals('pending', $invoice->payment_status);
    }

    /**
     * Only 'settlement' or 'capture' with 'accept' fraud status should process.
     * Other statuses (pending, deny, cancel) must NOT credit.
     */
    public function test_webhook_ignores_non_settlement_statuses(): void
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 0,
        ]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'company_membership_id' => $membership->id,
            'order_id' => 'ORDER-PENDING',
            'payment_status' => 'pending',
            'token_amount_granted' => 1000,
        ]);

        $correctSignature = hash('sha512', 'ORDER-PENDING' . '201' . '100000' . $this->serverKey);

        // Send webhook with 'pending' status (not settlement)
        $response = $this->postJson('/api/webhooks/payment/midtrans', [
            'order_id' => 'ORDER-PENDING',
            'status_code' => '201',
            'gross_amount' => '100000',
            'signature_key' => $correctSignature,
            'transaction_status' => 'pending',  // NOT settlement
            'fraud_status' => 'accept',
        ]);

        // Must return 200 (to ack webhook) but NOT credit
        $response->assertStatus(200);

        // Invoice must remain unpaid
        $invoice->refresh();
        $this->assertEquals('pending', $invoice->payment_status);

        // Balance must NOT change
        $membership->refresh();
        $this->assertEquals(0, $membership->current_token_balance);
    }

    /**
     * Capture with fraud_status='challenge' or 'deny' must NOT credit.
     */
    public function test_webhook_rejects_capture_with_fraud(): void
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 0,
        ]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'company_membership_id' => $membership->id,
            'order_id' => 'ORDER-FRAUD',
            'payment_status' => 'pending',
            'token_amount_granted' => 1000,
        ]);

        $correctSignature = hash('sha512', 'ORDER-FRAUD' . '200' . '100000' . $this->serverKey);

        $response = $this->postJson('/api/webhooks/payment/midtrans', [
            'order_id' => 'ORDER-FRAUD',
            'status_code' => '200',
            'gross_amount' => '100000',
            'signature_key' => $correctSignature,
            'transaction_status' => 'capture',
            'fraud_status' => 'challenge',  // Not 'accept'
        ]);

        $response->assertStatus(200);

        // Invoice must remain unpaid
        $invoice->refresh();
        $this->assertEquals('pending', $invoice->payment_status);

        // Balance must NOT change
        $membership->refresh();
        $this->assertEquals(0, $membership->current_token_balance);
    }
}
