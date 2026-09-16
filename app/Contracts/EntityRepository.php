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
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function save(array $record): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function query(array $filters = []): array;
}
