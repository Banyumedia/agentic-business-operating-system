<?php

namespace App\Livewire\Screens;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Contracts\PresetSource;
use App\Services\BusinessIdentityStore;
use App\Services\DynamicMenuRegistry;
use App\Services\Schema\EntitySchema;
use App\Services\Schema\SchemaPresenter;
use App\Services\TaxRateService;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Pola layar kasir generik.
 *
 * Pajak tidak pernah dihitung di layar: seluruhnya lewat `TaxRateService`
 * dengan profil dari identitas usaha (D-03). Bila usaha non-PKP, kosakata pajak
 * tidak dirender sama sekali (D-44).
 *
 * Tingkat konfirmasi ditetapkan per aksi (D-45), bukan per layar.
 */
class CashierScreen extends Component
{
    /**
     * Tingkat konfirmasi per aksi (D-45).
     *
     * `type`   - ireversibel & berdampak fiskal: wajib mengetik YA.
     * `simple` - destruktif tapi dapat dipulihkan: dua tombol.
     *
     * @var array<string, string>
     */
    private const CONFIRM = [
        'checkout' => 'type',
        'clearCart' => 'simple',
    ];

    #[Locked]
    public string $module;

    #[Locked]
    public ?string $submodule = null;

    #[Locked]
    public string $company;

    /** @var array<int, array{item_id: int|null, description: string, qty: float, unit_price: float}> */
    public array $cart = [];

    #[Locked]
    public ?string $pendingAction = null;

    public string $confirmPhrase = '';

    public string $paymentMethod = 'cash';

    public ?string $notice = null;

    public ?string $failure = null;

    public function mount(string $module, ?string $submodule = null): void
    {
        $this->module = $module;
        $this->submodule = $submodule;
        $this->company = app(CompanyContext::class)->current();
    }

    public function addItem(int $itemId): void
    {
        $this->resetFeedback();

        $item = app(EntityRepository::class)->for($this->company(), 'items')->find($itemId);
        if ($item === null) {
            $this->failure = 'Barang atau layanan itu tidak tersedia.';

            return;
        }

        foreach ($this->cart as $index => $line) {
            if ($line['item_id'] === $itemId) {
                $this->cart[$index]['qty'] = $line['qty'] + 1;

                return;
            }
        }

        $this->cart[] = [
            'item_id' => $itemId,
            'description' => (string) ($item['name'] ?? ('#'.$itemId)),
            'qty' => 1.0,
            'unit_price' => (float) ($item['price'] ?? 0),
        ];
    }

    public function setQty(int $index, string $qty): void
    {
        $this->resetFeedback();

        if (! isset($this->cart[$index])) {
            return;
        }

        $value = is_numeric($qty) ? (float) $qty : 0.0;
        if ($value <= 0) {
            $this->removeLine($index);

            return;
        }

        $this->cart[$index]['qty'] = $value;
    }

    public function removeLine(int $index): void
    {
        $this->resetFeedback();
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    /** Membuka konfirmasi sesuai tingkat aksi; tidak mengeksekusi apa pun. */
    public function requestAction(string $action): void
    {
        $this->resetFeedback();

        if (! array_key_exists($action, self::CONFIRM)) {
            return;
        }

        if ($this->cart === []) {
            $this->failure = 'Keranjang masih kosong.';

            return;
        }

        $this->pendingAction = $action;
        $this->confirmPhrase = '';
    }

    public function cancelAction(): void
    {
        $this->pendingAction = null;
        $this->confirmPhrase = '';
    }

    public function confirmAction(): void
    {
        $action = $this->pendingAction;
        if ($action === null) {
            return;
        }

        $this->resetFeedback();

        if (self::CONFIRM[$action] === 'type' && ! $this->phraseAccepted()) {
            $this->failure = 'Ketik YA tepat seperti tertulis untuk menegaskan.';

            return;
        }

        match ($action) {
            'checkout' => $this->checkout(),
            'clearCart' => $this->clearCart(),
            default => null,
        };
    }

    /**
     * Frasa penegasan dinormalkan (D-45): spasi dan huruf besar-kecil diabaikan,
     * tetapi singkatan seperti "y" tetap ditolak.
     */
    private function phraseAccepted(): bool
    {
        return mb_strtoupper(trim($this->confirmPhrase)) === 'YA';
    }

    private function clearCart(): void
    {
        $this->cart = [];
        $this->notice = 'Keranjang dikosongkan.';
        $this->cancelAction();
    }

    private function checkout(): void
    {
        $entity = $this->definition()['entity'];
        $orders = app(EntityRepository::class)->for($this->company(), $entity);
        $lines = app(EntityRepository::class)->for($this->company(), 'order_lines');
        $totals = $this->totals();

        $order = [
            'business_identity_id' => (int) (app(BusinessIdentityStore::class)->read($this->company())['id'] ?? 1),
            'order_no' => $this->nextOrderNumber($orders),
            'subtotal' => $totals['subtotal'],
            'discount_amount' => 0,
            'dpp' => $totals['dpp'],
            'tax_amount' => $totals['tax'],
            'grand_total' => $totals['grand_total'],
            'payment_method' => $this->paymentMethod,
            'paid_at' => now()->toIso8601String(),
            'source' => 'pos',
        ];

        $stage = $this->initialStage($entity);
        if ($stage !== null) {
            $order['stage'] = $stage;
        }

        try {
            $saved = $orders->save($order);

            foreach ($this->cart as $line) {
                $lines->save([
                    'order_id' => (int) $saved['id'],
                    'item_id' => $line['item_id'],
                    'description' => $line['description'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'discount_amount' => 0,
                    'line_total' => round($line['qty'] * $line['unit_price'], 2),
                ]);
            }
        } catch (InvalidArgumentException $exception) {
            $this->failure = $exception->getMessage();

            return;
        }

        $this->cart = [];
        $this->notice = 'Transaksi '.$saved['order_no'].' tersimpan.';
        $this->cancelAction();
    }

    /**
     * Tahap awal diambil dari alur kerja preset bila entitas ini memilikinya,
     * supaya transaksi kasir langsung tampil di papan tahap.
     */
    private function initialStage(string $entity): ?string
    {
        try {
            $preset = app(PresetSource::class)->find(app(CompanyContext::class)->preset());
            $stages = $preset['workflows'][$entity]['stages'] ?? null;

            return is_array($stages) ? ($stages[0]['code'] ?? null) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function nextOrderNumber(EntityRepository $orders): string
    {
        $highest = 0;
        foreach ($orders->all() as $row) {
            $id = $row['id'] ?? 0;
            if (is_int($id) && $id > $highest) {
                $highest = $id;
            }
        }

        return sprintf('%s-%04d', now()->format('ymd'), $highest + 1);
    }

    /** @return array{subtotal: float, dpp: float, tax: float, grand_total: float} */
    private function totals(): array
    {
        $subtotal = 0.0;
        foreach ($this->cart as $line) {
            $subtotal += $line['qty'] * $line['unit_price'];
        }

        $profile = app(BusinessIdentityStore::class)->taxProfile($this->company());
        $result = app(TaxRateService::class)->calculateTax(
            round($subtotal, 2),
            $profile->rate,
            $profile->priceIncludesTax,
        );

        return [
            'subtotal' => round($subtotal, 2),
            'dpp' => $result->dpp,
            'tax' => $result->tax,
            'grand_total' => $result->grandTotal,
        ];
    }

    public function render(): View
    {
        $definition = $this->definition();
        $profile = app(BusinessIdentityStore::class)->taxProfile($this->company());
        $presenter = app(SchemaPresenter::class);

        $catalog = [];
        foreach (app(EntityRepository::class)->for($this->company(), 'items')->all() as $item) {
            if (($item['is_active'] ?? true) === false) {
                continue;
            }

            $catalog[] = [
                'id' => $item['id'],
                'name' => (string) ($item[$presenter->titleField(EntitySchema::load('items')) ?? 'name'] ?? '#'.$item['id']),
                'price' => (float) ($item['price'] ?? 0),
                'unit' => (string) ($item['unit'] ?? ''),
            ];
        }

        return view('livewire.screens.cashier', [
            'label' => $definition['label'],
            'term' => $definition['term'] ?? $definition['label'],
            'catalog' => $catalog,
            'totals' => $this->totals(),
            // D-44: satu-satunya penentu apakah kosakata pajak dirender.
            'showsTax' => $profile->taxable,
            'confirmLevel' => $this->pendingAction === null ? null : self::CONFIRM[$this->pendingAction],
        ]);
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
