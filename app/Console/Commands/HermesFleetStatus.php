<?php

namespace App\Console\Commands;

use App\Services\Hermes\FleetMonitor;
use Illuminate\Console\Command;

/**
 * Keadaan kanal pesan seluruh armada Hermes, tanpa mengubah apa pun (T-84).
 *
 * Keluar dengan kode != 0 bila ada yang bermasalah, supaya bisa dipakai penjadwal
 * atau probe: perintah yang selalu keluar 0 tidak bisa membangunkan siapa pun.
 *
 * Bedanya dengan dua perintah pemeriksa yang sudah ada, dan kenapa ketiganya tetap
 * terpisah:
 *
 * - `bos:hermes-ping` memeriksa **bridge WhatsApp** (port loopback, tanpa auth).
 * - `bos:hermes-control-ping` memeriksa **dashboard API** node (butuh token).
 * - perintah ini memeriksa **platform di dalam satu profil** lewat dashboard API.
 *
 * Node bisa sehat pada yang satu dan mati pada yang lain. Menyatukan ketiganya akan
 * membuat satu lampu merah yang tidak memberi tahu di mana harus mencari.
 */
class HermesFleetStatus extends Command
{
    protected $signature = 'bos:hermes-fleet-status';

    protected $description = 'Memeriksa keadaan kanal pesan tiap profil Hermes di seluruh armada';

    public function handle(FleetMonitor $monitor): int
    {
        $baris = $monitor->forFleet();

        if ($baris === []) {
            $this->warn('Belum ada profil Hermes yang menempel pada node mana pun.');

            return self::SUCCESS;
        }

        $tabel = [];
        $bermasalah = 0;

        foreach ($baris as $keadaan) {
            $sehat = $monitor->sehat((string) $keadaan['keadaan']);
            $bermasalah += $sehat ? 0 : 1;

            $tabel[] = [
                $keadaan['node_name'],
                // Label bisa kosong untuk profil yang belum diberi nama; nama profil di
                // node selalu ada dan itulah yang dicari operator di sisi Hermes.
                $keadaan['label'] ?: $keadaan['profil'],
                $keadaan['keadaan'],
                // Hanya id platform dan keadaannya. Nilai env - walau sudah diredaksi
                // Hermes - tidak ikut: keluaran perintah bisa berakhir di log penjadwal.
                $this->ringkasPlatform($keadaan),
            ];
        }

        $this->table(['Node', 'Profil', 'Keadaan', 'Platform'], $tabel);

        if ($bermasalah > 0) {
            $this->error("{$bermasalah} profil bermasalah. Keadaan `belum_berwenang` berarti H-05 belum terpasang di Hermes, bukan node yang mati.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $keadaan
     */
    private function ringkasPlatform(array $keadaan): string
    {
        $platform = (array) ($keadaan['platform'] ?? []);

        if ($platform === []) {
            return '-';
        }

        return implode(', ', array_map(
            static fn (array $p): string => $p['id'].'='.($p['keadaan'] ?: 'tidak diketahui').($p['aktif'] ? '' : ' (nonaktif)'),
            $platform,
        ));
    }
}
