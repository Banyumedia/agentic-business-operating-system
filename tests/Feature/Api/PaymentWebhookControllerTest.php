<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Invoice;
use App\Models\MembershipPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PaymentWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.env', 'testing');
        Config::set('services.midtrans.server_key', 'test');
    }

    public function test_rejects_invalid_signature(): void
    {
        $response = $this->postJson('/api/webhooks/payment/midtrans', [
            'order_id' => 'INV-001',
            'status_code' => '200',
            'gross_amount' => '100000.00',
            'signature_key' => 'invalid-signature',
            'transaction_status' => 'settlement',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Invalid signature']);
    }

    public function test_rejects_unknown_order_id(): void
    {
        $payload = [
            'order_id' => 'UNKNOWN-001',
            'status_code' => '200',
            'gross_amount' => '100000.00',
            'transaction_status' => 'settlement',
        ];

        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test');

        $response = $this->postJson('/api/webhooks/payment/midtrans', $payload);

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Invoice not found']);
    }

    public function test_processes_valid_settlement_and_credits_ledger(): void
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'company_membership_id' => $membership->id,
            'order_id' => 'INV-001',
            'payment_status' => 'pending',
            'type' => 'subscription',
        ]);

        $payload = [
            'order_id' => 'INV-001',
            'status_code' => '200',
            'gross_amount' => '100000.00',
            'transaction_status' => 'settlement',
        ];

        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test');

        $response = $this->postJson('/api/webhooks/payment/midtrans', $payload);

        $response->assertStatus(200);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->payment_status);
        $this->assertNotNull($invoice->paid_at);

        $this->assertDatabaseHas('token_ledger_entries', [
            'company_id' => $company->id,
            'company_membership_id' => $membership->id,
            'direction' => 'credit',
            'amount' => 1,
            'source' => 'midtrans_payment',
        ]);
    }

    public function test_is_idempotent_for_already_paid_invoice(): void
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'company_membership_id' => $membership->id,
            'order_id' => 'INV-002',
            'payment_status' => 'paid',
            'type' => 'subscription',
            'paid_at' => now(),
        ]);

        $payload = [
            'order_id' => 'INV-002',
            'status_code' => '200',
            'gross_amount' => '100000.00',
            'transaction_status' => 'settlement',
        ];

        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test');

        $response = $this->postJson('/api/webhooks/payment/midtrans', $payload);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Already paid']);

        // Ledger count should be 0 because it was already paid before this request
        $this->assertDatabaseCount('token_ledger_entries', 0);
    }

    public function test_ignores_non_settlement_status(): void
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'company_membership_id' => $membership->id,
            'order_id' => 'INV-003',
            'payment_status' => 'pending',
            'type' => 'subscription',
        ]);

        $payload = [
            'order_id' => 'INV-003',
            'status_code' => '201',
            'gross_amount' => '100000.00',
            'transaction_status' => 'pending',
        ];

        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test');

        $response = $this->postJson('/api/webhooks/payment/midtrans', $payload);

        $response->assertStatus(200);

        $invoice->refresh();
        $this->assertEquals('pending', $invoice->payment_status);
        $this->assertNull($invoice->paid_at);
        $this->assertDatabaseCount('token_ledger_entries', 0);
    }

    public function test_capture_with_challenge_fraud_status_is_ignored(): void
    {
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'company_membership_id' => $membership->id,
            'order_id' => 'INV-004',
            'payment_status' => 'pending',
            'type' => 'subscription',
        ]);

        $payload = [
            'order_id' => 'INV-004',
            'status_code' => '200',
            'gross_amount' => '100000.00',
            'transaction_status' => 'capture',
            'fraud_status' => 'challenge',
        ];

        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test');

        $response = $this->postJson('/api/webhooks/payment/midtrans', $payload);

        $response->assertStatus(200);

        $invoice->refresh();
        $this->assertEquals('pending', $invoice->payment_status);
        $this->assertNull($invoice->paid_at);
        $this->assertDatabaseCount('token_ledger_entries', 0);
    }

    public function test_rejects_when_midtrans_key_not_configured(): void
    {
        Config::set('services.midtrans.server_key', '');

        $response = $this->postJson('/api/webhooks/payment/midtrans', [
            'order_id' => 'INV-005',
            'status_code' => '200',
            'gross_amount' => '100000.00',
            'signature_key' => hash('sha512', 'INV-005200100000.00test'),
            'transaction_status' => 'settlement',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Invalid signature']);
    }
}
