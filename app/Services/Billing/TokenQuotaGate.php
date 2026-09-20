<?php

namespace App\Services\Billing;

use App\Contracts\CompanyContext;
use App\Exceptions\Billing\InsufficientTokenQuotaException;
use App\Models\CompanyMembership;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * Enforces token quota based on membership plan or free tier.
 *
 * D-60: Free tier has limited token quota (from config, default ~500).
 * Active membership: uses token_quota from membership_plans/company_memberships.
 * D-48: When balance is exhausted, system moves to emergency mode or denies access.
 */
class TokenQuotaGate
{
    public function __construct(private readonly CompanyContext $companyContext) {}

    /**
     * Get token quota for current company (monthly limit).
     */
    public function getMonthlyTokenQuota(): int
    {
        $companyId = $this->companyContext->current();

        if (! Schema::hasTable('company_memberships')) {
            // No membership table - return free tier quota (D-60)
            return (int) config('billing.free_tier.token_quota', 500);
        }

        try {
            $membership = CompanyMembership::with('plan')
                ->where('company_id', $companyId)
                ->latest('id')
                ->first();
        } catch (QueryException) {
            // Query error - return free tier quota (D-60, fail-closed)
            return (int) config('billing.free_tier.token_quota', 500);
        }

        // No membership - return free tier quota (D-60)
        if (! $membership) {
            return (int) config('billing.free_tier.token_quota', 500);
        }

        // D-49: If membership status is not 'active', deny all tokens (fail-closed)
        if ($membership->status !== 'active') {
            return 0; // No tokens allowed when not active
        }

        // Return plan's monthly_token_quota
        return (int) $membership->monthly_token_quota;
    }

    /**
     * Get current token balance for company.
     */
    public function getCurrentTokenBalance(): int
    {
        $companyId = $this->companyContext->current();

        if (! Schema::hasTable('company_memberships')) {
            return 0;
        }

        try {
            $membership = CompanyMembership::where('company_id', $companyId)
                ->where('status', 'active')
                ->latest('id')
                ->first();

            if (! $membership) {
                return 0;
            }

            return (int) $membership->current_token_balance;
        } catch (QueryException) {
            return 0;
        }
    }

    /**
     * Check if company has sufficient token balance for AI action (fail-closed).
     *
     * @param  int  $tokensRequired  Number of tokens required for the action
     * @return bool True if sufficient balance, false if quota exceeded
     */
    public function hasEnoughTokens(int $tokensRequired = 1): bool
    {
        return $this->getCurrentTokenBalance() >= $tokensRequired;
    }

    /**
     * Assert company has enough tokens; throw if quota exceeded (fail-closed).
     *
     * @throws InsufficientTokenQuotaException If token quota would be exceeded
     */
    public function assertHasEnoughTokens(int $tokensRequired = 1): void
    {
        $available = $this->getCurrentTokenBalance();

        if ($available < $tokensRequired) {
            throw new InsufficientTokenQuotaException($tokensRequired, $available);
        }
    }
}
