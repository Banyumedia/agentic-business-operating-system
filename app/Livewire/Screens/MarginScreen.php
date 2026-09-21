<?php

namespace App\Livewire\Screens;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Services\DynamicMenuRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pola layar laba-rugi per baris entitas, BASIS KAS (D-62).
 *
 * Satu-satunya sumber angka laba adalah buku kas: pendapatan adalah uang yang
 * benar-benar diterima, biaya adalah uang yang benar-benar dikeluarkan. Tagihan
 * tidak pernah dibaca sebagai pendapatan. Itu keputusan struktural, bukan label
 * - dengan begini mustahil laba muncul dari uang yang belum masuk.
 *
 * Piutang berjalan tetap ditampilkan supaya pemilik tahu berapa yang masih
 * ditunggu, tetapi ia berada di luar perhitungan laba dan diberi judul sendiri.
 */
class MarginScreen extends Component
{
    /** Entitas uang. Nama kolomnya konvensi schema, bukan istilah bisnis. */
    private const CASH_ENTITY = 'cash_entries';

    private const INVOICE_ENTITY = 'customer_invoices';

    #[Locked]
    public string $module;

    #[Locked]
    public ?string $submodule = null;

    #[Locked]
    public string $company;

    public function mount(string $module, ?string $submodule = null): void
    {
        $this->module = $module;
        $this->submodule = $submodule;
        $this->company = app(CompanyContext::class)->current();
    }

    public function render(): View
    {
        $this->company();
        $definition = $this->definition();

        $cash = $this->aggregateCash();
        $receivable = $this->aggregateReceivable();

        $rows = [];
        $totalIn = 0.0;
        $totalOut = 0.0;
        $totalReceivable = 0.0;

        foreach ($this->repository($definition['entity'])->all() as $subject) {
            $id = (int) ($subject['id'] ?? 0);
            if ($id === 0) {
                continue;
            }

            $in = $cash[$id]['in'] ?? 0.0;
            $out = $cash[$id]['out'] ?? 0.0;
            $due = $receivable[$id] ?? 0.0;

            $totalIn += $in;
            $totalOut += $out;
            $totalReceivable += $due;

            $rows[] = [
                'id' => $id,
                'name' => $this->subjectName($subject, $id),
                'stage' => $subject['stage'] ?? null,
                'income' => round($in, 2),
                'expense' => round($out, 2),
                'profit' => round($in - $out, 2),
                'receivable' => round($due, 2),
            ];
        }

        // Entri kas tanpa subjek tidak dibebankan ke siapa pun, tapi tidak
        // disembunyikan: pemilik perlu tahu ada uang yang belum terbebani.
        $unassigned = [
            'income' => round($cash[0]['in'] ?? 0.0, 2),
            'expense' => round($cash[0]['out'] ?? 0.0, 2),
        ];

        return view('livewire.screens.margin', [
            'label' => $definition['label'],
            'term' => $definition['term'] ?? $definition['label'],
            'rows' => $rows,
            'unassigned' => $unassigned,
            'totalIncome' => round($totalIn, 2),
            'totalExpense' => round($totalOut, 2),
            'totalProfit' => round($totalIn - $totalOut, 2),
            'totalReceivable' => round($totalReceivable, 2),
        ]);
    }

    /**
     * Uang masuk dan keluar per subjek, dari buku kas. Entri tanpa subjek
     * dikumpulkan pada kunci 0.
     *
     * @return array<int, array{in: float, out: float}>
     */
    private function aggregateCash(): array
    {
        $totals = [];

        foreach ($this->repository(self::CASH_ENTITY)->all() as $entry) {
            $subjectId = (int) ($entry['project_id'] ?? 0);
            $direction = $entry['direction'] ?? null;

            if ($direction !== 'in' && $direction !== 'out') {
                continue;
            }

            $totals[$subjectId] ??= ['in' => 0.0, 'out' => 0.0];
            $totals[$subjectId][$direction] += (float) ($entry['amount'] ?? 0);
        }

        return $totals;
    }

    /**
     * Piutang berjalan per subjek: tagihan yang sudah diterbitkan dikurangi yang
     * sudah dibayar. Angka ini TIDAK ikut ke laba (D-62).
     *
     * @return array<int, float>
     */
    private function aggregateReceivable(): array
    {
        $totals = [];

        foreach ($this->repository(self::INVOICE_ENTITY)->all() as $invoice) {
            if (($invoice['status'] ?? 'draft') === 'draft') {
                continue;
            }

            $subjectId = (int) ($invoice['project_id'] ?? 0);
            $due = (float) ($invoice['grand_total'] ?? 0) - (float) ($invoice['paid_amount'] ?? 0);

            if ($due <= 0) {
                continue;
            }

            $totals[$subjectId] ??= 0.0;
            $totals[$subjectId] += $due;
        }

        return $totals;
    }

    /** @param array<string, mixed> $subject */
    private function subjectName(array $subject, int $id): string
    {
        foreach (['name', 'title'] as $field) {
            $value = $subject[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return '#'.$id;
    }

    private function repository(string $entity): EntityRepository
    {
        return app(EntityRepository::class)->for($this->company(), $entity);
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
}
