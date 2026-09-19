<?php

namespace App\Services\Workflow\Effects;

use App\Models\ChartOfAccount;
use App\Models\Order;
use App\Services\Accounting\JournalService;
use RuntimeException;

class JournalPost implements WorkflowEffect
{
    public function __construct(private readonly JournalService $journalService) {}

    public function key(): string
    {
        return 'journal.post';
    }

    public function execute(array $context): array
    {
        $record = $context['record'] ?? null;
        if (config('datasource.driver') !== 'eloquent'
            || ($context['entity'] ?? null) !== 'orders'
            || ! $record instanceof Order) {
            throw new RuntimeException('Journal effect requires an Order workflow record.');
        }

        $order = Order::query()
            ->where('company_id', $context['company'])
            ->whereKey($record->getKey())
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

        $accounts = ChartOfAccount::query()
            ->where('company_id', $context['company'])
            ->whereIn('account_code', ['1000', '4000'])
            ->pluck('id', 'account_code');
        if (! isset($accounts['1000'], $accounts['4000'])) {
            throw new RuntimeException('Akun kas atau pendapatan belum dikonfigurasi.');
        }

        $journal = $this->journalService->post([
            'company_id' => (int) $context['company'],
            'journal_number' => sprintf('ORD-%s-%s', $order->id, now()->format('YmdHis')),
            'transaction_date' => now()->toDateString(),
            'reference' => $order->order_no,
            'description' => 'Auto journal dari pembayaran order',
        ], [
            [
                'account_id' => (int) $accounts['1000'],
                'debit' => $amount,
                'credit' => 0,
                'description' => 'Kas dari pembayaran order '.$order->order_no,
            ],
            [
                'account_id' => (int) $accounts['4000'],
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
