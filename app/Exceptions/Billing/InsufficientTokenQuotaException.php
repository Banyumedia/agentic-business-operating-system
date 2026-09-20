<?php

namespace App\Exceptions\Billing;

use Exception;

/**
 * Exception thrown when token quota is exceeded.
 *
 * Used when attempting an AI action but the company's token balance
 * is insufficient or quota has been exhausted.
 *
 * Fail-closed: action is denied entirely.
 */
class InsufficientTokenQuotaException extends Exception
{
    public function __construct(
        int $tokensRequired,
        int $tokensAvailable,
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            'Kuota token AI tidak mencukupi. Diperlukan: %d token. Ketersediaan: %d token.',
            $tokensRequired,
            $tokensAvailable
        );

        parent::__construct($message, 0, $previous);
    }
}
