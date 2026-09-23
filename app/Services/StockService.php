<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class StockService
{
    /**
     * Add stock to an item.
     */
    public function addStock(Item $item, float $qty, string $reason, ?string $batchNo = null, ?string $expiresOn = null, ?Model $reference = null): void
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        DB::transaction(function () use ($item, $qty, $reason, $batchNo, $expiresOn, $reference) {
            $batchId = null;

            if ($item->track_batches && $batchNo) {
                $batch = ItemBatch::firstOrCreate(
                    [
                        'company_id' => $item->company_id,
                        'item_id' => $item->id,
                        'batch_no' => $batchNo,
                    ],
                    [
                        'expires_on' => $expiresOn,
                        'qty_on_hand' => 0,
                    ]
                );

                $batch->qty_on_hand += $qty;
                $batch->save();
                $batchId = $batch->id;
            }

            StockMovement::create([
                'company_id' => $item->company_id,
                'item_id' => $item->id,
                'batch_id' => $batchId,
                'direction' => 'in',
                'qty' => $qty,
                'reason' => $reason,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference ? $reference->id : null,
            ]);
        });
    }

    /**
     * Deduct stock from an item, using FEFO for tracked batches.
     *
     * MP-02: item TANPA `track_batches` sebelumnya tidak punya lantai stok
     * sama sekali - `recordDeduction()` menulis `stock_movements` tanpa
     * pernah memeriksa apakah stoknya cukup, jadi qty bisa jatuh negatif
     * tanpa satu galat pun. Item yang dilacak batch sudah aman lewat
     * `deductFeFo()` (menjumlah `qty_on_hand` dengan `lockForUpdate`); item
     * biasa kini diberi pemeriksaan yang setara: saldo dihitung dari
     * `stock_movements` (in - out) dengan kunci baris yang sama, ditolak
     * SEBELUM movement ditulis bila tidak cukup.
     */
    public function deductStock(Item $item, float $qty, string $reason, ?Model $reference = null): void
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        DB::transaction(function () use ($item, $qty, $reason, $reference) {
            if ($item->track_batches) {
                $this->deductFeFo($item, $qty, $reason, $reference);
            } else {
                $this->assertSufficientUntrackedStock($item, $qty);
                $this->recordDeduction($item, null, $qty, $reason, $reference);
            }
        });
    }

    /**
     * @throws RuntimeException bila saldo (in - out) kurang dari $qty.
     */
    private function assertSufficientUntrackedStock(Item $item, float $qty): void
    {
        $available = (float) StockMovement::where('item_id', $item->id)
            ->lockForUpdate()
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN qty ELSE -qty END), 0) as balance")
            ->value('balance');

        if ($available < $qty) {
            throw new RuntimeException("Insufficient stock for item [{$item->id}]. Required: {$qty}, Available: {$available}");
        }
    }

    /**
     * Consume components and produce finished good.
     */
    public function produce(Item $product, float $qty, string $reason, ?Model $reference = null, ?string $batchNo = null, ?string $expiresOn = null): void
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Produce quantity must be greater than zero.');
        }

        DB::transaction(function () use ($product, $qty, $reference, $batchNo, $expiresOn) {
            $components = $product->bomComponents;

            if ($components->isEmpty()) {
                throw new RuntimeException("Item [{$product->id}] has no BOM lines.");
            }

            foreach ($components as $line) {
                $requiredQty = $line->qty * $qty;
                $this->deductStock($line->component, $requiredQty, 'bom_consume', $reference);
            }

            $this->addStock($product, $qty, 'bom_produce', $batchNo, $expiresOn, $reference);
        });
    }

    private function deductFeFo(Item $item, float $remainingQty, string $reason, ?Model $reference): void
    {
        // FEFO: earliest expiry first, null expiry last.
        $batches = ItemBatch::where('item_id', $item->id)
            ->where('qty_on_hand', '>', 0)
            ->orderByRaw('expires_on IS NULL, expires_on ASC')
            ->orderBy('id', 'asc') // tiebreaker
            ->lockForUpdate()
            ->get();

        $totalAvailable = $batches->sum('qty_on_hand');
        if ($totalAvailable < $remainingQty) {
            throw new RuntimeException("Insufficient stock for item [{$item->id}]. Required: {$remainingQty}, Available: {$totalAvailable}");
        }

        foreach ($batches as $batch) {
            if ($remainingQty <= 0) {
                break;
            }

            $take = min($batch->qty_on_hand, $remainingQty);

            $batch->qty_on_hand -= $take;
            $batch->save();

            $this->recordDeduction($item, $batch->id, $take, $reason, $reference);

            $remainingQty -= $take;
        }

        if ($remainingQty > 0) {
            throw new RuntimeException("Insufficient stock in batches for item [{$item->id}] after calculation.");
        }
    }

    private function recordDeduction(Item $item, ?int $batchId, float $qty, string $reason, ?Model $reference): void
    {
        StockMovement::create([
            'company_id' => $item->company_id,
            'item_id' => $item->id,
            'batch_id' => $batchId,
            'direction' => 'out',
            'qty' => $qty,
            'reason' => $reason,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference ? $reference->id : null,
        ]);
    }
}
