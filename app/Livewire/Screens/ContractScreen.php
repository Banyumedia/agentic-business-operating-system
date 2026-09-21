<?php

namespace App\Livewire\Screens;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Services\BusinessIdentityStore;
use App\Services\CompanyRoleResolver;
use App\Services\DynamicMenuRegistry;
use App\Services\Schema\EntitySchema;
use App\Services\TaxRateService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pola layar dokumen tagihan (`contract`).
 *
 * Entitasnya datang dari registry, jadi layar ini tidak menyebut satu pun nama
 * industri maupun nama entitas di dalam kodenya (D-31). Nilai tagihan selalu
 * dihitung ulang dari barisnya lalu dilewatkan `TaxRateService` dengan profil
 * pajak company - tidak ada rumus pajak di layar, dan total yang dikirim klien
 * tidak pernah dipercaya.
 *
 * Pencatatan pembayaran menyusul di T-44; di sini tagihan berhenti pada
 * penerbitan.
 */
class ContractScreen extends Component
{
    /** Batas presisi aman sebelum float mulai kehilangan sen. */
    private const MAX_SAFE_MONEY = 999999999999.99;

    /** Entitas baris menuruti konvensi `{entity}_lines`. */
    private const LINE_SUFFIX = '_lines';

    #[Locked]
    public string $module;

    #[Locked]
    public ?string $submodule = null;

    #[Locked]
    public string $company;

    public bool $editing = false;

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array<string, mixed>> */
    public array $lines = [];

    public ?int $pendingIssueId = null;

    #[Locked]
    public ?int $pendingPaymentId = null;

    public string $paymentAmount = '';

    public ?string $notice = null;

    public ?string $failure = null;

    public function mount(string $module, ?string $submodule = null): void
    {
        $this->module = $module;
        $this->submodule = $submodule;
        $this->company = app(CompanyContext::class)->current();
    }

    public function create(): void
    {
        $this->resetFeedback();
        $this->editing = true;
        $this->editingId = null;
        $this->form = [
            'number' => $this->nextNumber(),
            'title' => '',
            'issue_date' => now()->toDateString(),
            'due_date' => null,
            'contact_id' => null,
            'project_id' => null,
            'notes' => null,
        ];
        $this->form['milestone_id'] = null;
        $this->form['quotation_id'] = null;
        $this->lines = [$this->blankLine()];
    }

    /**
     * Memuat satu termin proyek menjadi rincian tagihan.
     *
     * Nilai yang dipakai adalah nilai termin di basis data, bukan angka dari
     * klien, dan termin yang sudah pernah ditagih ditolak supaya satu termin
     * tidak bisa ditagih dua kali.
     */
    public function loadMilestone(): void
    {
        $this->resetFeedback();

        $id = $this->form['milestone_id'] ?? null;
        if ($id === null || $id === '') {
            return;
        }

        $milestone = $this->milestoneRepository()->find((int) $id);

        if ($milestone === null) {
            $this->failure = 'Termin tidak ditemukan pada usaha ini.';
            $this->form['milestone_id'] = null;

            return;
        }

        if (($milestone['customer_invoice_id'] ?? null) !== null) {
            $this->failure = 'Termin ini sudah pernah ditagih.';
            $this->form['milestone_id'] = null;

            return;
        }

        $this->form['project_id'] = $milestone['project_id'] ?? $this->form['project_id'] ?? null;
        $this->lines = [[
            'description' => (string) ($milestone['name'] ?? 'Termin'),
            'quantity' => 1,
            'unit_price' => (float) ($milestone['amount'] ?? 0),
        ]];
        $this->notice = 'Rincian diisi dari termin.';
    }

    /**
     * Memuat penawaran yang sudah disetujui menjadi rincian tagihan.
     *
     * Barisnya disalin dari penawaran di basis data, bukan dari klien, dan
     * penawaran yang sudah pernah ditagih ditolak.
     */
    public function loadQuotation(): void
    {
        $this->resetFeedback();

        $id = $this->form['quotation_id'] ?? null;
        if ($id === null || $id === '') {
            return;
        }

        $quotation = $this->quotationRepository()->find((int) $id);

        if ($quotation === null) {
            $this->failure = 'Penawaran tidak ditemukan pada usaha ini.';
            $this->form['quotation_id'] = null;

            return;
        }

        if ($this->quotationIsBilled((int) $id)) {
            $this->failure = 'Penawaran ini sudah pernah ditagih.';
            $this->form['quotation_id'] = null;

            return;
        }

        $lines = $this->quotationLinesOf((int) $id);

        if ($lines === []) {
            $this->failure = 'Penawaran ini belum punya rincian.';

            return;
        }

        $this->form['project_id'] = $quotation['project_id'] ?? $this->form['project_id'] ?? null;
        $this->form['contact_id'] = $quotation['contact_id'] ?? $this->form['contact_id'] ?? null;
        $this->form['title'] = $this->form['title'] === ''
            ? (string) ($quotation['title'] ?? '')
            : $this->form['title'];

        $this->lines = array_map(static fn (array $line): array => [
            'description' => (string) ($line['description'] ?? ''),
            'quantity' => (float) ($line['quantity'] ?? 1),
            'unit_price' => (float) ($line['unit_price'] ?? 0),
        ], $lines);

        $this->notice = 'Rincian diisi dari penawaran.';
    }

    public function edit(int $id): void
    {
        $this->resetFeedback();
        $invoice = $this->repository()->find($id);

        if ($invoice === null) {
            $this->failure = 'Tagihan tidak ditemukan atau sudah dihapus.';

            return;
        }

        if (($invoice['status'] ?? 'draft') !== 'draft') {
            $this->failure = 'Tagihan yang sudah diterbitkan tidak dapat diubah.';

            return;
        }

        $this->editing = true;
        $this->editingId = $id;
        $this->form = [
            'number' => (string) ($invoice['number'] ?? ''),
            'title' => (string) ($invoice['title'] ?? ''),
            'issue_date' => (string) ($invoice['issue_date'] ?? now()->toDateString()),
            'due_date' => $invoice['due_date'] ?? null,
            'contact_id' => $invoice['contact_id'] ?? null,
            'project_id' => $invoice['project_id'] ?? null,
            'notes' => $invoice['notes'] ?? null,
        ];

        $lines = $this->linesOf($id);
        $this->lines = $lines === [] ? [$this->blankLine()] : array_map(static fn (array $line): array => [
            'description' => (string) ($line['description'] ?? ''),
            'quantity' => $line['quantity'] ?? 1,
            'unit_price' => $line['unit_price'] ?? 0,
        ], $lines);
    }

    public function addLine(): void
    {
        $this->resetFeedback();
        $this->lines[] = $this->blankLine();
    }

    public function removeLine(int $index): void
    {
        $this->resetFeedback();
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);

        if ($this->lines === []) {
            $this->lines = [$this->blankLine()];
        }
    }

    public function cancel(): void
    {
        $this->editing = false;
        $this->editingId = null;
        $this->form = [];
        $this->lines = [];
        $this->resetFeedback();
    }

    public function save(): void
    {
        $this->resetFeedback();

        try {
            $lines = $this->normalizedLines();
            $totals = $this->totals($lines);
        } catch (InvalidArgumentException $exception) {
            $this->failure = $exception->getMessage();

            return;
        }

        foreach (['contact_id', 'project_id'] as $reference) {
            $value = $this->form[$reference] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            if (! $this->referenceExists($reference, (int) $value)) {
                $this->failure = 'Relasi yang dipilih tidak ditemukan pada usaha ini.';

                return;
            }
        }

        $record = [
            'number' => trim((string) ($this->form['number'] ?? '')),
            'title' => trim((string) ($this->form['title'] ?? '')),
            'status' => 'draft',
            'issue_date' => (string) ($this->form['issue_date'] ?? now()->toDateString()),
            'due_date' => $this->nullIfBlank($this->form['due_date'] ?? null),
            'contact_id' => $this->nullIfBlank($this->form['contact_id'] ?? null, true),
            'project_id' => $this->nullIfBlank($this->form['project_id'] ?? null, true),
            'quotation_id' => $this->nullIfBlank($this->form['quotation_id'] ?? null, true),
            'notes' => $this->nullIfBlank($this->form['notes'] ?? null),
            'subtotal' => $totals['subtotal'],
            'dpp' => $totals['dpp'],
            'tax' => $totals['tax'],
            'grand_total' => $totals['grand_total'],
        ];

        if ($this->editingId !== null) {
            $existing = $this->repository()->find($this->editingId);
            if ($existing === null) {
                $this->failure = 'Tagihan tidak ditemukan atau sudah dihapus.';
                $this->cancel();

                return;
            }

            $record = array_replace($existing, $record);
        } else {
            $record['paid_amount'] = 0;
        }

        try {
            $saved = $this->repository()->save($record);
        } catch (InvalidArgumentException $exception) {
            $this->failure = $exception->getMessage();

            return;
        }

        $this->replaceLines((int) $saved['id'], $lines);
        $this->linkMilestone((int) $saved['id']);

        $this->notice = $this->editingId === null ? 'Tagihan tersimpan sebagai draf.' : 'Perubahan tagihan tersimpan.';
        $this->editing = false;
        $this->editingId = null;
        $this->form = [];
        $this->lines = [];
    }

    public function requestIssue(int $id): void
    {
        $this->resetFeedback();
        $this->pendingIssueId = $id;
    }

    public function cancelIssue(): void
    {
        $this->pendingIssueId = null;
    }

    /**
     * Menerbitkan tagihan. Sejak diterbitkan, dokumen berhenti menjadi draf dan
     * nilainya tidak lagi boleh berubah - itu yang membuat pencatatan pembayaran
     * di T-44 punya dasar yang stabil.
     */
    public function issue(): void
    {
        $this->resetFeedback();

        if ($this->pendingIssueId === null) {
            return;
        }

        $this->assertOwner();

        $invoice = $this->repository()->find($this->pendingIssueId);
        $this->pendingIssueId = null;

        if ($invoice === null) {
            $this->failure = 'Tagihan tidak ditemukan atau sudah dihapus.';

            return;
        }

        if (($invoice['status'] ?? 'draft') !== 'draft') {
            $this->failure = 'Tagihan ini sudah diterbitkan.';

            return;
        }

        if ($this->linesOf((int) $invoice['id']) === [] || (float) ($invoice['grand_total'] ?? 0) <= 0) {
            $this->failure = 'Tagihan tanpa rincian atau bernilai nol tidak dapat diterbitkan.';

            return;
        }

        $invoice['status'] = 'issued';
        $this->repository()->save($invoice);

        $this->notice = 'Tagihan diterbitkan.';
    }

    public function requestPayment(int $id): void
    {
        $this->resetFeedback();
        $invoice = $this->repository()->find($id);

        if ($invoice === null) {
            $this->failure = 'Tagihan tidak ditemukan atau sudah dihapus.';

            return;
        }

        if (($invoice['status'] ?? 'draft') === 'draft') {
            $this->failure = 'Terbitkan tagihan lebih dulu sebelum mencatat pembayaran.';

            return;
        }

        if ($this->outstandingOf($invoice) <= 0) {
            $this->failure = 'Tagihan ini sudah lunas.';

            return;
        }

        $this->pendingPaymentId = $id;
        $this->paymentAmount = (string) $this->outstandingOf($invoice);
    }

    public function cancelPayment(): void
    {
        $this->pendingPaymentId = null;
        $this->paymentAmount = '';
    }

    /**
     * Mencatat pembayaran sebagai uang masuk di buku kas.
     *
     * `paid_amount` pada tagihan TIDAK ditambahkan, melainkan dihitung ulang
     * dari jumlah entri kas yang menunjuk tagihan ini. Dengan begitu angka lunas
     * tidak bisa menyimpang dari uang yang benar-benar tercatat, bahkan bila
     * aksi ini terpanggil dua kali.
     */
    public function recordPayment(): void
    {
        $this->resetFeedback();

        if ($this->pendingPaymentId === null) {
            return;
        }

        $this->assertOwner();

        $id = $this->pendingPaymentId;
        $this->pendingPaymentId = null;

        $invoice = $this->repository()->find($id);

        if ($invoice === null) {
            $this->failure = 'Tagihan tidak ditemukan atau sudah dihapus.';

            return;
        }

        if (($invoice['status'] ?? 'draft') === 'draft') {
            $this->failure = 'Terbitkan tagihan lebih dulu sebelum mencatat pembayaran.';

            return;
        }

        $amount = $this->paymentAmount;
        $this->paymentAmount = '';

        if (! is_numeric($amount) || (float) $amount <= 0) {
            $this->failure = 'Nominal pembayaran harus berupa angka lebih dari nol.';

            return;
        }

        $amount = round((float) $amount, 2);
        $outstanding = $this->outstandingOf($invoice);

        if ($outstanding <= 0) {
            $this->failure = 'Tagihan ini sudah lunas.';

            return;
        }

        if ($amount > $outstanding) {
            $this->failure = 'Nominal pembayaran melebihi sisa tagihan.';

            return;
        }

        $this->cashRepository()->save([
            'entry_date' => now()->toDateString(),
            'direction' => 'in',
            'amount' => $amount,
            'category' => 'pembayaran tagihan',
            'description' => trim((string) ($invoice['number'] ?? '').' '.($invoice['title'] ?? '')),
            'contact_id' => $invoice['contact_id'] ?? null,
            'project_id' => $invoice['project_id'] ?? null,
            'source_type' => $this->singularEntity(),
            'source_id' => (int) $invoice['id'],
        ]);

        $paid = $this->paidTotalOf((int) $invoice['id']);
        $total = (float) ($invoice['grand_total'] ?? 0);

        $invoice['paid_amount'] = $paid;
        $invoice['status'] = $paid >= $total ? 'paid' : 'partial';
        $this->repository()->save($invoice);

        $this->notice = $invoice['status'] === 'paid'
            ? 'Pembayaran tercatat. Tagihan lunas.'
            : 'Pembayaran sebagian tercatat.';
    }

    /**
     * Umur tunggakan dalam hari.
     *
     * Nol berarti tidak menunggak - dan itu berlaku untuk tiga keadaan yang
     * berbeda: belum jatuh tempo, tidak punya tanggal jatuh tempo, atau sudah
     * tidak ada sisa yang harus dibayar. Draf juga tidak pernah menunggak karena
     * ia belum menagih siapa pun.
     */
    private function overdueDays(?string $dueDate, float $outstanding, string $status): int
    {
        if ($dueDate === null || $status === 'draft' || $outstanding <= 0) {
            return 0;
        }

        $due = CarbonImmutable::parse($dueDate)->startOfDay();
        $today = CarbonImmutable::now()->startOfDay();

        return $due->lessThan($today) ? (int) $due->diffInDays($today) : 0;
    }

    /** @param array<string, mixed> $invoice */
    private function outstandingOf(array $invoice): float
    {
        $total = (float) ($invoice['grand_total'] ?? 0);

        return round($total - $this->paidTotalOf((int) ($invoice['id'] ?? 0)), 2);
    }

    /**
     * Uang yang benar-benar masuk untuk tagihan ini, dibaca dari buku kas -
     * bukan dari kolom di tagihan. Buku kas adalah sumber kebenaran uang, dan
     * laba-rugi proyek (T-46) juga membaca dari sana.
     */
    private function paidTotalOf(int $invoiceId): float
    {
        $source = $this->singularEntity();
        $total = 0.0;

        foreach ($this->cashRepository()->all() as $entry) {
            if (($entry['source_type'] ?? null) !== $source
                || (int) ($entry['source_id'] ?? 0) !== $invoiceId
                || ($entry['direction'] ?? null) !== 'in') {
                continue;
            }

            $total += (float) ($entry['amount'] ?? 0);
        }

        return round($total, 2);
    }

    private function cashRepository(): EntityRepository
    {
        return app(EntityRepository::class)->for($this->company(), 'cash_entries');
    }

    public function render(): View
    {
        // Pemeriksaan company dipaku lebih dulu: kalau company aktif sudah
        // berpindah, layar harus menolak sebelum menyentuh preset company baru.
        $this->company();

        $definition = $this->definition();

        $invoices = $this->repository()->all();
        usort($invoices, static fn (array $left, array $right): int => [(string) ($right['issue_date'] ?? ''), $right['id'] ?? 0]
            <=> [(string) ($left['issue_date'] ?? ''), $left['id'] ?? 0]);

        $rows = [];
        $outstanding = 0.0;
        foreach ($invoices as $invoice) {
            $total = (float) ($invoice['grand_total'] ?? 0);
            // Nilai terbayar dibaca dari buku kas, bukan dari kolom tagihan.
            $paid = $this->paidTotalOf((int) $invoice['id']);
            $due = round($total - $paid, 2);

            if (($invoice['status'] ?? 'draft') !== 'draft') {
                $outstanding += max($due, 0);
            }

            $dueDate = $this->nullIfBlank($invoice['due_date'] ?? null);
            $age = $this->overdueDays($dueDate, $due, $invoice['status'] ?? 'draft');

            $rows[] = [
                'id' => (int) $invoice['id'],
                'number' => (string) ($invoice['number'] ?? ''),
                'title' => (string) ($invoice['title'] ?? ''),
                'status' => (string) ($invoice['status'] ?? 'draft'),
                'issue_date' => (string) ($invoice['issue_date'] ?? ''),
                'due_date' => $dueDate,
                'grand_total' => $total,
                'paid_amount' => $paid,
                'outstanding' => $due,
                'is_draft' => ($invoice['status'] ?? 'draft') === 'draft',
                'overdue_days' => $age,
                'is_overdue' => $age > 0,
            ];
        }

        // Yang paling lama menunggak naik ke atas. Sisanya nilai bandingnya sama
        // sehingga urutan tanggal terbit di atas tetap terjaga (sort PHP 8 stabil).
        // Tanpa ini piutang tertua justru paling mudah terlewat.
        usort($rows, static fn (array $left, array $right): int => $right['overdue_days'] <=> $left['overdue_days']);

        return view('livewire.screens.contract', [
            'label' => $definition['label'],
            'term' => $definition['term'] ?? $definition['label'],
            'rows' => $rows,
            'outstanding' => round($outstanding, 2),
            'overdueTotal' => round(array_sum(array_map(
                static fn (array $row): float => $row['is_overdue'] ? $row['outstanding'] : 0.0,
                $rows,
            )), 2),
            'overdueCount' => count(array_filter($rows, static fn (array $row): bool => $row['is_overdue'])),
            'totals' => $this->previewTotals(),
            'contacts' => $this->referenceOptions('contacts'),
            'projects' => $this->referenceOptions('projects'),
            'milestones' => $this->milestoneOptions(),
            'quotations' => $this->quotationOptions(),
            'contactLabel' => term('contacts'),
            'projectLabel' => term('projects'),
            'pendingIssue' => $this->pendingIssueId === null ? null : $this->repository()->find($this->pendingIssueId),
            'pendingPayment' => $this->pendingPaymentId === null ? null : $this->repository()->find($this->pendingPaymentId),
        ]);
    }

    /**
     * Total untuk ditampilkan saat form terbuka. Kegagalan hitung tidak boleh
     * meledakkan render; form harus tetap bisa diperbaiki operator.
     *
     * @return array{subtotal: float|int, dpp: float|int, tax: float|int, grand_total: float|int}
     */
    private function previewTotals(): array
    {
        if (! $this->editing) {
            return ['subtotal' => 0, 'dpp' => 0, 'tax' => 0, 'grand_total' => 0];
        }

        try {
            return $this->totals($this->normalizedLines(false));
        } catch (InvalidArgumentException) {
            return ['subtotal' => 0, 'dpp' => 0, 'tax' => 0, 'grand_total' => 0];
        }
    }

    /**
     * Baris yang sudah dibersihkan. Nilai baris selalu dihitung ulang di sini,
     * jadi total yang dikirim klien tidak berpengaruh.
     *
     * @return list<array{description: string, quantity: float|int, unit_price: float|int, line_total: float|int, sort_order: int}>
     */
    private function normalizedLines(bool $strict = true): array
    {
        $lines = [];

        foreach ($this->lines as $index => $line) {
            $description = trim((string) ($line['description'] ?? ''));
            $quantity = $line['quantity'] ?? null;
            $unitPrice = $line['unit_price'] ?? null;

            if ($description === '' && ($quantity === null || $quantity === '') && ($unitPrice === null || $unitPrice === '')) {
                continue;
            }

            if ($description === '') {
                if (! $strict) {
                    continue;
                }

                throw new InvalidArgumentException('Setiap rincian wajib punya keterangan.');
            }

            if (! is_numeric($quantity) || ! is_numeric($unitPrice)) {
                if (! $strict) {
                    continue;
                }

                throw new InvalidArgumentException('Jumlah dan harga rincian wajib berupa angka.');
            }

            $quantity = (float) $quantity;
            $unitPrice = (float) $unitPrice;

            if ($quantity <= 0 || $unitPrice < 0) {
                if (! $strict) {
                    continue;
                }

                throw new InvalidArgumentException('Jumlah harus lebih dari nol dan harga tidak boleh negatif.');
            }

            $lines[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round($quantity * $unitPrice, 2),
                'sort_order' => $index,
            ];
        }

        if ($lines === [] && $strict) {
            throw new InvalidArgumentException('Tagihan wajib punya setidaknya satu rincian.');
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{subtotal: float|int, dpp: float|int, tax: float|int, grand_total: float|int}
     */
    private function totals(array $lines): array
    {
        $subtotal = array_sum(array_column($lines, 'line_total'));

        if (abs((float) $subtotal) > self::MAX_SAFE_MONEY) {
            throw new InvalidArgumentException('Nilai tagihan melampaui batas presisi aman.');
        }

        $profile = app(BusinessIdentityStore::class)->taxProfile($this->company());
        $result = app(TaxRateService::class)->calculateTax(
            round((float) $subtotal, 2),
            $profile->rate,
            $profile->priceIncludesTax,
        );

        return [
            'subtotal' => round((float) $subtotal, 2),
            'dpp' => $result->dpp,
            'tax' => $result->tax,
            'grand_total' => $result->grandTotal,
        ];
    }

    /**
     * Menautkan termin ke tagihan yang baru tersimpan. Satu termin hanya boleh
     * tertaut sekali; penautan kedua diabaikan tanpa mengubah apa pun.
     */
    private function linkMilestone(int $invoiceId): void
    {
        $id = $this->form['milestone_id'] ?? null;
        if ($id === null || $id === '') {
            return;
        }

        $repository = $this->milestoneRepository();
        $milestone = $repository->find((int) $id);

        if ($milestone === null || ($milestone['customer_invoice_id'] ?? null) !== null) {
            return;
        }

        $milestone['customer_invoice_id'] = $invoiceId;
        $milestone['status'] = 'invoiced';
        $repository->save($milestone);
    }

    /** Penawaran yang belum pernah ditagih pada company aktif. @return array<int, string> */
    private function quotationOptions(): array
    {
        $options = [];

        foreach ($this->quotationRepository()->all() as $quotation) {
            $id = (int) ($quotation['id'] ?? 0);
            if ($id === 0 || $this->quotationIsBilled($id)) {
                continue;
            }

            $number = (string) ($quotation['number'] ?? '#'.$id);
            $title = (string) ($quotation['title'] ?? '');

            $options[$id] = trim($number.' — '.$title, ' —');
        }

        return $options;
    }

    private function quotationIsBilled(int $quotationId): bool
    {
        foreach ($this->repository()->all() as $invoice) {
            if ((int) ($invoice['quotation_id'] ?? 0) === $quotationId) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array<string, mixed>> */
    private function quotationLinesOf(int $quotationId): array
    {
        $lines = array_values(array_filter(
            app(EntityRepository::class)->for($this->company(), 'quotation_lines')->all(),
            static fn (array $line): bool => (int) ($line['quotation_id'] ?? 0) === $quotationId,
        ));

        usort($lines, static fn (array $left, array $right): int => ($left['sort_order'] ?? 0) <=> ($right['sort_order'] ?? 0));

        return $lines;
    }

    private function quotationRepository(): EntityRepository
    {
        return app(EntityRepository::class)->for($this->company(), 'quotations');
    }

    /** Termin yang belum ditagih pada company aktif. @return array<int, string> */
    private function milestoneOptions(): array
    {
        $options = [];

        foreach ($this->milestoneRepository()->all() as $milestone) {
            if (($milestone['customer_invoice_id'] ?? null) !== null) {
                continue;
            }

            $id = (int) ($milestone['id'] ?? 0);
            if ($id === 0) {
                continue;
            }

            $name = (string) ($milestone['name'] ?? '#'.$id);
            $options[$id] = $name.' — '.number_format((float) ($milestone['amount'] ?? 0), 2, ',', '.');
        }

        return $options;
    }

    private function milestoneRepository(): EntityRepository
    {
        return app(EntityRepository::class)->for($this->company(), 'project_milestones');
    }

    /** @param list<array<string, mixed>> $lines */
    private function replaceLines(int $invoiceId, array $lines): void
    {
        $repository = $this->lineRepository();

        foreach ($this->linesOf($invoiceId) as $existing) {
            $repository->delete($existing['id']);
        }

        foreach ($lines as $line) {
            $repository->save(array_replace($line, [
                $this->lineForeignKey() => $invoiceId,
            ]));
        }
    }

    /** @return list<array<string, mixed>> */
    private function linesOf(int $invoiceId): array
    {
        $key = $this->lineForeignKey();

        $lines = array_values(array_filter(
            $this->lineRepository()->all(),
            static fn (array $line): bool => (int) ($line[$key] ?? 0) === $invoiceId,
        ));

        usort($lines, static fn (array $left, array $right): int => ($left['sort_order'] ?? 0) <=> ($right['sort_order'] ?? 0));

        return $lines;
    }

    /**
     * Nomor berikutnya dihitung per company dari nomor tertinggi yang sudah ada.
     * Keunikannya tetap dijaga indeks unik company-scoped di basis data.
     */
    private function nextNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ym').'-';
        $highest = 0;

        foreach ($this->repository()->all() as $invoice) {
            $number = (string) ($invoice['number'] ?? '');
            if (! str_starts_with($number, $prefix)) {
                continue;
            }

            $sequence = (int) substr($number, strlen($prefix));
            $highest = max($highest, $sequence);
        }

        return $prefix.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed> */
    private function blankLine(): array
    {
        return ['description' => '', 'quantity' => 1, 'unit_price' => 0];
    }

    private function nullIfBlank(mixed $value, bool $numeric = false): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $numeric ? (int) $value : $value;
    }

    /** @return array<int, string> */
    private function referenceOptions(string $entity): array
    {
        $options = [];

        foreach (app(EntityRepository::class)->for($this->company(), $entity)->all() as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id === 0) {
                continue;
            }

            $name = $row['name'] ?? null;
            $options[$id] = is_string($name) && trim($name) !== '' ? $name : '#'.$id;
        }

        return $options;
    }

    private function referenceExists(string $field, int $id): bool
    {
        $entity = $this->schemaReferences()[$field]['entity'] ?? null;

        if (! is_string($entity)) {
            return false;
        }

        return app(EntityRepository::class)->for($this->company(), $entity)->find($id) !== null;
    }

    /** @return array<string, array<string, mixed>> */
    private function schemaReferences(): array
    {
        return EntitySchema::load($this->definition()['entity'])->references();
    }

    /**
     * Entitas baris dan kunci asingnya diturunkan dari nama entitas dokumen
     * dalam bentuk tunggal: `customer_invoices` -> `customer_invoice_lines` dan
     * `customer_invoice_id`. Tidak ada nama entitas yang ditulis di layar ini.
     */
    private function lineEntity(): string
    {
        return $this->singularEntity().self::LINE_SUFFIX;
    }

    private function lineForeignKey(): string
    {
        return $this->singularEntity().'_id';
    }

    private function singularEntity(): string
    {
        return Str::singular($this->definition()['entity']);
    }

    private function repository(): EntityRepository
    {
        return app(EntityRepository::class)->for($this->company(), $this->definition()['entity']);
    }

    private function lineRepository(): EntityRepository
    {
        return app(EntityRepository::class)->for($this->company(), $this->lineEntity());
    }

    /** @return array{label: string, icon: string, route: string, screen: string, entity: string, term: string|null} */
    private function definition(): array
    {
        $registry = app(DynamicMenuRegistry::class);

        abort_unless($registry->hasPath($this->module, $this->submodule), 404);
        abort_unless($registry->isModuleVisible($this->module), 403);

        $definition = $registry->routeDefinition($this->module, $this->submodule);
        abort_if($definition === null, 403);

        return $definition;
    }

    private function company(): string
    {
        // Fail-closed: layar menolak melayani company yang berbeda dari saat mount.
        abort_unless(app(CompanyContext::class)->current() === $this->company, 403);

        return $this->company;
    }

    /**
     * Penerbitan dan pencatatan pembayaran adalah komitmen fiskal, jadi
     * owner-only dan diperiksa **server-side** - menyembunyikan tombol saja
     * tidak menghalangi pemanggilan aksi Livewire.
     *
     * Peran diambil dari sumber tepercaya (kepemilikan company terautentikasi),
     * bukan dari state komponen. Jalur session hanya berlaku pada fixture JSON
     * tanpa basis data, mengikuti pola `Settings`.
     */
    private function assertOwner(): void
    {
        $isOwner = auth()->check()
            ? app(CompanyRoleResolver::class)->isOwnerOfCompany($this->company())
            : app()->environment('testing') && session('company_role') === CompanyRoleResolver::ROLE_OWNER;

        abort_unless($isOwner, 403);
    }

    private function resetFeedback(): void
    {
        $this->notice = null;
        $this->failure = null;
    }
}
