<?php

namespace App\Livewire\Screens;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Models\WorkflowTransitionLog;
use App\Services\DynamicMenuRegistry;
use App\Services\Schema\EntitySchema;
use App\Services\Schema\SchemaPresenter;
use App\Services\Workflow\ArrayWorkflowRecord;
use App\Services\Workflow\JsonWorkflowLog;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Pola layar detail generik (MP-01).
 *
 * Satu kelas untuk seluruh entitas berskema, bukan satu layar per entitas
 * (D-31/D-42): field dari `SchemaPresenter`, baris anak dari relasi schema
 * (entitas lain yang menunjuk balik ke entitas ini), istilah lewat `term()`,
 * dan linimasa dari log workflow yang sudah ada. Transisi tahap TIDAK
 * diduplikasi di sini - selalu lewat `WorkflowEngine`, dan hanya transisi sah
 * dari tahap sekarang yang ditawarkan (D-46, §6.9 UX_UI_SPEC), mengikuti pola
 * `PipelineScreen`.
 */
class DetailScreen extends Component
{
    #[Locked]
    public string $module;

    #[Locked]
    public string $company;

    #[Locked]
    public int $id;

    #[Locked]
    public ?string $pendingStage = null;

    public string $note = '';

    public ?string $notice = null;

    public ?string $failure = null;

    public function mount(string $module, int $id): void
    {
        $this->module = $module;
        $this->id = $id;
        $this->company = app(CompanyContext::class)->current();
    }

    /**
     * Menawarkan perpindahan tahap, sama seperti `PipelineScreen::move()`.
     * Transisi yang menuntut alasan (D-46, perpindahan mundur) tidak langsung
     * dijalankan - layar meminta catatan lebih dulu.
     */
    public function move(string $to): void
    {
        $this->resetFeedback();

        $transition = $this->allowedTransition($to);
        if ($transition === null) {
            $this->failure = 'Perpindahan tahap itu tidak tersedia untuk peran atau tahap saat ini.';

            return;
        }

        if (($transition['requires_note'] ?? false) === true) {
            $this->pendingStage = $to;
            $this->note = '';

            return;
        }

        $this->apply($to, null);
    }

    public function confirmMove(): void
    {
        $this->resetFeedback();

        if ($this->pendingStage === null) {
            return;
        }

        if (trim($this->note) === '') {
            $this->failure = 'Perpindahan ini wajib disertai alasan.';

            return;
        }

        $this->apply($this->pendingStage, $this->note);
    }

    public function cancelMove(): void
    {
        $this->pendingStage = null;
        $this->note = '';
    }

    public function render(): View
    {
        $definition = $this->definition();
        $entity = $definition['entity'];
        $schema = EntitySchema::load($entity);
        $presenter = app(SchemaPresenter::class);

        $row = $this->repository()->find($this->id);
        abort_if($row === null, 404);

        $titleField = $presenter->titleField($schema);
        $title = $titleField !== null && is_string($row[$titleField] ?? null) && trim($row[$titleField]) !== ''
            ? $row[$titleField]
            : '#'.$row['id'];

        $fields = array_values(array_filter(
            $presenter->columns($schema),
            static fn (array $column): bool => $column['field'] !== 'id',
        ));

        return view('livewire.screens.detail', [
            'label' => $definition['label'],
            'term' => $definition['term'] ?? $definition['label'],
            'entity' => $entity,
            'title' => $title,
            'row' => $row,
            'fields' => $fields,
            'childRelations' => $this->childRows($entity),
            'timeline' => $this->timeline($entity),
            'stageLabel' => $this->currentStageLabel($entity, $row),
            'transitions' => $this->transitionsFor($row),
            'pendingStageLabel' => $this->pendingStage === null ? null : $this->stageLabelFor($entity, $this->pendingStage),
        ]);
    }

    /**
     * Baris anak: entitas lain yang schema-nya menunjuk balik ke entitas ini
     * (mis. `order_lines` -> `orders`). Diturunkan dari katalog schema, bukan
     * didaftar per entitas (D-31) - entitas baru dengan FK ke entitas ini
     * langsung muncul di sini tanpa menyentuh kelas ini.
     *
     * @return list<array{entity: string, term: string, rows: list<array<string, mixed>>, columns: list<array{field: string, label: string}>}>
     */
    private function childRows(string $entity): array
    {
        $children = [];

        foreach (glob(database_path('schemas/*.schema.json')) ?: [] as $path) {
            $childEntity = basename($path, '.schema.json');
            if ($childEntity === $entity) {
                continue;
            }

            try {
                $childSchema = EntitySchema::load($childEntity);
            } catch (InvalidArgumentException) {
                continue;
            }

            $foreignKey = null;
            foreach ($childSchema->references() as $field => $reference) {
                if (($reference['entity'] ?? null) === $entity) {
                    $foreignKey = $field;
                    break;
                }
            }

            if ($foreignKey === null) {
                continue;
            }

            try {
                $rows = array_values(array_filter(
                    app(EntityRepository::class)->for($this->company(), $childEntity)->all(),
                    fn (array $row): bool => (string) ($row[$foreignKey] ?? '') === (string) $this->id,
                ));
            } catch (Throwable) {
                continue;
            }

            if ($rows === []) {
                continue;
            }

            $presenter = app(SchemaPresenter::class);
            $columns = array_values(array_filter(
                $presenter->columns($childSchema),
                static fn (array $column): bool => $column['field'] !== 'id',
            ));

            $children[] = [
                'entity' => $childEntity,
                'term' => $this->childTerm($childEntity),
                'rows' => $rows,
                'columns' => $columns,
            ];
        }

        return $children;
    }

    /**
     * Istilah relasi anak: `term()` bila entitas anak sudah terdaftar di
     * kamus (D-31), kalau tidak nama tabel dihumanisasi apa adanya. Katalog
     * `TerminologyResolver` sengaja tertutup untuk kata benda utama
     * (`orders`, `contacts`) - tabel relasi seperti `order_lines` tidak wajib
     * masuk ke sana hanya karena punya layar detail induk.
     */
    private function childTerm(string $entity): string
    {
        try {
            return term($entity);
        } catch (InvalidArgumentException) {
            return ucwords(str_replace('_', ' ', $entity));
        }
    }

    /** @return list<array<string, mixed>> */
    private function timeline(string $entity): array
    {
        if (config('datasource.driver') === 'eloquent') {
            return WorkflowTransitionLog::query()
                ->where('company_id', $this->company())
                ->where('entity', $entity)
                ->where('entity_id', $this->id)
                ->orderBy('created_at')
                ->get()
                ->map(static fn (WorkflowTransitionLog $log): array => [
                    'from' => $log->from_stage,
                    'to' => $log->to_stage,
                    'note' => $log->note,
                    'occurred_at' => $log->created_at?->toIso8601String(),
                ])
                ->all();
        }

        try {
            return array_map(static fn (array $entry): array => [
                'from' => $entry['from'] ?? null,
                'to' => $entry['to'] ?? null,
                'note' => $entry['note'] ?? null,
                'occurred_at' => $entry['occurred_at'] ?? null,
            ], app(JsonWorkflowLog::class)->entriesFor($this->company(), $entity, $this->id));
        } catch (Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $row */
    private function currentStageLabel(string $entity, array $row): ?string
    {
        $stage = $row['stage'] ?? null;
        if (! is_string($stage)) {
            return null;
        }

        return $this->stageLabelFor($entity, $stage);
    }

    private function stageLabelFor(string $entity, string $code): ?string
    {
        try {
            $stages = app(WorkflowEngine::class)->stages($entity);
        } catch (Throwable) {
            return null;
        }

        foreach ($stages as $stage) {
            if ($stage['code'] === $code) {
                return $stage['label'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array{to: string, label: string, requires_note: bool}>
     */
    private function transitionsFor(array $row): array
    {
        $record = $this->workflowRecord($row);
        if ($record === null) {
            return [];
        }

        try {
            $transitions = app(WorkflowEngine::class)->availableTransitions($record, $this->actorRole());
        } catch (Throwable) {
            return [];
        }

        $offered = [];
        foreach ($transitions as $transition) {
            $to = $transition['to'];
            if (isset($offered[$to])) {
                continue;
            }

            $offered[$to] = [
                'to' => $to,
                'label' => $this->stageLabelFor($this->definition()['entity'], $to) ?? $to,
                'requires_note' => ($transition['requires_note'] ?? false) === true,
            ];
        }

        return array_values($offered);
    }

    private function allowedTransition(string $to): ?array
    {
        $row = $this->repository()->find($this->id);
        if ($row === null) {
            return null;
        }

        $record = $this->workflowRecord($row);
        if ($record === null) {
            return null;
        }

        try {
            $transitions = app(WorkflowEngine::class)->availableTransitions($record, $this->actorRole());
        } catch (Throwable) {
            return null;
        }

        foreach ($transitions as $transition) {
            if ($transition['to'] === $to) {
                return $transition;
            }
        }

        return null;
    }

    private function apply(string $to, ?string $note): void
    {
        $repository = $this->repository();
        $row = $repository->find($this->id);

        if ($row === null) {
            $this->failure = 'Data tidak ditemukan atau sudah dihapus.';
            $this->cancelMove();

            return;
        }

        $record = $this->workflowRecord($row);
        if ($record === null) {
            $this->failure = 'Baris ini belum memiliki tahap yang sah.';
            $this->cancelMove();

            return;
        }

        try {
            $result = app(WorkflowEngine::class)->transition($record, $to, $this->actorRole(), $note);
        } catch (AuthorizationException) {
            $this->failure = 'Peran Anda tidak berwenang melakukan perpindahan itu.';

            return;
        } catch (InvalidArgumentException $exception) {
            $this->failure = $exception->getMessage();

            return;
        }

        if ($result['status'] !== 'transitioned') {
            $this->notice = 'Perpindahan ditahan sampai pemilik menyetujui.';
            $this->cancelMove();

            return;
        }

        $repository->save($record->toArray());
        $this->notice = 'Tahap diperbarui.';
        $this->cancelMove();
    }

    /** @param array<string, mixed> $row */
    private function workflowRecord(array $row): ?ArrayWorkflowRecord
    {
        try {
            return new ArrayWorkflowRecord($this->company(), $this->definition()['entity'], $row);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function actorRole(): string
    {
        return session('company_role') === 'owner' ? 'owner' : 'staff';
    }

    /** @return array{label: string, icon: string, route: string, screen: string, entity: string, term: string|null} */
    private function definition(): array
    {
        $registry = app(DynamicMenuRegistry::class);

        abort_unless($registry->hasPath($this->module, 'detail'), 404);
        abort_unless($registry->isModuleVisible($this->module), 403);

        $definition = $registry->routeDefinition($this->module, 'detail');
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

    private function resetFeedback(): void
    {
        $this->notice = null;
        $this->failure = null;
    }
}
