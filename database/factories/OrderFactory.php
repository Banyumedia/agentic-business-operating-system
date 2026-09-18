<?php

namespace Database\Factories;

use App\Models\BusinessIdentity;
use App\Models\Company;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
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
            'business_identity_id' => BusinessIdentity::factory(),
            'order_no' => 'ORD-'.strtoupper(Str::random(8)),
            'stage' => 'open',
            'subtotal' => 100000,
            'dpp' => 100000,
            'tax_amount' => 11000,
            'grand_total' => 111000,
            'source' => 'pos',
        ];
    }
}
