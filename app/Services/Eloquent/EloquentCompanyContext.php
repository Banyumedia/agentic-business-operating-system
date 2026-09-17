<?php

namespace App\Services\Eloquent;

use App\Contracts\CompanyContext;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use LogicException;

class EloquentCompanyContext implements CompanyContext
{
    private ?Company $cachedCompany = null;

    public function current(): string
    {
        $company = $this->getCompany();

        return (string) $company->id;
    }

    public function preset(): string
    {
        $company = $this->getCompany();

        return $company->business_preset;
    }

    public function setCurrent(string $companyId): void
    {
        $company = Company::find($companyId);
        if (! $company) {
            throw new LogicException('Company tidak ditemukan.');
        }

        session(['active_company' => $companyId]);

        $user = Auth::user();
        if ($user) {
            $user->current_company_id = (int) $companyId;
            $user->save();
        }

        $this->cachedCompany = $company;
    }

    public function getCompany(): Company
    {
        if ($this->cachedCompany) {
            return $this->cachedCompany;
        }

        $activeCompanyId = session('active_company');
        if ($activeCompanyId) {
            $company = Company::find($activeCompanyId);
            if ($company) {
                $this->cachedCompany = $company;

                return $company;
            }
        }

        $user = Auth::user();
        if ($user && $user->current_company_id) {
            $company = Company::find($user->current_company_id);
            if ($company) {
                session(['active_company' => $company->id]);
                $this->cachedCompany = $company;

                return $company;
            }
        }

        throw new LogicException('Company aktif belum di-set pada EloquentCompanyContext.');
    }
}
