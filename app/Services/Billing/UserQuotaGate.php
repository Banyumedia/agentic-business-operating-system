<?php

namespace App\Services\Billing;

use App\Contracts\CompanyContext;
use App\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Kuota jumlah pengguna per company (D-65).
 *
 * Bentuknya meniru `WaGroupQuotaGate`: angka dari membership aktif, jatuh ke
 * tier gratis config-driven bila tidak ada, dan fail-closed ke tier gratis saat
 * tabel atau query bermasalah. Pembatasan lewat kuota, bukan kapabilitas
 * (D-60/D-61) - semua fitur tetap terbuka, yang dibatasi jumlah orangnya.
 */
class UserQuotaGate
{
    public function __construct(private readonly CompanyContext $companyContext) {}

    public function quota(?string $companyId = null): int
    {
        $companyId ??= $this->companyContext->current();

        if (! Schema::hasTable('company_memberships')) {
            return $this->freeTierQuota();
        }

        try {
            $membership = Company::query()
                ->whereKey($companyId)
                ->first()
                ?->memberships()
                ->where('status', 'active')
                ->first();
        } catch (QueryException) {
            return $this->freeTierQuota();
        }

        if ($membership === null) {
            return $this->freeTierQuota();
        }

        return max(1, (int) ($membership->max_users ?? 1));
    }

    /** Jumlah anggota yang sudah terpakai, termasuk owner. */
    public function used(?string $companyId = null): int
    {
        $companyId ??= $this->companyContext->current();

        if (! Schema::hasTable('company_user')) {
            return 0;
        }

        $company = Company::query()->whereKey($companyId)->first();

        return $company === null ? 0 : $company->members()->count();
    }

    public function remaining(?string $companyId = null): int
    {
        return max(0, $this->quota($companyId) - $this->used($companyId));
    }

    public function canAddUser(?string $companyId = null): bool
    {
        return $this->remaining($companyId) > 0;
    }

    /**
     * Fail-closed di titik undangan, bukan saat login: anggota yang sudah ada
     * tidak boleh tiba-tiba terkunci saat paket turun.
     */
    public function assertCanAddUser(?string $companyId = null): void
    {
        if (! $this->canAddUser($companyId)) {
            throw new RuntimeException('Kuota pengguna paket ini sudah penuh. Naikkan paket untuk menambah anggota.');
        }
    }

    private function freeTierQuota(): int
    {
        return max(1, (int) config('billing.free_tier.max_users', 1));
    }
}
