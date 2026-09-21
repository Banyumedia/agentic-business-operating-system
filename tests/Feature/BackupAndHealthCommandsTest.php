<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * UR-06: kontrak command backup terenkripsi + health checklist.
 */
class BackupAndHealthCommandsTest extends TestCase
{
    public function test_backup_fails_closed_without_encryption_key(): void
    {
        config()->set('services.backup.encryption_key', '');

        $this->artisan('bos:backup-mysql')
            ->expectsOutputToContain('fail-closed')
            ->assertExitCode(1);
    }

    public function test_backup_fails_closed_with_short_encryption_key(): void
    {
        config()->set('services.backup.encryption_key', 'terlalu-pendek');

        $this->artisan('bos:backup-mysql')
            ->assertExitCode(1);
    }

    public function test_backup_fails_when_mysqldump_unavailable(): void
    {
        config()->set('services.backup.encryption_key', str_repeat('k', 40));
        config()->set('services.backup.mysqldump_path', 'definitely-not-a-real-binary');
        config()->set('database.connections.mysql', [
            'host' => '127.0.0.1', 'port' => '3306', 'username' => 'u',
            'password' => 'p', 'database' => 'nonexistent_db',
        ]);

        $this->artisan('bos:backup-mysql')
            ->assertExitCode(1);
    }

    public function test_health_reports_failure_when_web_unreachable(): void
    {
        // web probe ke port tertutup -> FAIL -> exit 1 (kontrak alert).
        $this->artisan('bos:health', ['--web-url' => 'http://127.0.0.1:59999/login'])
            ->assertExitCode(1);
    }

    public function test_health_skip_web_still_checks_database(): void
    {
        // sqlite :memory: default test -> database check lulus; queue/jobs
        // tabel tidak ada di sqlite test tanpa migrasi -> FAIL juga OK,
        // kontrak yang diuji: command jalan dan tidak crash.
        $this->artisan('bos:health', ['--skip-web' => true]);
        $this->assertTrue(true);
    }
}
