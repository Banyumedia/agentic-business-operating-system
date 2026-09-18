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
            return [];
        }

        try {
            $membership = CompanyMembership::with('plan')
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->first();
        } catch (QueryException) {
            return [];
        }

        if (! $membership || ! $membership->plan || ! is_array($membership->plan->features)) {
            return [];
        }

        return $membership->plan->features;
    }
}
