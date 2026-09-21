<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\Schema\EntitySchema;
use App\Services\Schema\SchemaValidator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T-42 (D-62): lapisan data tagihan pelanggan.
 *
 * Ini tabel uang, jadi yang dijaga lebih dulu adalah perilaku menolaknya:
 * isolasi tenant, penomoran, batas nilai, dan presisi. Belum ada UI di task
 * ini - layarnya menyusul di T-43.
 */
class CustomerInvoiceDataLayerTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::factory()->create();
        $this->companyB = Company::factory()->create();
    }

    public function test_customer_invoice_is_a_separate_table_from_the_platform_invoice(): void
    {
        // D-62 berdiri di atas pemisahan ini. Kalau dua nama tabel ini pernah
        // menyatu, tagihan langganan platform akan tampil sebagai piutang tenant.
        $this->assertNotSame(
            (new CustomerInvoice)->getTable(),
            (new Invoice)->getTable(),
        );
        $this->assertSame('customer_invoices', (new CustomerInvoice)->getTable());

        // Kolom khas billing platform tidak boleh merembes ke tagihan tenant.
        $columns = array_keys(EntitySchema::load('customer_invoices')->properties());
        foreach (['company_membership_id', 'token_amount_granted', 'payment_url'] as $platformColumn) {
            $this->assertNotContains($platformColumn, $columns);
        }
    }

    public function test_negative_invoices_are_isolated_per_tenant(): void
    {
        $this->invoice($this->companyA, 'INV-A-001');
        $this->invoice($this->companyB, 'INV-B-001');

        $this->assertSame(1, CustomerInvoice::where('company_id', $this->companyA->id)->count());
        $this->assertSame(
            ['INV-A-001'],
            CustomerInvoice::where('company_id', $this->companyA->id)->pluck('number')->all(),
        );
        $this->assertSame(
            0,
            CustomerInvoice::where('company_id', $this->companyA->id)->where('number', 'INV-B-001')->count(),
        );
    }

    public function test_negative_invoice_lines_are_isolated_per_tenant(): void
    {
        $invoiceA = $this->invoice($this->companyA, 'INV-A-001');
        $invoiceB = $this->invoice($this->companyB, 'INV-B-001');

        $this->line($this->companyA, $invoiceA, 'Jasa desain');
        $this->line($this->companyB, $invoiceB, 'Jasa lain');

        $this->assertSame(1, CustomerInvoiceLine::where('company_id', $this->companyA->id)->count());
        $this->assertSame(
            ['Jasa desain'],
            CustomerInvoiceLine::where('company_id', $this->companyA->id)->pluck('description')->all(),
        );
    }

    public function test_negative_duplicate_number_within_one_company_is_rejected(): void
    {
        $this->invoice($this->companyA, 'INV-001');

        $this->expectException(QueryException::class);
        $this->invoice($this->companyA, 'INV-001');
    }

    public function test_the_same_number_in_another_company_is_accepted(): void
    {
        $this->invoice($this->companyA, 'INV-001');
        $this->invoice($this->companyB, 'INV-001');

        // Penomoran milik masing-masing usaha (D-26); bukan urutan global.
        $this->assertSame(2, CustomerInvoice::where('number', 'INV-001')->count());
    }

    public function test_negative_invoice_lines_are_removed_when_the_invoice_is_deleted(): void
    {
        $invoice = $this->invoice($this->companyA, 'INV-001');
        $this->line($this->companyA, $invoice, 'Baris satu');
        $this->line($this->companyA, $invoice, 'Baris dua');

        $invoice->delete();

        // Tidak boleh ada baris menggantung tanpa dokumen induknya.
        $this->assertSame(0, CustomerInvoiceLine::where('customer_invoice_id', $invoice->id)->count());
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('rejectedValues')]
    public function test_negative_schema_rejects_invalid_money_and_required_fields(array $override, string $expected): void
    {
        $validator = app(SchemaValidator::class);
        $schema = EntitySchema::load('customer_invoices');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/'.preg_quote($expected, '/').'/');

        $validator->validate($schema, array_replace([
            'id' => 1,
            'number' => 'INV-001',
            'title' => 'Tagihan uji',
            'issue_date' => '2026-09-22',
            'grand_total' => 1000,
        ], $override));
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function rejectedValues(): array
    {
        return [
            'grand_total negatif' => [['grand_total' => -1], 'grand_total'],
            'paid_amount negatif' => [['paid_amount' => -0.01], 'paid_amount'],
            'subtotal negatif' => [['subtotal' => -5000], 'subtotal'],
            'status di luar enum' => [['status' => 'lunas'], 'status'],
            'nomor hilang' => [['number' => null], 'number'],
            'tanggal terbit hilang' => [['issue_date' => null], 'issue_date'],
        ];
    }

    public function test_money_keeps_two_decimal_precision_on_a_database_round_trip(): void
    {
        // Nilai besar yang realistis untuk satu tagihan (belasan miliar
        // rupiah) beserta sen terkecil.
        //
        // Batas mutlak DECIMAL(18,2) sengaja TIDAK diuji lewat round-trip:
        // dev/test memakai SQLite yang menyimpan kolom decimal sebagai REAL,
        // jadi di atas ~13 digit signifikan sen mulai hilang ke mantissa float
        // (terukur: 1234567890123.45 kembali sebagai 1234567890123.40). Itu
        // batas SQLite, bukan batas schema. Penjagaan pada kapasitas kolom
        // dilakukan validator (test berikutnya); paritas MySQL urusan T-21b.
        $invoice = $this->invoice($this->companyA, 'INV-BIG', [
            'subtotal' => '12345678901.45',
            'grand_total' => '12345678901.45',
            'paid_amount' => '0.01',
        ]);

        $fresh = CustomerInvoice::findOrFail($invoice->id);

        $this->assertSame('12345678901.45', (string) $fresh->grand_total);
        $this->assertSame('12345678901.45', (string) $fresh->subtotal);
        $this->assertSame('0.01', (string) $fresh->paid_amount);
    }

    public function test_negative_schema_rejects_amounts_beyond_the_column_capacity(): void
    {
        $validator = app(SchemaValidator::class);
        $schema = EntitySchema::load('customer_invoices');

        $row = $validator->validate($schema, [
            'id' => 1,
            'number' => 'INV-MAX',
            'title' => 'Batas kolom',
            'issue_date' => '2026-09-22',
            'grand_total' => 9999999999999999,
        ]);
        $this->assertSame(9999999999999999, $row['grand_total']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Presisi field tidak valid: grand_total');
        $validator->validate($schema, [
            'id' => 2,
            'number' => 'INV-OVER',
            'title' => 'Lewat batas',
            'issue_date' => '2026-09-22',
            'grand_total' => 99999999999999999,
        ]);
    }

    public function test_invoice_may_stand_alone_or_attach_to_a_project(): void
    {
        $project = Project::factory()->create(['company_id' => $this->companyA->id]);

        $standalone = $this->invoice($this->companyA, 'INV-001');
        $attached = $this->invoice($this->companyA, 'INV-002', ['project_id' => $project->id]);

        // Keduanya sah menurut D-62.
        $this->assertNull($standalone->fresh()->project_id);
        $this->assertSame($project->id, $attached->fresh()->project_id);
        $this->assertSame($project->id, $attached->project->id);
    }

    /** @param array<string, mixed> $override */
    private function invoice(Company $company, string $number, array $override = []): CustomerInvoice
    {
        return CustomerInvoice::create(array_replace([
            'company_id' => $company->id,
            'number' => $number,
            'title' => 'Tagihan '.$number,
            'status' => 'draft',
            'issue_date' => '2026-09-22',
            'grand_total' => 1000,
        ], $override));
    }

    private function line(Company $company, CustomerInvoice $invoice, string $description): CustomerInvoiceLine
    {
        return CustomerInvoiceLine::create([
            'company_id' => $company->id,
            'customer_invoice_id' => $invoice->id,
            'description' => $description,
            'quantity' => 1,
            'unit_price' => 1000,
            'line_total' => 1000,
        ]);
    }
}
