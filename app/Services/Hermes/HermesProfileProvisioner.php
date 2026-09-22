<?php

namespace App\Services\Hermes;

use App\Models\HermesProfile;
use App\Models\User;
use InvalidArgumentException;

class HermesProfileProvisioner
{
    /**
     * Memastikan profil bot utama (Internal Operations) dibuat untuk owner.
     */
    public function ensurePrimaryProfile(User $owner): HermesProfile
    {
        return HermesProfile::firstOrCreate(
            [
                'owner_user_id' => $owner->id,
                'type' => 'primary',
            ],
            [
                'label' => 'Bot Operasional Internal',
                'instance_id' => 'inst_primary_'.$owner->id.'_'.bin2hex(random_bytes(4)),
                // Hash, bukan token (QA-08). Konsekuensinya: jalur ini tidak
                // mengembalikan token yang bisa dipakai - pemakainya harus menerbitkan
                // lewat `bos:hermes-profile --reissue`. Itu disengaja; kelas ini
                // memang tidak punya cara mengembalikan plaintext ke pemanggilnya.
                'webhook_secret_reference' => HermesProfile::hashBotToken('sec_'.bin2hex(random_bytes(16))),
                'status' => 'unpaired',
            ]
        );
    }

    /**
     * Membuat profil bot tambahan (CS Publik) untuk owner (berlangganan add-on).
     */
    public function provisionAddonCsProfile(User $owner, int $billingAddonId, string $label = 'Bot CS Publik'): HermesProfile
    {
        return HermesProfile::create([
            'owner_user_id' => $owner->id,
            'type' => 'addon',
            'billing_addon_id' => $billingAddonId,
            'label' => $label,
            'instance_id' => 'inst_addon_'.$owner->id.'_'.bin2hex(random_bytes(4)),
            'webhook_secret_reference' => HermesProfile::hashBotToken('sec_'.bin2hex(random_bytes(16))),
            'status' => 'unpaired',
        ]);
    }

    /**
     * Mendapatkan daftar tool yang diizinkan untuk tipe profil.
     *
     * @return array<string>
     */
    public function getAllowedTools(string $profileType): array
    {
        $config = config("hermes.profiles.{$profileType}");
        if (! $config) {
            throw new InvalidArgumentException("Tipe profil Hermes tidak dikenali: {$profileType}");
        }

        return $config['allowed_tools'] ?? [];
    }

    /**
     * Memeriksa apakah sebuah aksi/tool diizinkan untuk tipe profil tersebut (fail-closed).
     */
    public function isToolAllowed(string $profileType, string $toolName): bool
    {
        $config = config("hermes.profiles.{$profileType}");
        if (! $config) {
            return false;
        }

        $disallowed = $config['disallowed_tools'] ?? [];
        if (in_array($toolName, $disallowed, true)) {
            return false;
        }

        $allowed = $config['allowed_tools'] ?? [];

        return in_array($toolName, $allowed, true);
    }
}
