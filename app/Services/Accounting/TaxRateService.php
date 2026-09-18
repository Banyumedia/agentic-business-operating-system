<?php

namespace App\Services\Accounting;

class TaxRateService
{
    public function calculateTax(float $grossAmount, float $rate, bool $priceIncludesTax): TaxCalculationResult
    {
        // Normalisasi $rate: cegah salah input pecahan vs persen
        $normalizedRate = $rate > 1.0 ? $rate / 100.0 : $rate;

        if (! $priceIncludesTax) {
            $dpp = $grossAmount;
            $tax = round($dpp * $normalizedRate, 2);

            return new TaxCalculationResult(dpp: $dpp, tax: $tax, grandTotal: round($dpp + $tax, 2));
        }

        $dpp = round($grossAmount / (1.0 + $normalizedRate), 2);
        $tax = round($grossAmount - $dpp, 2);

        return new TaxCalculationResult(dpp: $dpp, tax: $tax, grandTotal: $grossAmount);
    }
}
