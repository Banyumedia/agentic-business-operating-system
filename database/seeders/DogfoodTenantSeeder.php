<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class DogfoodTenantSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'bos@nalar.army'],
            [
                'name' => 'BOS Admin',
                'password' => bcrypt('password'),
                'wa_number' => '0811111111',
            ]
        );

        $presets = ['bengkel', 'klinik', 'salon', 'laundry'];

        foreach ($presets as $preset) {
            Company::firstOrCreate(
                ['business_preset' => $preset, 'owner_user_id' => $owner->id],
                [
                    'name' => ucfirst($preset).' Dogfood',
                    'slug' => $preset.'-dogfood',
                ]
            );
        }
    }
}
