<?php

namespace Database\Factories;

use App\Models\HermesNode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HermesNode>
 */
class HermesNodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Hermes Node '.$this->faker->unique()->numberBetween(1, 99),
            'api_url' => $this->faker->url(),
            'api_secret_reference' => 'secret/hermes/node-'.$this->faker->unique()->numberBetween(1, 99),
            'max_capacity' => 100,
            'active_profiles' => 0,
            'status' => 'active',
        ];
    }
}
