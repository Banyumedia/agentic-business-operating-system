<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Livewire\Screens\StockAdjustmentScreen;
use App\Models\Company;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Providers\DataSourceServiceProvider;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * MP-10: penyesuaian stok fisik. Selisih opname ditulis sebagai mutasi
 * (StockService), tidak pernah menimpa `qty_on_hand` diam-diam.
 */
class StockAdjustmentScreenTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        config(['datasource.driver' => 'eloquent']);
        (new DataSourceServiceProvider($this->app))->register();
        $this->artisan('db:seed', ['--class' => 'BusinessPresetSeeder']);

        $this->owner = User::factory()->create();
        $this->company = Company::factory()->create(['owner_user_id' => $this->owner->id, 'business_preset' => 'bengkel']);
        $this->owner->update(['current_company_id' => $this->company->id]);
        $this->item = Item::create([
            'company_id' => $this->company->id,
            'name' => 'Oli Mesin',
            'track_batches' => false,
        ]);
        app(StockService::class)->addStock($this->item, 10, 'purchase');

        app(CompanyContext::class)->setCurrent((string) $this->company->id);
        $this->actingAs($this->owner);
    }

    public function test_owner_can_record_a_positive_adjustment(): void
    {
        Livewire::test(StockAdjustmentScreen::class, ['module' => 'inventory', 'submodule' => 'adjustment'])
            ->set('itemId', $this->item->id)
            ->set('delta', '3')
            ->set('reason', 'Hasil opname: kelebihan stok')
            ->call('adjust')
            ->assertSet('failure', null)
            ->assertSee('Penyesuaian stok tercatat');

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $this->item->id,
            'direction' => 'in',
            'qty' => 3,
        ]);
        $this->assertSame(13.0, app(StockService::class)->currentBalance($this->item->fresh()));
    }

    public function test_owner_can_record_a_negative_adjustment(): void
    {
        Livewire::test(StockAdjustmentScreen::class, ['module' => 'inventory', 'submodule' => 'adjustment'])
            ->set('itemId', $this->item->id)
            ->set('delta', '-2')
            ->set('reason', 'Hasil opname: rusak')
            ->call('adjust')
            ->assertSet('failure', null);

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $this->item->id,
            'direction' => 'out',
            'qty' => 2,
        ]);
        $this->assertSame(8.0, app(StockService::class)->currentBalance($this->item->fresh()));
    }

    public function test_negative_adjustment_without_a_reason_is_rejected(): void
    {
        Livewire::test(StockAdjustmentScreen::class, ['module' => 'inventory', 'submodule' => 'adjustment'])
            ->set('itemId', $this->item->id)
            ->set('delta', '3')
            ->set('reason', '')
            ->call('adjust')
            ->assertSee('Alasan penyesuaian wajib diisi');

        $this->assertSame(0, StockMovement::where('item_id', $this->item->id)->where('reason', 'like', 'adjustment:%')->count());
    }

    public function test_negative_zero_delta_is_rejected(): void
    {
        Livewire::test(StockAdjustmentScreen::class, ['module' => 'inventory', 'submodule' => 'adjustment'])
            ->set('itemId', $this->item->id)
            ->set('delta', '0')
            ->set('reason', 'Coba nol')
            ->call('adjust')
            ->assertSee('harus angka bukan nol');

        $this->assertSame(0, StockMovement::where('item_id', $this->item->id)->where('reason', 'like', 'adjustment:%')->count());
    }

    public function test_negative_non_numeric_delta_is_rejected(): void
    {
        Livewire::test(StockAdjustmentScreen::class, ['module' => 'inventory', 'submodule' => 'adjustment'])
            ->set('itemId', $this->item->id)
            ->set('delta', 'banyak')
            ->set('reason', 'Coba teks')
            ->call('adjust')
            ->assertSee('harus angka bukan nol');

        $this->assertSame(0, StockMovement::where('item_id', $this->item->id)->where('reason', 'like', 'adjustment:%')->count());
    }

    public function test_negative_item_belonging_to_another_company_is_rejected(): void
    {
        $foreignCompany = Company::factory()->create();
        $foreignItem = Item::create([
            'company_id' => $foreignCompany->id,
            'name' => 'Barang Usaha Lain',
            'track_batches' => false,
        ]);

        Livewire::test(StockAdjustmentScreen::class, ['module' => 'inventory', 'submodule' => 'adjustment'])
            ->set('itemId', $foreignItem->id)
            ->set('delta', '5')
            ->set('reason', 'Coba barang usaha lain')
            ->call('adjust')
            ->assertSee('tidak ditemukan pada usaha ini');

        $this->assertSame(0, StockMovement::where('item_id', $foreignItem->id)->count());
    }

    public function test_negative_insufficient_stock_for_negative_adjustment_is_rejected(): void
    {
        Livewire::test(StockAdjustmentScreen::class, ['module' => 'inventory', 'submodule' => 'adjustment'])
            ->set('itemId', $this->item->id)
            ->set('delta', '-100')
            ->set('reason', 'Coba lebih dari saldo')
            ->call('adjust')
            ->assertSee('Insufficient stock');

        $this->assertSame(10.0, app(StockService::class)->currentBalance($this->item->fresh()));
    }

    public function test_negative_staff_cannot_adjust_stock(): void
    {
        $staff = User::factory()->create(['current_company_id' => $this->company->id]);
        DB::table('company_user')->insert([
            'company_id' => $this->company->id,
            'user_id' => $staff->id,
            'role' => 'staff',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($staff);

        Livewire::test(StockAdjustmentScreen::class, ['module' => 'inventory', 'submodule' => 'adjustment'])
            ->set('itemId', $this->item->id)
            ->set('delta', '3')
            ->set('reason', 'Staf coba menyesuaikan')
            ->call('adjust')
            ->assertStatus(403);

        $this->assertSame(10.0, app(StockService::class)->currentBalance($this->item->fresh()));
    }

    public function test_history_shows_a_consistent_running_balance(): void
    {
        $component = Livewire::test(StockAdjustmentScreen::class, ['module' => 'inventory', 'submodule' => 'adjustment'])
            ->set('itemId', $this->item->id)
            ->set('delta', '5')
            ->set('reason', 'Tambahan pertama')
            ->call('adjust');

        $component->set('itemId', $this->item->id)
            ->set('delta', '-3')
            ->set('reason', 'Pengurangan kedua')
            ->call('adjust');

        $history = $component->set('itemId', $this->item->id)->viewData('history');

        // Diurutkan terbaru dulu: -3 (saldo 12) lalu +5 (saldo 15) lalu
        // pembelian awal +10 (saldo 10).
        $this->assertSame(12.0, $history[0]['balance_after']);
        $this->assertSame(15.0, $history[1]['balance_after']);
        $this->assertSame(10.0, $history[2]['balance_after']);
    }
}
