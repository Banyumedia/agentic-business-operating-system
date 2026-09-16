<?php

namespace App\Livewire\Screens;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Services\DynamicMenuRegistry;
use App\Services\Schema\EntitySchema;
use App\Services\Schema\SchemaPresenter;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pola layar daftar generik.
 *
 * Entitas dan pola layar tidak pernah datang dari input klien: keduanya
 * diturunkan ulang dari `DynamicMenuRegistry` pada setiap render sehingga
 * kapabilitas yang dicabut langsung menutup layar (fail-closed).
 */
class ListScreen extends Component
{
    private const PER_PAGE = 10;

    #[Locked]
    public string $module;

    #[Locked]
    public ?string $submodule = null;

    /**
     * Company dipaku saat mount. Tanpa ini, komponen basi dari company lama
     * dapat mengeksekusi edit/hapus pada company yang baru aktif dengan id yang
     * kebetulan sama.
     */
    #[Locked]
    public string $company;

    public string $search = '';

    public string $sort = 'id';

    public string $direction = 'asc';

    public int $page = 1;

    public bool $editing = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public ?int $deletingId = null;

    public ?string $notice = null;

    public ?string $failure = null;

    public function mount(string $module, ?string $submodule = null): void
    {
        $this->module = $module;
        $this->submodule = $submodule;
        $this->company = app(CompanyContext::class)->current();
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function sortBy(string $field): void
    {
        $allowed = array_column($this->presenter()->columns($this->schema()), 'field');

        if (! in_array($field, $allowed, true)) {
            return;
        }

        if ($this->sort === $field) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $field;
            $this->direction = 'asc';
        }

        $this->page = 1;
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function create(): void
    {
        $this->resetFeedback();
        $this->editing = true;
        $this->editingId = null;
        $this->form = $this->presenter()->blank($this->schema());
    }

    public function edit(int $id): void
    {
        $this->resetFeedback();
        $row = $this->repository()->find($id);

        if ($row === null) {
            $this->failure = 'Data tidak ditemukan atau sudah dihapus.';

            return;
        }

        $blank = $this->presenter()->blank($this->schema());
        $this->form = array_replace($blank, array_intersect_key($row, $blank));
        $this->editing = true;
        $this->editingId = $id;
    }

    public function cancel(): void
    {
        $this->editing = false;
        $this->editingId = null;
        $this->form = [];
        $this->resetFeedback();
    }

    public function save(): void
    {
        $this->resetFeedback();
        $schema = $this->schema();
        $presenter = $this->presenter();
        $repository = $this->repository();

        $record = $this->editingId === null ? [] : ($repository->find($this->editingId) ?? []);

        if ($this->editingId !== null && $record === []) {
            $this->failure = 'Data tidak ditemukan atau sudah dihapus.';
            $this->cancel();

            return;
        }

        foreach ($presenter->fields($schema) as $field) {
            $name = $field['field'];
            $record[$name] = $presenter->cast($schema, $name, $this->form[$name] ?? null);
        }

        $record = $this->stampTimestamps($schema, $record, $this->editingId === null);

        try {
            $saved = $repository->save($record);
        } catch (InvalidArgumentException $exception) {
            $this->failure = $exception->getMessage();

            return;
        }

        $this->notice = $this->editingId === null
            ? 'Data baru tersimpan.'
            : 'Perubahan tersimpan.';
        $this->editingId = $saved['id'] ?? null;
        $this->editing = false;
        $this->form = [];
    }

    public function confirmDelete(int $id): void
    {
        $this->resetFeedback();
        $this->deletingId = $id;
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
    }

    public function delete(): void
    {
        $this->resetFeedback();

        if ($this->deletingId === null) {
            return;
        }

        $removed = $this->repository()->delete($this->deletingId);
        $this->notice = $removed
            ? 'Data dihapus.'
            : 'Data tidak ditemukan atau sudah dihapus.';
        $this->deletingId = null;
    }

    public function render(): View
    {
        $definition = $this->definition();
        $schema = $this->schema();
        $presenter = $this->presenter();
        $columns = $presenter->columns($schema);
        $allowedSort = array_column($columns, 'field');

        $sort = in_array($this->sort, $allowedSort, true) ? $this->sort : ($allowedSort[0] ?? 'id');
        $direction = $this->direction === 'desc' ? 'desc' : 'asc';

        $result = $this->repository()->query([
            '_search' => $this->search,
            '_sort' => $sort,
            '_direction' => $direction,
            '_page' => max(1, $this->page),
            '_per_page' => self::PER_PAGE,
        ]);

        $pending = $this->deletingId === null ? null : $this->repository()->find($this->deletingId);

        return view('livewire.screens.list', [
            'label' => $definition['label'],
            'term' => $definition['term'] ?? $definition['label'],
            'entity' => $definition['entity'],
            'columns' => $columns,
            'fields' => $presenter->fields($schema),
            'rows' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'lastPage' => $result['last_page'],
            'sort' => $sort,
            'direction' => $direction,
            'pendingDeletion' => $pending,
        ]);
    }

    /**
     * Menandai waktu hanya bila schema mendeklarasikan kolomnya.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function stampTimestamps(EntitySchema $schema, array $record, bool $isNew): array
    {
        $properties = $schema->properties();
        $now = now()->toIso8601String();

        if ($isNew && array_key_exists('created_at', $properties)) {
            $record['created_at'] = $now;
        }

        if (array_key_exists('updated_at', $properties)) {
            $record['updated_at'] = $now;
        }

        return $record;
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

    private function schema(): EntitySchema
    {
        return EntitySchema::load($this->definition()['entity']);
    }

    private function presenter(): SchemaPresenter
    {
        return app(SchemaPresenter::class);
    }

    private function repository(): EntityRepository
    {
        return app(EntityRepository::class)->for($this->company(), $this->definition()['entity']);
    }

    private function company(): string
    {
        // Fail-closed: layar menolak melayani company yang berbeda dari saat mount.
        abort_unless(app(CompanyContext::class)->current() === $this->company, 403);

        return $this->company;
    }

    private function resetFeedback(): void
    {
        $this->notice = null;
        $this->failure = null;
    }
}
