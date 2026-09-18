<?php

namespace Tests\Feature;

use App\Models\BusinessIdentity;
use App\Models\Company;
use App\Models\Order;
use App\Models\Resource;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
