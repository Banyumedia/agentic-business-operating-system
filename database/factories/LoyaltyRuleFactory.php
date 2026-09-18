<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\LoyaltyRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyRule>
 */
class LoyaltyRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'nominal_per_point' => 10000,
            'expiry_months' => 12,
            'is_active' => true,
        ];
    }
}
