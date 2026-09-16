<?php

namespace Tests\Unit;

use App\Services\TaxRateService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaxRateServiceTest extends TestCase
{
    /** @return array<string, array{float, float, bool, float, float, float}> */
    public static function scenarios(): array
    {
        return [
            // Skenario 0 (REQUIREMENTS 1.1): non-PKP, tarif 0.
            'non-PKP tanpa pajak' => [305000.0, 0.0, false, 305000.0, 0.0, 305000.0],

            // Skenario A: harga belum termasuk PPN.
            'eksklusif bulat' => [1000000.0, 0.11, false, 1000000.0, 110000.0, 1110000.0],
            'eksklusif dengan pembulatan' => [333333.0, 0.11, false, 333333.0, 36666.63, 369999.63],

            // Skenario B: harga sudah termasuk PPN; total yang dibayar tidak bergeser.
            'inklusif bulat' => [1110000.0, 0.11, true, 1000000.0, 110000.0, 1110000.0],
            'inklusif dengan pembulatan' => [305000.0, 0.11, true, 274774.77, 30225.23, 305000.0],
        ];
    }

    #[DataProvider('scenarios')]
    public function test_calculates_every_documented_scenario(
        float $gross,
        float $rate,
        bool $includesTax,
        float $dpp,
        float $tax,
        float $grandTotal,
    ): void {
        $result = app(TaxRateService::class)->calculateTax($gross, $rate, $includesTax);

        $this->assertSame($dpp, $result->dpp);
        $this->assertSame($tax, $result->tax);
        $this->assertSame($grandTotal, $result->grandTotal);
    }

    public function test_inclusive_split_always_adds_back_to_the_amount_paid(): void
    {
        $service = app(TaxRateService::class);

        // Pembulatan tidak boleh membuat DPP + PPN menyimpang dari yang dibayar.
        foreach ([1.0, 99.99, 12345.67, 305000.0, 999999.99] as $gross) {
            $result = $service->calculateTax($gross, 0.11, true);

            $this->assertSame(round($gross, 2), $result->grandTotal);
            $this->assertSame(round($gross, 2), round($result->dpp + $result->tax, 2));
        }
    }

    public function test_percent_and_fraction_rates_are_treated_the_same(): void
    {
        $service = app(TaxRateService::class);

        $fraction = $service->calculateTax(1000000.0, 0.11, false);
        $percent = $service->calculateTax(1000000.0, 11.0, false);

        $this->assertSame($fraction->tax, $percent->tax);
        $this->assertSame(110000.0, $percent->tax);
    }

    public function test_invalid_money_or_rate_is_rejected(): void
    {
        $service = app(TaxRateService::class);

        foreach ([[-1.0, 0.11], [100.0, -0.11], [INF, 0.11], [100.0, NAN]] as [$gross, $rate]) {
            try {
                $service->calculateTax($gross, $rate, false);
                $this->fail('Nilai uang atau tarif tidak valid seharusnya ditolak.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
