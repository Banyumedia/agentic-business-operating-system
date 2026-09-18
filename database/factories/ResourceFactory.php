<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Resource>
 */
class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'type' => $this->faker->randomElement(['vehicle', 'room', 'table', 'seat']),
            'name' => $this->faker->words(2, true),
            'status' => 'available',
        ];
    }
}
