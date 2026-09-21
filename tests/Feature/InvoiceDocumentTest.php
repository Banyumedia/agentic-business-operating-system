<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * T-52 (D-62): dokumen tagihan siap cetak.
 *
 * Isolasi tenant di sini bukan hasil pemeriksaan tambahan, melainkan akibat
 * repository yang selalu ter-scope company aktif — id tagihan usaha lain
 * sederhananya tidak ditemukan.
 */
class InvoiceDocumentTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/doc-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);

        Storage::fake('company-json');
        foreach (['bengkel-arka', 'salon-ayu'] as $company) {
            Storage::disk('company-json')->put(
                "json/{$company}/settings.json",
                json_encode(['preset' => 'bengkel'], JSON_THROW_ON_ERROR),
            );
            Storage::disk('company-json')->put(
                "json/{$company}/business_identity.json",
                json_encode([
                    'id' => 1,
                    'name' => 'Usaha Uji',
                    'preset' => 'bengkel',
                    'tax_mode' => 'non_taxable',
                    'address' => 'Jalan Uji 1',
                ], JSON_THROW_ON_ERROR),
            );
        }

        app(CompanyContext::class)->setCurrent('bengkel-arka');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_issued_invoice_renders_its_document(): void
    {
        $id = $this->invoice('bengkel-arka', 'INV-DOC-1', 'issued');
        $this->line('bengkel-arka', $id, 'Jasa perbaikan', 2, 250000);

        $this->get("/app/invoices/{$id}/print?company=bengkel-arka")
            ->assertOk()
            ->assertSee('INV-DOC-1')
            ->assertSee('Usaha Uji')
            ->assertSee('Jalan Uji 1')
            ->assertSee('Jasa perbaikan')
            ->assertSee('500.000,00');
    }

    public function test_negative_draft_cannot_be_printed_as_an_official_document(): void
    {
        $id = $this->invoice('bengkel-arka', 'INV-DOC-DRAFT', 'draft');

        // Draf belum menagih siapa pun, jadi belum boleh dikirim ke pelanggan.
        $this->get("/app/invoices/{$id}/print?company=bengkel-arka")->assertForbidden();
    }

    public function test_negative_invoice_of_another_company_is_not_found(): void
    {
        $foreignId = $this->invoice('salon-ayu', 'INV-TETANGGA', 'issued');
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $this->get("/app/invoices/{$foreignId}/print?company=bengkel-arka")->assertNotFound();
    }

    public function test_negative_unknown_invoice_is_not_found(): void
    {
        $this->get('/app/invoices/4242/print?company=bengkel-arka')->assertNotFound();
    }

    private function invoice(string $company, string $number, string $status): int
    {
        app(CompanyContext::class)->setCurrent($company);

        $saved = app(EntityRepository::class)->for($company, 'customer_invoices')->save([
            'number' => $number,
            'title' => 'Tagihan dokumen',
            'status' => $status,
            'issue_date' => '2026-09-22',
            'due_date' => '2026-10-02',
            'subtotal' => 500000,
            'dpp' => 500000,
            'tax' => 0,
            'grand_total' => 500000,
            'paid_amount' => 0,
        ]);

        return (int) $saved['id'];
    }

    private function line(string $company, int $invoiceId, string $description, float $qty, float $price): void
    {
        app(EntityRepository::class)->for($company, 'customer_invoice_lines')->save([
            'customer_invoice_id' => $invoiceId,
            'description' => $description,
            'quantity' => $qty,
            'unit_price' => $price,
            'line_total' => $qty * $price,
        ]);
    }
}
