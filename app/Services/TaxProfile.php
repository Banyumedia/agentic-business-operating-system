<?php

namespace App\Services;

/**
 * Konfigurasi pajak dasar sebuah identitas usaha (D-03).
 *
 * `taxable` menentukan **penyajian**: bila false, kosakata pajak tidak boleh
 * dirender sama sekali (D-44) - bukan ditampilkan bernilai nol.
 */
class TaxProfile
{
    public function __construct(
        public readonly bool $taxable,
        public readonly bool $priceIncludesTax,
        public readonly float $rate,
    ) {}

    public static function nonTaxable(): self
    {
        return new self(taxable: false, priceIncludesTax: false, rate: 0.0);
    }
}
