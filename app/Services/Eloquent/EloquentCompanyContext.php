<?php

namespace App\Services\Eloquent;

use App\Contracts\CompanyContext;
use App\Models\AdminImpersonationSession;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use LogicException;

class EloquentCompanyContext implements CompanyContext
{
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

    public function displayName(): string
    {
        // F3 (QA MQ-01): paritas dengan driver JSON - jangan pernah
        // menampilkan string kosong; fallback ke slug bila nama kosong.
        $name = trim((string) $this->getCompany()->name);

        return $name !== ''
            ? $name
            : str($this->getCompany()->slug)->replace('-', ' ')->title()->toString();
    }

    public function setCurrent(string $companyId): void
    {
        $company = Company::find($companyId);
        if (! $company) {
            throw new LogicException('Company tidak ditemukan.');
        }

        $this->assertAuthorizedFor($company);

        session(['active_company' => $companyId]);

        $user = Auth::user();
        if ($user) {
            $user->current_company_id = (int) $companyId;
            $user->save();
        }
    }

    public function getCompany(): Company
    {
        $activeCompanyId = session('active_company');
        if ($activeCompanyId) {
            $company = Company::find($activeCompanyId);
            if ($company) {
                // Session adalah input tak tepercaya: kepemilikan (atau sesi
                // impersonasi admin yang sah) wajib diverifikasi ulang di
                // sini, bukan hanya di middleware HTTP - request Livewire
                // dan pemanggilan service langsung juga melewati jalur ini.
                $this->assertAuthorizedFor($company);

                return $company;
            }
        }

        $user = Auth::user();
        if ($user && $user->current_company_id) {
            $company = Company::find($user->current_company_id);
            if ($company) {
                $this->assertAuthorizedFor($company);

                session(['active_company' => $company->id]);

                return $company;
            }
        }

        throw new LogicException('Company aktif belum di-set pada EloquentCompanyContext.');
    }

    /**
     * Fail-closed: user harus owner company, atau admin platform dengan sesi
     * impersonasi sah yang menargetkan company ini. Tanpa user terautentikasi
     * (CLI/seed), tidak ada prinsipal untuk diverifikasi - izinkan set,
     * karena pembacaan getCompany() tetap diverifikasi saat ada user.
     */
    private function assertAuthorizedFor(Company $company): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        if ((int) $company->owner_user_id === (int) $user->id) {
            return;
        }

        $impersonationId = session('admin_impersonation_id');
        if (is_string($impersonationId) && $impersonationId !== '') {
            // F2 (QA MQ-01): sesi impersonasi berumur pendek - fail-closed pada
            // baris tanpa expires_at maupun yang sudah lewat.
            $valid = AdminImpersonationSession::query()
                ->where('session_id', $impersonationId)
                ->where('target_company_id', $company->id)
                ->where('admin_user_id', $user->id)
                ->whereNotNull('expires_at')
                ->where('expires_at', '>', now())
                ->exists();

            if ($valid) {
                return;
            }
        }

        throw new LogicException('Akses lintas company ditolak.');
    }
}
