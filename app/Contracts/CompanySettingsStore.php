<?php

namespace App\Contracts;

use Closure;

interface CompanySettingsStore
{
    /** @return array<string, mixed> */
    public function read(string $company): array;

    /**
     * @param  Closure(array<string, mixed>): array<string, mixed>  $update
     * @return array<string, mixed>
     */
    public function update(string $company, Closure $update): array;
}
