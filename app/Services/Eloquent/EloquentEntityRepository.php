<?php

namespace App\Services\Eloquent;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Services\Schema\EntitySchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class EloquentEntityRepository implements EntityRepository
{
    private ?string $companyId = null;

    private ?string $entity = null;

    private ?string $modelClass = null;

    public function __construct(
        private readonly CompanyContext $companyContext,
    ) {}

    public function for(string $company, string $entity): static
    {
        if ($company !== (string) $this->companyContext->current()) {
            throw new LogicException('Akses lintas company ditolak.');
        }

        $repository = clone $this;
        $repository->companyId = $company;
        $repository->entity = $entity;

        $studly = Str::studly(Str::singular($entity));

        $map = [
            'item_batches' => 'ItemBatch',
            'cash_entries' => 'CashEntry',
            'pos_shifts' => 'PosShift',
        ];

        $studly = $map[$entity] ?? $studly;

        $repository->modelClass = 'App\\Models\\'.$studly;

        if (! class_exists($repository->modelClass)) {
            throw new LogicException("Model tidak ditemukan untuk entity: {$entity}");
        }

        return $repository;
    }

    public function all(): array
    {
        $this->assertScoped();
        $model = new $this->modelClass;

        return $model->where('company_id', $this->companyId)->get()->toArray();
    }

    public function find(string|int $id): ?array
    {
        $this->assertScoped();
        $model = new $this->modelClass;
        $row = $model->where('company_id', $this->companyId)->find($id);

        return $row ? $row->toArray() : null;
    }

    public function save(array $record): array
    {
        $this->assertScoped();

        $modelClass = $this->modelClass;
        $id = $record['id'] ?? null;

        $record['company_id'] = $this->companyId;

        if ($id) {
            $model = $modelClass::where('company_id', $this->companyId)->findOrFail($id);
            $model->update($record);

            return $model->fresh()->toArray();
        }

        $model = $modelClass::create($record);

        return $model->fresh()->toArray();
    }

    public function saveAggregate(
        array $parent,
        string $childEntity,
        string $foreignKey,
        array $children,
        ?string $idempotencyField = null,
        array $guards = [],
    ): array {
        $this->assertScoped();

        return DB::transaction(function () use ($parent, $childEntity, $foreignKey, $children, $idempotencyField, $guards) {

            $parentModelClass = $this->modelClass;

            if ($idempotencyField !== null && array_key_exists($idempotencyField, $parent)) {
                $existing = $parentModelClass::where('company_id', $this->companyId)
                    ->where($idempotencyField, $parent[$idempotencyField])
                    ->first();

                if ($existing) {
                    $childRepo = $this->for($this->companyId, $childEntity);
                    $childModelClass = $childRepo->modelClass;
                    $existingChildren = $childModelClass::where($foreignKey, $existing->id)->get()->toArray();

                    return [
                        'parent' => $existing->toArray(),
                        'children' => $existingChildren,
                        'replayed' => true,
                    ];
                }
            }

            foreach ($guards as $guard) {
                $guardRepo = clone $this;
                $guardRepo = $guardRepo->for($this->companyId, $guard['entity']);
                $guardModelClass = $guardRepo->modelClass;

                $guardRow = $guardModelClass::where('company_id', $this->companyId)->find($guard['id']);
                if (! $guardRow) {
                    throw new InvalidArgumentException('Data acuan transaksi tidak lagi tersedia.');
                }

                foreach ($guard['expected'] as $field => $expected) {
                    if ($guardRow->{$field} !== $expected) {
                        throw new InvalidArgumentException('Data acuan transaksi berubah; muat ulang sebelum melanjutkan.');
                    }
                }
            }

            $parent['company_id'] = $this->companyId;
            $parentModel = $parentModelClass::create($parent);

            $childRepo = $this->for($this->companyId, $childEntity);
            $childModelClass = $childRepo->modelClass;

            $savedChildren = [];
            foreach ($children as $child) {
                $child['company_id'] = $this->companyId;
                $child[$foreignKey] = $parentModel->id;
                $childModel = $childModelClass::create($child);
                $savedChildren[] = $childModel->toArray();
            }

            return [
                'parent' => $parentModel->toArray(),
                'children' => $savedChildren,
                'replayed' => false,
            ];
        });
    }

    public function delete(string|int $id): bool
    {
        $this->assertScoped();
        $modelClass = $this->modelClass;
        $model = $modelClass::where('company_id', $this->companyId)->find($id);

        if (! $model) {
            return false;
        }

        return $model->delete() > 0;
    }

    public function query(array $filters = []): array
    {
        $this->assertScoped();

        $schema = EntitySchema::load($this->entity);
        $properties = array_keys($schema->properties());
        $sort = $filters['_sort'] ?? 'id';
        $direction = strtolower((string) ($filters['_direction'] ?? 'asc'));
        $page = filter_var($filters['_page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $perPage = filter_var($filters['_per_page'] ?? 25, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        $search = $filters['_search'] ?? null;

        if (! is_string($sort) || ! in_array($sort, $properties, true)) {
            throw new InvalidArgumentException('Field sort tidak diizinkan.');
        }

        $modelClass = $this->modelClass;
        $query = $modelClass::where('company_id', $this->companyId);

        $criteria = array_diff_key($filters, array_flip(['_sort', '_direction', '_page', '_per_page', '_search']));
        foreach ($criteria as $field => $value) {
            if (! in_array($field, $properties, true)) {
                throw new InvalidArgumentException("Filter tidak diizinkan: {$field}");
            }
            $query->where($field, $value);
        }

        if (is_string($search) && trim($search) !== '') {
            $term = trim($search);
            $textFields = array_keys(array_filter(
                $schema->properties(),
                static fn (array $definition): bool => ($definition['type'] ?? null) === 'string',
            ));

            if (! empty($textFields)) {
                $query->where(function ($q) use ($textFields, $term) {
                    foreach ($textFields as $field) {
                        $q->orWhere($field, 'LIKE', '%'.$term.'%');
                    }
                });
            }
        }

        $total = $query->count();
        $rows = $query->orderBy($sort, $direction)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    private function assertScoped(): void
    {
        if ($this->companyId === null || $this->entity === null) {
            throw new LogicException('Repository belum memiliki scope.');
        }

        if ((string) $this->companyId !== (string) $this->companyContext->current()) {
            throw new LogicException('Scope repository tidak lagi sesuai dengan company aktif.');
        }
    }
}
