<?php

namespace Database\Factories;

use App\Models\AiReminder;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiReminder>
 */
class AiReminderFactory extends Factory
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
            'task_description' => fake()->sentence(),
            'remind_at' => fake()->dateTimeBetween('now', '+1 month'),
            'is_completed' => false,
        ];
    }
}
