<?php

namespace App\Services\Addons\EFaktur;

use App\Contracts\Addons\EFaktur\EFakturGatewayContract;
use App\Models\Order;

class DummyEFakturGateway implements EFakturGatewayContract
{
    public function submitInvoice(Order $order): array
    {
        return [
            'status' => 'terkirim',
            'nomor_seri_faktur_pajak' => '010.000-26.'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT),
            'npwp' => '01.234.567.8-901.000',
        ];
    }
}
