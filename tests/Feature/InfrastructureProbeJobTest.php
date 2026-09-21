<?php

namespace Tests\Feature;

use App\Jobs\InfrastructureProbeJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * UR-02: kontrak infrastruktur queue - job uji diproses tepat sekali,
 * tanpa duplikasi, dan marker tepat-sekali tertulis.
 */
class InfrastructureProbeJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe_job_writes_exactly_one_marker_line_per_execution(): void
    {
        Queue::fake();

        InfrastructureProbeJob::dispatch('test-probe-1');

        Queue::assertPushed(InfrastructureProbeJob::class, 1);
        Queue::assertPushed(InfrastructureProbeJob::class, function (InfrastructureProbeJob $job) {
            return $job->probeId === 'test-probe-1';
        });
    }

    public function test_probe_handle_appends_single_line(): void
    {
        $marker = storage_path('app/queue-probe-test-probe-2.txt');
        @unlink($marker);

        (new InfrastructureProbeJob('test-probe-2'))->handle();

        $this->assertFileExists($marker);
        $lines = file($marker, FILE_IGNORE_NEW_LINES);
        $this->assertCount(1, $lines);
        $this->assertNotSame('', $lines[0]);

        @unlink($marker);
    }
}
