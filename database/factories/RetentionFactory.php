<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Project;
use App\Models\Retention;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Retention>
 */
class RetentionFactory extends Factory
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
            'project_id' => Project::factory(),
            'amount' => 1000000,
            'status' => 'held',
        ];
    }
}
