<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Models\AccountingJournal;
use App\Models\BusinessIdentity;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Item;
use App\Models\Order;
use App\Models\Resource;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\FeatureResolver;
use App\Services\HermesNodeClient;
use App\Services\OrderService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrderService::class);
    }

    public function test_tenant_isolation()
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $identityA = BusinessIdentity::factory()->create(['company_id' => $companyA->id, 'tax_rate' => 11, 'price_includes_tax' => false]);
        $identityB = BusinessIdentity::factory()->create(['company_id' => $companyB->id, 'tax_rate' => 11, 'price_includes_tax' => false]);

        $mockContext = Mockery::mock(CompanyContext::class);
        $mockContext->shouldReceive('current')->andReturn((string) $companyA->id, (string) $companyB->id);
        $mockContext->shouldReceive('preset')->andReturn('bengkel');
        $this->app->instance(CompanyContext::class, $mockContext);

        $orderA = $this->service->createOrder([
            'company_id' => $companyA->id,
            'business_identity_id' => $identityA->id,
            'order_no' => 'ORD-A-1',
        ]);

        $orderB = $this->service->createOrder([
            'company_id' => $companyB->id,
            'business_identity_id' => $identityB->id,
            'order_no' => 'ORD-B-1',
        ]);

        $this->assertEquals($companyA->id, $orderA->company_id);
        $this->assertEquals($companyB->id, $orderB->company_id);
    }

    public function test_tax_exclusive_calculation()
    {
        $company = Company::factory()->create();
        $identity = BusinessIdentity::factory()->create([
            'company_id' => $company->id,
            'tax_rate' => 11,
            'price_includes_tax' => false,
        ]);

        $mockContext = Mockery::mock(CompanyContext::class);
        $mockContext->shouldReceive('current')->andReturn((string) $company->id);
        $mockContext->shouldReceive('preset')->andReturn('bengkel');
        $this->app->instance(CompanyContext::class, $mockContext);

        $order = $this->service->createOrder([
            'company_id' => $company->id,
            'business_identity_id' => $identity->id,
            'order_no' => 'ORD-TAX-EX',
            'lines' => [
                [
                    'description' => 'Item 1',
                    'qty' => 2,
                    'unit_price' => 10000,
                ],
            ],
        ]);

        $this->assertEquals(20000, $order->subtotal);
        $this->assertEquals(0, $order->discount_amount);
        $this->assertEquals(20000, $order->dpp);
        $this->assertEquals(2200, $order->tax_amount);
        $this->assertEquals(22200, $order->grand_total);
    }

    public function test_tax_inclusive_calculation()
    {
        $company = Company::factory()->create();
        $identity = BusinessIdentity::factory()->create([
            'company_id' => $company->id,
            'tax_rate' => 11,
            'price_includes_tax' => true,
        ]);

        $mockContext = Mockery::mock(CompanyContext::class);
        $mockContext->shouldReceive('current')->andReturn((string) $company->id);
        $mockContext->shouldReceive('preset')->andReturn('bengkel');
        $this->app->instance(CompanyContext::class, $mockContext);

        $order = $this->service->createOrder([
            'company_id' => $company->id,
            'business_identity_id' => $identity->id,
            'order_no' => 'ORD-TAX-IN',
            'lines' => [
                [
                    'description' => 'Item 1',
                    'qty' => 2,
                    'unit_price' => 11100,
                ],
            ],
        ]);

        $this->assertEquals(22200, $order->subtotal);
        $this->assertEquals(0, $order->discount_amount);
        $this->assertEquals(20000, $order->dpp);
        $this->assertEquals(2200, $order->tax_amount);
        $this->assertEquals(22200, $order->grand_total);
    }

    public function test_webhook_idempotency_via_external_ref()
    {
        $company = Company::factory()->create();
        $identity = BusinessIdentity::factory()->create(['company_id' => $company->id]);

        $data = [
            'company_id' => $company->id,
            'business_identity_id' => $identity->id,
            'order_no' => 'ORD-WEBHOOK-1',
            'external_ref' => 'WH-REF-123',
        ];

        $order1 = $this->service->createOrder($data);
        $order2 = $this->service->createOrder($data);

        $this->assertEquals($order1->id, $order2->id);
        $this->assertEquals(1, Order::where('external_ref', 'WH-REF-123')->count());
    }

    public function test_pos_tables_flow()
    {
        $company = Company::factory()->create();
        $identity = BusinessIdentity::factory()->create(['company_id' => $company->id]);
        $resource = Resource::factory()->create(['company_id' => $company->id]);

        $order = $this->service->createOrder([
            'company_id' => $company->id,
            'business_identity_id' => $identity->id,
            'resource_id' => $resource->id,
            'order_no' => 'ORD-TABLE-1',
            'lines' => [
                [
                    'description' => 'Burger',
                    'qty' => 1,
                    'unit_price' => 50000,
                ],
            ],
        ]);

        $this->assertEquals($resource->id, $order->resource_id);

        $line = $order->lines->first();
        $this->assertNull($line->fired_at);

        $this->service->fireLine($line);
        $this->assertNotNull($line->refresh()->fired_at);
    }

    public function test_order_paid_triggers_journal_post_effect()
    {
        $company = Company::factory()->create();
        $identity = BusinessIdentity::factory()->create(['company_id' => $company->id]);

        $mockContext = Mockery::mock(CompanyContext::class);
        $mockContext->shouldReceive('current')->andReturn((string) $company->id);
        $mockContext->shouldReceive('preset')->andReturn('bengkel');
        $this->app->instance(CompanyContext::class, $mockContext);

        $mockHermes = Mockery::mock(HermesNodeClient::class);
        $mockHermes->shouldReceive('sendWhatsAppMessage')->andReturn(true);
        $this->app->instance(HermesNodeClient::class, $mockHermes);

        // MP-02: journal.post digerbang 'finance.accounting' (dibenahi dari
        // 'finance.cashbook' yang tidak konsisten dengan gerbang jurnal/COA
        // lain di DynamicMenuRegistry).
        $features = Mockery::mock(FeatureResolver::class);
        $features->shouldReceive('enabled')->once()->with('finance.accounting')->andReturnTrue();
        $this->app->instance(FeatureResolver::class, $features);

        Config::set('datasource.driver', 'eloquent');
        // TX-ISO: WorkflowEngine tetap menulis audit JSON di mode Eloquent;
        // tanpa fake ini, payOrder() di bawah menulis ke storage/app/json
        // NYATA dan mencemari fixture demo company lain (residu terbukti:
        // storage/app/json/1/workflow_log.json bertambah entri preset
        // bengkel + journal.post setiap full suite dijalankan).
        Storage::fake('company-json');
        WorkflowDefinition::create([
            'company_id' => $company->id,
            'entity' => 'orders',
            'version' => 1,
            'is_active' => true,
            'definition' => [
                'stages' => [
                    ['code' => 'siap_diambil', 'label' => 'Siap Diambil'],
                    ['code' => 'selesai', 'label' => 'Selesai'],
                ],
                'terminal' => ['selesai'],
                'transitions' => [[
                    'from' => 'siap_diambil',
                    'to' => 'selesai',
                    'roles' => ['staff'],
                    'effects' => ['journal.post'],
                ]],
            ],
        ]);

        $service = app(OrderService::class);

        $foreignCompany = Company::factory()->create();
        ChartOfAccount::create(['company_id' => $foreignCompany->id, 'account_code' => '1000', 'name' => 'Kas Asing', 'type' => 'asset', 'is_active' => true]);
        ChartOfAccount::create(['company_id' => $foreignCompany->id, 'account_code' => '4000', 'name' => 'Pendapatan Asing', 'type' => 'revenue', 'is_active' => true]);
        $cashAccount = ChartOfAccount::create(['company_id' => $company->id, 'account_code' => '1000', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        $revenueAccount = ChartOfAccount::create(['company_id' => $company->id, 'account_code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);

        $user = User::factory()->create([
            'current_company_id' => $company->id,
            'wa_number' => '6281234567890',
            'wa_is_verified' => true,
        ]);
        $company->update(['owner_user_id' => $user->id]);

        $order = $service->createOrder([
            'company_id' => $company->id,
            'business_identity_id' => $identity->id,
            'order_no' => 'ORD-JOURNAL-1',
            'stage' => 'siap_diambil',
            'lines' => [
                [
                    'description' => 'Item 1',
                    'qty' => 1,
                    'unit_price' => 100000,
                ],
            ],
        ]);

        $result = $service->payOrder($order, 'cash', 'staff');

        $this->assertEquals('transitioned', $result['status']);
        $this->assertEquals('siap_diambil', $result['from']);
        $this->assertEquals('selesai', $result['to']);
        $this->assertEquals('selesai', $order->refresh()->stage);

        $journalEffectResult = null;
        foreach ($result['effects'] as $effect) {
            if (($effect['effect'] ?? '') === 'journal.post') {
                $journalEffectResult = $effect;
                break;
            }
        }

        $this->assertNotNull($journalEffectResult, 'Efek journal.post tidak ditemukan dalam hasil workflow.');
        $this->assertDatabaseHas('accounting_journal_lines', [
            'company_id' => $company->id,
            'account_id' => $cashAccount->id,
        ]);
        $this->assertDatabaseHas('accounting_journal_lines', [
            'company_id' => $company->id,
            'account_id' => $revenueAccount->id,
        ]);
        $this->assertDatabaseMissing('accounting_journal_lines', [
            'company_id' => $company->id,
            'account_id' => 1,
        ]);
    }

    // ==== MP-02: checkoutPos() - integritas POS (stok + jurnal) ====

    public function test_checkout_pos_deducts_stock_and_posts_a_balanced_journal(): void
    {
        [$company, $identity, $item] = $this->posFixture();

        $order = app(OrderService::class)->checkoutPos([
            'company_id' => $company->id,
            'business_identity_id' => $identity->id,
            'order_no' => 'POS-1',
            'external_ref' => 'ref-pos-1',
            'stage' => null,
            'payment_method' => 'cash',
            'lines' => [[
                'item_id' => $item->id,
                'description' => $item->name,
                'qty' => 2,
                'unit_price' => 50000,
                'discount_amount' => 0,
            ]],
        ]);

        $this->assertNotNull($order->paid_at);
        $this->assertSame('cash', $order->payment_method);

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'direction' => 'out',
            'qty' => 2,
            'reason' => 'pos_sale',
            'reference_type' => Order::class,
            'reference_id' => $order->id,
        ]);

        $journal = AccountingJournal::where('reference', 'POS-1')->first();
        $this->assertNotNull($journal);
        $lines = $journal->lines;
        $this->assertEqualsWithDelta(0.0, (float) $lines->sum('debit') - (float) $lines->sum('credit'), 0.01);
    }

    public function test_negative_replay_does_not_deduct_stock_or_post_a_second_journal(): void
    {
        [$company, $identity, $item] = $this->posFixture();
        $request = [
            'company_id' => $company->id,
            'business_identity_id' => $identity->id,
            'order_no' => 'POS-REPLAY',
            'external_ref' => 'ref-replay',
            'stage' => null,
            'payment_method' => 'cash',
            'lines' => [[
                'item_id' => $item->id,
                'description' => $item->name,
                'qty' => 1,
                'unit_price' => 50000,
                'discount_amount' => 0,
            ]],
        ];

        $first = app(OrderService::class)->checkoutPos($request);
        $second = app(OrderService::class)->checkoutPos($request);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::where('external_ref', 'ref-replay')->count());
        $this->assertSame(1, StockMovement::where('item_id', $item->id)->where('direction', 'out')->count());
        $this->assertSame(1, AccountingJournal::where('reference', 'POS-REPLAY')->count());
    }

    public function test_negative_insufficient_stock_rejects_checkout_before_order_is_persisted(): void
    {
        [$company, $identity, $item] = $this->posFixture(stockQty: 1);

        try {
            app(OrderService::class)->checkoutPos([
                'company_id' => $company->id,
                'business_identity_id' => $identity->id,
                'order_no' => 'POS-INSUFFICIENT',
                'external_ref' => 'ref-insufficient',
                'stage' => null,
                'payment_method' => 'cash',
                'lines' => [[
                    'item_id' => $item->id,
                    'description' => $item->name,
                    'qty' => 5,
                    'unit_price' => 50000,
                    'discount_amount' => 0,
                ]],
            ]);
            $this->fail('Stok tidak cukup harus menolak checkout.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Insufficient stock', $exception->getMessage());
        }

        // Semua-atau-tidak-sama-sekali: order TIDAK boleh tersimpan sama
        // sekali, bukan tersimpan tanpa mutasi stok. Setup hanya menulis
        // movement `in` (addStock) - checkout yang ditolak tidak boleh
        // meninggalkan satu pun movement `out`.
        $this->assertSame(0, Order::where('external_ref', 'ref-insufficient')->count());
        $this->assertSame(0, StockMovement::where('item_id', $item->id)->where('direction', 'out')->count());
    }

    public function test_negative_item_belonging_to_another_company_is_rejected(): void
    {
        [$company, $identity] = $this->posFixture();
        $foreignCompany = Company::factory()->create();
        $foreignItem = Item::create([
            'company_id' => $foreignCompany->id,
            'name' => 'Barang Usaha Lain',
            'price' => 10000,
            'track_batches' => false,
        ]);

        try {
            app(OrderService::class)->checkoutPos([
                'company_id' => $company->id,
                'business_identity_id' => $identity->id,
                'order_no' => 'POS-FOREIGN',
                'external_ref' => 'ref-foreign',
                'stage' => null,
                'payment_method' => 'cash',
                'lines' => [[
                    'item_id' => $foreignItem->id,
                    'description' => $foreignItem->name,
                    'qty' => 1,
                    'unit_price' => 10000,
                    'discount_amount' => 0,
                ]],
            ]);
            $this->fail('Item milik company lain harus ditolak.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('tidak lagi tersedia', $exception->getMessage());
        }

        $this->assertSame(0, Order::where('external_ref', 'ref-foreign')->count());
    }

    public function test_negative_missing_chart_of_accounts_rejects_checkout_with_no_partial_state(): void
    {
        // Sama seperti fixture lain TAPI tanpa membuat akun 1000/4000.
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $identity = BusinessIdentity::factory()->create(['company_id' => $company->id, 'tax_rate' => 0, 'price_includes_tax' => false, 'tax_mode' => 'non_taxable']);
        $item = Item::create([
            'company_id' => $company->id,
            'name' => 'Barang Tanpa COA',
            'price' => 50000,
            'track_batches' => false,
        ]);
        app(StockService::class)->addStock($item, 10, 'purchase');

        Config::set('datasource.driver', 'eloquent');
        $features = Mockery::mock(FeatureResolver::class);
        $features->shouldReceive('enabled')->with('finance.accounting')->andReturnTrue();
        $this->app->instance(FeatureResolver::class, $features);

        try {
            app(OrderService::class)->checkoutPos([
                'company_id' => $company->id,
                'business_identity_id' => $identity->id,
                'order_no' => 'POS-NOCOA',
                'external_ref' => 'ref-nocoa',
                'stage' => null,
                'payment_method' => 'cash',
                'lines' => [[
                    'item_id' => $item->id,
                    'description' => $item->name,
                    'qty' => 1,
                    'unit_price' => 50000,
                    'discount_amount' => 0,
                ]],
            ]);
            $this->fail('Bagan Akun yang belum disiapkan harus menolak checkout.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Bagan Akun', $exception->getMessage());
        }

        // Order dan mutasi stok HARUS ikut batal - jurnal gagal di akhir
        // rantai tidak boleh meninggalkan order/stok yang sudah terlanjur jalan.
        $this->assertSame(0, Order::where('external_ref', 'ref-nocoa')->count());
        $this->assertSame(0, StockMovement::where('item_id', $item->id)->where('direction', 'out')->count());
    }

    /** @return array{0: Company, 1: BusinessIdentity, 2: Item} */
    private function posFixture(float $stockQty = 100): array
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $identity = BusinessIdentity::factory()->create([
            'company_id' => $company->id,
            'tax_rate' => 0,
            'price_includes_tax' => false,
            'tax_mode' => 'non_taxable',
        ]);

        $item = Item::create([
            'company_id' => $company->id,
            'name' => 'Ganti Oli',
            'price' => 50000,
            'track_batches' => false,
        ]);
        app(StockService::class)->addStock($item, $stockQty, 'purchase');

        ChartOfAccount::create(['company_id' => $company->id, 'account_code' => '1000', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        ChartOfAccount::create(['company_id' => $company->id, 'account_code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);

        Config::set('datasource.driver', 'eloquent');
        $features = Mockery::mock(FeatureResolver::class);
        $features->shouldReceive('enabled')->with('finance.accounting')->andReturnTrue();
        $this->app->instance(FeatureResolver::class, $features);

        return [$company, $identity, $item];
    }
}
