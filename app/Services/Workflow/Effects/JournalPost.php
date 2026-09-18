<?php

namespace App\Services\Workflow\Effects;

use App\Models\Order;
use App\Services\Accounting\JournalService;

class JournalPost implements WorkflowEffect
{
    public function __construct(private readonly JournalService $journalService) {}

    public function key(): string
    {
        return 'journal.post';
    }

    public function execute(array $context): array
    {
        $order = Order::query()
            ->where('company_id', $context['company'])
            ->whereKey($context['record_id'])
            ->first();

        if (! $order) {
            return [
                'effect' => $this->key(),
                'status' => 'skipped',
                'reason' => 'order_not_found',
            ];
        }

        $amount = (float) $order->grand_total;
        if ($amount <= 0) {
            return [
                'effect' => $this->key(),
                'status' => 'skipped',
                'reason' => 'invalid_amount',
            ];
        }

        $journal = $this->journalService->post([
            'company_id' => (int) $context['company'],
            'journal_number' => sprintf('ORD-%s-%s', $order->id, now()->format('YmdHis')),
            'transaction_date' => now()->toDateString(),
            'reference' => $order->order_no,
            'description' => 'Auto journal dari pembayaran order',
        ], [
            [
                'account_id' => 1,
                'debit' => $amount,
                'credit' => 0,
                'description' => 'Kas dari pembayaran order '.$order->order_no,
            ],
            [
                'account_id' => 2,
                'debit' => 0,
                'credit' => $amount,
                'description' => 'Pendapatan order '.$order->order_no,
            ],
        ]);

        return [
            'effect' => $this->key(),
            'status' => 'posted',
            'journal_id' => $journal->id,
            'amount' => $amount,
        ];
    }
}
