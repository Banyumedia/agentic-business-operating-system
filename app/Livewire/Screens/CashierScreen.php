<?php

namespace App\Livewire\Screens;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Contracts\PresetSource;
use App\Services\BusinessIdentityStore;
use App\Services\DynamicMenuRegistry;
use App\Services\OrderService;
use App\Services\Schema\EntitySchema;
use App\Services\Schema\SchemaPresenter;
use App\Services\TaxRateService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;
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
    /** Float tax arithmetic stays cent-accurate below this operational cap. */
    private const MAX_SAFE_MONEY = 1_000_000_000_000;

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

    #[Locked]
    public string $checkoutToken;

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
        $this->checkoutToken = (string) Str::uuid();
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
        if (! in_array($this->paymentMethod, ['cash', 'transfer', 'qris'], true)) {
            $this->failure = 'Metode pembayaran tidak valid.';

            return;
        }

        try {
            $lines = $this->authoritativeLines();
            $orderNo = $this->orderNumber();

            $orderNo = config('datasource.driver') === 'eloquent'
                ? $this->checkoutViaOrderService($lines, $orderNo)
                : $this->checkoutViaAggregate($lines, $orderNo);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->failure = $exception->getMessage();

            return;
        }

        $this->cart = [];
        $this->checkoutToken = (string) Str::uuid();
        $this->notice = 'Transaksi '.$orderNo.' tersimpan.';
        $this->cancelAction();
    }

    private function orderNumber(): string
    {
        return now()->format('ymd').'-'.mb_strtoupper(substr(str_replace('-', '', $this->checkoutToken), 0, 8));
    }

    /**
     * Jalur JSON (D-42): tetap `saveAggregate()` seperti sebelumnya, tidak
     * diubah sama sekali. `StockService`/`JournalService` murni Eloquent
     * (menulis model langsung dalam satu transaksi database) - tidak ada
     * jalur JSON yang setara untuk `stock_movements`/`accounting_journals`
     * (keduanya tidak punya schema JSON, keduanya tabel SQL saja). Menutup
     * batas ini sepenuhnya butuh menulis ulang kedua servis untuk
     * `EntityRepository`, di luar file target task ini (`CashierScreen`,
     * `OrderService`, `StockService`) - dicatat eksplisit sebagai batas
     * (MP-02), bukan diselundupkan sebagai "sudah beres".
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function checkoutViaAggregate(array $lines, string $orderNo): string
    {
        $entity = $this->definition()['entity'];
        $orders = app(EntityRepository::class)->for($this->company(), $entity);
        $identity = app(BusinessIdentityStore::class)->read($this->company());
        $totals = $this->totals($lines);

        $order = [
            'business_identity_id' => $identity['id'],
            'order_no' => $orderNo,
            'subtotal' => $totals['subtotal'],
            'discount_amount' => 0,
            'dpp' => $totals['dpp'],
            'tax_amount' => $totals['tax'],
            'grand_total' => $totals['grand_total'],
            'payment_method' => $this->paymentMethod,
            'paid_at' => now()->toIso8601String(),
            'source' => 'pos',
            'external_ref' => $this->checkoutToken,
        ];

        $stage = $this->initialStage($entity);
        if ($stage !== null) {
            $order['stage'] = $stage;
        }

        $children = array_map(static function (array $line): array {
            unset($line['_source_active']);

            return $line;
        }, $lines);

        $result = $orders->saveAggregate(
            $order,
            'order_lines',
            'order_id',
            $children,
            'external_ref',
            array_map(static fn (array $line): array => [
                'entity' => 'items',
                'id' => $line['item_id'],
                'expected' => [
                    'name' => $line['description'],
                    'price' => $line['unit_price'],
                    'is_active' => $line['_source_active'],
                ],
            ], $lines),
        );

        return (string) $result['parent']['order_no'];
    }

    /**
     * Jalur Eloquent (MP-02): checkout melewati `OrderService` (order+lines),
     * `StockService` (stok berkurang, `stock_movements` tercatat), dan
     * `JournalService` (jurnal terposting bila `finance.accounting` aktif) -
     * bukan menulis agregat sendiri seperti sebelumnya. Seluruh orkestrasi
     * (transaksi database, idempotensi, urutan panggilan servis) hidup di
     * `OrderService::checkoutPos()`, BUKAN di sini - `CashierScreen` tidak
     * boleh memuat literal akses basis data maupun logika domain langsung
     * (dijaga `test_cashier_sources_have_no_industry_branch_or_direct_database_access`).
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function checkoutViaOrderService(array $lines, string $orderNo): string
    {
        $identity = app(BusinessIdentityStore::class)->read($this->company());

        $order = app(OrderService::class)->checkoutPos([
            'company_id' => $this->company(),
            'business_identity_id' => $identity['id'],
            'order_no' => $orderNo,
            'external_ref' => $this->checkoutToken,
            'stage' => $this->initialStage($this->definition()['entity']),
            'payment_method' => $this->paymentMethod,
            'lines' => $lines,
        ]);

        return $order->order_no;
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

    /** @return array{subtotal: float, dpp: float, tax: float, grand_total: float} */
    private function totals(?array $lines = null): array
    {
        if ($lines === null) {
            $lines = $this->cart === [] ? [] : $this->authoritativeLines();
        }
        $subtotal = array_sum(array_column($lines, 'line_total'));
        if (! is_int($subtotal) && ! is_float($subtotal) || abs((float) $subtotal) > self::MAX_SAFE_MONEY) {
            throw new InvalidArgumentException('Total transaksi melampaui batas presisi aman.');
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

    /** @return list<array<string, mixed>> */
    private function authoritativeLines(): array
    {
        $items = app(EntityRepository::class)->for($this->company(), 'items');
        $lines = [];

        foreach ($this->cart as $cartLine) {
            $itemId = $cartLine['item_id'] ?? null;
            $qty = $cartLine['qty'] ?? null;
            if (! is_int($itemId) || (! is_int($qty) && ! is_float($qty)) || ! is_finite((float) $qty) || $qty <= 0) {
                throw new InvalidArgumentException('Isi keranjang tidak valid.');
            }

            $item = $items->find($itemId);
            if ($item === null || ($item['is_active'] ?? true) === false) {
                throw new InvalidArgumentException('Barang atau layanan tidak lagi tersedia.');
            }

            $price = $item['price'] ?? null;
            // MP-02: jalur Eloquent mengembalikan kolom `decimal:2` sebagai
            // STRING numerik ("75000.00"), bukan int/float - perilaku
            // `toArray()` bawaan Laravel untuk cast decimal, bukan cacat
            // data. Jalur JSON selalu mengirim int/float asli. `is_numeric()`
            // menerima keduanya sekaligus menolak nilai yang benar-benar
            // rusak (string bukan angka, array, null).
            if (! is_int($price) && ! is_float($price) && ! (is_string($price) && is_numeric($price))) {
                throw new InvalidArgumentException('Harga barang atau layanan tidak valid.');
            }

            $qty = $this->normalizedQuantity($qty);
            $price = $this->normalizedMoney(is_string($price) ? (float) $price : $price);
            $lines[] = [
                'item_id' => $itemId,
                'description' => (string) ($item['name'] ?? ('#'.$itemId)),
                'qty' => $qty,
                'unit_price' => $price,
                'discount_amount' => 0,
                'line_total' => $this->multiplyMoney($price, $qty),
                '_source_active' => $item['is_active'] ?? null,
            ];
        }

        if ($lines === []) {
            throw new InvalidArgumentException('Keranjang masih kosong.');
        }

        return $lines;
    }

    private function normalizedQuantity(int|float $qty): int|float
    {
        return is_float($qty) && floor($qty) === $qty ? (int) $qty : $qty;
    }

    private function normalizedMoney(int|float $money): int|float
    {
        return is_float($money) && floor($money) === $money && $money <= PHP_INT_MAX
            ? (int) $money
            : $money;
    }

    private function multiplyMoney(int|float $money, int|float $qty): int|float
    {
        if (is_int($money) && is_int($qty)) {
            if ($qty !== 0 && $money > intdiv(PHP_INT_MAX, $qty)) {
                throw new InvalidArgumentException('Total baris transaksi melampaui batas aman.');
            }

            return $money * $qty;
        }

        if (abs((float) $money) > self::MAX_SAFE_MONEY) {
            throw new InvalidArgumentException('Harga pecahan melampaui batas presisi aman.');
        }

        return round((float) $money * (float) $qty, 2);
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

        try {
            $totals = $this->totals();
        } catch (InvalidArgumentException $exception) {
            $this->failure = $exception->getMessage();
            $totals = ['subtotal' => 0.0, 'dpp' => 0.0, 'tax' => 0.0, 'grand_total' => 0.0];
        }

        return view('livewire.screens.cashier', [
            'label' => $definition['label'],
            'term' => $definition['term'] ?? $definition['label'],
            'catalog' => $catalog,
            'totals' => $totals,
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
