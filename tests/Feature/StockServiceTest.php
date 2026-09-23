<?php

namespace Tests\Feature;

use App\Models\BomLine;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stockService;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockService = app(StockService::class);
        $user = User::factory()->create();
        $this->companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a', 'owner_user_id' => $user->id]);
        $this->companyB = Company::create(['name' => 'Company B', 'slug' => 'company-b', 'owner_user_id' => $user->id]);
    }

    public function test_add_stock_creates_movement_and_batch()
    {
        $item = Item::create([
            'company_id' => $this->companyA->id,
            'name' => 'Vaccine',
            'track_batches' => true,
        ]);

        $this->stockService->addStock($item, 10, 'purchase', 'BATCH-001', '2026-12-31');

        $this->assertDatabaseHas('item_batches', [
            'company_id' => $this->companyA->id,
            'item_id' => $item->id,
            'batch_no' => 'BATCH-001',
            'qty_on_hand' => 10,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $this->companyA->id,
            'item_id' => $item->id,
            'direction' => 'in',
            'qty' => 10,
            'reason' => 'purchase',
        ]);
    }

    public function test_fefo_deducts_closest_expiry_first()
    {
        $item = Item::create([
            'company_id' => $this->companyA->id,
            'name' => 'Medicine',
            'track_batches' => true,
        ]);

        $batch1 = ItemBatch::create([
            'company_id' => $this->companyA->id,
            'item_id' => $item->id,
            'batch_no' => 'B1',
            'expires_on' => '2026-10-01',
            'qty_on_hand' => 5,
        ]);

        $batch2 = ItemBatch::create([
            'company_id' => $this->companyA->id,
            'item_id' => $item->id,
            'batch_no' => 'B2',
            'expires_on' => '2026-09-01', // Should be consumed first
            'qty_on_hand' => 5,
        ]);

        $batch3 = ItemBatch::create([
            'company_id' => $this->companyA->id,
            'item_id' => $item->id,
            'batch_no' => 'B3',
            'expires_on' => null, // Should be consumed last
            'qty_on_hand' => 5,
        ]);

        $this->stockService->deductStock($item, 7, 'sale');

        $this->assertEquals(0, $batch2->fresh()->qty_on_hand); // All 5 consumed
        $this->assertEquals(3, $batch1->fresh()->qty_on_hand); // 2 consumed, 3 left
        $this->assertEquals(5, $batch3->fresh()->qty_on_hand); // Untouched

        $this->assertEquals(2, StockMovement::where('direction', 'out')->count());
    }

    public function test_fefo_fails_closed_on_insufficient_stock()
    {
        $item = Item::create([
            'company_id' => $this->companyA->id,
            'name' => 'Medicine',
            'track_batches' => true,
        ]);

        ItemBatch::create([
            'company_id' => $this->companyA->id,
            'item_id' => $item->id,
            'batch_no' => 'B1',
            'qty_on_hand' => 5,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->stockService->deductStock($item, 10, 'sale');
    }

    public function test_negative_untracked_item_fails_closed_on_insufficient_stock(): void
    {
        // MP-02: item TANPA track_batches sebelumnya bisa jatuh negatif tanpa
        // satu galat pun - tidak ada lantai stok sama sekali.
        $item = Item::create([
            'company_id' => $this->companyA->id,
            'name' => 'Sabun Cuci',
            'track_batches' => false,
        ]);

        $this->stockService->addStock($item, 5, 'purchase');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->stockService->deductStock($item, 10, 'sale');
    }

    public function test_negative_untracked_item_rejection_leaves_no_partial_movement(): void
    {
        $item = Item::create([
            'company_id' => $this->companyA->id,
            'name' => 'Sabun Cuci',
            'track_batches' => false,
        ]);

        $this->stockService->addStock($item, 5, 'purchase');

        try {
            $this->stockService->deductStock($item, 10, 'sale');
            $this->fail('Deduksi harus ditolak sebelum menulis movement apa pun.');
        } catch (RuntimeException) {
            // diharapkan
        }

        $this->assertEquals(0, StockMovement::where('item_id', $item->id)->where('direction', 'out')->count());
        $this->assertEquals(5, StockMovement::where('item_id', $item->id)->where('direction', 'in')->sum('qty'));
    }

    public function test_untracked_item_with_sufficient_stock_deducts_normally(): void
    {
        // Kebalikan dari test negatif di atas: stok cukup HARUS tetap berhasil,
        // supaya pemeriksaan lantai stok tidak diam-diam menolak semua orang.
        $item = Item::create([
            'company_id' => $this->companyA->id,
            'name' => 'Sabun Cuci',
            'track_batches' => false,
        ]);

        $this->stockService->addStock($item, 10, 'purchase');
        $this->stockService->deductStock($item, 4, 'sale');

        $this->assertEquals(4, StockMovement::where('item_id', $item->id)->where('direction', 'out')->sum('qty'));
    }

    public function test_bom_produce_consumes_components_and_adds_product()
    {
        $product = Item::create([
            'company_id' => $this->companyA->id,
            'name' => 'Burger',
            'type' => 'finished_good',
            'track_batches' => false,
        ]);

        $bun = Item::create([
            'company_id' => $this->companyA->id,
            'name' => 'Bun',
            'track_batches' => false,
        ]);

        $patty = Item::create([
            'company_id' => $this->companyA->id,
            'name' => 'Patty',
            'track_batches' => true,
        ]);

        BomLine::create([
            'company_id' => $this->companyA->id,
            'product_item_id' => $product->id,
            'component_item_id' => $bun->id,
            'qty' => 1,
        ]);

        BomLine::create([
            'company_id' => $this->companyA->id,
            'product_item_id' => $product->id,
            'component_item_id' => $patty->id,
            'qty' => 1,
        ]);

        $this->stockService->addStock($bun, 10, 'purchase');
        $this->stockService->addStock($patty, 10, 'purchase', 'P1');

        $this->stockService->produce($product, 2, 'production');

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $bun->id,
            'direction' => 'out',
            'qty' => 2,
            'reason' => 'bom_consume',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $patty->id,
            'direction' => 'out',
            'qty' => 2,
            'reason' => 'bom_consume',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $product->id,
            'direction' => 'in',
            'qty' => 2,
            'reason' => 'bom_produce',
        ]);

        $this->assertEquals(8, ItemBatch::where('item_id', $patty->id)->first()->qty_on_hand);
    }

    public function test_tenant_isolation_company_id_does_not_leak()
    {
        $itemA = Item::create([
            'company_id' => $this->companyA->id,
            'name' => 'Item A',
        ]);

        $this->stockService->addStock($itemA, 10, 'adjustment');

        $this->assertDatabaseMissing('stock_movements', [
            'company_id' => $this->companyB->id,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $this->companyA->id,
            'item_id' => $itemA->id,
        ]);
    }
}
