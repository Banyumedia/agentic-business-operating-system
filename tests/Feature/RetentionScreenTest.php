<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Livewire\Screens\RetentionScreen;
use App\Models\CashEntry;
use App\Models\Company;
use App\Models\Project;
use App\Models\Retention;
use App\Models\User;
use App\Providers\DataSourceServiceProvider;
use App\Services\FeatureResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * MP-09: pencairan retensi. `RetentionService::computeAndHold()`/
 * `ensureCanBeInvoiced()` sudah teruji sejak sebelum task ini; yang belum ada
 * adalah jalur UI untuk mencairkan retensi yang tertahan.
 */
class RetentionScreenTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        config(['datasource.driver' => 'eloquent']);
        (new DataSourceServiceProvider($this->app))->register();
        $this->artisan('db:seed', ['--class' => 'BusinessPresetSeeder']);

        $this->owner = User::factory()->create();
        $this->company = Company::factory()->create(['owner_user_id' => $this->owner->id, 'business_preset' => 'contractor']);
        $this->owner->update(['current_company_id' => $this->company->id]);
        $this->project = Project::factory()->create(['company_id' => $this->company->id]);

        app(CompanyContext::class)->setCurrent((string) $this->company->id);
        $this->actingAs($this->owner);
        $this->mockConstructionRetentionEnabled();
    }

    /**
     * Company fixture di test ini tidak punya membership plan aktif, jadi
     * `FeatureResolver` nyata akan menjawab `construction.retention` mati
     * terlepas dari capability preset - sama seperti pola
     * `CashierScreenEloquentTest::mockFinanceAccountingEnabled()`. Yang diuji
     * di sini adalah wiring pencairan, bukan gerbang kapabilitas itu sendiri.
     */
    private function mockConstructionRetentionEnabled(): void
    {
        $features = Mockery::mock(FeatureResolver::class);
        $features->shouldReceive('enabled')->andReturnTrue();
        $this->app->instance(FeatureResolver::class, $features);
    }

    private function heldRetention(?Carbon $releaseOn = null): Retention
    {
        return Retention::factory()->create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'amount' => 750000,
            'status' => 'held',
            'release_on' => $releaseOn,
        ]);
    }

    public function test_owner_can_disburse_a_retention_past_its_release_date(): void
    {
        $retention = $this->heldRetention(Carbon::yesterday());

        Livewire::test(RetentionScreen::class, ['module' => 'projects', 'submodule' => 'retention'])
            ->call('requestRelease', $retention->id)
            ->set('confirmPhrase', 'YA')
            ->call('confirmRelease')
            ->assertSet('failure', null)
            ->assertSee('Retensi dicairkan');

        $retention->refresh();
        $this->assertSame('released', $retention->status);
        $this->assertNotNull($retention->released_at);

        $this->assertDatabaseHas('cash_entries', [
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'direction' => 'out',
            'amount' => 750000,
            'source_type' => 'retention',
            'source_id' => $retention->id,
        ]);
    }

    public function test_negative_disbursement_without_typing_ya_is_rejected(): void
    {
        $retention = $this->heldRetention(Carbon::yesterday());

        Livewire::test(RetentionScreen::class, ['module' => 'projects', 'submodule' => 'retention'])
            ->call('requestRelease', $retention->id)
            ->set('confirmPhrase', 'ya, tolong')
            ->call('confirmRelease')
            ->assertSee('Ketik YA tepat seperti tertulis');

        $this->assertSame('held', $retention->fresh()->status);
        $this->assertSame(0, CashEntry::where('source_type', 'retention')->count());
    }

    public function test_negative_disbursement_before_release_date_is_rejected(): void
    {
        $retention = $this->heldRetention(Carbon::tomorrow());

        Livewire::test(RetentionScreen::class, ['module' => 'projects', 'submodule' => 'retention'])
            ->call('requestRelease', $retention->id)
            ->assertSee('belum mencapai tanggal rilis')
            ->assertSet('pendingReleaseId', null);

        $this->assertSame('held', $retention->fresh()->status);
        $this->assertSame(0, CashEntry::where('source_type', 'retention')->count());
    }

    public function test_negative_already_released_retention_cannot_be_disbursed_twice(): void
    {
        $retention = $this->heldRetention(Carbon::yesterday());

        Livewire::test(RetentionScreen::class, ['module' => 'projects', 'submodule' => 'retention'])
            ->call('requestRelease', $retention->id)
            ->set('confirmPhrase', 'YA')
            ->call('confirmRelease')
            ->assertSet('failure', null);

        $this->assertSame('released', $retention->fresh()->status);

        // Percobaan kedua lewat jalur publik yang sama - requestRelease
        // menolak lebih dulu karena status bukan lagi `held`, sebelum
        // sempat membuka dialog konfirmasi.
        Livewire::test(RetentionScreen::class, ['module' => 'projects', 'submodule' => 'retention'])
            ->call('requestRelease', $retention->id)
            ->assertSee('sudah tidak berstatus tertahan')
            ->assertSet('pendingReleaseId', null);

        $this->assertSame(1, CashEntry::where('source_type', 'retention')->where('source_id', $retention->id)->count());
    }

    public function test_negative_staff_cannot_disburse_a_retention(): void
    {
        $retention = $this->heldRetention(Carbon::yesterday());

        $staff = User::factory()->create(['current_company_id' => $this->company->id]);
        DB::table('company_user')->insert([
            'company_id' => $this->company->id,
            'user_id' => $staff->id,
            'role' => 'staff',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($staff);

        Livewire::test(RetentionScreen::class, ['module' => 'projects', 'submodule' => 'retention'])
            ->call('requestRelease', $retention->id)
            ->set('confirmPhrase', 'YA')
            ->call('confirmRelease')
            ->assertStatus(403);

        $this->assertSame('held', $retention->fresh()->status);
    }

    public function test_negative_retention_belonging_to_another_company_cannot_be_disbursed(): void
    {
        $foreignCompany = Company::factory()->create();
        $foreignProject = Project::factory()->create(['company_id' => $foreignCompany->id]);
        $foreignRetention = Retention::factory()->create([
            'company_id' => $foreignCompany->id,
            'project_id' => $foreignProject->id,
            'status' => 'held',
            'release_on' => Carbon::yesterday(),
        ]);

        Livewire::test(RetentionScreen::class, ['module' => 'projects', 'submodule' => 'retention'])
            ->call('requestRelease', $foreignRetention->id)
            ->assertSee('tidak ditemukan pada usaha ini');

        $this->assertSame('held', $foreignRetention->fresh()->status);
    }

    public function test_negative_cancelling_a_pending_release_leaves_status_unchanged(): void
    {
        $retention = $this->heldRetention(Carbon::yesterday());

        Livewire::test(RetentionScreen::class, ['module' => 'projects', 'submodule' => 'retention'])
            ->call('requestRelease', $retention->id)
            ->assertSet('pendingReleaseId', $retention->id)
            ->call('cancelRelease')
            ->assertSet('pendingReleaseId', null);

        $this->assertSame('held', $retention->fresh()->status);
        $this->assertSame(0, CashEntry::where('source_type', 'retention')->count());
    }

    public function test_retention_without_a_release_date_can_be_disbursed_immediately(): void
    {
        $retention = $this->heldRetention(null);

        Livewire::test(RetentionScreen::class, ['module' => 'projects', 'submodule' => 'retention'])
            ->call('requestRelease', $retention->id)
            ->set('confirmPhrase', 'YA')
            ->call('confirmRelease')
            ->assertSet('failure', null);

        $this->assertSame('released', $retention->fresh()->status);
    }
}
