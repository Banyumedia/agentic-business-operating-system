<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\EntityRepository;
use App\Livewire\Screens\ContractScreen;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T-43 (D-62): layar dokumen tagihan pelanggan.
 *
 * Yang dijaga: nilai tagihan selalu dihitung ulang dari barisnya lewat
 * `TaxRateService` (bukan dari angka yang dikirim klien), penomoran berjalan per
 * company, dan layar tetap fail-closed terhadap perpindahan company maupun
 * pencabutan capability.
 */
class ContractScreenTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/contract-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);

        Storage::fake('company-json');
        $this->useCompany('bengkel-arka', 'bengkel', ['tax_mode' => 'non_taxable']);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_invoice_total_is_computed_from_its_lines(): void
    {
        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->assertOk()
            ->call('create')
            ->set('form.title', 'Termin pertama')
            ->set('lines.0.description', 'Jasa perencanaan')
            ->set('lines.0.quantity', 2)
            ->set('lines.0.unit_price', 1500000)
            ->call('addLine')
            ->set('lines.1.description', 'Biaya survei')
            ->set('lines.1.quantity', 1)
            ->set('lines.1.unit_price', 250000)
            ->call('save')
            ->assertSet('failure', null);

        $invoice = $this->invoices()->all()[0];

        $this->assertSame(3250000.0, (float) $invoice['subtotal']);
        $this->assertSame(3250000.0, (float) $invoice['grand_total']);
        $this->assertSame('draft', $invoice['status']);
        $this->assertCount(2, $this->lines());
    }

    public function test_line_total_is_recomputed_and_client_sent_totals_are_ignored(): void
    {
        // Klien hanya mengirim keterangan, jumlah, dan harga. Tidak ada jalan
        // menitipkan `grand_total` sendiri.
        $component = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.title', 'Uji hitung')
            ->set('lines.0.description', 'Jasa')
            ->set('lines.0.quantity', 3)
            ->set('lines.0.unit_price', 111111.11);

        $component->call('save')->assertSet('failure', null);

        $invoice = $this->invoices()->all()[0];
        $this->assertSame(333333.33, (float) $invoice['grand_total']);
        $this->assertSame(333333.33, (float) $this->lines()[0]['line_total']);
    }

    public function test_tax_is_applied_from_the_company_profile_for_every_mode(): void
    {
        // Non-PKP: pajak nol, dasar pengenaan sama dengan subtotal.
        $this->assertSame(
            [1000000.0, 1000000.0, 0.0, 1000000.0],
            $this->totalsFor('non_taxable', 'INV-A'),
        );

        // PKP harga eksklusif: pajak ditambahkan di atas subtotal.
        $this->useCompany('bengkel-arka', 'bengkel', [
            'tax_mode' => 'taxable',
            'tax_rate' => 11,
            'price_includes_tax' => false,
        ]);
        $this->assertSame(
            [1000000.0, 1000000.0, 110000.0, 1110000.0],
            $this->totalsFor('eksklusif', 'INV-B'),
        );

        // PKP harga inklusif: harga sudah memuat pajak, total tetap sama dengan subtotal.
        $this->useCompany('bengkel-arka', 'bengkel', [
            'tax_mode' => 'taxable',
            'tax_rate' => 11,
            'price_includes_tax' => true,
        ]);
        [$subtotal, $dpp, $tax, $grandTotal] = $this->totalsFor('inklusif', 'INV-C');
        $this->assertSame(1000000.0, $subtotal);
        $this->assertSame(1000000.0, $grandTotal);
        $this->assertSame(900900.9, $dpp);
        // Pajak diambil sebagai selisih supaya total tidak bergeser.
        $this->assertSame(round($grandTotal - $dpp, 2), $tax);
    }

    public function test_numbering_runs_per_company(): void
    {
        $first = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->get('form')['number'];

        $this->saveInvoice('Pertama');

        $second = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->get('form')['number'];

        $this->assertStringEndsWith('0001', $first);
        $this->assertStringEndsWith('0002', $second);

        // Usaha lain punya urutannya sendiri, tidak melanjutkan milik orang lain.
        $this->useCompany('salon-ayu', 'bengkel', ['tax_mode' => 'non_taxable']);
        $other = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->get('form')['number'];
        $this->assertStringEndsWith('0001', $other);
    }

    public function test_negative_invoice_without_lines_is_rejected(): void
    {
        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.title', 'Tanpa rincian')
            ->set('lines.0.description', '')
            ->call('save')
            ->assertSet('failure', 'Setiap rincian wajib punya keterangan.');

        $this->assertSame([], $this->invoices()->all());
    }

    public function test_negative_line_with_zero_quantity_is_rejected(): void
    {
        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.title', 'Jumlah nol')
            ->set('lines.0.description', 'Jasa')
            ->set('lines.0.quantity', 0)
            ->set('lines.0.unit_price', 1000)
            ->call('save')
            ->assertSet('failure', 'Jumlah harus lebih dari nol dan harga tidak boleh negatif.');

        $this->assertSame([], $this->invoices()->all());
    }

    public function test_negative_project_outside_the_company_is_rejected(): void
    {
        $this->useCompany('salon-ayu', 'bengkel', ['tax_mode' => 'non_taxable']);
        app(EntityRepository::class)->for('salon-ayu', 'projects')
            ->save(['id' => 99, 'name' => 'Proyek Usaha Lain', 'stage' => 'survei']);
        $this->useCompany('bengkel-arka', 'bengkel', ['tax_mode' => 'non_taxable']);

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.title', 'Relasi asing')
            ->set('form.project_id', 99)
            ->set('lines.0.description', 'Jasa')
            ->set('lines.0.quantity', 1)
            ->set('lines.0.unit_price', 1000)
            ->call('save')
            ->assertSet('failure', 'Relasi yang dipilih tidak ditemukan pada usaha ini.');

        $this->assertSame([], $this->invoices()->all());
    }

    public function test_issued_invoice_can_no_longer_be_edited_or_issued_again(): void
    {
        $this->saveInvoice('Untuk diterbitkan');
        $id = (int) $this->invoices()->all()[0]['id'];

        $component = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('requestIssue', $id)
            ->call('issue')
            ->assertSet('failure', null);

        $this->assertSame('issued', $this->invoices()->find($id)['status']);

        $component->call('requestIssue', $id)->call('issue')
            ->assertSet('failure', 'Tagihan ini sudah diterbitkan.');

        $component->call('edit', $id)
            ->assertSet('failure', 'Tagihan yang sudah diterbitkan tidak dapat diubah.')
            ->assertSet('editing', false);
    }

    public function test_negative_draft_without_value_cannot_be_issued(): void
    {
        // Draf bernilai nol tidak boleh menjadi tagihan resmi.
        $this->invoices()->save([
            'number' => 'INV-ZERO',
            'title' => 'Nol',
            'status' => 'draft',
            'issue_date' => '2026-09-22',
            'grand_total' => 0,
        ]);
        $id = (int) $this->invoices()->all()[0]['id'];

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('requestIssue', $id)
            ->call('issue')
            ->assertSet('failure', 'Tagihan tanpa rincian atau bernilai nol tidak dapat diterbitkan.');

        $this->assertSame('draft', $this->invoices()->find($id)['status']);
    }

    public function test_outstanding_counts_issued_invoices_only(): void
    {
        $this->saveInvoice('Draf', 500000);
        $this->saveInvoice('Terbit', 750000);

        $issued = (int) $this->invoices()->all()[1]['id'];
        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('requestIssue', $issued)
            ->call('issue');

        $outstanding = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->viewData('outstanding');

        // Draf belum menagih apa pun, jadi tidak ikut dihitung.
        $this->assertSame(750000.0, $outstanding);
    }

    public function test_screen_fails_closed_when_the_company_changes_after_mount(): void
    {
        $component = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])->assertOk();

        app(CompanyContext::class)->setCurrent('salon-ayu');
        $component->call('$refresh')->assertForbidden();
    }

    public function test_screen_is_closed_when_the_capability_is_revoked(): void
    {
        $component = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])->assertOk();

        app(CompanySettingsStore::class)->update('bengkel-arka', static function (array $settings): array {
            $settings['features']['pos'] = false;
            $settings['features']['milestone_billing'] = false;

            return $settings;
        });

        $component->call('$refresh')->assertForbidden();
    }

    public function test_contract_sources_have_no_industry_branch_or_direct_database_access(): void
    {
        $source = implode("\n", [
            file_get_contents(app_path('Livewire/Screens/ContractScreen.php')),
            file_get_contents(resource_path('views/livewire/screens/contract.blade.php')),
        ]);

        $this->assertDoesNotMatchRegularExpression('/\b(?:bengkel|klinik|salon|laundry|apotek|kontraktor|agency)\b/i', $source);
        $this->assertStringNotContainsString('DB::', $source);
    }

    public function test_invoice_can_be_billed_from_a_project_milestone(): void
    {
        $milestoneId = $this->milestone('Termin 1 (30%)', 3000000, 5);

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.title', 'Termin pertama')
            ->set('form.milestone_id', $milestoneId)
            ->call('loadMilestone')
            ->assertSet('failure', null)
            ->assertSet('lines.0.description', 'Termin 1 (30%)')
            ->assertSet('lines.0.unit_price', 3000000.0)
            ->call('save')
            ->assertSet('failure', null);

        $invoice = $this->invoices()->all()[0];
        $this->assertSame(3000000.0, (float) $invoice['grand_total']);
        // Proyek termin ikut menempel supaya nilainya masuk laba-rugi proyek.
        $this->assertSame(5, (int) $invoice['project_id']);

        $milestone = $this->milestones()->find($milestoneId);
        $this->assertSame((int) $invoice['id'], (int) $milestone['customer_invoice_id']);
        $this->assertSame('invoiced', $milestone['status']);
    }

    public function test_negative_milestone_cannot_be_billed_twice(): void
    {
        $milestoneId = $this->milestone('Termin 1', 1000000, 5);

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.title', 'Tagihan pertama')
            ->set('form.milestone_id', $milestoneId)
            ->call('loadMilestone')
            ->call('save')
            ->assertSet('failure', null);

        // Percobaan kedua: termin sudah tertaut, jadi ditolak sebelum memuat.
        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.milestone_id', $milestoneId)
            ->call('loadMilestone')
            ->assertSet('failure', 'Termin ini sudah pernah ditagih.');

        $this->assertCount(1, $this->invoices()->all());
    }

    public function test_negative_milestone_of_another_company_is_rejected(): void
    {
        $this->useCompany('salon-ayu', 'bengkel', ['tax_mode' => 'non_taxable']);
        app(EntityRepository::class)->for('salon-ayu', 'project_milestones')->save([
            'id' => 77,
            'project_id' => 1,
            'name' => 'Termin Usaha Lain',
            'trigger_type' => 'manual',
            'amount' => 500000,
        ]);
        $this->useCompany('bengkel-arka', 'bengkel', ['tax_mode' => 'non_taxable']);

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.milestone_id', 77)
            ->call('loadMilestone')
            ->assertSet('failure', 'Termin tidak ditemukan pada usaha ini.');
    }

    public function test_billed_milestone_disappears_from_the_options(): void
    {
        $milestoneId = $this->milestone('Termin 1', 1000000, 5);
        $this->milestone('Termin 2', 2000000, 5);

        $before = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->viewData('milestones');
        $this->assertCount(2, $before);

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.title', 'Tagih termin 1')
            ->set('form.milestone_id', $milestoneId)
            ->call('loadMilestone')
            ->call('save');

        $after = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->viewData('milestones');

        $this->assertCount(1, $after);
        $this->assertArrayNotHasKey($milestoneId, $after);
    }

    public function test_invoice_can_be_billed_from_an_approved_quotation(): void
    {
        $quotationId = $this->quotation('PEN-001', 'Renovasi tahap awal', [
            ['description' => 'Bongkar dinding', 'quantity' => 1, 'unit_price' => 2000000, 'sort_order' => 0],
            ['description' => 'Pasang keramik', 'quantity' => 20, 'unit_price' => 150000, 'sort_order' => 1],
        ]);

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.quotation_id', $quotationId)
            ->call('loadQuotation')
            ->assertSet('failure', null)
            ->assertSet('lines.0.description', 'Bongkar dinding')
            ->assertSet('lines.1.description', 'Pasang keramik')
            ->call('save')
            ->assertSet('failure', null);

        $invoice = $this->invoices()->all()[0];

        // Judul dan total ikut dari penawaran, dihitung ulang dari barisnya.
        $this->assertSame('Renovasi tahap awal', $invoice['title']);
        $this->assertSame(5000000.0, (float) $invoice['grand_total']);
        $this->assertSame($quotationId, (int) $invoice['quotation_id']);
        $this->assertCount(2, $this->lines());
    }

    public function test_negative_quotation_cannot_be_billed_twice(): void
    {
        $quotationId = $this->quotation('PEN-002', 'Sekali saja', [
            ['description' => 'Jasa', 'quantity' => 1, 'unit_price' => 1000000, 'sort_order' => 0],
        ]);

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.quotation_id', $quotationId)
            ->call('loadQuotation')
            ->call('save')
            ->assertSet('failure', null);

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.quotation_id', $quotationId)
            ->call('loadQuotation')
            ->assertSet('failure', 'Penawaran ini sudah pernah ditagih.');

        $this->assertCount(1, $this->invoices()->all());
    }

    public function test_negative_quotation_without_lines_is_rejected(): void
    {
        $quotationId = $this->quotation('PEN-003', 'Kosong', []);

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.quotation_id', $quotationId)
            ->call('loadQuotation')
            ->assertSet('failure', 'Penawaran ini belum punya rincian.');
    }

    public function test_negative_quotation_of_another_company_is_rejected(): void
    {
        $this->useCompany('salon-ayu', 'bengkel', ['tax_mode' => 'non_taxable']);
        app(EntityRepository::class)->for('salon-ayu', 'quotations')
            ->save(['id' => 88, 'number' => 'PEN-LAIN', 'title' => 'Penawaran Usaha Lain']);
        $this->useCompany('bengkel-arka', 'bengkel', ['tax_mode' => 'non_taxable']);

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.quotation_id', 88)
            ->call('loadQuotation')
            ->assertSet('failure', 'Penawaran tidak ditemukan pada usaha ini.');
    }

    public function test_negative_staff_cannot_issue_an_invoice(): void
    {
        $this->saveInvoice('Disiapkan staf');
        $id = (int) $this->invoices()->all()[0]['id'];

        // Staf boleh menyiapkan draf, tapi menerbitkan adalah komitmen fiskal.
        session(['company_role' => 'staff']);

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('requestIssue', $id)
            ->call('issue')
            ->assertForbidden();

        $this->assertSame('draft', $this->invoices()->find($id)['status']);
    }

    public function test_negative_staff_cannot_record_a_payment(): void
    {
        $id = $this->issuedInvoice(250000);

        session(['company_role' => 'staff']);

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('requestPayment', $id)
            ->set('paymentAmount', '250000')
            ->call('recordPayment')
            ->assertForbidden();

        $this->assertSame([], $this->cashEntries());
        $this->assertSame('issued', $this->invoices()->find($id)['status']);
    }

    public function test_role_is_revalidated_when_it_changes_mid_session(): void
    {
        $id = $this->issuedInvoice(100000);
        $component = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('requestPayment', $id)
            ->set('paymentAmount', '100000');

        // Peran diperiksa saat aksi dijalankan, bukan saat komponen dipasang.
        session(['company_role' => 'staff']);

        $component->call('recordPayment')->assertForbidden();
        $this->assertSame([], $this->cashEntries());
    }

    public function test_overdue_invoice_is_flagged_with_its_age(): void
    {
        // Waktu dibekukan supaya umur tunggakan tidak bergantung jam mesin.
        $this->travelTo('2026-09-22 09:00:00');
        $this->issuedInvoiceWithDue(1000000, '2026-09-12');

        $component = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices']);
        $row = $component->viewData('rows')[0];

        $this->assertTrue($row['is_overdue']);
        $this->assertSame(10, $row['overdue_days']);
        $this->assertSame('2026-09-12', $row['due_date']);
        $this->assertSame(1, $component->viewData('overdueCount'));
        $this->assertSame(1000000.0, $component->viewData('overdueTotal'));
    }

    public function test_invoice_not_yet_due_is_not_flagged(): void
    {
        $this->travelTo('2026-09-22 09:00:00');
        $this->issuedInvoiceWithDue(1000000, '2026-10-30');

        $row = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->viewData('rows')[0];

        $this->assertFalse($row['is_overdue']);
        $this->assertSame(0, $row['overdue_days']);
    }

    public function test_invoice_without_a_due_date_is_never_overdue(): void
    {
        $this->issuedInvoiceWithDue(1000000, null);

        $row = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->viewData('rows')[0];

        $this->assertFalse($row['is_overdue']);
        $this->assertNull($row['due_date']);
    }

    public function test_settled_invoice_is_not_overdue_even_past_the_due_date(): void
    {
        $id = $this->issuedInvoiceWithDue(500000, '2026-09-01');

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('requestPayment', $id)
            ->set('paymentAmount', '500000')
            ->call('recordPayment');

        $component = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices']);

        // Sudah lunas: tanggalnya lewat tapi tidak ada lagi yang ditunggu.
        $this->assertFalse($component->viewData('rows')[0]['is_overdue']);
        $this->assertSame(0, $component->viewData('overdueCount'));
    }

    public function test_draft_is_never_overdue(): void
    {
        $this->invoices()->save([
            'number' => 'INV-DRAFT-DUE',
            'title' => 'Draf lewat tanggal',
            'status' => 'draft',
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-10',
            'grand_total' => 750000,
        ]);

        $row = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->viewData('rows')[0];

        // Draf belum menagih siapa pun, jadi tidak bisa menunggak.
        $this->assertFalse($row['is_overdue']);
    }

    public function test_oldest_overdue_invoice_is_listed_first(): void
    {
        $this->travelTo('2026-09-22 09:00:00');
        $this->issuedInvoiceWithDue(100000, '2026-09-20');
        $this->issuedInvoiceWithDue(200000, '2026-09-01');
        $this->issuedInvoiceWithDue(300000, null);

        $rows = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->viewData('rows');

        $this->assertSame('2026-09-01', $rows[0]['due_date']);
        $this->assertSame('2026-09-20', $rows[1]['due_date']);
        $this->assertNull($rows[2]['due_date']);
    }

    public function test_assistant_sop_does_not_promise_unimplemented_reminders(): void
    {
        // Janji yang tidak bisa ditepati sistem lebih buruk daripada tidak
        // menjanjikan apa pun (T-48; pengingat otomatis menunggu T-49).
        $source = file_get_contents(app_path('Livewire/Settings/AssistantSettings.php'));

        $this->assertStringNotContainsString('Berikan pengingat tagihan piutang H-1', $source);
        $this->assertStringContainsString('belum tersedia', $source);
    }

    public function test_payment_is_recorded_as_cash_in_linked_to_the_invoice(): void
    {
        app(EntityRepository::class)->for('bengkel-arka', 'projects')
            ->save(['id' => 5, 'name' => 'Proyek Uji', 'stage' => 'survei']);

        $id = $this->issuedInvoice(1000000, 5);

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('requestPayment', $id)
            ->set('paymentAmount', '400000')
            ->call('recordPayment')
            ->assertSet('failure', null);

        $entries = $this->cashEntries();

        $this->assertCount(1, $entries);
        $this->assertSame('in', $entries[0]['direction']);
        $this->assertSame(400000.0, (float) $entries[0]['amount']);
        $this->assertSame(5, (int) $entries[0]['project_id']);
        $this->assertSame('customer_invoice', $entries[0]['source_type']);
        $this->assertSame($id, (int) $entries[0]['source_id']);

        $invoice = $this->invoices()->find($id);
        $this->assertSame('partial', $invoice['status']);
        $this->assertSame(400000.0, (float) $invoice['paid_amount']);
    }

    public function test_negative_replayed_payment_does_not_duplicate_cash_or_paid_amount(): void
    {
        $id = $this->issuedInvoice(1000000);

        $component = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('requestPayment', $id)
            ->set('paymentAmount', '1000000')
            ->call('recordPayment')
            ->assertSet('failure', null);

        // Klik kedua pada tombol yang sama: tidak ada lagi yang tertunda.
        $component->call('recordPayment');

        $this->assertCount(1, $this->cashEntries());
        $invoice = $this->invoices()->find($id);
        $this->assertSame('paid', $invoice['status']);
        $this->assertSame(1000000.0, (float) $invoice['paid_amount']);
    }

    public function test_negative_overpayment_is_rejected(): void
    {
        $id = $this->issuedInvoice(1000000);

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('requestPayment', $id)
            ->set('paymentAmount', '1000000.01')
            ->call('recordPayment')
            ->assertSet('failure', 'Nominal pembayaran melebihi sisa tagihan.');

        $this->assertSame([], $this->cashEntries());
    }

    public function test_negative_paid_invoice_cannot_be_paid_again(): void
    {
        $id = $this->issuedInvoice(500000);

        $component = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('requestPayment', $id)
            ->set('paymentAmount', '500000')
            ->call('recordPayment');

        $component->call('requestPayment', $id)->assertSet('failure', 'Tagihan ini sudah lunas.');

        $this->assertCount(1, $this->cashEntries());
    }

    public function test_negative_draft_cannot_receive_payment(): void
    {
        $this->saveInvoice('Masih draf');
        $id = (int) $this->invoices()->all()[0]['id'];

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('requestPayment', $id)
            ->assertSet('failure', 'Terbitkan tagihan lebih dulu sebelum mencatat pembayaran.');

        $this->assertSame([], $this->cashEntries());
    }

    public function test_negative_non_numeric_payment_is_rejected(): void
    {
        $id = $this->issuedInvoice(100000);

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('requestPayment', $id)
            ->set('paymentAmount', 'seratus ribu')
            ->call('recordPayment')
            ->assertSet('failure', 'Nominal pembayaran harus berupa angka lebih dari nol.');

        $this->assertSame([], $this->cashEntries());
    }

    public function test_two_partial_payments_settle_the_invoice(): void
    {
        $id = $this->issuedInvoice(900000);
        $component = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices']);

        $component->call('requestPayment', $id)->set('paymentAmount', '400000')->call('recordPayment');
        $component->call('requestPayment', $id)->set('paymentAmount', '500000')->call('recordPayment')
            ->assertSet('failure', null);

        $this->assertCount(2, $this->cashEntries());

        $invoice = $this->invoices()->find($id);
        $this->assertSame('paid', $invoice['status']);
        // Terbayar dihitung ulang dari buku kas, bukan ditambahkan.
        $this->assertSame(900000.0, (float) $invoice['paid_amount']);
    }

    public function test_payment_action_fails_closed_after_the_company_changes(): void
    {
        $id = $this->issuedInvoice(100000);
        $component = Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('requestPayment', $id)
            ->set('paymentAmount', '100000');

        app(CompanyContext::class)->setCurrent('salon-ayu');
        $component->call('recordPayment')->assertForbidden();
    }

    /** @param list<array<string, mixed>> $lines */
    private function quotation(string $number, string $title, array $lines): int
    {
        $company = app(CompanyContext::class)->current();

        $saved = app(EntityRepository::class)->for($company, 'quotations')
            ->save(['number' => $number, 'title' => $title]);

        $repository = app(EntityRepository::class)->for($company, 'quotation_lines');
        foreach ($lines as $line) {
            $repository->save(array_replace($line, [
                'quotation_id' => (int) $saved['id'],
                'line_total' => (float) $line['quantity'] * (float) $line['unit_price'],
            ]));
        }

        return (int) $saved['id'];
    }

    private function issuedInvoiceWithDue(float $price, ?string $dueDate): int
    {
        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.title', 'Tagihan jatuh tempo')
            ->set('form.due_date', $dueDate)
            ->set('lines.0.description', 'Jasa')
            ->set('lines.0.quantity', 1)
            ->set('lines.0.unit_price', $price)
            ->call('save')
            ->assertSet('failure', null);

        $id = (int) collect($this->invoices()->all())->last()['id'];

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('requestIssue', $id)
            ->call('issue')
            ->assertSet('failure', null);

        return $id;
    }

    private function milestone(string $name, float $amount, int $projectId): int
    {
        app(EntityRepository::class)->for('bengkel-arka', 'projects')
            ->save(['id' => $projectId, 'name' => 'Proyek Uji', 'stage' => 'survei']);

        $saved = $this->milestones()->save([
            'project_id' => $projectId,
            'name' => $name,
            'trigger_type' => 'manual',
            'amount' => $amount,
            'status' => 'pending',
        ]);

        return (int) $saved['id'];
    }

    private function milestones(): EntityRepository
    {
        return app(EntityRepository::class)->for(app(CompanyContext::class)->current(), 'project_milestones');
    }

    private function issuedInvoice(float $price, ?int $projectId = null): int
    {
        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.title', 'Tagihan uji')
            ->set('form.project_id', $projectId)
            ->set('lines.0.description', 'Jasa')
            ->set('lines.0.quantity', 1)
            ->set('lines.0.unit_price', $price)
            ->call('save')
            ->assertSet('failure', null);

        $id = (int) collect($this->invoices()->all())->last()['id'];

        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('requestIssue', $id)
            ->call('issue')
            ->assertSet('failure', null);

        return $id;
    }

    /** @return list<array<string, mixed>> */
    private function cashEntries(): array
    {
        return app(EntityRepository::class)
            ->for(app(CompanyContext::class)->current(), 'cash_entries')
            ->all();
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} */
    private function totalsFor(string $mode, string $number): array
    {
        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.number', $number)
            ->set('form.title', 'Mode '.$mode)
            ->set('lines.0.description', 'Jasa')
            ->set('lines.0.quantity', 1)
            ->set('lines.0.unit_price', 1000000)
            ->call('save')
            ->assertSet('failure', null);

        $invoice = collect($this->invoices()->all())->firstWhere('number', $number);

        return [
            (float) $invoice['subtotal'],
            (float) $invoice['dpp'],
            (float) $invoice['tax'],
            (float) $invoice['grand_total'],
        ];
    }

    private function saveInvoice(string $title, float $price = 1000000): void
    {
        Livewire::test(ContractScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->call('create')
            ->set('form.title', $title)
            ->set('lines.0.description', 'Jasa')
            ->set('lines.0.quantity', 1)
            ->set('lines.0.unit_price', $price)
            ->call('save')
            ->assertSet('failure', null);
    }

    private function invoices(): EntityRepository
    {
        return app(EntityRepository::class)->for(app(CompanyContext::class)->current(), 'customer_invoices');
    }

    /** @return list<array<string, mixed>> */
    private function lines(): array
    {
        return app(EntityRepository::class)
            ->for(app(CompanyContext::class)->current(), 'customer_invoice_lines')
            ->all();
    }

    /** @param array<string, mixed> $identity */
    private function useCompany(string $company, string $preset, array $identity): void
    {
        Storage::disk('company-json')->put(
            "json/{$company}/settings.json",
            json_encode(['preset' => $preset], JSON_THROW_ON_ERROR),
        );
        Storage::disk('company-json')->put(
            "json/{$company}/business_identity.json",
            json_encode(
                array_merge(['id' => 1, 'name' => 'Usaha Uji', 'preset' => $preset], $identity),
                JSON_THROW_ON_ERROR,
            ),
        );

        app(CompanyContext::class)->setCurrent($company);

        // Aksi fiskal owner-only (T-50); fixture default bertindak sebagai owner.
        session(['company_role' => 'owner']);
    }
}
