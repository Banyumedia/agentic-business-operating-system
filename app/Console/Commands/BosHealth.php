<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

/**
 * UR-06: health checklist observability - web/DB/queue/scheduler/failed-jobs.
 * Exit code non-zero bila ada check gagal (dipakai alert scheduler).
 */
class BosHealth extends Command
{
    /** @var array<int, array{ok: bool, name: string, detail: string}> */
    private array $results = [];

    protected $signature = 'bos:health
        {--web-url=http://127.0.0.1:8010/login : URL probe web}
        {--web-timeout=10 : Timeout probe web (detik)}
        {--skip-web : Lewati probe web (untuk run dari dalam web worker)}';

    protected $description = 'Health checklist: DB, queue, scheduler, failed jobs, web; exit 1 bila ada gagal';

    public function handle(): int
    {
        $this->checkDatabase();
        $this->checkQueue();
        $this->checkScheduler();
        $this->checkFailedJobs();
        if (! $this->option('skip-web')) {
            $this->checkWeb();
        }

        $failed = array_filter($this->results, fn ($r) => ! $r['ok']);
        foreach ($this->results as $r) {
            $line = sprintf('[%s] %s: %s', $r['ok'] ? ' OK ' : 'FAIL', $r['name'], $r['detail']);
            $r['ok'] ? $this->line($line) : $this->error($line);
        }

        if ($failed !== []) {
            $this->error('HEALTH: '.count($failed).' check gagal.');

            return self::FAILURE;
        }

        $this->info('HEALTH: semua check lulus ('.count($this->results).').');

        return self::SUCCESS;
    }

    private function checkDatabase(): void
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $tables = Schema::getTableListing();
            $this->results[] = [
                'ok' => true,
                'name' => 'database',
                'detail' => sprintf('ping %.0fms, %d tabel', (microtime(true) - $start) * 1000, count($tables)),
            ];
        } catch (\Throwable $e) {
            $this->results[] = ['ok' => false, 'name' => 'database', 'detail' => $e->getMessage()];
        }
    }

    private function checkQueue(): void
    {
        try {
            $pending = DB::table('jobs')->count();
            $lastActivity = DB::table('jobs')->max('created_at');
            // Queue worker hidup = tabel jobs bisa dibaca; worker macet total
            // terdeteksi lewat job tertua menunggu terlalu lama.
            $oldest = DB::table('jobs')->min('available_at');
            $stalled = $pending > 0 && $oldest !== null && now()->timestamp - (int) $oldest > 900;
            $this->results[] = [
                'ok' => ! $stalled,
                'name' => 'queue',
                'detail' => sprintf('pending=%d last=%s oldest_wait=%ss', $pending, $lastActivity ?? '-', $oldest !== null ? now()->timestamp - (int) $oldest : 0),
            ];
        } catch (\Throwable $e) {
            $this->results[] = ['ok' => false, 'name' => 'queue', 'detail' => $e->getMessage()];
        }
    }

    private function checkScheduler(): void
    {
        // Scheduler PM2 menjalankan `schedule:work`; jejak kesehatan = log
        // scheduler berisi baris run < hari ini (schedule:list jalan tiap menit).
        $log = storage_path('logs/pm2-scheduler-out.log');
        $fresh = is_file($log) && now()->diffInSeconds(now()->setTimestamp(filemtime($log))) < 3600
            && filesize($log) > 0;
        // Fallback: cache schedule terakhir diproses (schedule:run menghitung).
        try {
            $lastRun = cache()->get('bos:schedule:last-run');
            $schedulerOk = $fresh || ($lastRun !== null && now()->diffInMinutes($lastRun) < 5);
        } catch (\Throwable) {
            $schedulerOk = $fresh;
        }
        $this->results[] = [
            'ok' => $schedulerOk,
            'name' => 'scheduler',
            'detail' => sprintf('log_mtime_fresh=%s last_run_cache=%s', $fresh ? 'ya' : 'tidak', isset($lastRun) ? ($lastRun ? $lastRun->toDateTimeString() : '-') : '-'),
        ];
    }

    private function checkFailedJobs(): void
    {
        try {
            $failed = DB::table('failed_jobs')->count();
            $this->results[] = [
                'ok' => $failed === 0,
                'name' => 'failed-jobs',
                'detail' => sprintf('total=%d', $failed),
            ];
        } catch (\Throwable $e) {
            $this->results[] = ['ok' => false, 'name' => 'failed-jobs', 'detail' => $e->getMessage()];
        }
    }

    private function checkWeb(): void
    {
        $url = (string) $this->option('web-url');
        // curl exit != 0 wajar di Windows (target /dev/null); validasi lewat
        // HTTP code yang tercetak di output, bukan exit code proses.
        $result = Process::timeout((int) $this->option('web-timeout'))->run(
            sprintf('curl -s -o NUL -w "%%{http_code}" %s', escapeshellarg($url))
        );
        $code = trim($result->output());
        $ok = $code !== '' && in_array($code, ['200', '302'], true);
        $this->results[] = [
            'ok' => $ok,
            'name' => 'web',
            'detail' => sprintf('%s -> HTTP %s', $url, $code !== '' ? $code : 'ERR'),
        ];
    }
}
