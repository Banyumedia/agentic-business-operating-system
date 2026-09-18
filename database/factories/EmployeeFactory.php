<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
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
            'user_id' => null,
            'name' => fake()->name(),
            'position' => fake()->jobTitle(),
            'base_salary' => fake()->randomFloat(2, 3000, 15000),
            'hourly_cost' => fake()->optional()->randomFloat(2, 25, 200),
            'joined_on' => fake()->optional()->date(),
            'is_active' => true,
            'attributes' => ['source' => 'factory'],
        ];
    }
}
