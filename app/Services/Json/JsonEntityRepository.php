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
        $path = $this->path();
        (new Filesystem)->ensureDirectoryExists(dirname($path));

        $lock = $this->acquireLock($path);

        try {
            $rows = $this->readRows();

            if (! array_key_exists('id', $record) || $record['id'] === null) {
                $record['id'] = $this->nextId($rows);
            }

            $record = $this->validator->validate($this->entity, $record);
            $this->assertNoOverlap($record, $rows);

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

            $this->writeRows($path, $rows);
        } finally {
            $this->releaseLock($lock);
        }

        return $record;
    }

    public function delete(string|int $id): bool
    {
        $this->assertScoped();
        $path = $this->path();

        if (! is_file($path)) {
            return false;
        }

        $lock = $this->acquireLock($path);

        try {
            $rows = $this->readRows();
            $remaining = array_values(array_filter(
                $rows,
                static fn (array $row): bool => (string) ($row['id'] ?? '') !== (string) $id,
            ));

            if (count($remaining) === count($rows)) {
                return false;
            }

            $this->writeRows($path, $remaining);
        } finally {
            $this->releaseLock($lock);
        }

        return true;
    }

    /**
     * Menolak rentang waktu yang bertumpang-tindih bila schema entitas
     * mendeklarasikan `no_overlap`. Aturannya data, bukan kode per entitas,
     * sehingga adapter Fase 3 dapat memasangnya sebagai constraint database.
     *
     * Batas yang bersentuhan (`end` sama dengan `start` berikutnya) dianggap sah
     * supaya slot berurutan tetap bisa dibuat.
     *
     * @param  array<string, mixed>  $record
     * @param  list<array<string, mixed>>  $rows
     */
    private function assertNoOverlap(array $record, array $rows): void
    {
        $rule = EntitySchema::load($this->scopedEntity())->noOverlap();
        if ($rule === null) {
            return;
        }

        $start = $record[$rule['start']] ?? null;
        $end = $record[$rule['end']] ?? null;
        if (! is_string($start) || ! is_string($end)) {
            return;
        }

        $newStart = strtotime($start);
        $newEnd = strtotime($end);
        if ($newStart === false || $newEnd === false) {
            return;
        }

        foreach ($rows as $row) {
            if ((string) ($row['id'] ?? '') === (string) $record['id']) {
                continue;
            }

            foreach ($rule['scope'] as $field) {
                if (($row[$field] ?? null) !== ($record[$field] ?? null)) {
                    continue 2;
                }
            }

            $existingStart = strtotime((string) ($row[$rule['start']] ?? ''));
            $existingEnd = strtotime((string) ($row[$rule['end']] ?? ''));
            if ($existingStart === false || $existingEnd === false) {
                continue;
            }

            if ($newStart < $existingEnd && $existingStart < $newEnd) {
                throw new InvalidArgumentException(
                    "Jadwal bertumpang-tindih dengan baris #{$row['id']} pada sumber daya yang sama."
                );
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function nextId(array $rows): int
    {
        $highest = 0;

        foreach ($rows as $row) {
            $id = $row['id'] ?? null;
            if (is_int($id) && $id > $highest) {
                $highest = $id;
            }
        }

        return $highest + 1;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeRows(string $path, array $rows): void
    {
        $contents = json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
        $this->writeAtomically($path, $contents);
    }

    /** @return resource */
    private function acquireLock(string $path)
    {
        $lock = fopen($path.'.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Lock repository tidak dapat dibuat.');
        }

        if (! flock($lock, LOCK_EX)) {
            fclose($lock);

            throw new RuntimeException('Lock repository tidak dapat diperoleh.');
        }

        return $lock;
    }

    /** @param resource $lock */
    private function releaseLock($lock): void
    {
        flock($lock, LOCK_UN);
        fclose($lock);
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

        $search = $filters['_search'] ?? null;
        if ($search !== null && ! is_string($search)) {
            throw new InvalidArgumentException('Kata pencarian harus string.');
        }

        $criteria = array_diff_key($filters, array_flip(['_sort', '_direction', '_page', '_per_page', '_search']));
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

        $rows = $this->applySearch($rows, $schema, is_string($search) ? trim($search) : '');

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

    /**
     * Pencarian bebas sebagai substring case-insensitive pada seluruh properti
     * bertipe string. Field target diturunkan dari schema, bukan dari pemanggil,
     * sehingga adapter Eloquent Fase 3 dapat menerjemahkannya menjadi `LIKE`.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function applySearch(array $rows, EntitySchema $schema, string $term): array
    {
        if ($term === '') {
            return $rows;
        }

        $textFields = array_keys(array_filter(
            $schema->properties(),
            static fn (array $definition): bool => ($definition['type'] ?? null) === 'string',
        ));

        return array_values(array_filter($rows, static function (array $row) use ($textFields, $term): bool {
            foreach ($textFields as $field) {
                $value = $row[$field] ?? null;
                if (is_string($value) && mb_stripos($value, $term) !== false) {
                    return true;
                }
            }

            return false;
        }));
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
            $bytesWritten = $this->writeTemporaryFile($temporary, $contents);
            if ($bytesWritten !== strlen($contents) || ! rename($temporary, $path)) {
                throw new RuntimeException('Data repository tidak dapat diganti secara atomik.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    protected function writeTemporaryFile(string $path, string $contents): int|false
    {
        return file_put_contents($path, $contents);
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
