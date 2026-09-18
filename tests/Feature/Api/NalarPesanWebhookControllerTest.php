<?php

namespace Tests\Feature\Api;

use App\Models\BusinessIdentity;
use App\Models\Company;
use App\Models\Item;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class NalarPesanWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.env', 'testing');
        Config::set('services.nalarpesan.webhook_secret', 'test-secret');
    }

    protected function generateSignature(array $payload, string $secret): string
    {
        return hash_hmac('sha256', json_encode($payload), $secret);
    }

    public function test_rejects_invalid_signature(): void
    {
        $payload = [
            'external_ref' => 'REF-001',
            'business_identity_id' => 1,
            'company_id' => 1,
            'items' => [],
            'grand_total' => 100000.00,
        ];

        $response = $this->postJson('/api/webhooks/nalar-pesan', $payload, [
            'X-NalarPesan-Signature' => 'invalid-signature',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Invalid signature']);
    }

    public function test_rejects_unknown_business_identity(): void
    {
        $payload = [
            'external_ref' => 'REF-001',
            'business_identity_id' => 999,
            'company_id' => 999,
            'items' => [
                [
                    'item_id' => 1,
                    'quantity' => 1,
                    'unit_price' => 100000.00,
                ],
            ],
            'grand_total' => 100000.00,
        ];

        $signature = $this->generateSignature($payload, 'test-secret');

        $response = $this->postJson('/api/webhooks/nalar-pesan', $payload, [
            'X-NalarPesan-Signature' => $signature,
        ]);

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Business identity not found']);
    }

    public function test_processes_valid_webhook_and_creates_order(): void
    {
        $company = Company::factory()->create();
        $businessIdentity = BusinessIdentity::factory()->create(['company_id' => $company->id]);
        $item = new Item([
            'company_id' => $company->id,
            'name' => 'Test Item',
            'type' => 'product',
            'sku' => 'TEST-001',
            'base_price' => 100000,
            'is_active' => true,
        ]);
        $item->save();

        $payload = [
            'external_ref' => 'REF-001',
            'business_identity_id' => $businessIdentity->id,
            'company_id' => $company->id,
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 2,
                    'unit_price' => 100000.00,
                ],
            ],
            'grand_total' => 200000.00,
            'resource_id' => null,
        ];

        $signature = $this->generateSignature($payload, 'test-secret');

        $response = $this->postJson('/api/webhooks/nalar-pesan', $payload, [
            'X-NalarPesan-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'order_id']);

        $this->assertDatabaseHas('orders', [
            'company_id' => $company->id,
            'business_identity_id' => $businessIdentity->id,
            'stage' => 'open',
            'source' => 'nalar_pesan',
            'external_ref' => 'REF-001',
            'grand_total' => 200000.00,
        ]);

        $this->assertDatabaseHas('order_lines', [
            'item_id' => $item->id,
            'qty' => 2,
            'unit_price' => 100000.00,
            'line_total' => 200000.00,
        ]);
    }

    public function test_is_idempotent_for_existing_external_ref(): void
    {
        $company = Company::factory()->create();
        $businessIdentity = BusinessIdentity::factory()->create(['company_id' => $company->id]);

        $order = new Order([
            'company_id' => $company->id,
            'business_identity_id' => $businessIdentity->id,
            'order_no' => 'TEST-001',
            'stage' => 'open',
            'subtotal' => 100000,
            'grand_total' => 100000,
            'source' => 'nalar_pesan',
            'external_ref' => 'REF-002',
        ]);
        $order->save();

        $item = new Item([
            'company_id' => $company->id,
            'name' => 'Test Item Existing',
            'type' => 'product',
            'sku' => 'ITEM-EXISTING-001',
            'base_price' => 100000,
            'is_active' => true,
        ]);
        $item->save();

        $payload = [
            'external_ref' => 'REF-002',
            'business_identity_id' => $businessIdentity->id,
            'company_id' => $company->id,
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'unit_price' => 100000.00,
                ],
            ],
            'grand_total' => 100000.00,
        ];

        $signature = $this->generateSignature($payload, 'test-secret');

        $response = $this->postJson('/api/webhooks/nalar-pesan', $payload, [
            'X-NalarPesan-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Order already processed']);

        // Should only have 1 order with this external ref
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_rejects_when_webhook_secret_not_configured(): void
    {
        Config::set('services.nalarpesan.webhook_secret', '');

        $payload = [
            'external_ref' => 'REF-SECRET-001',
            'business_identity_id' => 1,
            'company_id' => 1,
            'items' => [
                [
                    'item_id' => 1,
                    'quantity' => 1,
                    'unit_price' => 100000.00,
                ],
            ],
            'grand_total' => 100000.00,
        ];

        $response = $this->postJson('/api/webhooks/nalar-pesan', $payload, [
            'X-NalarPesan-Signature' => $this->generateSignature($payload, 'test-secret'),
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Webhook secret not configured']);
    }

    public function test_rejects_item_from_other_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $businessIdentityA = BusinessIdentity::factory()->create(['company_id' => $companyA->id]);
        $itemB = new Item([
            'company_id' => $companyB->id,
            'name' => 'Foreign Item',
            'type' => 'product',
            'sku' => 'ITEM-FOREIGN-001',
            'base_price' => 100000,
            'is_active' => true,
        ]);
        $itemB->save();

        $payload = [
            'external_ref' => 'REF-CROSS-001',
            'business_identity_id' => $businessIdentityA->id,
            'company_id' => $companyA->id,
            'items' => [
                [
                    'item_id' => $itemB->id,
                    'quantity' => 1,
                    'unit_price' => 100000.00,
                ],
            ],
            'grand_total' => 100000.00,
        ];

        $signature = $this->generateSignature($payload, 'test-secret');

        $response = $this->postJson('/api/webhooks/nalar-pesan', $payload, [
            'X-NalarPesan-Signature' => $signature,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Invalid item reference for company']);
    }
}
