<?php

namespace App\Services\Accounting;

class TaxCalculationResult
{
    public function __construct(
        public readonly float $dpp,
        public readonly float $tax,
        public readonly float $grandTotal
    ) {}
}
