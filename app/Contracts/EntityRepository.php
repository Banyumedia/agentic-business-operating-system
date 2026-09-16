<?php

namespace App\Contracts;

interface EntityRepository
{
    public function for(string $company, string $entity): static;

    /** @return array<int, array<string, mixed>> */
    public function all(): array;

    /** @return array<string, mixed>|null */
    public function find(string|int $id): ?array;

    /**
     * Menyimpan satu row. Bila `id` tidak diisi, repository menetapkan id
     * berikutnya di dalam lock sehingga pembuatan row baru tidak balapan.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function save(array $record): array;

    /**
     * Menyimpan parent dan seluruh child sebagai satu aggregate all-or-nothing.
     * Adapter database Fase 3 menerjemahkannya menjadi transaksi database.
     *
     * @param  array<string, mixed>  $parent
     * @param  list<array<string, mixed>>  $children
     * @param  list<array{entity: string, id: string|int, expected: array<string, mixed>}>  $guards
     * @return array{parent: array<string, mixed>, children: list<array<string, mixed>>, replayed: bool}
     */
    public function saveAggregate(
        array $parent,
        string $childEntity,
        string $foreignKey,
        array $children,
        ?string $idempotencyField = null,
        array $guards = [],
    ): array;

    /**
     * Menghapus satu row. Mengembalikan false bila id tidak ditemukan.
     */
    public function delete(string|int $id): bool;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public function query(array $filters = []): array;
}
