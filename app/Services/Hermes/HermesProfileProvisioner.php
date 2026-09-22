<?php

namespace App\Services\Hermes;

use App\Models\HermesProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HermesProfileProvisioner
{
    public function __construct(private readonly ?NodePlacement $placement = null) {}

    /**
     * Memastikan profil bot utama (Internal Operations) dibuat untuk owner.
     *
     * **Kenapa menempatkan di node saat membuat.** Dulu kolom `node_id` dibiarkan
     * kosong, jadi profil lahir tanpa node dan `HermesNodeClient` menolaknya dengan
     * "belum ditempatkan pada node" - tenant baru mendapat bot yang tidak akan pernah
     * bisa mengirim, dan penyebabnya jauh dari kejadian. Sekarang penempatan +
     * alokasi port dilakukan di sini, dan bila tidak ada node layak, pembuatan
     * **gagal di depan** (lewat `NodePlacement`) alih-alih menyimpan profil bisu.
     *
     * Idempotensi `firstOrCreate` tetap dijaga: `NodePlacement::place()` hanya
     * dipanggil untuk profil yang **benar-benar** akan dibuat, sehingga panggilan
     * kedua menemukan profil lama apa adanya - tidak dipindah ke node lain, tidak
     * dialokasikan port baru. Larik atribut dibungkus closure supaya penempatan tidak
     * dievaluasi saat baris sudah ada.
     */
    public function ensurePrimaryProfile(User $owner): HermesProfile
    {
        $placement = $this->placement ?? new NodePlacement;

        // Bungkus dalam transaksi supaya kunci baris `NodePlacement` bermakna dan
        // penempatan dua permintaan bersamaan tidak melewati kapasitas.
        return DB::transaction(function () use ($owner, $placement): HermesProfile {
            // firstOrCreate mengevaluasi seluruh larik atribut walau barisnya sudah ada.
            // Karena `place()` melempar saat tidak ada node layak, memanggilnya tanpa
            // syarat akan membuat panggilan idempoten kedua ikut gagal. Jadi cari dulu;
            // hanya tempatkan bila memang belum ada.
            $existing = HermesProfile::query()
                ->where('owner_user_id', $owner->id)
                ->where('type', 'primary')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $chosen = $placement->place();

            return HermesProfile::create([
                'owner_user_id' => $owner->id,
                'type' => 'primary',
                'node_id' => $chosen['node']->id,
                'bridge_port' => $chosen['port'],
                // Alamat bridge profil = host node + port yang dialokasikan. Kalau
                // dikosongkan, ia jatuh ke alamat node (T-81) yang memakai port node -
                // padahal port profil inilah yang baru saja dialokasikan.
                'api_url' => $this->bridgeAddressFor($chosen['node']->api_url, $chosen['port']),
                'label' => 'Bot Operasional Internal',
                'instance_id' => 'inst_primary_'.$owner->id.'_'.bin2hex(random_bytes(4)),
                // Hash, bukan token (QA-08). Konsekuensinya: jalur ini tidak
                // mengembalikan token yang bisa dipakai - pemakainya harus menerbitkan
                // lewat `bos:hermes-profile --reissue`. Itu disengaja; kelas ini
                // memang tidak punya cara mengembalikan plaintext ke pemanggilnya.
                'webhook_secret_reference' => HermesProfile::hashBotToken('sec_'.bin2hex(random_bytes(16))),
                'status' => 'unpaired',
            ]);
        });
    }

    /**
     * Menyusun alamat bridge profil dari host alamat node dan port yang dialokasikan.
     *
     * Host diambil dari `api_url` node (skema + host), portnya diganti dengan port
     * profil. Kalau alamat node tidak bisa dibaca, kembalikan null - profil akan
     * jatuh ke alamat node saat mengirim (T-81), bukan menyimpan alamat yang cacat.
     */
    private function bridgeAddressFor(?string $nodeApiUrl, int $port): ?string
    {
        $nodeApiUrl = trim((string) $nodeApiUrl);

        if ($nodeApiUrl === '') {
            return null;
        }

        $scheme = parse_url($nodeApiUrl, PHP_URL_SCHEME);
        $host = parse_url($nodeApiUrl, PHP_URL_HOST);

        if (! is_string($scheme) || ! is_string($host) || $host === '') {
            return null;
        }

        return $scheme.'://'.$host.':'.$port;
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
