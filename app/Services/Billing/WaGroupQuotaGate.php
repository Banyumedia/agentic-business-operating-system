<?php

namespace App\Services\Billing;

use App\Contracts\CompanyContext;
use App\Models\CompanyMembership;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * Enforces WA group quota based on membership plan or free tier.
 *
 * D-60: Free tier allows max 1 WA group (read from config).
 * Active membership: uses max_wa_groups from membership_plans/company_memberships.
 */
class WaGroupQuotaGate
{
    public function __construct(private readonly CompanyContext $companyContext) {}

    /**
     * Get maximum allowed WA groups for current company.
     */
    public function maxAllowedGroups(): int
    {
        $companyId = $this->companyContext->current();

        if (! Schema::hasTable('company_memberships')) {
            // No membership table - return free tier quota (D-60)
            return (int) config('billing.free_tier.max_wa_groups', 1);
        }

        try {
            $membership = CompanyMembership::with('plan')
                ->where('company_id', $companyId)
                ->first();
        } catch (QueryException) {
            // Query error - return free tier quota (D-60, fail-closed)
            return (int) config('billing.free_tier.max_wa_groups', 1);
        }

        // No membership - return free tier quota (D-60)
        if (! $membership) {
            return (int) config('billing.free_tier.max_wa_groups', 1);
        }

        // D-49: If membership status is not 'active', deny all WA groups (fail-closed)
        if ($membership->status !== 'active') {
            return 0; // No groups allowed when not active
        }

        // Return plan's max_wa_groups
        return (int) $membership->max_wa_groups;
    }

    /**
     * Check if company can add another WA group (fail-closed).
     *
     * @param  int  $currentGroupCount  Current number of groups already added
     * @return bool True if can add, false if quota exhausted
     */
    public function canAddGroup(int $currentGroupCount = 0): bool
    {
        return $currentGroupCount < $this->maxAllowedGroups();
    }

    /**
     * Assert company can add a group; throw if quota exceeded (fail-closed).
     *
     * @throws \Exception If quota would be exceeded
     */
    public function assertCanAddGroup(int $currentGroupCount = 0): void
    {
        if (! $this->canAddGroup($currentGroupCount)) {
            throw new \Exception(
                sprintf(
                    'Kuota grup WhatsApp tercapai. Maksimal: %d grup.',
                    $this->maxAllowedGroups()
                )
            );
        }
    }
}
