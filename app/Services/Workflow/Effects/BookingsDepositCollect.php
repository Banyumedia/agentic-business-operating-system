<?php

namespace App\Services\Workflow\Effects;

class BookingsDepositCollect implements WorkflowEffect
{
    public function key(): string
    {
        return 'bookings.deposit.collect';
    }

    public function execute(array $context): array
    {
        $depositAmount = $context['deposit_amount'] ?? 0;

        if ($depositAmount <= 0) {
            return [
                'effect' => $this->key(),
                'status' => 'failed',
                'reason' => 'Deposit amount is zero or negative',
            ];
        }

        return [
            'effect' => $this->key(),
            'status' => 'success',
            'amount' => $depositAmount,
        ];
    }
}
