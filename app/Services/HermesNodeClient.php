<?php

namespace App\Services;

use RuntimeException;

class HermesNodeClient
{
    /**
     * Send a WhatsApp message.
     *
     * @throws RuntimeException If delivery fails or the number is not verified.
     */
    public function sendWhatsAppMessage(string $companyId, string $to, string $message): void
    {
        // Fail-closed external call.
        // During tests, this should be mocked.
        throw new RuntimeException('HermesNodeClient is not configured for actual delivery in this environment.');
    }
}
