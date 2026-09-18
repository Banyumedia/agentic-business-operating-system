<?php

namespace Database\Factories;

use App\Models\CashEntry;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashEntry>
 */
class CashEntryFactory extends Factory
{
    protected $model = CashEntry::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'entry_date' => $this->faker->date(),
            'direction' => $this->faker->randomElement(['in', 'out']),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'category' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'created_by_user_id' => 1,
        ];
    }
}
