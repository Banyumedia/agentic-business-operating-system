<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MP-03 (D-62): struk POS siap cetak, termal 58mm + faktur A4 dari satu
 * sumber data. Pola identik `InvoiceDocumentTest` (T-52): isolasi tenant
 * bukan hasil pemeriksaan tambahan, melainkan akibat repository yang selalu
 * ter-scope company aktif.
 */
class OrderDocumentTest extends TestCase
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

    public function test_paid_order_renders_its_receipt(): void
    {
        $id = $this->order('bengkel-arka', 'POS-DOC-1', paid: true);
        $this->line($id, 'Ganti oli', 2, 75000);

        $this->get("/app/pos/{$id}/print?company=bengkel-arka")
            ->assertOk()
            ->assertSee('POS-DOC-1')
            ->assertSee('Usaha Uji')
            ->assertSee('Jalan Uji 1')
            ->assertSee('Ganti oli')
            ->assertSee('150.000,00');
    }

    public function test_a4_format_shows_the_same_stored_total_as_the_thermal_default(): void
    {
        $id = $this->order('bengkel-arka', 'POS-DOC-A4', paid: true);
        $this->line($id, 'Ganti oli', 2, 75000);

        $this->get("/app/pos/{$id}/print?company=bengkel-arka")
            ->assertOk()
            ->assertSee('150.000,00');

        $this->get("/app/pos/{$id}/print?format=a4&company=bengkel-arka")
            ->assertOk()
            ->assertSee('POS-DOC-A4')
            ->assertSee('150.000,00');
    }

    public function test_negative_non_taxable_receipt_never_shows_tax_vocabulary(): void
    {
        $id = $this->order('bengkel-arka', 'POS-DOC-NONTAX', paid: true);
        $this->line($id, 'Ganti oli', 1, 150000);

        $this->get("/app/pos/{$id}/print?company=bengkel-arka")
            ->assertOk()
            ->assertDontSee('Dasar pengenaan')
            ->assertDontSee('Pajak')
            ->assertSee('Subtotal');
    }

    public function test_taxable_receipt_shows_tax_vocabulary(): void
    {
        // Kebalikan dari test di atas: usaha PKP HARUS tetap melihat kosakata
        // pajak, supaya penyembunyian non-PKP tidak diam-diam menghilangkannya
        // untuk semua orang.
        Storage::disk('company-json')->put(
            'json/bengkel-arka/business_identity.json',
            json_encode([
                'id' => 1,
                'name' => 'Usaha Uji',
                'preset' => 'bengkel',
                'tax_mode' => 'taxable',
                'tax_rate' => 11,
                'price_includes_tax' => false,
                'address' => 'Jalan Uji 1',
            ], JSON_THROW_ON_ERROR),
        );

        $id = $this->order('bengkel-arka', 'POS-DOC-TAX', paid: true);
        $this->line($id, 'Ganti oli', 1, 150000);

        $this->get("/app/pos/{$id}/print?company=bengkel-arka")
            ->assertOk()
            ->assertSee('Dasar pengenaan')
            ->assertSee('Pajak');
    }

    public function test_negative_unpaid_order_cannot_be_printed_as_proof_of_payment(): void
    {
        $id = $this->order('bengkel-arka', 'POS-DOC-UNPAID', paid: false);

        $this->get("/app/pos/{$id}/print?company=bengkel-arka")->assertForbidden();
    }

    public function test_negative_order_of_another_company_is_not_found(): void
    {
        $foreignId = $this->order('salon-ayu', 'POS-DOC-TETANGGA', paid: true);
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $this->get("/app/pos/{$foreignId}/print?company=bengkel-arka")->assertNotFound();
    }

    public function test_negative_unknown_order_is_not_found(): void
    {
        $this->get('/app/pos/4242/print?company=bengkel-arka')->assertNotFound();
    }

    private function order(string $company, string $orderNo, bool $paid): int
    {
        app(CompanyContext::class)->setCurrent($company);

        $saved = app(EntityRepository::class)->for($company, 'orders')->save([
            'business_identity_id' => 1,
            'order_no' => $orderNo,
            'subtotal' => 150000,
            'dpp' => 150000,
            'tax_amount' => 0,
            'grand_total' => 150000,
            'payment_method' => 'cash',
            'paid_at' => $paid ? now()->toIso8601String() : null,
            'source' => 'pos',
        ]);

        return (int) $saved['id'];
    }

    private function line(int $orderId, string $description, float $qty, float $price): void
    {
        app(EntityRepository::class)->for(app(CompanyContext::class)->current(), 'order_lines')->save([
            'order_id' => $orderId,
            'description' => $description,
            'qty' => $qty,
            'unit_price' => $price,
            'line_total' => $qty * $price,
        ]);
    }
}
