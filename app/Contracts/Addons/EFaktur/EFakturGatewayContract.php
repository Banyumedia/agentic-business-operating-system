<?php

namespace App\Contracts\Addons\EFaktur;

use App\Models\Order;

interface EFakturGatewayContract
{
    /**
     * Send order data to external e-Faktur/Coretax API.
     */
    public function submitInvoice(Order $order): array;
}
