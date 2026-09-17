<?php

namespace Database\Factories;

use App\Models\BusinessIdentity;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessIdentity>
 */
class BusinessIdentityFactory extends Factory
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
            'legal_name' => fake()->company().' PT',
            'npwp' => fake()->numerify('##.###.###.#-###.###'),
            'address' => fake()->address(),
            'price_includes_tax' => false,
            'tax_mode' => 'non_taxable',
            'tax_rate' => 0.00,
            'is_default' => true,
        ];
    }
}
