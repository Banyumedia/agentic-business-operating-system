<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use App\Models\LoyaltyPoint;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyPoint>
 */
class LoyaltyPointFactory extends Factory
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
            'contact_id' => Contact::factory(),
            'order_id' => Order::factory(),
            'points' => 10,
            'source_type' => 'earned_nominal',
            'notes' => 'Points from transaction',
        ];
    }
}
