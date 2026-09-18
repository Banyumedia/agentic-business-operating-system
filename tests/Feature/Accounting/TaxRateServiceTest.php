<?php

namespace Tests\Feature\Accounting;

use App\Services\Accounting\TaxRateService;
use Tests\TestCase;

class TaxRateServiceTest extends TestCase
{
    private TaxRateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TaxRateService;
    }

    public function test_non_taxable_mode_with_zero_rate(): void
    {
        $result = $this->service->calculateTax(
            grossAmount: 100000.0,
            rate: 0.0,
            priceIncludesTax: false
        );

        $this->assertEquals(100000.0, $result->dpp);
        $this->assertEquals(0.0, $result->tax);
        $this->assertEquals(100000.0, $result->grandTotal);
    }

    public function test_tax_exclusive(): void
    {
        $result = $this->service->calculateTax(
            grossAmount: 100000.0,
            rate: 0.11,
            priceIncludesTax: false
        );

        $this->assertEquals(100000.0, $result->dpp);
        $this->assertEquals(11000.0, $result->tax);
        $this->assertEquals(111000.0, $result->grandTotal);
    }

    public function test_tax_inclusive(): void
    {
        $result = $this->service->calculateTax(
            grossAmount: 111000.0,
            rate: 0.11,
            priceIncludesTax: true
        );

        $this->assertEquals(100000.0, $result->dpp);
        $this->assertEquals(11000.0, $result->tax);
        $this->assertEquals(111000.0, $result->grandTotal);
    }

    public function test_rate_normalization(): void
    {
        // Rate as whole number 11 instead of 0.11
        $result = $this->service->calculateTax(
            grossAmount: 100000.0,
            rate: 11.0,
            priceIncludesTax: false
        );

        $this->assertEquals(100000.0, $result->dpp);
        $this->assertEquals(11000.0, $result->tax);
        $this->assertEquals(111000.0, $result->grandTotal);

        // Inclusive test with normalized rate
        $resultInclusive = $this->service->calculateTax(
            grossAmount: 111000.0,
            rate: 11.0,
            priceIncludesTax: true
        );

        $this->assertEquals(100000.0, $resultInclusive->dpp);
        $this->assertEquals(11000.0, $resultInclusive->tax);
        $this->assertEquals(111000.0, $resultInclusive->grandTotal);
    }
}
