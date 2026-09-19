<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;

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

        $isOwner = Company::query()
            ->where('id', $companyId)
            ->where('owner_user_id', $user->id)
            ->exists();

        return $isOwner ? self::ROLE_OWNER : self::ROLE_STAFF;
    }

    public function isOwnerOfActiveCompany(): bool
    {
        return $this->roleForActiveCompany() === self::ROLE_OWNER;
    }
}
