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
    }
}
