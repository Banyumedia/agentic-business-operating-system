<?php

namespace App\Services\Payment;

class MidtransSignatureVerifier
{
    /**
     * Verify Midtrans webhook signature.
     *
     * Signature formulation:
     * SHA512(order_id + status_code + gross_amount + server_key)
     */
    public function verify(string $orderId, string $statusCode, string $grossAmount, string $signatureKey): bool
    {
        $serverKey = (string) config('services.midtrans.server_key', '');

        if ($serverKey === '') {
            return false;
        }

        $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return hash_equals($expectedSignature, $signatureKey);
    }
}
