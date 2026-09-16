<?php

namespace App\Contracts;

interface CompanyContext
{
    public function current(): string;

    public function setCurrent(string $company): void;
}
