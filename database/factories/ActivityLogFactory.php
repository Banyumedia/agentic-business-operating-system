<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'subject_type' => 'App\Models\Deal',
            'subject_id' => 1,
            'type' => 'note',
            'content' => $this->faker->sentence(),
            'created_at' => now(),
        ];
    }
}
