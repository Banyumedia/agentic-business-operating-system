<?php

namespace App\Livewire\Screens;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Services\DynamicMenuRegistry;
use App\Services\Schema\EntitySchema;
use App\Services\Schema\SchemaPresenter;
use App\Services\Workflow\ArrayWorkflowRecord;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Pola layar papan tahap (kanban) generik.
 *
 * Kolom, transisi yang ditawarkan, kewajiban catatan, dan penahanan approval
 * seluruhnya berasal dari `WorkflowEngine` (yaitu dari preset). Tidak ada daftar
 * tahap maupun aturan alur di kelas ini (D-31/D-46).
 */
class PipelineScreen extends Component
{
    /** Nama field tahap mengikuti konvensi schema, bukan istilah bisnis. */
    private const STAGE_FIELD = 'stage';

    #[Locked]
    public string $module;

    #[Locked]
    public ?string $submodule = null;

    #[Locked]
    public string $company;

    #[Locked]
    public ?int $pendingId = null;

    #[Locked]
    public ?string $pendingStage = null;

    public string $note = '';

    public ?string $notice = null;

    public ?string $failure = null;

    public function mount(string $module, ?string $submodule = null): void
    {
        $this->module = $module;
        $this->submodule = $submodule;
        $this->company = app(CompanyContext::class)->current();
    }

    /**
     * Menawarkan perpindahan tahap. Transisi yang menuntut alasan menurut alur
     * kerja (D-46 mewajibkannya untuk perpindahan mundur) tidak langsung
     * dijalankan: layar meminta catatan lebih dulu supaya alasannya tercatat.
     */
    public function move(int $id, string $to): void
    {
        $this->resetFeedback();

        $transition = $this->allowedTransition($id, $to);
        if ($transition === null) {
            $this->failure = 'Perpindahan tahap itu tidak tersedia untuk peran atau tahap saat ini.';

            return;
        }

        if (($transition['requires_note'] ?? false) === true) {
            $this->pendingId = $id;
            $this->pendingStage = $to;
            $this->note = '';

            return;
        }

        $this->apply($id, $to, null);
    }

    public function confirmMove(): void
    {
        $this->resetFeedback();

        if ($this->pendingId === null || $this->pendingStage === null) {
            return;
        }

        if (trim($this->note) === '') {
            $this->failure = 'Perpindahan ini wajib disertai alasan.';

            return;
        }

        $this->apply($this->pendingId, $this->pendingStage, $this->note);
    }

    public function cancelMove(): void
    {
        $this->pendingId = null;
        $this->pendingStage = null;
        $this->note = '';
    }

    public function render(): View
    {
        $definition = $this->definition();
        $entity = $definition['entity'];
        $schema = EntitySchema::load($entity);
        $engine = app(WorkflowEngine::class);
        $presenter = app(SchemaPresenter::class);

        $stages = $engine->stages($entity);
        $stageCodes = array_column($stages, 'code');
        $display = $this->displayFields($presenter, $schema);

        $columns = [];
        foreach ($stages as $stage) {
            $columns[$stage['code']] = ['code' => $stage['code'], 'label' => $stage['label'], 'cards' => []];
        }

        $orphans = 0;
        foreach ($this->repository()->all() as $row) {
            $stage = $row[self::STAGE_FIELD] ?? null;

            if (! is_string($stage) || ! in_array($stage, $stageCodes, true)) {
                $orphans++;

                continue;
            }

            $columns[$stage]['cards'][] = [
                'id' => $row['id'],
                'title' => $this->cardTitle($row, $display),
                'meta' => $this->cardMeta($row, $display),
                'transitions' => $this->transitionsFor($engine, $row, $stages),
            ];
        }

        return view('livewire.screens.pipeline', [
            'label' => $definition['label'],
            'term' => $definition['term'] ?? $definition['label'],
            'entity' => $entity,
            'columns' => array_values($columns),
            'orphans' => $orphans,
            'pendingCard' => $this->pendingId === null ? null : $this->repository()->find($this->pendingId),
            'pendingStageLabel' => $this->stageLabel($stages, $this->pendingStage),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array{code: string, label: string}>  $stages
     * @return list<array{to: string, label: string, requires_note: bool}>
     */
    private function transitionsFor(WorkflowEngine $engine, array $row, array $stages): array
    {
        $record = $this->workflowRecord($row);
        if ($record === null) {
            return [];
        }

        try {
            $transitions = $engine->availableTransitions($record, $this->actorRole());
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
                'label' => $this->stageLabel($stages, $to) ?? $to,
                'requires_note' => ($transition['requires_note'] ?? false) === true,
            ];
        }

        return array_values($offered);
    }

    /** @return array<string, mixed>|null */
    private function allowedTransition(int $id, string $to): ?array
    {
        $row = $this->repository()->find($id);
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

    private function apply(int $id, string $to, ?string $note): void
    {
        $repository = $this->repository();
        $row = $repository->find($id);

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

        // Transisi ber-approval ditahan: stage TIDAK ikut disimpan.
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

    /**
     * Field yang dipakai kartu diturunkan dari schema: judul memakai kolom string
     * pertama selain `id`, sisanya menjadi baris keterangan.
     *
     * @return array{title: string|null, meta: list<array{field: string, label: string}>}
     */
    private function displayFields(SchemaPresenter $presenter, EntitySchema $schema): array
    {
        $columns = array_values(array_filter(
            $presenter->columns($schema),
            static fn (array $column): bool => $column['field'] !== 'id' && $column['field'] !== self::STAGE_FIELD,
        ));

        $title = $presenter->titleField($schema);
        foreach ($columns as $index => $column) {
            if ($column['field'] === $title) {
                unset($columns[$index]);
                break;
            }
        }

        return [
            'title' => $title,
            'meta' => array_map(
                static fn (array $column): array => ['field' => $column['field'], 'label' => $column['label']],
                array_slice(array_values($columns), 0, 2),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{title: string|null, meta: list<array{field: string, label: string}>}  $display
     */
    private function cardTitle(array $row, array $display): string
    {
        $value = $display['title'] === null ? null : ($row[$display['title']] ?? null);

        return is_string($value) && trim($value) !== '' ? $value : '#'.$row['id'];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{title: string|null, meta: list<array{field: string, label: string}>}  $display
     * @return list<array{label: string, value: string}>
     */
    private function cardMeta(array $row, array $display): array
    {
        $meta = [];
        foreach ($display['meta'] as $field) {
            $value = $row[$field['field']] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $meta[] = [
                'label' => $field['label'],
                'value' => is_bool($value) ? ($value ? 'Ya' : 'Tidak') : (string) $value,
            ];
        }

        return $meta;
    }

    /** @param list<array{code: string, label: string}> $stages */
    private function stageLabel(array $stages, ?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        foreach ($stages as $stage) {
            if ($stage['code'] === $code) {
                return $stage['label'];
            }
        }

        return null;
    }

    /** Peran selalu dibaca ulang dari sesi server, tidak dari state komponen. */
    private function actorRole(): string
    {
        return session('company_role') === 'owner' ? 'owner' : 'staff';
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

    private function resetFeedback(): void
    {
        $this->notice = null;
        $this->failure = null;
    }
}
