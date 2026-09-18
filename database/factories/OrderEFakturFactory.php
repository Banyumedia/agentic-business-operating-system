<?php

namespace Database\Factories;

use App\Models\OrderEFaktur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderEFaktur>
 */
class OrderEFakturFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => null, // Provide in test
            'order_id' => null, // Provide in test
            'nomor_seri' => null,
            'npwp_lawan_transaksi' => '01.234.567.8-901.000',
            'ppn' => 11000,
            'status' => 'belum_dikirim',
        ];
    }
}
