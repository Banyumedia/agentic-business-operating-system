<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
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
            'company_membership_id' => CompanyMembership::factory(),
            'type' => 'subscription',
            'order_id' => 'INV-'.Str::random(8),
            'amount' => 100000,
            'token_amount_granted' => null,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'payment_status' => 'pending',
        ];
    }
}
