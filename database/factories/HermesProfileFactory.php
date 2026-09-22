<?php

namespace Database\Factories;

use App\Models\HermesNode;
use App\Models\HermesProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HermesProfile>
 */
class HermesProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory(),
            'node_id' => HermesNode::factory(),
            // Alamat bridge dibiarkan kosong: bawaannya jatuh ke alamat node,
            // sehingga test yang tidak peduli soal alamat tetap bekerja seperti
            // sebelum kolom ini ada.
            'api_url' => null,
            'type' => 'primary',
            'label' => 'Primary Bot',
            'instance_id' => Str::uuid()->toString(),
            'webhook_secret_reference' => 'secret/hermes/webhook-'.Str::random(8),
            // Produksi hanya pernah menulis `paired`/`unpaired`. Factory yang
            // mem-default `connected` membuat seluruh suite bergantung pada kosakata
            // yang tidak ada yang menulisnya, sehingga drift kosakata tersembunyi di
            // balik test yang hijau. Kosakata factory harus sama dengan produksi.
            'status' => 'paired',
        ];
    }
}
