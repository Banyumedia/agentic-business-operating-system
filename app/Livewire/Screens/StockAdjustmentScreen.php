<?php

namespace App\Livewire\Screens;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Models\Item;
use App\Models\StockMovement;
use App\Services\CompanyRoleResolver;
use App\Services\DynamicMenuRegistry;
use App\Services\StockService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * Penyesuaian stok fisik (MP-10).
 *
 * Selisih opname ditulis sebagai mutasi lewat `StockService`, BUKAN
 * menimpa `qty_on_hand` diam-diam - setiap penyesuaian wajib mencatat alasan
 * dan meninggalkan jejak di `stock_movements`, sama seperti mutasi lain
 * (penjualan, produksi). Owner-only (pola T-50), ditegakkan server-side dari
 * `CompanyRoleResolver`, bukan hanya menyembunyikan tombol.
 *
 * Eloquent-only (mengikuti batas yang sama dengan MP-02): `stock_movements`
 * tidak punya schema JSON (tabel SQL murni, D-42 tidak mencakupnya), jadi
 * jalur JSON tidak punya cara menulis mutasi yang bisa diaudit. Item sendiri
 * (`items`) tetap dibaca lewat `EntityRepository` supaya pemilihan barang
 * bekerja di kedua driver; hanya PENYIMPANAN mutasi yang Eloquent-only.
 */
class StockAdjustmentScreen extends Component
{
    #[Locked]
    public string $module;

    #[Locked]
    public string $company;

    public ?int $itemId = null;

    public string $delta = '';

    public string $reason = '';

    public ?string $notice = null;

    public ?string $failure = null;

    public function mount(string $module, ?string $submodule = null): void
    {
        $this->module = $module;
        $this->company = app(CompanyContext::class)->current();
    }

    public function adjust(): void
    {
        $this->resetFeedback();

        abort_unless($this->isOwner(), 403);

        if (config('datasource.driver') !== 'eloquent') {
            $this->failure = 'Penyesuaian stok belum tersedia untuk mode data JSON.';

            return;
        }

        if (trim($this->reason) === '') {
            $this->failure = 'Alasan penyesuaian wajib diisi.';

            return;
        }

        $delta = is_numeric($this->delta) ? (float) $this->delta : null;
        if ($delta === null || $delta === 0.0) {
            $this->failure = 'Selisih penyesuaian harus angka bukan nol.';

            return;
        }

        if ($this->itemId === null) {
            $this->failure = 'Pilih barang yang ingin disesuaikan.';

            return;
        }

        $item = Item::query()->where('company_id', (int) $this->company)->find($this->itemId);
        if ($item === null) {
            $this->failure = 'Barang tidak ditemukan pada usaha ini.';

            return;
        }

        $stock = app(StockService::class);
        $reason = 'adjustment: '.trim($this->reason);

        try {
            if ($delta > 0) {
                $stock->addStock($item, $delta, $reason);
            } else {
                $stock->deductStock($item, abs($delta), $reason);
            }
        } catch (RuntimeException $exception) {
            $this->failure = $exception->getMessage();

            return;
        }

        $this->notice = 'Penyesuaian stok tercatat.';
        $this->delta = '';
        $this->reason = '';
    }

    public function render(): View
    {
        $definition = $this->definition();

        $items = config('datasource.driver') === 'eloquent'
            ? Item::query()->where('company_id', (int) $this->company)->orderBy('name')->get()
                ->map(static fn (Item $item): array => ['id' => $item->id, 'name' => $item->name])
                ->all()
            : app(EntityRepository::class)->for($this->company(), 'items')->all();

        return view('livewire.screens.stock-adjustment', [
            'label' => $definition['label'],
            'term' => $definition['term'] ?? $definition['label'],
            'items' => $items,
            'currentBalance' => $this->itemId === null ? null : $this->balanceFor($this->itemId),
            'history' => $this->itemId === null ? [] : $this->historyFor($this->itemId),
            'isOwner' => $this->isOwner(),
        ]);
    }

    private function balanceFor(int $itemId): ?float
    {
        if (config('datasource.driver') !== 'eloquent') {
            return null;
        }

        $item = Item::query()->where('company_id', (int) $this->company)->find($itemId);

        return $item === null ? null : app(StockService::class)->currentBalance($item);
    }

    /** @return list<array{direction: string, qty: float, reason: string, occurred_at: string|null, balance_after: float}> */
    private function historyFor(int $itemId): array
    {
        if (config('datasource.driver') !== 'eloquent') {
            return [];
        }

        $item = Item::query()->where('company_id', (int) $this->company)->find($itemId);
        if ($item === null) {
            return [];
        }

        $movements = StockMovement::where('item_id', $itemId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $running = 0.0;
        $withBalance = [];
        foreach ($movements as $movement) {
            $running += $movement->direction === 'in' ? (float) $movement->qty : -(float) $movement->qty;
            $withBalance[] = [
                'direction' => $movement->direction,
                'qty' => (float) $movement->qty,
                'reason' => $movement->reason,
                'occurred_at' => $movement->created_at?->toIso8601String(),
                'balance_after' => $running,
            ];
        }

        return array_reverse($withBalance);
    }

    private function isOwner(): bool
    {
        return app(CompanyRoleResolver::class)->isOwnerOfCompany($this->company());
    }

    /** @return array{label: string, icon: string, route: string, screen: string, entity: string, term: string|null} */
    private function definition(): array
    {
        $registry = app(DynamicMenuRegistry::class);

        abort_unless($registry->hasPath($this->module, 'adjustment'), 404);
        abort_unless($registry->isModuleVisible($this->module), 403);

        $definition = $registry->routeDefinition($this->module, 'adjustment');
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
