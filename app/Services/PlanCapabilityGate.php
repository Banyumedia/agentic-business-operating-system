<?php

namespace App\Services;

use App\Contracts\CompanyContext;
use App\Models\CompanyMembership;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class PlanCapabilityGate
{
    public function __construct(private readonly CompanyContext $companyContext) {}

    /** @return list<string> */
    public function allowedCapabilities(): array
    {
        $companyId = $this->companyContext->current();

        if (! Schema::hasTable('company_memberships')) {
            // No membership table - return free tier config (D-60)
            return config('billing.free_tier.capabilities', []);
        }

        try {
            $membership = CompanyMembership::with('plan')
                ->where('company_id', $companyId)
                ->first();
        } catch (QueryException) {
            // Query error - return free tier config (D-60, fail-closed)
            return config('billing.free_tier.capabilities', []);
        }

        // No membership found - return free tier config (D-60)
        if (! $membership) {
            return config('billing.free_tier.capabilities', []);
        }

        // D-49: If membership status is not 'active', deny all capabilities (fail-closed)
        // Only 'active' status allows capabilities; other statuses (ai_suspended, read_only, frozen, etc.)
        // are blocked to enforce dunning ladder restrictions
        if ($membership->status !== 'active') {
            return [];
        }

        // Return plan features if membership is active
        if (! $membership->plan || ! is_array($membership->plan->features)) {
            return config('billing.free_tier.capabilities', []);
        }

        return $membership->plan->features;
    }
}
