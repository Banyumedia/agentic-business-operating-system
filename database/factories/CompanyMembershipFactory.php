<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyMembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'plan_id' => MembershipPlan::factory(),
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'trial_ends_at' => null,
            'max_wa_groups' => 1,
            'monthly_token_quota' => 500000,
            'emergency_token_quota' => 25000,
            'emergency_balance' => 25000,
            'current_token_balance' => 500000,
        ];
    }
}
