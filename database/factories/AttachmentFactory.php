<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
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
            'attachable_type' => 'App\Models\Contact',
            'attachable_id' => 1,
            'file_name' => 'KTP.pdf',
            'file_type' => 'application/pdf',
            'drive_file_id' => Str::random(32),
            'drive_url' => $this->faker->url(),
        ];
    }
}
