<?php

namespace App\Livewire\Screens;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Contracts\PresetSource;
use App\Services\DynamicMenuRegistry;
use App\Services\Schema\EntitySchema;
use App\Services\Schema\SchemaPresenter;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pola layar papan okupansi generik.
 *
 * Kolomnya adalah sumber daya (`resources`) milik company, dan isinya adalah
 * baris entitas yang menunjuk sumber daya itu. Relasi tersebut TIDAK dihardcode:
 * ia ditemukan dari `references` di schema entitas, sehingga satu layar yang
 * sama melayani pesanan maupun booking tanpa cabang per industri (D-31). Nama
 * kolom selalu datang dari data sumber daya company, bukan dari kelas ini.
 *
 * Layar ini baca-saja. Perpindahan tahap sudah punya rumah sendiri di pola
 * `pipeline`, dan menduplikasi logika transisi di sini hanya menambah jalur
 * yang harus diamankan ulang.
 */
class BoardScreen extends Component
{
    /** Nama field tahap mengikuti konvensi schema, bukan istilah bisnis. */
    private const STAGE_FIELD = 'stage';

    /** Entitas yang dipakai sebagai kolom papan. */
    private const RESOURCE_ENTITY = 'resources';

    /**
     * Urutan pencarian field nilai. `grand_total` lebih dipercaya daripada
     * komponen penyusunnya (subtotal/pajak) bila schema memilikinya.
     *
     * @var list<string>
     */
    private const AMOUNT_PREFERENCE = ['grand_total', 'amount', 'rate_amount'];

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
        $definition = $this->definition();
        $entity = $definition['entity'];
        $schema = EntitySchema::load($entity);
        $presenter = app(SchemaPresenter::class);

        $resourceField = $this->resourceField($schema);
        $amountField = $this->amountField($schema);
        $depositField = $this->fieldIfExists($schema, 'deposit_amount');
        $titleField = $presenter->titleField($schema);
        $stageLabels = $this->stageLabels($entity);
        $terminal = $this->terminalStages($entity);

        $columns = [];
        foreach ($this->rowsFor(self::RESOURCE_ENTITY) as $resource) {
            $columns[(int) $resource['id']] = [
                'key' => 'resource-'.$resource['id'],
                'label' => $this->resourceLabel($resource),
                'status' => $this->stringOrNull($resource['status'] ?? null),
                'capacity' => isset($resource['capacity']) && $resource['capacity'] !== null
                    ? (int) $resource['capacity']
                    : null,
                'cards' => [],
                'amount' => 0.0,
                'deposit' => 0.0,
            ];
        }

        $unassigned = [
            'key' => 'unassigned',
            'label' => 'Belum ditempatkan',
            'status' => null,
            'capacity' => null,
            'cards' => [],
            'amount' => 0.0,
            'deposit' => 0.0,
        ];

        $closed = 0;

        foreach ($this->rowsFor($entity) as $row) {
            // Baris yang alurnya sudah tamat bukan okupansi berjalan; kalau
            // diikutkan, papan tidak lagi menjawab "mana yang sedang terpakai".
            $stage = $this->stringOrNull($row[self::STAGE_FIELD] ?? null);
            if ($stage !== null && in_array($stage, $terminal, true)) {
                $closed++;

                continue;
            }

            $amount = $amountField === null ? 0.0 : (float) ($row[$amountField] ?? 0);
            $deposit = $depositField === null ? 0.0 : (float) ($row[$depositField] ?? 0);

            $card = [
                'id' => (int) $row['id'],
                'title' => $this->cardTitle($row, $titleField),
                'stage' => $stage === null ? null : ($stageLabels[$stage] ?? $stage),
                'amount' => $amount,
                'deposit' => $deposit,
                'hasAmount' => $amountField !== null,
                'hasDeposit' => $depositField !== null,
            ];

            $resourceId = $resourceField === null ? null : ($row[$resourceField] ?? null);
            $target = $resourceId !== null && isset($columns[(int) $resourceId])
                ? (int) $resourceId
                : null;

            if ($target === null) {
                $unassigned['cards'][] = $card;
                $unassigned['amount'] += $amount;
                $unassigned['deposit'] += $deposit;

                continue;
            }

            $columns[$target]['cards'][] = $card;
            $columns[$target]['amount'] += $amount;
            $columns[$target]['deposit'] += $deposit;
        }

        $board = array_values($columns);
        if ($unassigned['cards'] !== []) {
            $board[] = $unassigned;
        }

        return view('livewire.screens.board', [
            'label' => $definition['label'],
            'term' => $definition['term'] ?? $definition['label'],
            'entity' => $entity,
            'columns' => $board,
            'hasResourceLink' => $resourceField !== null,
            'hasAmount' => $amountField !== null,
            'hasDeposit' => $depositField !== null,
            'occupied' => count(array_filter($board, static fn (array $column): bool => $column['cards'] !== [])),
            'closed' => $closed,
        ]);
    }

    /**
     * Field yang menghubungkan entitas ini ke sumber daya, dibaca dari
     * `references` schema. Null bila entitas memang tidak punya relasi itu -
     * papan lalu jujur menyatakan dirinya belum bisa dikelompokkan.
     */
    private function resourceField(EntitySchema $schema): ?string
    {
        foreach ($schema->references() as $field => $reference) {
            if (($reference['entity'] ?? null) === self::RESOURCE_ENTITY) {
                return $field;
            }
        }

        return null;
    }

    private function amountField(EntitySchema $schema): ?string
    {
        $properties = $schema->properties();

        foreach (self::AMOUNT_PREFERENCE as $candidate) {
            if (($properties[$candidate]['type'] ?? null) === 'number') {
                return $candidate;
            }
        }

        foreach ($properties as $field => $definition) {
            if (($definition['type'] ?? null) === 'number') {
                return $field;
            }
        }

        return null;
    }

    private function fieldIfExists(EntitySchema $schema, string $field): ?string
    {
        return ($schema->properties()[$field]['type'] ?? null) === 'number' ? $field : null;
    }

    /**
     * Label tahap dari alur kerja preset. Item menu berpola papan tidak
     * mewajibkan alur kerja, jadi ketiadaannya bukan error.
     *
     * @return array<string, string>
     */
    private function stageLabels(string $entity): array
    {
        $labels = [];
        foreach ($this->workflow($entity)['stages'] ?? [] as $stage) {
            if (is_array($stage) && isset($stage['code'], $stage['label'])) {
                $labels[(string) $stage['code']] = (string) $stage['label'];
            }
        }

        return $labels;
    }

    /** @return list<string> */
    private function terminalStages(string $entity): array
    {
        $terminal = $this->workflow($entity)['terminal'] ?? [];

        return is_array($terminal)
            ? array_values(array_map(static fn (mixed $code): string => (string) $code, $terminal))
            : [];
    }

    /** @return array<string, mixed> */
    private function workflow(string $entity): array
    {
        $preset = app(PresetSource::class)->find(app(CompanyContext::class)->preset());
        $workflow = $preset['workflows'][$entity] ?? null;

        return is_array($workflow) ? $workflow : [];
    }

    /** @param array<string, mixed> $resource */
    private function resourceLabel(array $resource): string
    {
        $name = $this->stringOrNull($resource['name'] ?? null);

        return $name ?? '#'.($resource['id'] ?? '?');
    }

    /** @param array<string, mixed> $row */
    private function cardTitle(array $row, ?string $titleField): string
    {
        $value = $titleField === null ? null : $this->stringOrNull($row[$titleField] ?? null);

        return $value ?? '#'.$row['id'];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /** @return list<array<string, mixed>> */
    private function rowsFor(string $entity): array
    {
        return app(EntityRepository::class)->for($this->company(), $entity)->all();
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
