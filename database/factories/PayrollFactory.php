<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payroll>
 */
class PayrollFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gross = fake()->randomFloat(2, 3000, 15000);
        $deductions = fake()->randomFloat(2, 0, 1500);

        return [
            'company_id' => Company::factory(),
            'employee_id' => Employee::factory(),
            'period_month' => fake()->date('Y-m'),
            'gross_salary' => $gross,
            'deductions' => $deductions,
            'net_salary' => $gross - $deductions,
            'status' => fake()->randomElement(['draft', 'paid']),
            'paid_at' => fake()->optional()->dateTime(),
        ];
    }
}
