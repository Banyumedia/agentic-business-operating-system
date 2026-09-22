<?php

namespace App\Console\Commands;

use App\Models\HermesProfile;
use App\Services\Hermes\ProfileStatusRefresher;
use Illuminate\Console\Command;

/**
 * Menyegarkan status seluruh profil Hermes dari bridge-nya masing-masing.
 *
 * Bedanya dengan `bos:hermes-ping`: perintah itu memeriksa **node** (host) dan
 * tidak menulis apa pun; perintah ini memeriksa **profil** (nomor) dan menyimpan
 * hasilnya ke `hermes_profiles.status`, sehingga lajur pengiriman menolak lebih
 * awal ketika nomornya sudah lepas.
 *
 * Profil tanpa alamat - belum ditempatkan pada node mana pun - dilaporkan sebagai
 * dilewati, bukan menjatuhkan pemeriksaan seluruh armada.
 */
class HermesProfileStatus extends Command
{
    protected $signature = 'bos:hermes-profile-status
        {--profile= : Hanya periksa satu profil berdasarkan id}';

    protected $description = 'Menyegarkan status profil Hermes dari bridge WhatsApp-nya';

    public function handle(ProfileStatusRefresher $refresher): int
    {
        $query = HermesProfile::query()->with('node');

        if ($this->option('profile')) {
            $query->whereKey($this->option('profile'));
        }

        $profiles = $query->get();

        if ($profiles->isEmpty()) {
            $this->warn('Tidak ada profil Hermes yang cocok.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($profiles as $profile) {
            $result = $refresher->refresh($profile);

            $rows[] = [
                $result['profile_id'],
                $result['label'],
                $result['address'] === '' ? '-' : $result['address'],
                $result['ok'] ? 'paired' : $result['status'],
                mb_substr($result['detail'], 0, 60),
            ];
        }

        $this->table(['ID', 'Label', 'Bridge', 'Status', 'Keterangan'], $rows);

        // Profil yang belum siap adalah keadaan yang wajar (nomor belum discan),
        // bukan kegagalan perintah. Perintah ini gagal hanya bila ia sendiri tidak
        // bisa bekerja - supaya penjadwal tidak membanjiri operator dengan alarm
        // untuk armada yang memang sedang separuh terpasang.
        return self::SUCCESS;
    }
}
