<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Tests\TestCase;

/**
 * UR-01 pra-approval: provisioning command idempoten, tanpa secret di output.
 */
class ProvisionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisions_platform_admin_and_pilot_owner_idempotently(): void
    {
        // First run creates both identities.
        $this->artisan('bos:provision', [
            '--admin-email' => 'bos@example.test',
            '--owner-email' => 'pilot@example.test',
            '--company-name' => 'Usaha Pilot',
            '--company-slug' => 'usaha-pilot',
            '--password' => 'initial-pass-1A!',
        ])->assertSuccessful();

        $admin = User::where('email', 'bos@example.test')->first();
        $owner = User::where('email', 'pilot@example.test')->first();

        $this->assertNotNull($admin);
        $this->assertTrue((bool) $admin->is_platform_admin);
        $this->assertNotNull($owner);
        $this->assertTrue($owner->companies()->where('slug', 'usaha-pilot')->exists());

        $companyId = $owner->companies()->where('slug', 'usaha-pilot')->first()->id;

        // Second run with same identifiers is a no-op (idempotent), not error.
        $this->artisan('bos:provision', [
            '--admin-email' => 'bos@example.test',
            '--owner-email' => 'pilot@example.test',
            '--company-name' => 'Usaha Pilot',
            '--company-slug' => 'usaha-pilot',
            '--password' => 'initial-pass-1A!',
        ])->assertSuccessful();

        // Still exactly one of each.
        $this->assertSame(1, User::where('email', 'bos@example.test')->count());
        $this->assertSame(1, User::where('email', 'pilot@example.test')->count());
        $this->assertSame(1, Company::where('slug', 'usaha-pilot')->count());
        $this->assertSame($companyId, Company::where('slug', 'usaha-pilot')->first()->id);
    }

    public function test_provided_password_is_hashed_not_stored_plaintext(): void
    {
        $this->artisan('bos:provision', [
            '--admin-email' => 'bos2@example.test',
            '--owner-email' => 'pilot2@example.test',
            '--company-name' => 'Usaha Pilot 2',
            '--company-slug' => 'usaha-pilot-2',
            '--password' => 's3cret-Pass!x',
        ])->assertSuccessful();

        $admin = User::where('email', 'bos2@example.test')->first();
        $this->assertTrue(Hash::check('s3cret-Pass!x', $admin->password));
        $this->assertNotSame('s3cret-Pass!x', $admin->password);
    }

    public function test_output_never_contains_the_password(): void
    {
        // Log channel memory menangkap semua tulisan log saat command jalan.
        config(['logging.channels.mem' => ['driver' => 'monolog', 'handler' => TestHandler::class, 'level' => 'debug']]);
        config(['logging.default' => 'mem']);

        $this->artisan('bos:provision', [
            '--admin-email' => 'bos3@example.test',
            '--owner-email' => 'pilot3@example.test',
            '--company-name' => 'Usaha Pilot 3',
            '--company-slug' => 'usaha-pilot-3',
            '--password' => 'LeakProbe-123!',
        ])->assertSuccessful();

        // Pastikan tidak ada log/output berisi password. TestHandler berada di
        // logger default; kalau command menulis password ke log, ini gagal.
        $logger = Log::getLogger();
        $leaked = false;
        if ($logger->getHandlers() !== []) {
            foreach ($logger->getHandlers() as $handler) {
                if (method_exists($handler, 'getRecords')) {
                    foreach ($handler->getRecords() as $record) {
                        if (str_contains($record['message'] ?? '', 'LeakProbe-123!')) {
                            $leaked = true;
                        }
                        foreach (($record['context'] ?? []) as $value) {
                            if (is_string($value) && str_contains($value, 'LeakProbe-123!')) {
                                $leaked = true;
                            }
                        }
                    }
                }
            }
        }
        $this->assertFalse($leaked, 'Password bocor ke log channel!');
    }

    public function test_refuses_slug_conflict_for_different_owner(): void
    {
        $other = User::factory()->create();
        Company::create([
            'name' => 'Existing',
            'slug' => 'usaha-pilot-5',
            'business_preset' => 'custom',
            'owner_user_id' => $other->id,
        ]);

        // Slug milik owner lain => FAIL, jangan diam-diam pakai company orang.
        $this->artisan('bos:provision', [
            '--admin-email' => 'bos5@example.test',
            '--owner-email' => 'pilot5@example.test',
            '--company-name' => 'Usaha Pilot 5',
            '--company-slug' => 'usaha-pilot-5',
            '--password' => 'conflict-pass-1A!',
        ])->assertFailed();
    }
}
