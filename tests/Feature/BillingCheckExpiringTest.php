<?php

namespace Tests\Feature;

use App\Contracts\HermesNodeClient;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Hermes\FakeHermesNodeClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class BillingCheckExpiringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // T-69: bawaan `App\Contracts\HermesNodeClient` sekarang
        // `PlatformHermesNodeClient` yang benar-benar memanggil bridge. Test ini
        // menguji **tangga dunning**, bukan transportnya, jadi transport dipalsukan
        // di sini secara **eksplisit**.
        //
        // Sebelumnya fake itu adalah bawaan aplikasi, dan test ini mengunci teks
        // "FAKE WA to ..." - artinya yang terbukti selama ini adalah fake-nya, bukan
        // lajurnya. Lajur sungguhan diuji `Tests\Feature\Hermes\PlatformDeliveryTest`.
        $this->app->instance(HermesNodeClient::class, new FakeHermesNodeClient);
    }

    public function test_h3_warning_sent(): void
    {
        Log::shouldReceive('info')->once()->withArgs(function ($msg) {
            return str_contains($msg, 'FAKE WA to 08123456789') && str_contains($msg, 'akan jatuh tempo dalam 3 hari');
        });

        $user = User::factory()->create(['wa_number' => '08123456789']);
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'type' => 'subscription',
            'payment_status' => 'pending',
            'due_date' => Carbon::now()->addDays(3)->toDateString(),
            'order_id' => 'INV-123',
        ]);

        $this->artisan('billing:check-expiring')->assertExitCode(0);
    }

    public function test_h0_status_ai_suspended(): void
    {
        Log::shouldReceive('info')->once()->withArgs(function ($msg) {
            return str_contains($msg, 'Layanan AI ditangguhkan');
        });

        $user = User::factory()->create(['wa_number' => '08123456789']);
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $membership = CompanyMembership::factory()->create(['company_id' => $company->id, 'status' => 'active']);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'type' => 'subscription',
            'payment_status' => 'pending',
            'due_date' => Carbon::now()->toDateString(),
        ]);

        $this->artisan('billing:check-expiring');

        $this->assertEquals('ai_suspended', $membership->fresh()->status);
    }

    public function test_h7_status_read_only(): void
    {
        Log::shouldReceive('info')->once()->withArgs(function ($msg) {
            return str_contains($msg, 'Akses tulis ditangguhkan');
        });

        $user = User::factory()->create(['wa_number' => '08123456789']);
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $membership = CompanyMembership::factory()->create(['company_id' => $company->id, 'status' => 'ai_suspended']);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'type' => 'subscription',
            'payment_status' => 'pending',
            'due_date' => Carbon::now()->subDays(7)->toDateString(),
        ]);

        $this->artisan('billing:check-expiring');

        $this->assertEquals('read_only', $membership->fresh()->status);
    }

    public function test_h30_status_frozen(): void
    {
        Log::shouldReceive('info')->once()->withArgs(function ($msg) {
            return str_contains($msg, 'Peringatan 1: Akun Anda dibekukan');
        });

        $user = User::factory()->create(['wa_number' => '08123456789']);
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $membership = CompanyMembership::factory()->create(['company_id' => $company->id, 'status' => 'read_only']);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'type' => 'subscription',
            'payment_status' => 'pending',
            'due_date' => Carbon::now()->subDays(30)->toDateString(),
        ]);

        $this->artisan('billing:check-expiring');

        $membership->refresh();
        $this->assertEquals('frozen', $membership->status);
        $this->assertCount(1, $membership->metadata['dunning_notified_at']);
    }

    public function test_h60_warning2(): void
    {
        Log::shouldReceive('info')->once()->withArgs(function ($msg) {
            return str_contains($msg, 'Peringatan 2: Akun Anda menunggak 60 hari');
        });

        $user = User::factory()->create(['wa_number' => '08123456789']);
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'status' => 'frozen',
            'metadata' => ['dunning_notified_at' => [Carbon::now()->subDays(30)->toIso8601String()]],
        ]);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'type' => 'subscription',
            'payment_status' => 'pending',
            'due_date' => Carbon::now()->subDays(60)->toDateString(),
        ]);

        $this->artisan('billing:check-expiring');

        $membership->refresh();
        $this->assertEquals('frozen', $membership->status);
        $this->assertCount(2, $membership->metadata['dunning_notified_at']);
    }

    public function test_h83_warning3(): void
    {
        Log::shouldReceive('info')->once()->withArgs(function ($msg) {
            return str_contains($msg, 'Peringatan 3: H-7 penghapusan data');
        });

        $user = User::factory()->create(['wa_number' => '08123456789']);
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'status' => 'frozen',
            'metadata' => ['dunning_notified_at' => [
                Carbon::now()->subDays(53)->toIso8601String(),
                Carbon::now()->subDays(23)->toIso8601String(),
            ]],
        ]);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'type' => 'subscription',
            'payment_status' => 'pending',
            'due_date' => Carbon::now()->subDays(83)->toDateString(),
        ]);

        $this->artisan('billing:check-expiring');

        $membership->refresh();
        $this->assertEquals('frozen', $membership->status);
        $this->assertCount(3, $membership->metadata['dunning_notified_at']);
    }

    public function test_h90_deletion_allowed_with_warnings(): void
    {
        Log::shouldReceive('info')->once()->withArgs(function ($msg) use (&$companyId) {
            return str_contains($msg, "Company $companyId eligible for deletion after 3 warnings.");
        });

        $user = User::factory()->create(['wa_number' => '08123456789']);
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $companyId = $company->id;
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'status' => 'frozen',
            'metadata' => ['dunning_notified_at' => [
                Carbon::now()->subDays(60)->toIso8601String(),
                Carbon::now()->subDays(30)->toIso8601String(),
                Carbon::now()->subDays(7)->toIso8601String(),
            ]],
        ]);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'type' => 'subscription',
            'payment_status' => 'pending',
            'due_date' => Carbon::now()->subDays(90)->toDateString(),
        ]);

        $this->artisan('billing:check-expiring');
    }

    public function test_h90_deletion_denied_without_warnings(): void
    {
        // Assert no deletion log
        Log::shouldReceive('info')->never()->withArgs(function ($msg) {
            return str_contains($msg, 'eligible for deletion after 3 warnings.');
        });

        $user = User::factory()->create(['wa_number' => '08123456789']);
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $companyId = $company->id;
        $membership = CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'status' => 'frozen',
            'metadata' => ['dunning_notified_at' => [
                Carbon::now()->subDays(60)->toIso8601String(), // Only 1 warning
            ]],
        ]);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'type' => 'subscription',
            'payment_status' => 'pending',
            'due_date' => Carbon::now()->subDays(90)->toDateString(),
        ]);

        $this->artisan('billing:check-expiring');
    }
}
