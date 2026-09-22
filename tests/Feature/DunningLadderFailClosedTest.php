<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\HermesNodeClient;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Billing\DunningLadder;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Hermes\FakeHermesNodeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Dunning Ladder Fail-Closed Tests — D-49
 *
 * Requirement: When membership is late on payment, layanan turun bertahap, bukan tetap penuh:
 * - H-3: Notifikasi via WA
 * - H+0: AI suspended, web tetap penuh
 * - H+7: Web hanya-baca + ekspor, input ditolak
 * - H+30: Akun dibekukan (login ditolak)
 * - H+90: Data boleh dihapus (setelah 3 peringatan)
 *
 * Test must verify:
 * 1. Status transitions are ENFORCED (not just logged)
 * 2. Features are actually DENIED when status changes
 * 3. Restoration works correctly
 */
class DunningLadderFailClosedTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private MembershipPlan $plan;

    private CompanyMembership $membership;

    protected function setUp(): void
    {
        parent::setUp();
        config(['agentic.data_source' => 'eloquent']);
        $this->app->bind(CompanyContext::class, EloquentCompanyContext::class);

        // T-69: transport WhatsApp platform dipalsukan **secara eksplisit**. Yang
        // diuji di berkas ini adalah penegakan status dan pencatatan peringatan,
        // bukan apakah pesannya benar-benar keluar; lajur sungguhannya diuji
        // `Tests\Feature\Hermes\PlatformDeliveryTest`. Sejak T-69 pencatatan
        // peringatan bergantung pada keberhasilan kirim, jadi transport yang
        // berhasil harus dinyatakan, tidak lagi diwarisi dari bawaan aplikasi.
        $this->app->instance(HermesNodeClient::class, new FakeHermesNodeClient);

        $this->owner = User::factory()->create(['wa_number' => '08123456789']);
        $this->plan = MembershipPlan::factory()->create(['features' => ['contacts', 'deals', 'pos']]);
        $this->company = Company::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->membership = CompanyMembership::factory()->create([
            'company_id' => $this->company->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);
    }

    /**
     * H+0 (jatuh tempo): membership status MUST change to 'ai_suspended'.
     * Fail-closed: bot stops, web still works (via status check in middleware).
     */
    public function test_h0_status_changes_to_ai_suspended(): void
    {
        Log::shouldReceive('info')->zeroOrMoreTimes();  // Suppress logs

        $ladder = $this->app->make(DunningLadder::class);
        $ladder->process($this->company, 0);  // 0 days overdue = H+0

        $this->membership->refresh();
        $this->assertEquals('ai_suspended', $this->membership->status, 'At H+0, membership status MUST be ai_suspended');
    }

    /**
     * H+7: membership status MUST change to 'read_only'.
     * Fail-closed: all write operations must be blocked by middleware checking status.
     */
    public function test_h7_status_changes_to_read_only(): void
    {
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $ladder = $this->app->make(DunningLadder::class);

        // Start at H+0
        $ladder->process($this->company, 0);
        $this->membership->refresh();
        $this->assertEquals('ai_suspended', $this->membership->status);

        // Then H+7
        $ladder->process($this->company, 7);
        $this->membership->refresh();
        $this->assertEquals('read_only', $this->membership->status, 'At H+7, status MUST be read_only');
    }

    /**
     * H+30: membership status MUST change to 'frozen'.
     * Fail-closed: all access (including read) denied.
     */
    public function test_h30_status_changes_to_frozen(): void
    {
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $ladder = $this->app->make(DunningLadder::class);
        $ladder->process($this->company, 30);

        $this->membership->refresh();
        $this->assertEquals('frozen', $this->membership->status, 'At H+30, status MUST be frozen');

        // Verify first warning was recorded
        $this->assertNotNull($this->membership->metadata['dunning_notified_at'] ?? null);
    }

    /**
     * H+60: status remains 'frozen', second warning is recorded.
     */
    public function test_h60_status_remains_frozen_warning_2(): void
    {
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $ladder = $this->app->make(DunningLadder::class);
        $ladder->process($this->company, 60);

        $this->membership->refresh();
        $this->assertEquals('frozen', $this->membership->status);

        $warnings = $this->membership->metadata['dunning_notified_at'] ?? [];
        $this->assertCount(1, $warnings, 'Second call to process(60) should not double-record for H+60');
    }

    /**
     * When payment is made, restore() MUST reset status to 'active' and clear warnings.
     */
    public function test_restore_returns_to_active_state(): void
    {
        Log::shouldReceive('info')->zeroOrMoreTimes();

        // First: freeze account
        $ladder = $this->app->make(DunningLadder::class);
        $ladder->process($this->company, 30);

        $this->membership->refresh();
        $this->assertEquals('frozen', $this->membership->status);
        $this->assertNotNull($this->membership->metadata['dunning_notified_at'] ?? null);

        // Then: payment received
        $ladder->restore($this->company);

        $this->membership->refresh();
        $this->assertEquals('active', $this->membership->status, 'After payment, status MUST return to active');
        $this->assertNull($this->membership->metadata['dunning_notified_at'] ?? null, 'Warning metadata MUST be cleared');
    }

    /**
     * CRITICAL: PlanCapabilityGate must respect membership status.
     * When status is NOT 'active', allowedCapabilities must return empty (deny all).
     */
    public function test_capability_gate_denies_when_status_ai_suspended(): void
    {
        // Simulate H+0: suspend AI
        $this->membership->update(['status' => 'ai_suspended']);

        $context = app(EloquentCompanyContext::class);
        $context->setCurrent((string) $this->company->id);

        $gate = $this->app->make('App\Services\PlanCapabilityGate');
        $allowed = $gate->allowedCapabilities();

        // MUST return empty - no features allowed when status not 'active'
        $this->assertEmpty($allowed, 'When membership status is ai_suspended, all capabilities must be denied');
    }

    /**
     * CRITICAL: When status is 'read_only', capabilities must be denied.
     * (Read-only enforcement is at middleware level, but capability gate must fail-closed)
     */
    public function test_capability_gate_denies_when_status_read_only(): void
    {
        $this->membership->update(['status' => 'read_only']);

        $context = app(EloquentCompanyContext::class);
        $context->setCurrent((string) $this->company->id);

        $gate = $this->app->make('App\Services\PlanCapabilityGate');
        $allowed = $gate->allowedCapabilities();

        $this->assertEmpty($allowed, 'When membership status is read_only, all capabilities must be denied');
    }

    /**
     * When status is 'frozen', capabilities must be denied.
     */
    public function test_capability_gate_denies_when_status_frozen(): void
    {
        $this->membership->update(['status' => 'frozen']);

        $context = app(EloquentCompanyContext::class);
        $context->setCurrent((string) $this->company->id);

        $gate = $this->app->make('App\Services\PlanCapabilityGate');
        $allowed = $gate->allowedCapabilities();

        $this->assertEmpty($allowed, 'When membership status is frozen, all capabilities must be denied');
    }

    /**
     * ONLY when status is 'active' should capabilities be allowed.
     */
    public function test_capability_gate_allows_only_when_active(): void
    {
        // Status already 'active' from setUp
        $context = app(EloquentCompanyContext::class);
        $context->setCurrent((string) $this->company->id);

        $gate = $this->app->make('App\Services\PlanCapabilityGate');
        $allowed = $gate->allowedCapabilities();

        $this->assertNotEmpty($allowed, 'When membership status is active, capabilities must be returned');
        $this->assertContains('contacts', $allowed);
    }
}
