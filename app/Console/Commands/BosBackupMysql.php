<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;

/**
 * UR-06: backup MySQL produksi terenkripsi (mysqldump + openssl AES-256-CBC).
 *
 * Fail-closed: tanpa BACKUP_ENCRYPTION_KEY command menolak jalan - tidak ada
 * backup plain-text. Password DB dari config, bukan hardcoded.
 */
class BosBackupMysql extends Command
{
    protected $signature = 'bos:backup-mysql
        {--keep=7 : Retensi maksimum file backup (dirotasi)}
        {--key-hint : Tampilkan fingerprint kunci untuk verifikasi restore}';

    protected $description = 'Backup database MySQL terenkripsi (AES-256-CBC) + rotasi retensi';

    public function handle(): int
    {
        $key = Config::get('services.backup.encryption_key') ?? env('BACKUP_ENCRYPTION_KEY');

        if (! is_string($key) || strlen($key) < 32) {
            $this->error('BACKUP_ENCRYPTION_KEY tidak diset / terlalu pendek (min 32 char). Backup tanpa enkripsi ditolak (fail-closed).');

            return self::FAILURE;
        }

        $connection = Config::get('database.connections.mysql');
        if (! is_array($connection)) {
            $this->error('Koneksi mysql tidak ditemukan di config database.');

            return self::FAILURE;
        }

        // ponytail: path binary dari config, default di PATH - upgrade saat multi-host.
        $mysqldump = Config::get('services.backup.mysqldump_path') ?: 'mysqldump';
        $openssl = Config::get('services.backup.openssl_path') ?: 'openssl';

        $backupDir = storage_path('app/backups');
        if (! is_dir($backupDir) && ! mkdir($backupDir, 0775, true) && ! is_dir($backupDir)) {
            $this->error("Tidak bisa membuat direktori backup: {$backupDir}");

            return self::FAILURE;
        }

        $timestamp = now()->format('Ymd-His');
        $dest = "{$backupDir}/mysql-{$timestamp}.sql.enc";
        $tmpDump = "{$backupDir}/.tmp-dump-{$timestamp}.sql";

        $dumpCmd = sprintf(
            '%s --host=%s --port=%s --user=%s %s --routines --triggers --single-transaction --no-tablespaces --result-file=%s %s',
            escapeshellarg($mysqldump),
            escapeshellarg((string) $connection['host']),
            escapeshellarg((string) $connection['port']),
            escapeshellarg((string) $connection['username']),
            $connection['password'] !== null && $connection['password'] !== ''
                ? '--password='.escapeshellarg((string) $connection['password'])
                : '',
            escapeshellarg($tmpDump),
            escapeshellarg((string) $connection['database'])
        );

        $dumpResult = Process::run($dumpCmd);
        if (! $dumpResult->successful() || ! is_file($tmpDump) || (int) filesize($tmpDump) === 0) {
            $this->error('mysqldump gagal: '.trim($dumpResult->errorOutput()));
            @unlink($tmpDump);

            return self::FAILURE;
        }

        // Kunci masuk via -pass pass: argumen - terbatas di shell lokal single
        // user Windows service; upgrade path: env var OPENSSL_PASS via Process::env.
        $encResult = Process::run(sprintf(
            '%s enc -aes-256-cbc -pbkdf2 -iter 60000 -salt -pass pass:%s -in %s -out %s',
            escapeshellarg($openssl),
            escapeshellarg($key),
            escapeshellarg($tmpDump),
            escapeshellarg($dest)
        ));
        @unlink($tmpDump);

        if (! $encResult->successful() || ! is_file($dest) || (int) filesize($dest) < 200) {
            $this->error('openssl enkripsi gagal: '.trim($encResult->errorOutput()));
            @unlink($dest);

            return self::FAILURE;
        }

        $size = filesize($dest);
        $this->info("Backup OK: {$dest} ({$size} bytes, AES-256-CBC + PBKDF2 60k iter)");

        $keep = max(1, (int) $this->option('keep'));
        $files = glob("{$backupDir}/mysql-*.sql.enc") ?: [];
        usort($files, fn ($a, $b) => strcmp($b, $a)); // terbaru dulu
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
            $this->line('Rotasi hapus: '.basename($old));
        }

        if ($this->option('key-hint')) {
            $this->line('Key fingerprint (sha256/12): '.substr(hash('sha256', $key), 0, 12));
        }

        return self::SUCCESS;
    }
}
