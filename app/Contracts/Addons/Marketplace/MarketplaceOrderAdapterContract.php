<?php

namespace App\Contracts\Addons\Marketplace;

use App\Models\Order;
use Illuminate\Http\Request;

interface MarketplaceOrderAdapterContract
{
    /**
     * Map a generic marketplace webhook payload to a local Order.
     * Must use idempotent external_ref to prevent duplicates.
     */
    public function handleWebhook(Request $request, string $companyId, string $identityId): ?Order;
}
