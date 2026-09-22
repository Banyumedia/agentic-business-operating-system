<?php

namespace App\Livewire\Screens;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Services\Dashboard\CashFlowCalculator;
use App\Services\DynamicMenuRegistry;
use App\Services\Schema\EntitySchema;
use App\Services\Schema\SchemaPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use UnexpectedValueException;

/**
 * Pola layar buku (ledger) generik dengan saldo berjalan.
 *
 * Kolom nilai, kolom tanggal, dan arah masuk/keluar diturunkan dari schema
 * entitas - bukan dari daftar per entitas - sehingga buku kas maupun buku
 * tagihan memakai satu layar yang sama.
 *
 * Dua aturan uang yang dipegang layar ini:
 *
 * 1. **Saldo adalah posisi kas usaha, bukan jumlah baris yang sedang dilihat.**
 *    Penyaring mengubah daftar dan ringkasan tersaring, tidak pernah mengubah
 *    saldo. Saldo yang bergeser saat operator menyaring tampilan adalah angka
 *    yang tidak bisa dipakai siapa pun.
 * 2. **Baris yang tidak dapat dihitung tidak pernah dianggap uang masuk.**
 *    Nominal dan arah diuraikan `CashFlowCalculator` - kontrak kalkulasi yang
 *    sama dengan KPI dan widget Dashboard, bukan rumus kedua. Baris yang
 *    ditolaknya dikeluarkan dari seluruh total dan **tetap ditampilkan**
 *    dengan penanda, karena baris bermasalah yang disembunyikan tidak akan
 *    pernah diperbaiki siapa pun.
 */
class LedgerScreen extends Component
{
    /** Arah dianggap ada bila schema mendeklarasikan enum tepat seperti ini. */
    private const DIRECTION_ENUM = ['in', 'out'];

    private const ALL = 'all';

    private const NONE = 'none';

    #[Locked]
    public string $module;

    #[Locked]
    public ?string $submodule = null;

    #[Locked]
    public string $company;

    /** Periode `YYYY-MM`, atau `all`. */
    public string $period = self::ALL;

    /** `in`, `out`, atau `all`. */
    public string $direction = self::ALL;

    /**
     * Penyaring relasi per field referensi: `all`, `none`, atau id.
     *
     * @var array<string, string>
     */
    public array $relation = [];

    public function mount(string $module, ?string $submodule = null): void
    {
        $this->module = $module;
        $this->submodule = $submodule;
        $this->company = app(CompanyContext::class)->current();
    }

    public function resetFilters(): void
    {
        $this->period = self::ALL;
        $this->direction = self::ALL;
        $this->relation = [];
    }

    public function render(): View
    {
        $definition = $this->definition();
        $schema = EntitySchema::load($definition['entity']);
        $presenter = app(SchemaPresenter::class);

        $amountField = $this->amountField($schema);
        $dateField = $this->dateField($schema);
        $directionField = $this->directionField($schema);
        $descriptionField = $presenter->titleField($schema);

        $rows = $this->repository()->all();
        usort($rows, static function (array $left, array $right) use ($dateField): int {
            $leftKey = [(string) ($left[$dateField] ?? ''), $left['id'] ?? 0];
            $rightKey = [(string) ($right[$dateField] ?? ''), $right['id'] ?? 0];

            return $leftKey <=> $rightKey;
        });

        $relationFilters = $this->relationFilters($schema, $presenter);
        $calculator = app(CashFlowCalculator::class);

        $balanceCents = 0;
        $valid = [];
        $visibleValid = [];
        $rejected = 0;
        $entries = [];

        foreach ($rows as $row) {
            $normalized = $this->normalize($row, $amountField, $directionField);
            $delta = $this->deltaCents($calculator, $normalized);

            $entry = [
                'id' => $row['id'] ?? null,
                'date' => (string) ($row[$dateField] ?? ''),
                'description' => $this->describe($row, $descriptionField, $amountField, $dateField, $directionField),
                'amount' => $row[$amountField] ?? null,
                'outgoing' => $directionField !== null && ($row[$directionField] ?? null) === 'out',
                'rejected' => $delta === null,
                'balance' => null,
            ];

            if ($delta === null) {
                $rejected++;
            } else {
                $balanceCents += $delta;
                $valid[] = $normalized;
                $entry['amount'] = (float) ($this->cents($calculator, $normalized) / 100);
                $entry['balance'] = (float) ($balanceCents / 100);
            }

            if ($this->passesFilters($row, $dateField, $directionField, $relationFilters)) {
                $entries[] = $entry;

                if ($delta !== null) {
                    $visibleValid[] = $normalized;
                }
            }
        }

        // Guard limpahan integer sekaligus penjagaan bahwa kolom saldo berjalan
        // dan angka di header berasal dari hitungan yang sama: kalau akumulasi
        // per baris berselisih dari agregat, salah satunya tidak bisa dipercaya
        // dan layar wajib berhenti, bukan menampilkan dua versi kebenaran.
        $totals = $calculator->calculate($valid);
        if ($totals['balance_cents'] !== $balanceCents) {
            throw new UnexpectedValueException('Saldo berjalan tidak konsisten dengan total buku.');
        }

        $filteredTotals = $calculator->calculate($visibleValid);

        return view('livewire.screens.ledger', [
            'label' => $definition['label'],
            'term' => $definition['term'] ?? $definition['label'],
            'entries' => array_reverse($entries),
            'hasDirection' => $directionField !== null,
            'incoming' => (float) ($filteredTotals['incoming_cents'] / 100),
            'outgoing' => (float) ($filteredTotals['outgoing_cents'] / 100),
            'balance' => (float) ($totals['balance_cents'] / 100),
            'rejected' => $rejected,
            'periods' => $this->periods($rows, $dateField),
            'relationFilters' => $relationFilters,
            'filtered' => $this->hasActiveFilter($relationFilters),
        ]);
    }

    /**
     * Bentuk minimal yang dimengerti `CashFlowCalculator`.
     *
     * Entitas tanpa kolom arah tidak punya konsep masuk/keluar, jadi seluruh
     * barisnya dihitung sebagai nilai tercatat - itulah yang selama ini
     * ditampilkan sebagai "Nilai tercatat", bukan saldo.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalize(array $row, string $amountField, ?string $directionField): array
    {
        return [
            'id' => $row['id'] ?? null,
            'direction' => $directionField === null ? 'in' : ($row[$directionField] ?? null),
            'amount' => $row[$amountField] ?? null,
        ];
    }

    /** @param array<string, mixed> $normalized */
    private function deltaCents(CashFlowCalculator $calculator, array $normalized): ?int
    {
        try {
            $totals = $calculator->calculate([$normalized]);
        } catch (UnexpectedValueException) {
            return null;
        }

        return $totals['incoming_cents'] - $totals['outgoing_cents'];
    }

    /** @param array<string, mixed> $normalized */
    private function cents(CashFlowCalculator $calculator, array $normalized): int
    {
        $totals = $calculator->calculate([$normalized]);

        return $totals['incoming_cents'] + $totals['outgoing_cents'];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array<string, mixed>>  $relationFilters
     */
    private function passesFilters(array $row, string $dateField, ?string $directionField, array $relationFilters): bool
    {
        $period = $this->activePeriod();
        if ($period !== self::ALL && mb_substr((string) ($row[$dateField] ?? ''), 0, 7) !== $period) {
            return false;
        }

        $direction = $this->activeDirection($directionField);
        if ($direction !== self::ALL && ($row[$directionField] ?? null) !== $direction) {
            return false;
        }

        foreach ($relationFilters as $filter) {
            $selected = $filter['selected'];
            if ($selected === self::ALL) {
                continue;
            }

            $value = $row[$filter['field']] ?? null;

            if ($selected === self::NONE) {
                if ($value !== null && $value !== '') {
                    return false;
                }

                continue;
            }

            if ((string) $value !== $selected) {
                return false;
            }
        }

        return true;
    }

    /**
     * Nilai penyaring yang tidak dikenal dikembalikan ke "semua".
     *
     * Penyaring bukan pintu data - repository sudah ter-scope company, jadi
     * nilai asing tidak dapat membocorkan apa pun. Yang berbahaya justru
     * keadaan "tersaring diam-diam": operator melihat sebagian buku tanpa tahu
     * sebagian yang lain disembunyikan oleh nilai yang tidak sah.
     */
    private function activePeriod(): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->period) === 1 ? $this->period : self::ALL;
    }

    private function activeDirection(?string $directionField): string
    {
        if ($directionField === null) {
            return self::ALL;
        }

        return in_array($this->direction, self::DIRECTION_ENUM, true) ? $this->direction : self::ALL;
    }

    /** @param list<array<string, mixed>> $relationFilters */
    private function hasActiveFilter(array $relationFilters): bool
    {
        foreach ($relationFilters as $filter) {
            if ($filter['selected'] !== self::ALL) {
                return true;
            }
        }

        return $this->activePeriod() !== self::ALL || $this->direction !== self::ALL;
    }

    /**
     * Penyaring relasi diturunkan dari schema: hanya referensi yang memang
     * boleh dipilih operator (`assignable`, mekanisme T-41). Kolom sistem
     * seperti `journal_id` tidak pernah menjadi penyaring, dan entitas baru
     * mendapat penyaringnya tanpa menyunting layar ini.
     *
     * @return list<array{field: string, label: string, nullable: bool, selected: string, options: array<int, string>}>
     */
    private function relationFilters(EntitySchema $schema, SchemaPresenter $presenter): array
    {
        $filters = [];

        foreach ($presenter->relations($schema) as $relation) {
            $field = $relation['field'];
            $term = $relation['term'] ?? null;
            $selected = (string) ($this->relation[$field] ?? self::ALL);
            $options = $this->relationOptions((string) $relation['relation']);

            if ($selected !== self::ALL && $selected !== self::NONE && ! array_key_exists((int) $selected, $options)) {
                $selected = self::ALL;
            }

            $filters[] = [
                'field' => $field,
                'label' => is_string($term) && $term !== '' ? term($term) : $relation['label'],
                'nullable' => $relation['nullable'],
                'selected' => $selected,
                'options' => $options,
            ];
        }

        return $filters;
    }

    /** @return array<int, string> */
    private function relationOptions(string $entity): array
    {
        $titleField = app(SchemaPresenter::class)->titleField(EntitySchema::load($entity));
        $options = [];

        foreach (app(EntityRepository::class)->for($this->company(), $entity)->all() as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id === 0) {
                continue;
            }

            $title = $titleField === null ? null : ($row[$titleField] ?? null);
            $options[$id] = is_string($title) && trim($title) !== '' ? $title : '#'.$id;
        }

        return $options;
    }

    /**
     * Periode yang dapat dipilih diturunkan dari data, bukan dari kalender:
     * bulan tanpa transaksi tidak perlu ditawarkan, dan bulan yang punya
     * transaksi tidak boleh hilang dari pilihan.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{value: string, label: string}>
     */
    private function periods(array $rows, string $dateField): array
    {
        $months = [];

        foreach ($rows as $row) {
            $month = mb_substr((string) ($row[$dateField] ?? ''), 0, 7);
            if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1) {
                $months[$month] = true;
            }
        }

        $months = array_keys($months);
        rsort($months);

        return array_map(static fn (string $month): array => [
            'value' => $month,
            // Locale disebut eksplisit karena aplikasi berjalan dengan locale
            // `en` sementara antarmukanya berbahasa Indonesia. Tabel nama bulan
            // sendiri tidak dibuat di sini: salinan kedua tabel seperti itu
            // adalah awal dari dua layar yang menyebut bulan berbeda.
            'label' => CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')
                ->locale('id')
                ->translatedFormat('F Y'),
        ], $months);
    }

    /**
     * Keterangan baris.
     *
     * `description` dan `category` didahulukan sebelum judul turunan schema.
     * Urutan sebelumnya memakai `SchemaPresenter::titleField()` lebih dulu, dan
     * untuk buku kas field itu jatuh ke `entry_date` (kolom string pertama),
     * sehingga kolom "Keterangan" menampilkan **tanggal yang sama** dengan
     * kolom di sebelahnya dan keterangan yang ditulis operator tidak pernah
     * terlihat. Kolom tanggal, nilai, dan arah dikecualikan secara eksplisit:
     * ketiganya sudah punya kolomnya sendiri.
     *
     * @param  array<string, mixed>  $row
     */
    private function describe(array $row, ?string $descriptionField, string $amountField, string $dateField, ?string $directionField): string
    {
        foreach (['description', 'category', $descriptionField] as $candidate) {
            if ($candidate === null
                || $candidate === $amountField
                || $candidate === $dateField
                || $candidate === $directionField) {
                continue;
            }

            $value = $row[$candidate] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return '#'.$row['id'];
    }

    private function amountField(EntitySchema $schema): string
    {
        $properties = $schema->properties();

        if (($properties['amount']['type'] ?? null) === 'number') {
            return 'amount';
        }

        foreach ($properties as $field => $definition) {
            if (($definition['type'] ?? null) === 'number') {
                return $field;
            }
        }

        return 'id';
    }

    private function dateField(EntitySchema $schema): string
    {
        foreach (['date', 'date-time'] as $format) {
            foreach ($schema->properties() as $field => $definition) {
                if (($definition['format'] ?? null) === $format) {
                    return $field;
                }
            }
        }

        return 'id';
    }

    private function directionField(EntitySchema $schema): ?string
    {
        foreach ($schema->properties() as $field => $definition) {
            $enum = $definition['enum'] ?? null;
            if (is_array($enum) && $enum === self::DIRECTION_ENUM) {
                return $field;
            }
        }

        return null;
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

    private function repository(): EntityRepository
    {
        return app(EntityRepository::class)->for($this->company(), $this->definition()['entity']);
    }

    private function company(): string
    {
        abort_unless(app(CompanyContext::class)->current() === $this->company, 403);

        return $this->company;
    }
}
