<?php

namespace Tests\Feature\Manufacturing;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Models\Company;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderLine;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
        $this->app->scoped(CompanyContext::class, EloquentCompanyContext::class);
        $this->app->scoped(CompanySettingsStore::class, EloquentCompanySettingsStore::class);
        $this->artisan('db:seed', ['--class' => 'BusinessPresetSeeder']);
    }

    public function test_production_order_lifecycle(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'custom',
        ]);
        app(CompanySettingsStore::class)->update($company->id, function ($settings) {
            $settings['business_preset'] = [
                'tier' => 'B',
                'capabilities' => ['manufacturing.production_order' => true, 'inventory' => true],
                'workflows' => [
                    'production_orders' => [
                        'transitions' => [
                            ['from' => 'draft', 'to' => 'produksi', 'roles' => ['owner']],
                            ['from' => 'produksi', 'to' => 'selesai', 'roles' => ['owner']],
                        ],
                        'terminal' => ['selesai'],
                    ],
                ],
            ];

            return $settings;
        });

        app(CompanyContext::class)->setCurrent($company->id);

        $product = Item::create(['company_id' => $company->id, 'name' => 'Prod', 'sku' => 'P1', 'type' => 'product', 'unit' => 'pcs', 'price' => 1000]);
        $component1 = Item::create(['company_id' => $company->id, 'name' => 'M1', 'sku' => 'M1', 'type' => 'material', 'unit' => 'pcs', 'price' => 100]);
        $component2 = Item::create(['company_id' => $company->id, 'name' => 'M2', 'sku' => 'M2', 'type' => 'material', 'unit' => 'pcs', 'price' => 200]);

        $po = ProductionOrder::factory()->create([
            'company_id' => $company->id,
            'item_id' => $product->id,
            'target_qty' => 10,
            'stage' => 'draft',
        ]);

        ProductionOrderLine::factory()->create([
            'company_id' => $company->id,
            'production_order_id' => $po->id,
            'component_item_id' => $component1->id,
            'planned_qty' => 20,
            'cost_per_unit' => 1500,
        ]);

        ProductionOrderLine::factory()->create([
            'company_id' => $company->id,
            'production_order_id' => $po->id,
            'component_item_id' => $component2->id,
            'planned_qty' => 5,
            'cost_per_unit' => 500,
        ]);

        $this->assertNull($po->started_at);
        $this->assertNull($po->completed_at);

        // State changes via HasWorkflow implementor method
        $po->setWorkflowStage('produksi');
        $this->assertNotNull($po->fresh()->started_at);

        $po->setWorkflowStage('selesai');
        $this->assertNotNull($po->fresh()->completed_at);

        $this->assertCount(2, $po->lines);
        $this->assertSame($product->id, $po->item->id);
    }
}
