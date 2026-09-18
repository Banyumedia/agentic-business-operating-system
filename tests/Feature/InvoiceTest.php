<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_creation_and_relations(): void
    {
        $company = Company::factory()->create();

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'type' => 'topup',
            'token_amount_granted' => 10000,
        ]);

        $this->assertEquals(1, Invoice::count());
        $this->assertEquals($company->id, $invoice->company->id);
        $this->assertEquals('topup', $invoice->type);
        $this->assertEquals('pending', $invoice->payment_status);
    }
}
