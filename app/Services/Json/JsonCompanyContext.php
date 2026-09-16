<?php

namespace App\Services\Json;

use App\Contracts\CompanyContext;
use InvalidArgumentException;
use LogicException;

class JsonCompanyContext implements CompanyContext
{
    /** @var list<string> */
    private array $allowedCompanies;

    public function __construct()
    {
        $companies = config('datasource.demo_companies', []);
        $this->allowedCompanies = is_array($companies) ? array_values($companies) : [];
    }

    public function current(): string
    {
        $this->assertDemoEnvironment();

        $requested = request()->query('company');
        if ($requested !== null) {
            if (! is_string($requested)) {
                throw new InvalidArgumentException('Company demo tidak valid.');
            }

            $this->setCurrent($requested);
        }

        $company = session('active_company');
        if (! is_string($company)) {
            throw new LogicException('Company aktif belum dipilih.');
        }

        $this->assertAllowed($company);

        return $company;
    }

    public function setCurrent(string $company): void
    {
        $this->assertDemoEnvironment();
        $this->assertAllowed($company);
        session(['active_company' => $company]);
    }

    private function assertDemoEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('JsonCompanyContext hanya tersedia di environment demo.');
        }
    }

    private function assertAllowed(string $company): void
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $company)
            || ! in_array($company, $this->allowedCompanies, true)) {
            throw new InvalidArgumentException('Company demo tidak diizinkan.');
        }
    }
}
