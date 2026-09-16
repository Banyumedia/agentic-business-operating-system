<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Perhitungan PPN inklusif/eksklusif sesuai `docs/REQUIREMENTS.md` 1.2-1.3.
 *
 * Mode non-PKP tidak punya cabang tersendiri: ia hanya memanggil jalur
 * eksklusif dengan `rate = 0` supaya alur kode seragam. Yang berbeda antara PKP
 * dan non-PKP adalah **penyajian** (D-44), bukan perhitungan.
 */
class TaxRateService
{
    public function calculateTax(float $grossAmount, float $rate, bool $priceIncludesTax): TaxCalculationResult
    {
        if (! is_finite($grossAmount) || $grossAmount < 0) {
            throw new InvalidArgumentException('Nilai transaksi tidak valid.');
        }

        if (! is_finite($rate) || $rate < 0) {
            throw new InvalidArgumentException('Tarif pajak tidak valid.');
        }

        // Normalisasi: 11 dan 11.0 diperlakukan sama dengan 0.11 supaya salah
        // input persen vs pecahan tidak menggandakan pajak sebelas kali.
        $normalizedRate = $rate > 1.0 ? $rate / 100.0 : $rate;

        if (! $priceIncludesTax) {
            $dpp = $grossAmount;
            $tax = round($dpp * $normalizedRate, 2);

            return new TaxCalculationResult(
                dpp: round($dpp, 2),
                tax: $tax,
                grandTotal: round($dpp + $tax, 2),
            );
        }

        $dpp = round($grossAmount / (1.0 + $normalizedRate), 2);

        return new TaxCalculationResult(
            dpp: $dpp,
            // Diambil sebagai selisih, bukan dihitung ulang, agar total yang
            // dibayar pelanggan tidak bergeser karena pembulatan.
            tax: round($grossAmount - $dpp, 2),
            grandTotal: round($grossAmount, 2),
        );
    }
}
