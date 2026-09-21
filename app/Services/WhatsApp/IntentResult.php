<?php

namespace App\Services\WhatsApp;

class IntentResult
{
    public function __construct(
        public readonly string $intent, // 'reminder', 'report', 'approval', 'setup', 'fallback'
        public readonly array $parameters = [],
        public readonly float $confidence = 1.0,
    ) {}
}
