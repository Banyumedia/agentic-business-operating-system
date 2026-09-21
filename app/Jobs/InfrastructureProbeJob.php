<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * UR-02: job uji infrastruktur queue. Idempoten-ganda: menulis marker
 * file + log setiap eksekusi supaya pemrosesan tepat-sekali terlihat
 * dari jumlah marker (ditambah job_id unik per dispatch).
 */
class InfrastructureProbeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $probeId) {}

    public function handle(): void
    {
        $marker = storage_path('app/queue-probe-'.$this->probeId.'.txt');

        // Append (bukan overwrite): kalau job sama diproses dua kali, file
        // berisi 2 baris - kegagalan "tepat sekali" langsung terlihat.
        @file_put_contents($marker, now()->toIso8601String().PHP_EOL, FILE_APPEND);
        Log::info('queue-probe executed', ['probe_id' => $this->probeId]);
    }
}
