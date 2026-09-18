<?php

namespace App\Services\Addons\Marketplace;

use App\Contracts\Addons\Marketplace\MarketplaceOrderAdapterContract;
use App\Models\Order;
use Illuminate\Http\Request;

class DummyMarketplaceAdapter implements MarketplaceOrderAdapterContract
{
    public function handleWebhook(Request $request, string $companyId, string $identityId): ?Order
    {
        $payload = $request->json()->all();
        if (empty($payload['external_id'])) {
            return null;
        }

        // Idempotency check via external_ref
        $existing = Order::where('company_id', $companyId)
            ->where('external_ref', $payload['external_id'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $order = Order::create([
            'company_id' => $companyId,
            'business_identity_id' => $identityId,
            'order_no' => 'MKT-'.$payload['external_id'],
            'stage' => 'open',
            'subtotal' => $payload['total'] ?? 0,
            'dpp' => $payload['total'] ?? 0,
            'tax_amount' => 0,
            'grand_total' => $payload['total'] ?? 0,
            'source' => 'marketplace',
            'external_ref' => $payload['external_id'],
        ]);

        return $order;
    }
}
