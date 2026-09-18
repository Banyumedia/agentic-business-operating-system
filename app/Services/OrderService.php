<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderLine;
use App\Services\Accounting\JournalService;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class OrderService
{
    public function __construct(
        private readonly TaxRateService $taxRateService,
        private readonly JournalService $journalService,
        private readonly WorkflowEngine $workflowEngine
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
}
