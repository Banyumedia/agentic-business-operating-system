<?php

namespace App\Services;

/**
 * Hasil perhitungan pajak satu dokumen.
 *
 * `grandTotal` adalah nilai yang dibayar pelanggan; `dpp` dasar pengenaan pajak;
 * `tax` selisihnya. Pada mode non-PKP `tax` selalu 0 dan `dpp` sama dengan
 * `grandTotal` (REQUIREMENTS 1 Skenario 0).
 */
class TaxCalculationResult
{
    public function __construct(
        public readonly float $dpp,
        public readonly float $tax,
        public readonly float $grandTotal,
    ) {}
}
