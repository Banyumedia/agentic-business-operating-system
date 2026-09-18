<?php

namespace App\Contracts;

interface HermesNodeClient
{
    public function sendWhatsApp(string $waNumber, string $message): bool;
}
