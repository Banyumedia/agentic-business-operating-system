<?php

namespace Database\Factories;

use App\Models\AiModelPricing;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiModelPricingFactory extends Factory
{
    protected $model = AiModelPricing::class;

    public function definition(): array
    {
        return [
            'model_name' => $this->faker->unique()->word.'-model',
            'input_multiplier' => 1.0,
            'output_multiplier' => 3.0,
            'is_active' => true,
        ];
    }
}
