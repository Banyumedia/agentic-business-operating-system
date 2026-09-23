<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Livewire\Screens\CashierScreen;
use App\Models\AccountingJournal;
use App\Models\BusinessIdentity;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Item;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\User;
use App\Providers\DataSourceServiceProvider;
use App\Services\FeatureResolver;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * MP-02: pembuktian ujung-ke-ujung lewat komponen Livewire nyata (bukan
 * hanya `OrderService` terisolasi seperti di `OrderServiceTest`) bahwa
 * checkout kasir mode Eloquent benar-benar melewati `StockService` dan
 * `JournalService`, bukan menulis agregat sendiri.
 */
class CashierScreenEloquentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['datasource.driver' => 'eloquent']);
        (new DataSourceServiceProvider($this->app))->register();

        $this->artisan('db:seed', ['--class' => 'BusinessPresetSeeder']);
    }

    public function test_checkout_via_the_livewire_component_deducts_stock_and_posts_a_journal(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id, 'business_preset' => 'bengkel']);
        BusinessIdentity::factory()->create([
            'company_id' => $company->id,
            'tax_rate' => 0,
            'price_includes_tax' => false,
            'tax_mode' => 'non_taxable',
        ]);
        $item = Item::create([
            'company_id' => $company->id,
            'name' => 'Ganti Oli',
            'price' => 75000,
            'track_batches' => false,
        ]);
        app(StockService::class)->addStock($item, 10, 'purchase');
        ChartOfAccount::create(['company_id' => $company->id, 'account_code' => '1000', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        ChartOfAccount::create(['company_id' => $company->id, 'account_code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);

        app(CompanyContext::class)->setCurrent((string) $company->id);
        $this->mockFinanceAccountingEnabled();

        Livewire::test(CashierScreen::class, ['module' => 'pos'])
            ->call('addItem', $item->id)
            ->set('paymentMethod', 'cash')
            ->call('requestAction', 'checkout')
            ->set('confirmPhrase', 'YA')
            ->call('confirmAction')
            ->assertSet('failure', null)
            ->assertSet('cart', [])
            ->assertSee('tersimpan');

        $order = Order::where('company_id', $company->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('cash', $order->payment_method);
        $this->assertNotNull($order->paid_at);

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'direction' => 'out',
            'qty' => 1,
            'reason' => 'pos_sale',
        ]);

        $journal = AccountingJournal::where('company_id', $company->id)->first();
        $this->assertNotNull($journal, 'Checkout via komponen Livewire harus memposting jurnal, bukan hanya menyimpan order.');
    }

    public function test_negative_replay_of_the_same_confirmed_action_does_not_duplicate_order_or_stock_movement(): void
    {
        // Pola yang sama dengan test JSON `test_checkout_is_all_or_nothing_and_replay_is_a_no_op`:
        // memutar ulang `confirmAction()` PERSIS seperti yang UI bisa lakukan
        // (double-submit tombol yang sama sebelum state ter-reset) - bukan
        // transaksi baru dengan token baru.
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id, 'business_preset' => 'bengkel']);
        BusinessIdentity::factory()->create([
            'company_id' => $company->id,
            'tax_rate' => 0,
            'price_includes_tax' => false,
            'tax_mode' => 'non_taxable',
        ]);
        $item = Item::create([
            'company_id' => $company->id,
            'name' => 'Ganti Oli',
            'price' => 75000,
            'track_batches' => false,
        ]);
        app(StockService::class)->addStock($item, 10, 'purchase');
        ChartOfAccount::create(['company_id' => $company->id, 'account_code' => '1000', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        ChartOfAccount::create(['company_id' => $company->id, 'account_code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);

        app(CompanyContext::class)->setCurrent((string) $company->id);
        $this->mockFinanceAccountingEnabled();

        $component = Livewire::test(CashierScreen::class, ['module' => 'pos'])
            ->call('addItem', $item->id)
            ->set('paymentMethod', 'cash')
            ->call('requestAction', 'checkout')
            ->set('confirmPhrase', 'YA')
            ->call('confirmAction')
            ->assertSet('failure', null);

        // Replay: memanggil confirmAction() lagi tanpa requestAction() baru.
        // Sesudah sukses, pendingAction sudah null (cancelAction() di akhir
        // checkout()), jadi ini seharusnya no-op murni - tapi kalaupun
        // idempotensi external_ref satu-satunya penjaga (mis. token belum
        // sempat dirotasi client), stok/jurnal tetap tidak boleh dobel.
        $component->call('confirmAction');

        $this->assertSame(1, Order::where('company_id', $company->id)->count());
        $this->assertSame(1, StockMovement::where('item_id', $item->id)->where('direction', 'out')->count());
    }

    /**
     * Company fixture di test ini tidak punya membership plan aktif, jadi
     * `FeatureResolver` nyata (yang bergantung `PlanCapabilityGate`) akan
     * menjawab `finance.accounting` mati terlepas dari capability preset -
     * sama seperti pola `OrderServiceTest::posFixture()`.
     */
    private function mockFinanceAccountingEnabled(): void
    {
        // Livewire memuat shell modul penuh (DynamicMenuRegistry menanyakan
        // capability lain juga, mis. 'pos', untuk menyusun menu) - bukan
        // hanya CashierScreen. Mock ini sengaja menjawab true untuk SEMUA
        // capability (bukan hanya finance.accounting) karena yang diuji di
        // sini adalah wiring stok+jurnal, bukan gerbang kapabilitas itu
        // sendiri (yang sudah punya test sendiri di PresetMenuContractTest
        // dkk).
        $features = Mockery::mock(FeatureResolver::class);
        $features->shouldReceive('enabled')->andReturnTrue();
        $this->app->instance(FeatureResolver::class, $features);
    }
}
