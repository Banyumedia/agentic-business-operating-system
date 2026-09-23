<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderLine;
use App\Services\Accounting\JournalService;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class OrderService
{
    public function __construct(
        private readonly TaxRateService $taxRateService,
        private readonly JournalService $journalService,
        private readonly WorkflowEngine $workflowEngine,
        private readonly StockService $stockService,
        private readonly FeatureResolver $features,
    ) {}

    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            if (! empty($data['external_ref'])) {
                $existing = Order::where('company_id', $data['company_id'])
                    ->where('external_ref', $data['external_ref'])
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $order = Order::create([
                'company_id' => $data['company_id'],
                'business_identity_id' => $data['business_identity_id'],
                'shift_id' => $data['shift_id'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'resource_id' => $data['resource_id'] ?? null,
                'prescription_id' => $data['prescription_id'] ?? null,
                'order_no' => $data['order_no'] ?? uniqid('ORD-'),
                'stage' => $data['stage'] ?? $this->initialOrderStage(),
                'source' => $data['source'] ?? 'pos',
                'external_ref' => $data['external_ref'] ?? null,
            ]);

            if (! empty($data['lines'])) {
                $this->syncLines($order, $data['lines']);
            }

            return $order->refresh();
        });
    }

    public function syncLines(Order $order, array $linesData): Order
    {
        return DB::transaction(function () use ($order, $linesData) {
            $order->lines()->delete();

            $subtotal = 0.0;
            $totalDiscount = 0.0;

            foreach ($linesData as $lineData) {
                $qty = (float) $lineData['qty'];
                $unitPrice = (float) $lineData['unit_price'];
                $discountAmount = (float) ($lineData['discount_amount'] ?? 0);

                $lineTotal = round(($qty * $unitPrice) - $discountAmount, 2);

                $order->lines()->create([
                    'company_id' => $order->company_id,
                    'item_id' => $lineData['item_id'] ?? null,
                    'description' => $lineData['description'],
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discountAmount,
                    'line_total' => $lineTotal,
                    'fired_at' => $lineData['fired_at'] ?? null,
                ]);

                $subtotal += ($qty * $unitPrice);
                $totalDiscount += $discountAmount;
            }

            $businessIdentity = $order->businessIdentity;
            $taxRate = (float) $businessIdentity->tax_rate;
            $includesTax = (bool) $businessIdentity->price_includes_tax;

            $grossAmount = round($subtotal - $totalDiscount, 2);
            $taxResult = $this->taxRateService->calculateTax($grossAmount, $taxRate, $includesTax);

            $order->update([
                'subtotal' => $subtotal,
                'discount_amount' => $totalDiscount,
                'dpp' => $taxResult->dpp,
                'tax_amount' => $taxResult->tax,
                'grand_total' => $taxResult->grandTotal,
            ]);

            return $order;
        });
    }

    public function fireLine(OrderLine $line): OrderLine
    {
        $line->update(['fired_at' => now()]);

        return $line;
    }

    public function payOrder(
        Order $order,
        string $paymentMethod,
        string $actorRole,
        ?array $journalHeader = null,
        ?array $journalLines = null,
        ?string $targetStage = 'selesai'
    ): array {
        return DB::transaction(function () use ($order, $paymentMethod, $actorRole, $journalHeader, $journalLines, $targetStage) {
            $transitionResult = $this->workflowEngine->transition($order, $targetStage ?? 'selesai', $actorRole);

            $order->update([
                'payment_method' => $paymentMethod,
                'paid_at' => now(),
            ]);

            if ($journalHeader !== null && $journalLines !== null) {
                try {
                    $this->journalService->post($journalHeader, $journalLines);
                } catch (Throwable $e) {
                    throw new InvalidArgumentException('Failed to post journal: '.$e->getMessage());
                }
            }

            return $transitionResult;
        });
    }

    private function initialOrderStage(): string
    {
        try {
            $stages = $this->workflowEngine->stages('orders');
            $code = $stages[0]['code'] ?? null;

            if (is_string($code) && $code !== '') {
                return $code;
            }
        } catch (Throwable) {
            // Fallback untuk konteks non-HTTP (mis. unit test service tanpa active_company).
        }

        return 'open';
    }

    /**
     * Checkout kasir lengkap (MP-02): order+lines, stok berkurang lewat
     * `StockService` (`stock_movements` tercatat), jurnal terposting lewat
     * `JournalService` bila `finance.accounting` aktif - satu transaksi,
     * semua-atau-tidak-sama-sekali.
     *
     * Idempotensi mengikuti `createOrder()`: replay `external_ref` yang sama
     * mengembalikan order yang sama TANPA mengulang stok/jurnal
     * (`Order::wasRecentlyCreated` membedakan "baru dibuat" dari "replay") -
     * klik dua kali tidak boleh mengurangi stok dua kali maupun memposting
     * jurnal dua kali untuk transaksi yang sama.
     *
     * @param  array{company_id: int|string, business_identity_id: int, order_no: string, external_ref: string, stage: string|null, payment_method: string, lines: list<array<string, mixed>>}  $data
     */
    public function checkoutPos(array $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $order = $this->createOrder([
                'company_id' => $data['company_id'],
                'business_identity_id' => $data['business_identity_id'],
                'order_no' => $data['order_no'],
                'source' => 'pos',
                'external_ref' => $data['external_ref'],
                'stage' => $data['stage'],
                'lines' => array_map(static fn (array $line): array => [
                    'item_id' => $line['item_id'],
                    'description' => $line['description'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'discount_amount' => $line['discount_amount'] ?? 0,
                ], $data['lines']),
            ]);

            if (! $order->wasRecentlyCreated) {
                return $order;
            }

            $order->forceFill([
                'payment_method' => $data['payment_method'],
                'paid_at' => now(),
            ])->save();

            foreach ($data['lines'] as $line) {
                $item = Item::query()
                    ->where('company_id', $data['company_id'])
                    ->find($line['item_id']);

                if ($item === null) {
                    throw new InvalidArgumentException('Barang atau layanan tidak lagi tersedia.');
                }

                $this->stockService->deductStock($item, (float) $line['qty'], 'pos_sale', $order);
            }

            $this->postPosSalesJournal($order);

            return $order;
        });
    }

    private function postPosSalesJournal(Order $order): void
    {
        if (! $this->features->enabled('finance.accounting')) {
            return;
        }

        $amount = (float) $order->grand_total;
        if ($amount <= 0) {
            return;
        }

        $accounts = ChartOfAccount::query()
            ->where('company_id', $order->company_id)
            ->whereIn('account_code', ['1000', '4000'])
            ->pluck('id', 'account_code');

        if (! isset($accounts['1000'], $accounts['4000'])) {
            throw new RuntimeException('Akun kas atau pendapatan belum dikonfigurasi. Hubungi owner untuk menyiapkan Bagan Akun.');
        }

        $this->journalService->post([
            'company_id' => (int) $order->company_id,
            'journal_number' => 'POS-'.$order->id.'-'.now()->format('YmdHis'),
            'transaction_date' => now()->toDateString(),
            'reference' => $order->order_no,
            'description' => 'Auto journal dari penjualan kasir',
        ], [
            [
                'account_id' => (int) $accounts['1000'],
                'debit' => $amount,
                'credit' => 0,
                'description' => 'Kas dari penjualan '.$order->order_no,
            ],
            [
                'account_id' => (int) $accounts['4000'],
                'debit' => 0,
                'credit' => $amount,
                'description' => 'Pendapatan penjualan '.$order->order_no,
            ],
        ]);
    }
}
