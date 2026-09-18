<?php

namespace App\Services\Workflow\Effects;

class BookingsDepositSettle implements WorkflowEffect
{
    public function key(): string
    {
        return 'bookings.deposit.settle';
    }

    public function execute(array $context): array
    {
        $depositAmount = $context['deposit_amount'] ?? 0;

        return [
            'effect' => $this->key(),
            'status' => 'success',
            'settled_amount' => $depositAmount,
        ];
    }
}
