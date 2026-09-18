<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Payment\MidtransSignatureVerifier;
use App\Services\Token\TokenLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private MidtransSignatureVerifier $signatureVerifier,
        private TokenLedgerService $tokenLedgerService,
    ) {}

    public function handle(Request $request)
    {
        $payload = $request->validate([
            'order_id' => 'required|string',
            'status_code' => 'required|string',
            'gross_amount' => 'required|string',
            'signature_key' => 'required|string',
            'transaction_status' => 'required|string',
            'fraud_status' => 'nullable|string',
        ]);

        $isValid = $this->signatureVerifier->verify(
            $payload['order_id'],
            $payload['status_code'],
            $payload['gross_amount'],
            $payload['signature_key']
        );

        if (! $isValid) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $invoice = Invoice::where('order_id', $payload['order_id'])->first();

        if (! $invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        $isSettlement = $payload['transaction_status'] === 'settlement';
        $isAcceptedCapture = $payload['transaction_status'] === 'capture'
            && (($payload['fraud_status'] ?? 'accept') === 'accept');

        if ($isSettlement || $isAcceptedCapture) {
            $result = DB::transaction(function () use ($invoice, $payload) {
                $lockedInvoice = Invoice::query()
                    ->whereKey($invoice->id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedInvoice) {
                    return 'invoice_not_found';
                }

                if ($lockedInvoice->payment_status === 'paid') {
                    return 'already_paid';
                }

                $membership = $lockedInvoice->membership;

                if (! $membership) {
                    return 'membership_not_found';
                }

                $tokenAmount = (int) ($lockedInvoice->token_amount_granted ?? 1);

                $lockedInvoice->update([
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);

                $this->tokenLedgerService->recordTransaction(
                    membership: $membership,
                    direction: 'credit',
                    amount: $tokenAmount,
                    source: 'midtrans_payment',
                    idempotencyKey: 'midtrans_'.$payload['order_id'],
                    metadata: [
                        'invoice_id' => $lockedInvoice->id,
                        'order_id' => $payload['order_id'],
                        'transaction_status' => $payload['transaction_status'],
                    ],
                );

                return 'processed';
            });

            if ($result === 'already_paid') {
                return response()->json(['status' => 'success', 'message' => 'Already paid']);
            }

            if ($result === 'membership_not_found') {
                return response()->json(['error' => 'Company membership not found'], 422);
            }
        }

        return response()->json(['status' => 'success']);
    }
}
