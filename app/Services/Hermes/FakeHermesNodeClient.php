<?php

namespace App\Services\Hermes;

use App\Contracts\HermesNodeClient;
use Illuminate\Support\Facades\Log;

class FakeHermesNodeClient implements HermesNodeClient
{
    public function sendWhatsApp(string $waNumber, string $message): bool
    {
        Log::info(sprintf('FAKE WA to %s: %s', $waNumber, $message));

        return true;
    }
}
