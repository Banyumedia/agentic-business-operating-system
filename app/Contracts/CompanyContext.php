<?php

namespace App\Contracts;

interface CompanyContext
{
    public function current(): string;

    public function preset(): string;

    public function setCurrent(string $company): void;

    /**
     * Nama tampilan company untuk UI (MQ-01C4): kontrak yang sama untuk
     * JSON dan Eloquent - nama ter-cased, bukan identifier/ID mentah.
     */
    public function displayName(): string;
}
