<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'external_ref' => 'PO-'.$this->faker->unique()->numberBetween(1000, 9999),
            'item_id' => function (array $attributes) {
                return Item::create(['company_id' => $attributes['company_id'] ?? 1, 'name' => 'T', 'sku' => 'T-'.$this->faker->unique()->numberBetween(1000, 9999), 'type' => 'product', 'unit' => 'pcs', 'price' => 0])->id;
            },
            'target_qty' => $this->faker->randomFloat(3, 1, 100),
            'stage' => 'draft',
        ];
    }
}
