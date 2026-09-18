<?php

namespace Database\Factories;

use App\Models\AccessLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessLog>
 */
class AccessLogFactory extends Factory
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
            'user_id' => User::factory(),
            'subject_type' => Contact::class,
            'subject_id' => 1,
            'action' => 'read',
            'ip' => '127.0.0.1',
        ];
    }
}
