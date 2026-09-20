<?php

namespace App\Exceptions\Billing;

use Exception;

/**
 * Exception thrown when WA group quota is exceeded.
 *
 * Used when attempting to add a WhatsApp group but the company
 * has already reached its max allowed groups based on membership plan or free tier.
 *
 * Fail-closed: action is denied entirely.
 */
class WaGroupQuotaExceededException extends Exception
{
    public function __construct(
        int $maxAllowedGroups,
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            'Kuota grup WhatsApp tercapai. Maksimal: %d grup.',
            $maxAllowedGroups
        );

        parent::__construct($message, 0, $previous);
    }
}
