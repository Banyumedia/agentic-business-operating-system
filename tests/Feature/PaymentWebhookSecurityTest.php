<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Invoice;
use App\Models\MembershipPlan;
use App\Services\Payment\MidtransSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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
        $correctSignature = hash('sha512', 'ORDER-123'.'200'.'100000'.$this->serverKey);

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

        $correctSignature = hash('sha512', 'ORDER-REPLAY'.'200'.'100000'.$this->serverKey);

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

        $correctSignature = hash('sha512', 'ORDER-PAID'.'200'.'100000'.$this->serverKey);

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

        $correctSignature = hash('sha512', 'ORDER-LOST'.'200'.'100000'.$this->serverKey);

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

        $correctSignature = hash('sha512', 'ORDER-PENDING'.'201'.'100000'.$this->serverKey);

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
     * BS-01: settlement invoice subscription harus **mengaktifkan** membership,
     * bukan hanya menandai invoice `paid` + kredit token.
     *
     * Cacat yang ditutup: `InvoiceCreationService` membuat membership placeholder
     * berstatus `pending` saat invoice subscription dibuat, dan `InvoiceConfirmationService`
     * (jalur manual) mengaktifkannya. Jalur **gateway** (webhook Midtrans) melewatkan
     * langkah itu, jadi pelanggan yang bayar lewat Midtrans sudah membayar tetapi
     * membership-nya tetap `pending` - layanan tidak menyala, dan tidak ada galat yang
     * memberi tahu siapa pun.
     */
    public function test_settlement_of_a_subscription_invoice_activates_the_membership(): void
    {
        $plan = MembershipPlan::factory()->create(['monthly_token_quota' => 1000]);
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            // Placeholder yang dibuat saat invoice subscription diterbitkan (enum: trial).
            'status' => 'trial',
            'current_token_balance' => 0,
        ]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'company_membership_id' => $membership->id,
            'type' => 'subscription',
            'order_id' => 'ORDER-SUB',
            'payment_status' => 'pending',
            'token_amount_granted' => 1000,
        ]);

        $this->settle('ORDER-SUB');

        $membership->refresh();
        // Inti temuan: tanpa perbaikan ini status tetap `pending`.
        $this->assertSame('active', $membership->status);
        $this->assertNotNull($membership->starts_at);
        $this->assertNotNull($membership->expires_at);
    }

    public function test_settlement_of_a_topup_invoice_only_credits_and_leaves_membership_alone(): void
    {
        // Topup bukan langganan: ia menambah saldo token pada membership yang sudah
        // aktif, dan **tidak boleh** memperpanjang masa langganan. Menyamakannya dengan
        // subscription akan memberi perpanjangan gratis setiap kali orang mengisi token.
        $plan = MembershipPlan::factory()->create(['monthly_token_quota' => 1000]);
        $company = Company::factory()->create();
        $expiresAt = now()->addDays(3)->startOfSecond();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDays(27),
            'expires_at' => $expiresAt,
            'current_token_balance' => 200,
        ]);
        Invoice::factory()->create([
            'company_id' => $company->id,
            'company_membership_id' => $membership->id,
            'type' => 'topup',
            'order_id' => 'ORDER-TOPUP',
            'payment_status' => 'pending',
            'token_amount_granted' => 5000,
        ]);

        $this->settle('ORDER-TOPUP');

        $membership->refresh();
        $this->assertSame('active', $membership->status);
        $this->assertSame(5200, (int) $membership->current_token_balance, 'Topup menambah saldo, bukan menggantinya.');
        // Masa langganan tidak bergeser: topup tidak memperpanjang.
        $this->assertEquals($expiresAt->toDateTimeString(), $membership->expires_at->toDateTimeString());
    }

    public function test_negative_settling_twice_activates_and_credits_only_once(): void
    {
        // Idempotensi harus tetap berlaku **setelah** aktivasi ditambahkan: aktivasi
        // yang berjalan dua kali akan menggeser `starts_at`/`expires_at` maju dan
        // memberi bulan gratis pada setiap retry webhook.
        $plan = MembershipPlan::factory()->create(['monthly_token_quota' => 1000]);
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'trial',
            'current_token_balance' => 0,
        ]);
        Invoice::factory()->create([
            'company_id' => $company->id,
            'company_membership_id' => $membership->id,
            'type' => 'subscription',
            'order_id' => 'ORDER-SUB-2X',
            'payment_status' => 'pending',
            'token_amount_granted' => 1000,
        ]);

        $this->settle('ORDER-SUB-2X');
        $membership->refresh();
        $pertama = $membership->expires_at;
        $this->assertSame(1000, (int) $membership->current_token_balance);

        $this->settle('ORDER-SUB-2X')->assertJson(['status' => 'success', 'message' => 'Already paid']);

        $membership->refresh();
        $this->assertSame(1000, (int) $membership->current_token_balance, 'Kredit tidak boleh berlipat.');
        $this->assertEquals($pertama->toDateTimeString(), $membership->expires_at->toDateTimeString(), 'Masa langganan tidak boleh maju dua kali.');
    }

    public function test_negative_a_cancelled_membership_is_reactivated_by_settlement_not_left_dead(): void
    {
        // Temuan bug scout: membership placeholder bisa berakhir `cancelled` (mis. invoice
        // pending dibatalkan lalu dibayar lewat gateway yang datang terlambat). Jalur
        // gateway harus menyejajarkan jalur manual: settlement yang sah mengaktifkan,
        // bukan meninggalkan pelanggan yang sudah membayar dalam keadaan mati.
        $plan = MembershipPlan::factory()->create(['monthly_token_quota' => 1000]);
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'cancelled',
            'current_token_balance' => 0,
        ]);
        Invoice::factory()->create([
            'company_id' => $company->id,
            'company_membership_id' => $membership->id,
            'type' => 'subscription',
            'order_id' => 'ORDER-REVIVE',
            'payment_status' => 'pending',
            'token_amount_granted' => 1000,
        ]);

        $this->settle('ORDER-REVIVE');

        $this->assertSame('active', $membership->fresh()->status);
    }

    private function settle(string $orderId, string $status = 'settlement'): TestResponse
    {
        $signature = hash('sha512', $orderId.'200'.'100000'.$this->serverKey);

        return $this->postJson('/api/webhooks/payment/midtrans', [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => '100000',
            'signature_key' => $signature,
            'transaction_status' => $status,
            'fraud_status' => 'accept',
        ]);
    }

    public function test_negative_settlement_without_a_token_grant_is_refused_fail_closed(): void
    {
        // BS-02: fallback `?? 1` di webhook menyembunyikan invoice yang lupa mengisi
        // `token_amount_granted` - pelanggan membayar penuh lalu dikredit **satu**
        // token. Lebih baik menolak dan meninggalkan jejak daripada mengkredit angka
        // karangan: settlement tanpa grant harus gagal tanpa mengubah apa pun.
        $plan = MembershipPlan::factory()->create();
        $company = Company::factory()->create();
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_token_balance' => 0,
        ]);
        Invoice::factory()->create([
            'company_id' => $company->id,
            'company_membership_id' => $membership->id,
            'type' => 'topup',
            'order_id' => 'ORDER-NOGRANT',
            'payment_status' => 'pending',
            'token_amount_granted' => null,
        ]);

        $this->settle('ORDER-NOGRANT')->assertStatus(422);

        $invoice = Invoice::where('order_id', 'ORDER-NOGRANT')->first();
        $this->assertSame('pending', $invoice->payment_status, 'Invoice tidak boleh berubah saat grant tidak diketahui.');
        $this->assertNull($invoice->paid_at);
        $this->assertSame(0, (int) $membership->fresh()->current_token_balance);

        // Dan tidak ada satu pun baris ledger yang dibuat.
        $entries = $this->app->make('App\Models\TokenLedgerEntry')
            ->whereJsonContains('metadata->order_id', 'ORDER-NOGRANT')->get();
        $this->assertCount(0, $entries);
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

        $correctSignature = hash('sha512', 'ORDER-FRAUD'.'200'.'100000'.$this->serverKey);

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
