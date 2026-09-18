<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Item;
use App\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionOrderLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'production_order_id' => ProductionOrder::factory(),
            'component_item_id' => function (array $attributes) {
                return Item::create([
                    'company_id' => $attributes['company_id'],
                    'name' => 'C',
                    'sku' => 'C-'.$this->faker->unique()->numberBetween(1000, 9999),
                    'type' => 'material',
                    'unit' => 'pcs',
                    'price' => 0,
                ])->id;
            },
            'planned_qty' => $this->faker->randomFloat(3, 1, 50),
            'actual_qty' => null,
            'cost_per_unit' => $this->faker->randomFloat(2, 100, 5000),
        ];
    }
}
