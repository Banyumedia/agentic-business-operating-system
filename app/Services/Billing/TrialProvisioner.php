<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TrialProvisioner
{
    /**
     * Start a trial for a company on a specific plan.
     */
    public function provision(Company $company, MembershipPlan $plan, int $days = 14): CompanyMembership
    {
        return DB::transaction(function () use ($company, $plan, $days) {
            $owner = $company->owner;

            if (! $owner) {
                throw new InvalidArgumentException('Company must have an owner to start a trial.');
            }

            // D-51: Satu owner satu trial, dicegah via wa_number dan email
            // Cari apakah ada user lain yang punya email atau wa_number sama (atau user ini sendiri)
            // yang sudah pernah mendapatkan trial.

            $query = User::where(function ($q) use ($owner) {
                $q->where('email', $owner->email);
                if (! empty($owner->wa_number)) {
                    $q->orWhere('wa_number', $owner->wa_number);
                }
            });

            $userIds = $query->pluck('id');

            // Cek apakah ada membership dengan trial_ends_at terisi untuk company milik user-user tersebut
            $existingTrial = CompanyMembership::whereNotNull('trial_ends_at')
                ->whereHas('company', function ($q) use ($userIds) {
                    $q->whereIn('owner_user_id', $userIds);
                })
                ->exists();

            if ($existingTrial) {
                throw new InvalidArgumentException('Anda sudah pernah menikmati masa trial pada akun lain.');
            }

            // D-51: Trial 14 hari fitur penuh, tanpa kartu
            return CompanyMembership::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => 'active', // PlanCapabilityGate checks for 'active'
                'starts_at' => now(),
                'expires_at' => now()->addDays($days),
                'trial_ends_at' => now()->addDays($days),
                'current_token_balance' => $plan->trial_token_quota,
                'monthly_token_quota' => $plan->monthly_token_quota,
                'emergency_token_quota' => $plan->emergency_token_quota,
                'emergency_balance' => $plan->emergency_token_quota,
                'max_wa_groups' => $plan->max_wa_groups,
                'max_users' => $plan->max_users ?? 1,
            ]);
        });
    }
}
