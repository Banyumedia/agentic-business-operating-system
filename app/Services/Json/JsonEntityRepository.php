<?php

namespace App\Services\Json;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Services\Schema\EntitySchema;
use App\Services\Schema\SchemaValidator;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use JsonException;
use LogicException;
use RuntimeException;

class JsonEntityRepository implements EntityRepository
{
    private ?string $company = null;

    private ?string $entity = null;

    public function __construct(
        private readonly CompanyContext $companyContext,
        private readonly SchemaValidator $validator,
    ) {}

    public function for(string $company, string $entity): static
    {
        $this->assertIdentifier($company, 'Company');
        $this->assertIdentifier($entity, 'Entity');

        if ($company !== $this->companyContext->current()) {
            throw new LogicException('Akses lintas company ditolak.');
        }

        EntitySchema::load($entity);

        $repository = clone $this;
        $repository->company = $company;
        $repository->entity = $entity;

        return $repository;
    }

    public function all(): array
    {
        return $this->readRows();
    }

    public function find(string|int $id): ?array
    {
        foreach ($this->readRows() as $row) {
            if ((string) ($row['id'] ?? '') === (string) $id) {
                return $row;
            }
        }

        return null;
    }

    public function save(array $record): array
    {
        $this->assertScoped();
        $record = $this->validator->validate($this->entity, $record);
        $path = $this->path();
        (new Filesystem)->ensureDirectoryExists(dirname($path));

        $lock = fopen($path.'.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Lock repository tidak dapat dibuat.');
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Lock repository tidak dapat diperoleh.');
            }

            $rows = $this->readRows();
            $updated = false;
            foreach ($rows as $index => $row) {
                if ((string) $row['id'] === (string) $record['id']) {
                    $rows[$index] = $record;
                    $updated = true;
                    break;
                }
            }

            if (! $updated) {
                $rows[] = $record;
            }

            $contents = json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
            $this->writeAtomically($path, $contents);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $record;
    }

    public function query(array $filters = []): array
    {
        $schema = EntitySchema::load($this->scopedEntity());
        $properties = array_keys($schema->properties());
        $sort = $filters['_sort'] ?? 'id';
        $direction = strtolower((string) ($filters['_direction'] ?? 'asc'));
        $page = filter_var($filters['_page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $perPage = filter_var($filters['_per_page'] ?? 25, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);

        if (! is_string($sort) || ! in_array($sort, $properties, true)) {
            throw new InvalidArgumentException('Field sort tidak diizinkan.');
        }
        if (! in_array($direction, ['asc', 'desc'], true) || $page === false || $perPage === false) {
            throw new InvalidArgumentException('Parameter pagination atau arah sort tidak valid.');
        }

        $criteria = array_diff_key($filters, array_flip(['_sort', '_direction', '_page', '_per_page']));
        foreach (array_keys($criteria) as $field) {
            if (! in_array($field, $properties, true)) {
                throw new InvalidArgumentException("Filter tidak diizinkan: {$field}");
            }
        }

        $rows = array_values(array_filter($this->readRows(), static function (array $row) use ($criteria): bool {
            foreach ($criteria as $field => $value) {
                if (! array_key_exists($field, $row) || $row[$field] !== $value) {
                    return false;
                }
            }

            return true;
        }));

        usort($rows, static function (array $left, array $right) use ($sort, $direction): int {
            $comparison = ($left[$sort] ?? null) <=> ($right[$sort] ?? null);

            return $direction === 'desc' ? -$comparison : $comparison;
        });

        $total = count($rows);

        return [
            'data' => array_slice($rows, ($page - 1) * $perPage, $perPage),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function readRows(): array
    {
        $path = $this->path();
        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Tidak dapat membaca data [$path].");
        }

        if (! str_starts_with(ltrim($contents), '[')) {
            throw new JsonException("Root data [$path] harus berupa JSON array.");
        }

        $rows = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new JsonException('Root data repository harus array JSON.');
        }

        foreach ($rows as $row) {
            if (! is_array($row) || array_is_list($row)) {
                throw new JsonException('Setiap row repository harus object JSON.');
            }
            $this->validator->validate($this->scopedEntity(), $row);
        }

        return $rows;
    }

    private function path(): string
    {
        $this->assertScoped();
        $root = (string) config('datasource.json_path', storage_path('app/json'));

        return rtrim($root, '/\\').DIRECTORY_SEPARATOR.$this->company.DIRECTORY_SEPARATOR.$this->entity.'.json';
    }

    private function writeAtomically(string $path, string $contents): void
    {
        $temporary = tempnam(dirname($path), basename($path).'.tmp-');
        if ($temporary === false) {
            throw new RuntimeException('File sementara tidak dapat dibuat.');
        }

        try {
            if (file_put_contents($temporary, $contents) === false || ! rename($temporary, $path)) {
                throw new RuntimeException('Data repository tidak dapat diganti secara atomik.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function scopedEntity(): string
    {
        $this->assertScoped();

        return $this->entity;
    }

    private function assertScoped(): void
    {
        if ($this->company === null || $this->entity === null) {
            throw new LogicException('Repository belum memiliki scope.');
        }

        if ($this->company !== $this->companyContext->current()) {
            throw new LogicException('Scope repository tidak lagi sesuai dengan company aktif.');
        }
    }

    private function assertIdentifier(string $value, string $label): void
    {
        if (! preg_match('/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/', $value)) {
            throw new InvalidArgumentException("{$label} tidak valid.");
        }
    }
}
