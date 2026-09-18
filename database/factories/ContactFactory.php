<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'type' => 'customer',
            'name' => $this->faker->name(),
            'wa_number' => $this->faker->numerify('08##########'),
            'email' => $this->faker->unique()->safeEmail(),
            'source' => 'manual',
            'tags' => ['new'],
        ];
    }
}
