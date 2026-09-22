<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Peran efektif company aktif dari sumber tepercaya (D-41), bukan dari
 * session `company_role` yang tidak punya writer tepercaya.
 *
 * Owner = `companies.owner_user_id` milik pengguna terautentikasi; selain itu
 * staf. Sesi impersonasi admin tidak dianggap owner usaha - admin memantau,
 * tidak mengubah konfigurasi klien.
 */
class CompanyRoleResolver
{
    public const ROLE_OWNER = 'owner';

    public const ROLE_STAFF = 'staff';

    public function roleForActiveCompany(): string
    {
        $user = Auth::user();
        if ($user === null) {
            return self::ROLE_STAFF;
        }

        $companyId = $user->current_company_id;
        if ($companyId === null) {
            return self::ROLE_STAFF;
        }

        return $this->roleFor($user, $companyId);
    }

    public function isOwnerOfActiveCompany(): bool
    {
        return $this->roleForActiveCompany() === self::ROLE_OWNER;
    }

    /**
     * Apakah pengguna anggota company ini - sebagai owner maupun staf (D-65).
     *
     * Sebelum ada `company_user`, satu-satunya hubungan yang tercatat adalah
     * kepemilikan, sehingga staf tidak punya keanggotaan untuk diperiksa.
     */
    public function isMemberOfCompany(string|int $companyId): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $this->roleFor($user, $companyId) === self::ROLE_OWNER
            || $this->membershipRole($user, $companyId) !== null;
    }

    /**
     * Peran efektif: kepemilikan selalu menang, lalu baris keanggotaan, dan
     * bila tidak ada keduanya jatuh ke staf - peran paling terbatas yang masih
     * berhak login (fail-closed).
     */
    private function roleFor(User $user, string|int $companyId): string
    {
        $isOwner = Company::query()
            ->whereKey($companyId)
            ->where('owner_user_id', $user->id)
            ->exists();

        if ($isOwner) {
            return self::ROLE_OWNER;
        }

        $membershipRole = $this->membershipRole($user, $companyId);

        return $membershipRole === self::ROLE_OWNER ? self::ROLE_OWNER : self::ROLE_STAFF;
    }

    private function membershipRole(User $user, string|int $companyId): ?string
    {
        if (! Schema::hasTable('company_user')) {
            return null;
        }

        $role = DB::table('company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->value('role');

        return is_string($role) ? $role : null;
    }

    public function isOwnerOfCompany(string|int $companyId): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        $normalizedId = filter_var($companyId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $company = Company::query()
            ->when(
                $normalizedId !== false,
                fn ($query) => $query->whereKey($normalizedId),
                fn ($query) => $query->where('slug', (string) $companyId),
            )
            ->where('owner_user_id', $user->id)
            ->first();

        return $company !== null && (int) $user->current_company_id === $company->id;
    }
}
