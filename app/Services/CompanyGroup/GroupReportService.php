<?php

namespace App\Services\CompanyGroup;

use App\Models\CashEntry;
use App\Models\Company;
use App\Models\Contact;

class GroupReportService
{
    /**
     * Get aggregate statistics for a group of companies (root + branches).
     */
    public function getAggregate(Company $company): array
    {
        // 1. Identify the group root (HQ)
        $rootCompanyId = $company->parent_company_id ?? $company->id;

        // 2. Collect all company IDs in the group (HQ + all branches)
        $groupCompanyIds = Company::where('id', $rootCompanyId)
            ->orWhere('parent_company_id', $rootCompanyId)
            ->pluck('id')
            ->toArray();

        // 3. Aggregate data
        $totalContacts = Contact::whereIn('company_id', $groupCompanyIds)->count();
        $totalCashEntries = CashEntry::whereIn('company_id', $groupCompanyIds)->count();

        return [
            'total_contacts' => $totalContacts,
            'total_cash_entries' => $totalCashEntries,
        ];
    }
}
