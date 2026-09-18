<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Company;
use App\Models\Resource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = Carbon::now()->addDays($this->faker->numberBetween(1, 10));
        $endsAt = $startsAt->copy()->addHours(2);

        return [
            'company_id' => Company::factory(),
            'resource_id' => Resource::factory(),
            'type' => 'booking',
            'stage' => 'draft',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }
}
