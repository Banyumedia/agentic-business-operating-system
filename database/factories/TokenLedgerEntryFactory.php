<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyMembership;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TokenLedgerEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'company_membership_id' => CompanyMembership::factory(),
            'direction' => 'debit',
            'amount' => 100,
            'balance_after' => 499900,
            'source' => 'inference',
            'idempotency_key' => Str::uuid()->toString(),
        ];
    }
}
