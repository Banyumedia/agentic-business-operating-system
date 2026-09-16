<?php

namespace App\Contracts;

interface PresetSource
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array;

    /** @return array<string, mixed>|null */
    public function find(string $key): ?array;
}
