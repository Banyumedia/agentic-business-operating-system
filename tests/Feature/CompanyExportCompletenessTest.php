<?php

namespace Tests\Feature;

use App\Jobs\BuildCompanyExport;
use App\Models\BusinessNote;
use App\Models\Company;
use App\Models\Contact;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

/**
 * T-55 (D-51): arsip portabilitas data harus memuat seluruh tabel tenant.
 *
 * Sebelumnya hanya empat berkas yang diekspor, sehingga data uang - termasuk
 * tagihan pelanggan dan entri kas - tertinggal di dalam sistem. Daftar tabel
 * sekarang diturunkan dari katalog schema, jadi test ini juga menjaga agar
 * entitas baru tidak diam-diam tertinggal.
 */
class CompanyExportCompletenessTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->company = Company::factory()->create(['owner_user_id' => $this->owner->id]);
    }

    protected function tearDown(): void
    {
        $dir = storage_path('app/exports/'.$this->company->id);
        if (is_dir($dir)) {
            foreach (glob($dir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }

        parent::tearDown();
    }

    public function test_archive_contains_every_company_scoped_entity_in_the_schema_catalog(): void
    {
        $this->runExport();

        $names = $this->archiveEntries();

        foreach ($this->expectedEntities() as $entity) {
            $this->assertContains(
                $entity.'.csv',
                $names,
                "Entitas ber-company_id tidak ikut terekspor: {$entity}"
            );
        }

        $this->assertContains('manifest.json', $names);
        $this->assertContains('identities.json', $names);
        $this->assertContains('settings.json', $names);
    }

    public function test_money_entities_added_in_fase_8_are_included_with_their_rows(): void
    {
        $invoice = CustomerInvoice::create([
            'company_id' => $this->company->id,
            'number' => 'INV-EXPORT-1',
            'title' => 'Tagihan terekspor',
            'status' => 'issued',
            'issue_date' => '2026-09-22',
            'grand_total' => 1250000,
        ]);
        CustomerInvoiceLine::create([
            'company_id' => $this->company->id,
            'customer_invoice_id' => $invoice->id,
            'description' => 'Jasa terekspor',
            'quantity' => 1,
            'unit_price' => 1250000,
            'line_total' => 1250000,
        ]);

        $this->runExport();

        $invoices = $this->archiveContent('customer_invoices.csv');
        $lines = $this->archiveContent('customer_invoice_lines.csv');

        $this->assertStringContainsString('INV-EXPORT-1', $invoices);
        $this->assertStringContainsString('1250000', $invoices);
        $this->assertStringContainsString('Jasa terekspor', $lines);

        $manifest = json_decode($this->archiveContent('manifest.json'), true);
        $this->assertSame(1, $manifest['entities']['customer_invoices']);
        $this->assertSame(1, $manifest['entities']['customer_invoice_lines']);
    }

    public function test_business_notes_are_included_without_editing_this_job(): void
    {
        // T-107(g): entitas baru harus ikut terekspor karena diturunkan dari
        // katalog schema (T-55), bukan karena job ini disunting untuknya.
        BusinessNote::create([
            'company_id' => $this->company->id,
            'title' => 'SOP Terekspor',
            'content' => 'Isi SOP yang harus ikut dalam arsip portabilitas data.',
            'author_type' => 'user',
            'created_by_user_id' => $this->owner->id,
        ]);

        $this->runExport();

        $notes = $this->archiveContent('business_notes.csv');
        $this->assertStringContainsString('SOP Terekspor', $notes);

        $manifest = json_decode($this->archiveContent('manifest.json'), true);
        $this->assertSame(1, $manifest['entities']['business_notes']);
    }

    public function test_business_notes_also_export_as_one_markdown_file_per_note_with_front_matter(): void
    {
        $note = BusinessNote::create([
            'company_id' => $this->company->id,
            'title' => 'SOP Cucian Ekspres',
            'content' => 'Langkah pertama, cek label perawatan pada pakaian.',
            'author_type' => 'user',
            'created_by_user_id' => $this->owner->id,
        ]);

        $this->runExport();

        $names = $this->archiveEntries();
        $expectedName = 'business_notes/'.$note->id.'-sop-cucian-ekspres.md';
        $this->assertContains($expectedName, $names);

        $markdown = $this->archiveContent($expectedName);
        $this->assertStringContainsString('---', $markdown);
        $this->assertStringContainsString('author_type: user', $markdown);
        $this->assertStringContainsString('Langkah pertama, cek label perawatan', $markdown);
    }

    public function test_negative_archive_contains_no_row_from_another_company(): void
    {
        $other = Company::factory()->create();

        Contact::create(['company_id' => $this->company->id, 'type' => 'customer', 'name' => 'Kontak Kita']);
        Contact::create(['company_id' => $other->id, 'type' => 'customer', 'name' => 'Kontak Tetangga']);

        CustomerInvoice::create([
            'company_id' => $other->id,
            'number' => 'INV-TETANGGA',
            'title' => 'Bukan milik kita',
            'status' => 'issued',
            'issue_date' => '2026-09-22',
            'grand_total' => 999,
        ]);

        $this->runExport();

        $this->assertStringContainsString('Kontak Kita', $this->archiveContent('contacts.csv'));
        $this->assertStringNotContainsString('Kontak Tetangga', $this->archiveContent('contacts.csv'));
        $this->assertStringNotContainsString('INV-TETANGGA', $this->archiveContent('customer_invoices.csv'));
    }

    public function test_negative_export_is_refused_for_a_user_who_does_not_own_the_company(): void
    {
        $stranger = User::factory()->create();

        dispatch_sync(new BuildCompanyExport((string) $this->company->id, $stranger->id));

        // Tidak ada arsip yang dibuat sama sekali.
        $this->assertFileDoesNotExist(storage_path('app/exports/'.$this->company->id.'/export.zip'));
    }

    /** @return list<string> */
    private function expectedEntities(): array
    {
        $entities = [];

        foreach (glob(database_path('schemas/*.schema.json')) ?: [] as $path) {
            $entity = str_replace('.schema.json', '', basename($path));
            $aliases = ['item_batches' => 'ItemBatch', 'cash_entries' => 'CashEntry', 'pos_shifts' => 'PosShift'];
            $class = 'App\\Models\\'.($aliases[$entity] ?? Str::studly(Str::singular($entity)));

            if (! class_exists($class)) {
                continue;
            }

            $table = (new $class)->getTable();
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            $entities[] = $entity;
        }

        $this->assertNotEmpty($entities);

        return $entities;
    }

    private function runExport(): void
    {
        dispatch_sync(new BuildCompanyExport((string) $this->company->id, $this->owner->id));
    }

    /** @return list<string> */
    private function archiveEntries(): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(storage_path('app/exports/'.$this->company->id.'/export.zip')) === true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        return $names;
    }

    private function archiveContent(string $name): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(storage_path('app/exports/'.$this->company->id.'/export.zip')) === true);
        $content = (string) $zip->getFromName($name);
        $zip->close();

        return $content;
    }
}
