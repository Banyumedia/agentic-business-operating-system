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
use Throwable;

class JsonEntityRepository implements EntityRepository
{
    private ?string $company = null;

    private ?string $entity = null;

    private bool $suppressRecovery = false;

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

        $transactionLock = $this->acquireLock($this->transactionLockPath());
        $lock = null;

        try {
            $this->recoverPendingJournals();
            $this->suppressRecovery = true;
            $lock = $this->acquireLock($path);
            $rows = $this->readRows();

            if (! array_key_exists('id', $record) || $record['id'] === null) {
                $record['id'] = $this->nextId($rows);
            }

            $record = $this->validator->validate($this->entity, $record);
            $this->assertNoOverlap($record, $rows);
            $this->assertUnique($record, $rows);

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
            if (is_resource($lock)) {
                $this->releaseLock($lock);
            }
            $this->suppressRecovery = false;
            $this->releaseLock($transactionLock);
        }

        return $record;
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
        $this->assertIdentifier($childEntity, 'Entity');
        EntitySchema::load($childEntity);

        if (! preg_match('/^[a-z][a-z0-9_]*$/', $foreignKey)) {
            throw new InvalidArgumentException('Foreign key aggregate tidak valid.');
        }

        $childRepository = $this->for($this->scopedCompany(), $childEntity);
        if ($childRepository->path() === $this->path()) {
            throw new InvalidArgumentException('Parent dan child aggregate harus berbeda entitas.');
        }

        $guardRepositories = [];
        foreach ($guards as $guard) {
            $guardEntity = $guard['entity'] ?? null;
            if (! is_string($guardEntity)) {
                throw new InvalidArgumentException('Entity guard aggregate tidak valid.');
            }
            $guardRepositories[$guardEntity] = $this->for($this->scopedCompany(), $guardEntity);
        }

        $repositoriesByPath = [];
        foreach ([$this, $childRepository, ...array_values($guardRepositories)] as $repository) {
            $repositoriesByPath[$repository->path()] = $repository;
        }
        $repositories = array_values($repositoriesByPath);
        usort($repositories, static fn (self $left, self $right): int => $left->path() <=> $right->path());

        foreach ($repositories as $repository) {
            (new Filesystem)->ensureDirectoryExists(dirname($repository->path()));
        }

        $transactionLock = $this->acquireLock($this->transactionLockPath());
        $locks = [];
        try {
            $this->recoverPendingJournals();
            foreach ($repositories as $repository) {
                $repository->suppressRecovery = true;
            }
            foreach ($repositories as $repository) {
                $locks[] = $repository->acquireLock($repository->path());
            }

            $parentRows = $this->readRows();
            $childRows = $childRepository->readRows();

            if ($idempotencyField !== null && array_key_exists($idempotencyField, $parent)) {
                foreach ($parentRows as $existing) {
                    if (($existing[$idempotencyField] ?? null) === $parent[$idempotencyField]) {
                        return [
                            'parent' => $existing,
                            'children' => array_values(array_filter(
                                $childRows,
                                static fn (array $row): bool => (string) ($row[$foreignKey] ?? '') === (string) $existing['id'],
                            )),
                            'replayed' => true,
                        ];
                    }
                }
            }

            foreach ($guards as $guard) {
                $guardRepository = $guardRepositories[$guard['entity']];
                $guardRow = null;
                foreach ($guardRepository->readRows() as $row) {
                    if ((string) ($row['id'] ?? '') === (string) $guard['id']) {
                        $guardRow = $row;
                        break;
                    }
                }
                if ($guardRow === null) {
                    throw new InvalidArgumentException('Data acuan transaksi tidak lagi tersedia.');
                }
                foreach ($guard['expected'] as $field => $expected) {
                    if (($guardRow[$field] ?? null) !== $expected) {
                        throw new InvalidArgumentException('Data acuan transaksi berubah; muat ulang sebelum melanjutkan.');
                    }
                }
            }

            if (array_key_exists('id', $parent)) {
                throw new InvalidArgumentException('ID parent aggregate ditetapkan repository.');
            }
            $parent['id'] = $this->nextId($parentRows);
            $parent = $this->validator->validate($this->scopedEntity(), $parent);
            $this->assertNoOverlap($parent, $parentRows);
            $this->assertUnique($parent, $parentRows);

            $savedChildren = [];
            $nextChildId = $childRepository->nextId($childRows);
            foreach ($children as $child) {
                if (array_key_exists('id', $child)) {
                    throw new InvalidArgumentException('ID child aggregate ditetapkan repository.');
                }
                $child['id'] = $nextChildId++;
                $child[$foreignKey] = $parent['id'];
                $child = $this->validator->validate($childEntity, $child);
                $childRepository->assertNoOverlap($child, [...$childRows, ...$savedChildren]);
                $childRepository->assertUnique($child, [...$childRows, ...$savedChildren]);
                $savedChildren[] = $child;
            }

            $parentPath = $this->path();
            $childPath = $childRepository->path();
            $journalPath = dirname($parentPath).DIRECTORY_SEPARATOR.'.aggregate-'.$this->scopedEntity().'-'.$childEntity.'.json';
            $journal = [
                'state' => 'prepared',
                'files' => [
                    $parentPath => [
                        'before' => is_file($parentPath) ? file_get_contents($parentPath) : null,
                        'after' => $this->encodeRows([...$parentRows, $parent]),
                    ],
                    $childPath => [
                        'before' => is_file($childPath) ? file_get_contents($childPath) : null,
                        'after' => $this->encodeRows([...$childRows, ...$savedChildren]),
                    ],
                ],
            ];
            $this->writeAtomically($journalPath, json_encode($journal, JSON_THROW_ON_ERROR));

            try {
                $this->writeAtomically($parentPath, $journal['files'][$parentPath]['after']);
                $this->afterAggregateParentWrite();
                $childRepository->writeAtomically($childPath, $journal['files'][$childPath]['after']);
                $journal['state'] = 'committed';
                $this->writeAtomically($journalPath, json_encode($journal, JSON_THROW_ON_ERROR));
                unlink($journalPath);
            } catch (Throwable $exception) {
                $this->recoverJournal($journalPath, true);

                throw $exception;
            }

            return ['parent' => $parent, 'children' => $savedChildren, 'replayed' => false];
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $this->releaseLock($lock);
            }
            foreach ($repositories as $repository) {
                $repository->suppressRecovery = false;
            }
            $this->releaseLock($transactionLock);
        }
    }

    public function delete(string|int $id): bool
    {
        $this->assertScoped();
        $path = $this->path();

        if (! is_file($path)) {
            return false;
        }

        $transactionLock = $this->acquireLock($this->transactionLockPath());
        $lock = null;

        try {
            $this->recoverPendingJournals();
            $this->suppressRecovery = true;
            $lock = $this->acquireLock($path);
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
            if (is_resource($lock)) {
                $this->releaseLock($lock);
            }
            $this->suppressRecovery = false;
            $this->releaseLock($transactionLock);
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

    /** @param list<array<string, mixed>> $rows */
    private function assertUnique(array $record, array $rows): void
    {
        foreach (EntitySchema::load($this->scopedEntity())->unique() as $constraint) {
            $fields = $constraint['fields'];
            if (! is_array($fields)) {
                continue;
            }

            foreach ($rows as $row) {
                if ((string) ($row['id'] ?? '') === (string) ($record['id'] ?? '')) {
                    continue;
                }

                $matches = true;
                foreach ($fields as $field) {
                    $value = $record[$field] ?? null;
                    if ($value === null || ($row[$field] ?? null) !== $value) {
                        $matches = false;
                        break;
                    }
                }

                if ($matches) {
                    throw new InvalidArgumentException('Nilai unik sudah digunakan: '.implode(', ', $fields));
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeRows(string $path, array $rows): void
    {
        $this->writeAtomically($path, $this->encodeRows($rows));
    }

    /** @param list<array<string, mixed>> $rows */
    private function encodeRows(array $rows): string
    {
        return json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
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
        if (! $this->suppressRecovery) {
            $transactionLock = $this->acquireLock($this->transactionLockPath());
            try {
                $this->recoverPendingJournals();
            } finally {
                $this->releaseLock($transactionLock);
            }
        }
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

    private function transactionLockPath(): string
    {
        $directory = dirname($this->path());
        (new Filesystem)->ensureDirectoryExists($directory);

        return $directory.DIRECTORY_SEPARATOR.'.repository-transaction';
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

    protected function afterAggregateParentWrite(): void {}

    private function recoverPendingJournals(): void
    {
        $directory = dirname($this->path());
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.DIRECTORY_SEPARATOR.'.aggregate-*.json') ?: [] as $journalPath) {
            $this->recoverJournal($journalPath);
        }
    }

    private function recoverJournal(string $journalPath, bool $locksHeld = false): void
    {
        if (! is_file($journalPath)) {
            return;
        }

        $journal = json_decode((string) file_get_contents($journalPath), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($journal) || ! in_array($journal['state'] ?? null, ['prepared', 'committed'], true) || ! is_array($journal['files'] ?? null)) {
            throw new JsonException('Journal aggregate rusak; recovery ditolak.');
        }

        $companyDirectory = dirname($this->path());
        if (count($journal['files']) !== 2) {
            throw new JsonException('Journal aggregate harus memuat tepat dua file entitas.');
        }
        foreach (array_keys($journal['files']) as $path) {
            if (! is_string($path) || dirname($path) !== $companyDirectory || ! preg_match('/^([a-z][a-z0-9_]*)\.json$/', basename($path), $matches)) {
                throw new JsonException('Path journal aggregate keluar dari scope company.');
            }
            EntitySchema::load($matches[1]);
        }

        $recoveryLocks = [];
        if (! $locksHeld) {
            $paths = array_keys($journal['files']);
            sort($paths);
            try {
                foreach ($paths as $path) {
                    if (! is_string($path)) {
                        throw new JsonException('Path journal aggregate tidak valid.');
                    }
                    $lock = fopen($path.'.lock', 'c');
                    if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
                        if (is_resource($lock)) {
                            fclose($lock);
                        }
                        throw new RuntimeException('Transaksi aggregate masih berlangsung.');
                    }
                    $recoveryLocks[] = $lock;
                }
            } catch (Throwable $exception) {
                foreach (array_reverse($recoveryLocks) as $lock) {
                    $this->releaseLock($lock);
                }

                throw $exception;
            }
        }

        try {
            foreach ($journal['files'] as $path => $versions) {
                if (! is_string($path) || ! is_array($versions)) {
                    throw new JsonException('Isi journal aggregate tidak valid.');
                }

                $contents = $journal['state'] === 'committed' ? ($versions['after'] ?? null) : ($versions['before'] ?? null);
                if (is_string($contents)) {
                    $this->writeAtomically($path, $contents);
                } elseif ($contents === null) {
                    if (is_file($path)) {
                        unlink($path);
                    }
                } else {
                    throw new JsonException('Versi file journal aggregate tidak valid.');
                }
            }

            unlink($journalPath);
        } finally {
            foreach (array_reverse($recoveryLocks) as $lock) {
                $this->releaseLock($lock);
            }
        }
    }

    private function scopedEntity(): string
    {
        $this->assertScoped();

        return $this->entity;
    }

    private function scopedCompany(): string
    {
        $this->assertScoped();

        return $this->company;
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
