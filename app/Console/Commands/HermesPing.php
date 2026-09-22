<?php

namespace App\Console\Commands;

use App\Models\HermesNode;
use App\Services\HermesNodeClient;
use Illuminate\Console\Command;

/**
 * Memeriksa node Hermes tanpa mengirim pesan apa pun.
 *
 * Aktivasi WhatsApp selalu punya dua pertanyaan berbeda: "apakah
 * konfigurasinya benar" dan "apakah pesannya sampai". Perintah ini menjawab yang
 * pertama, supaya yang kedua tidak pernah diuji dengan menembak nomor sungguhan
 * lebih dulu.
 */
class HermesPing extends Command
{
    protected $signature = 'bos:hermes-ping
        {--url= : Uji satu alamat langsung, tanpa membaca tabel hermes_nodes}
        {--secret-ref= : Referensi rahasia untuk --url}';

    protected $description = 'Memeriksa kesehatan node Hermes tanpa mengirim pesan WhatsApp';

    public function handle(HermesNodeClient $client): int
    {
        if ($this->option('url')) {
            $result = $client->ping((string) $this->option('url'), (string) $this->option('secret-ref'));

            return $this->report((string) $this->option('url'), $result);
        }

        $nodes = HermesNode::query()->get();

        if ($nodes->isEmpty()) {
            $this->warn('Belum ada node Hermes terdaftar di tabel hermes_nodes.');

            return self::FAILURE;
        }

        $failures = 0;

        foreach ($nodes as $node) {
            $result = $client->ping((string) $node->api_url, (string) $node->api_secret_reference);
            $failures += $this->report($node->name.' ('.$node->api_url.')', $result) === self::SUCCESS ? 0 : 1;
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param array{ok: bool, status: int|null, detail: string} $result */
    private function report(string $label, array $result): int
    {
        if ($result['ok']) {
            $this->info('OK   '.$label.' - '.$result['detail']);

            return self::SUCCESS;
        }

        $this->error('GAGAL '.$label.' - '.$result['detail']);

        return self::FAILURE;
    }
}
