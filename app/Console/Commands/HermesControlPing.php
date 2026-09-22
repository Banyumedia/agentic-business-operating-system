<?php

namespace App\Console\Commands;

use App\Models\HermesNode;
use App\Services\Hermes\ControlPlaneException;
use App\Services\Hermes\HermesControlPlaneClient;
use Illuminate\Console\Command;

/**
 * Membuktikan control plane sebuah node bisa dipanggil, tanpa mengubah apa pun.
 *
 * Bedanya dengan `bos:hermes-ping`: perintah itu memeriksa **bridge WhatsApp**
 * (port loopback, tanpa auth). Perintah ini memeriksa **dashboard API** (port lain,
 * butuh token bearer). Node bisa sehat pada yang satu dan mati pada yang lain, dan
 * menyatukan keduanya akan membuat operator mencari masalah di tempat yang salah.
 *
 * Hanya memanggil endpoint kesehatan dan statistik yang tidak ter-scope profil,
 * supaya tidak pernah menyentuh konfigurasi tenant mana pun.
 */
class HermesControlPing extends Command
{
    protected $signature = 'bos:hermes-control-ping
        {--node= : ID node yang diperiksa; kosong berarti semua node yang punya control plane}';

    protected $description = 'Memeriksa dashboard API (control plane) node Hermes tanpa mengubah apa pun';

    public function handle(HermesControlPlaneClient $client): int
    {
        $nodes = $this->option('node')
            ? HermesNode::query()->whereKey($this->option('node'))->get()
            : HermesNode::query()->whereNotNull('control_url')->get();

        if ($nodes->isEmpty()) {
            $this->warn('Tidak ada node dengan control plane. Isi Alamat Control Plane di /admin/hermes-nodes.');

            return self::FAILURE;
        }

        $failures = 0;

        foreach ($nodes as $node) {
            try {
                // Perintah ini tidak menuliskan path dashboard: rutenya hidup di satu
                // tempat saja (T-86 penjaga a).
                $health = $client->health($node);
                $stats = $this->statsOrNull($client, $node);

                $this->info('OK   '.$node->name.' — '.$this->describe($health, $stats));
            } catch (ControlPlaneException $exception) {
                // Pesannya sudah dirancang tidak memuat nilai rahasia.
                $this->error('GAGAL '.$node->name.' — '.$exception->getMessage());
                $failures++;
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Statistik sistem bersifat tambahan: node yang sehat tetapi belum
     * mengekspornya - atau yang menolaknya karena belum berwenang - tidak boleh
     * dilaporkan gagal.
     *
     * @return array<mixed>|null
     */
    private function statsOrNull(HermesControlPlaneClient $client, HermesNode $node): ?array
    {
        try {
            return $client->systemStats($node);
        } catch (ControlPlaneException) {
            return null;
        }
    }

    /**
     * @param  array<mixed>  $health
     * @param  array<mixed>|null  $stats
     */
    private function describe(array $health, ?array $stats): string
    {
        $parts = [];

        foreach (['status', 'version'] as $key) {
            if (isset($health[$key]) && is_scalar($health[$key])) {
                $parts[] = $key.' '.$health[$key];
            }
        }

        if ($stats !== null && isset($stats['version']) && is_scalar($stats['version'])) {
            $parts[] = 'version '.$stats['version'];
        }

        // Kalau node menjawab tanpa field yang dikenal, tampilkan kuncinya saja -
        // bukan isinya, karena badan respons bisa memuat data yang tidak perlu
        // muncul di terminal.
        return $parts === [] ? 'menjawab dengan kunci: '.implode(', ', array_keys($health)) : implode(', ', $parts);
    }
}
