<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MembershipPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'slug' => $this->faker->unique()->slug(),
            'monthly_price' => 100000,
            'annual_price' => 1000000,
            'max_wa_groups' => 1,
            'monthly_token_quota' => 500000,
            'emergency_token_quota' => 25000,
            'trial_token_quota' => 50000,
            'features' => [],
            'is_active' => true,
        ];
    }
}
