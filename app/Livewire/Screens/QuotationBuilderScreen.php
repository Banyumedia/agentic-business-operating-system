<?php

namespace App\Livewire\Screens;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Contracts\PresetSource;
use App\Services\BusinessIdentityStore;
use App\Services\CompanyRoleResolver;
use App\Services\DynamicMenuRegistry;
use App\Services\TaxRateService;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Builder penawaran berbaris (RAB) + konversi ke proyek (MP-08).
 *
 * Pola baris/total ditiru persis dari `ContractScreen` (T-43): nilai selalu
 * dihitung ulang dari baris lewat `TaxRateService` dengan profil pajak
 * company - total yang dikirim klien tidak pernah dipercaya. Bukan dibangun
 * sebagai pola `contract` yang diperluas karena siklus hidupnya berbeda
 * (draft/sent/approved -> proyek, bukan draft/issued/paid) dan `quotations`
 * tidak punya konsep pembayaran sama sekali.
 *
 * Jalur penawaran -> tagihan (T-53) sudah ada di `ContractScreen` dan TIDAK
 * diduplikasi di sini - layar ini hanya menyediakan penyusunan baris dan
 * konversi ke proyek. `quotation.project_id` (kolom yang sudah ada sejak
 * migration awal) dipakai sebagai satu-satunya sumber kebenaran "sudah
 * dikonversi", diperiksa ulang tepat sebelum menulis (bukan hanya saat
 * menyusun pilihan) supaya pengiriman ganda tidak membuat dua proyek dari
 * satu penawaran.
 */
class QuotationBuilderScreen extends Component
{
    #[Locked]
    public string $module;

    #[Locked]
    public ?string $submodule = null;

    #[Locked]
    public string $company;

    public bool $editing = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array<string, mixed>> */
    public array $lines = [];

    #[Locked]
    public ?int $pendingConvertId = null;

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
            'title' => '',
            'contact_id' => null,
            'project_id' => null,
            'valid_until' => null,
            'notes' => null,
        ];
        $this->lines = [$this->blankLine()];
    }

    public function edit(int $id): void
    {
        $this->resetFeedback();
        $quotation = $this->repository()->find($id);

        if ($quotation === null) {
            $this->failure = 'Penawaran tidak ditemukan atau sudah dihapus.';

            return;
        }

        if (($quotation['project_id'] ?? null) !== null) {
            $this->failure = 'Penawaran ini sudah jadi proyek dan tidak dapat diubah lagi.';

            return;
        }

        $this->editing = true;
        $this->editingId = $id;
        $this->form = [
            'title' => (string) ($quotation['title'] ?? ''),
            'contact_id' => $quotation['contact_id'] ?? null,
            'project_id' => $quotation['project_id'] ?? null,
            'valid_until' => $quotation['valid_until'] ?? null,
            'notes' => $quotation['notes'] ?? null,
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

            $entity = $reference === 'contact_id' ? 'contacts' : 'projects';
            if (app(EntityRepository::class)->for($this->company(), $entity)->find((int) $value) === null) {
                $this->failure = 'Relasi yang dipilih tidak ditemukan pada usaha ini.';

                return;
            }
        }

        $record = [
            'number' => $this->editingId === null ? $this->nextNumber() : null,
            'title' => trim((string) ($this->form['title'] ?? '')),
            'contact_id' => $this->nullIfBlank($this->form['contact_id'] ?? null, true),
            'valid_until' => $this->nullIfBlank($this->form['valid_until'] ?? null),
            'notes' => $this->nullIfBlank($this->form['notes'] ?? null),
            'subtotal' => $totals['subtotal'],
            'dpp' => $totals['dpp'],
            'tax' => $totals['tax'],
            'grand_total' => $totals['grand_total'],
        ];

        if ($record['title'] === '') {
            $this->failure = 'Judul penawaran wajib diisi.';

            return;
        }

        if ($this->editingId !== null) {
            $existing = $this->repository()->find($this->editingId);
            if ($existing === null) {
                $this->failure = 'Penawaran tidak ditemukan atau sudah dihapus.';
                $this->cancel();

                return;
            }

            if (($existing['project_id'] ?? null) !== null) {
                $this->failure = 'Penawaran ini sudah jadi proyek dan tidak dapat diubah lagi.';

                return;
            }

            unset($record['number']);
            $record = array_replace($existing, $record);
        } else {
            $record['stage'] = 'draft';
        }

        $saved = $this->repository()->save($record);
        $this->replaceLines((int) $saved['id'], $lines);

        $this->notice = $this->editingId === null ? 'Penawaran tersimpan sebagai draf.' : 'Perubahan penawaran tersimpan.';
        $this->cancel();
    }

    /** Tahap manual - `quotations` tidak punya alur workflow terdeklarasi. */
    public function markSent(int $id): void
    {
        $this->resetFeedback();
        $this->transitionStage($id, from: 'draft', to: 'sent', notice: 'Penawaran ditandai terkirim.');
    }

    public function markApproved(int $id): void
    {
        $this->resetFeedback();
        $this->transitionStage($id, from: 'sent', to: 'approved', notice: 'Penawaran ditandai disetujui.');
    }

    /** Membuka konfirmasi; tidak mengonversi apa pun. */
    public function requestConvert(int $id): void
    {
        $this->resetFeedback();

        $quotation = $this->repository()->find($id);
        if ($quotation === null) {
            $this->failure = 'Penawaran tidak ditemukan pada usaha ini.';

            return;
        }

        if (($quotation['project_id'] ?? null) !== null) {
            $this->failure = 'Penawaran ini sudah jadi proyek.';

            return;
        }

        if (($quotation['stage'] ?? 'draft') !== 'approved') {
            $this->failure = 'Hanya penawaran yang disetujui yang dapat dikonversi menjadi proyek.';

            return;
        }

        if ($this->quotationLinesOf($id) === []) {
            $this->failure = 'Penawaran ini belum punya rincian, tidak dapat dikonversi.';

            return;
        }

        $this->pendingConvertId = $id;
    }

    public function cancelConvert(): void
    {
        $this->pendingConvertId = null;
    }

    public function confirmConvert(): void
    {
        $id = $this->pendingConvertId;
        if ($id === null) {
            return;
        }

        $this->resetFeedback();
        $this->pendingConvertId = null;

        $this->assertOwner();

        // Diperiksa ulang di titik penulisan, bukan hanya saat dialog dibuka -
        // pengiriman ganda (dua tab, klik dua kali sebelum dialog sempat
        // tertutup) tidak boleh membuat dua proyek dari satu penawaran.
        $quotation = $this->repository()->find($id);
        if ($quotation === null) {
            $this->failure = 'Penawaran tidak ditemukan pada usaha ini.';

            return;
        }

        if (($quotation['project_id'] ?? null) !== null) {
            $this->failure = 'Penawaran ini sudah jadi proyek.';

            return;
        }

        if (($quotation['stage'] ?? 'draft') !== 'approved') {
            $this->failure = 'Hanya penawaran yang disetujui yang dapat dikonversi menjadi proyek.';

            return;
        }

        $lines = $this->quotationLinesOf($id);
        if ($lines === []) {
            $this->failure = 'Penawaran ini belum punya rincian, tidak dapat dikonversi.';

            return;
        }

        // Nilai proyek diambil dari basis data (total penawaran tersimpan),
        // bukan dihitung ulang atau dipercaya dari input baru.
        $project = app(EntityRepository::class)->for($this->company(), 'projects')->save([
            'contact_id' => $quotation['contact_id'] ?? null,
            'name' => (string) ($quotation['title'] ?? ('Proyek dari '.($quotation['number'] ?? '#'.$id))),
            'stage' => $this->initialProjectStage(),
            'budget' => $quotation['grand_total'] ?? 0,
        ]);

        $quotation['project_id'] = (int) $project['id'];
        $this->repository()->save($quotation);

        $this->notice = 'Penawaran dikonversi menjadi proyek.';
    }

    public function render(): View
    {
        $definition = $this->definition();

        $quotations = $this->repository()->all();
        usort($quotations, static fn (array $left, array $right): int => ($right['id'] ?? 0) <=> ($left['id'] ?? 0));

        return view('livewire.screens.quotation-builder', [
            'label' => $definition['label'],
            'term' => $definition['term'] ?? $definition['label'],
            'quotations' => $quotations,
            'totals' => $this->previewTotals(),
            'contactOptions' => $this->referenceOptions('contacts'),
            'projectOptions' => $this->referenceOptions('projects'),
            'isOwner' => $this->isOwner(),
        ]);
    }

    /** @return array{subtotal: float|int, dpp: float|int, tax: float|int, grand_total: float|int} */
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
     * Baris yang sudah dibersihkan. Nilai baris selalu dihitung ulang di
     * sini, jadi total yang dikirim klien tidak berpengaruh.
     *
     * @return list<array{description: string, quantity: float, unit_price: float, line_total: float, sort_order: int}>
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
            throw new InvalidArgumentException('Penawaran wajib punya setidaknya satu rincian.');
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

    private function transitionStage(int $id, string $from, string $to, string $notice): void
    {
        $quotation = $this->repository()->find($id);
        if ($quotation === null) {
            $this->failure = 'Penawaran tidak ditemukan pada usaha ini.';

            return;
        }

        if (($quotation['project_id'] ?? null) !== null) {
            $this->failure = 'Penawaran ini sudah jadi proyek.';

            return;
        }

        if (($quotation['stage'] ?? 'draft') !== $from) {
            $this->failure = "Penawaran harus berstatus \"{$from}\" lebih dulu.";

            return;
        }

        $quotation['stage'] = $to;
        $this->repository()->save($quotation);

        $this->notice = $notice;
    }

    /** @return list<array<string, mixed>> */
    private function quotationLinesOf(int $quotationId): array
    {
        return $this->linesOf($quotationId);
    }

    /** @return list<array<string, mixed>> */
    private function linesOf(int $quotationId): array
    {
        $lines = array_values(array_filter(
            $this->lineRepository()->all(),
            static fn (array $line): bool => (int) ($line['quotation_id'] ?? 0) === $quotationId,
        ));

        usort($lines, static fn (array $left, array $right): int => ($left['sort_order'] ?? 0) <=> ($right['sort_order'] ?? 0));

        return $lines;
    }

    /** @param list<array<string, mixed>> $lines */
    private function replaceLines(int $quotationId, array $lines): void
    {
        $repository = $this->lineRepository();

        foreach ($this->linesOf($quotationId) as $existing) {
            $repository->delete($existing['id']);
        }

        foreach ($lines as $line) {
            $repository->save(array_replace($line, [
                'quotation_id' => $quotationId,
            ]));
        }
    }

    /** Tahap awal proyek dari alur kerja preset bila entitas `projects` memilikinya. */
    private function initialProjectStage(): ?string
    {
        try {
            $preset = app(PresetSource::class)->find(app(CompanyContext::class)->preset());
            $stages = $preset['workflows']['projects']['stages'] ?? null;

            return is_array($stages) ? ($stages[0]['code'] ?? null) : null;
        } catch (Throwable) {
            return null;
        }
    }

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

    private function nextNumber(): string
    {
        $count = count($this->repository()->all());

        return 'QUO-'.now()->format('Ym').'-'.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    private function lineRepository(): EntityRepository
    {
        return app(EntityRepository::class)->for($this->company(), 'quotation_lines');
    }

    private function repository(): EntityRepository
    {
        return app(EntityRepository::class)->for($this->company(), $this->definition()['entity']);
    }

    /**
     * Peran diambil dari sumber tepercaya (kepemilikan company terautentikasi),
     * bukan dari state komponen. Jalur session hanya berlaku pada fixture JSON
     * tanpa basis data, mengikuti pola `ContractScreen`/`Settings`.
     */
    private function isOwner(): bool
    {
        return auth()->check()
            ? app(CompanyRoleResolver::class)->isOwnerOfCompany($this->company())
            : app()->environment('testing') && session('company_role') === CompanyRoleResolver::ROLE_OWNER;
    }

    private function assertOwner(): void
    {
        abort_unless($this->isOwner(), 403);
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
        abort_unless(app(CompanyContext::class)->current() === $this->company, 403);

        return $this->company;
    }

    private function resetFeedback(): void
    {
        $this->notice = null;
        $this->failure = null;
    }
}
