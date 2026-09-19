<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Jobs\BuildCompanyExport;
use App\Livewire\Settings\DataExport;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\Preset\EloquentPresetSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DataExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('datasource.driver', 'eloquent');
        $this->app->scoped(CompanyContext::class, EloquentCompanyContext::class);
        $this->app->scoped(CompanySettingsStore::class, EloquentCompanySettingsStore::class);
        $this->app->scoped(PresetSource::class, EloquentPresetSource::class);

        Storage::fake('local');
        Storage::fake('company-json');
    }

    public function test_owner_can_see_export_tab_and_dispatch_job_even_when_read_only(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'slug' => 'demo-company',
        ]);

        $plan = MembershipPlan::factory()->create();
        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'read_only', // menunggak / habis trial
        ]);

        $this->actingAs($user);
        app(CompanyContext::class)->setCurrent((string) $company->id);
        $this->withSession(['company_role' => 'owner', 'active_company' => $company->slug]);

        // Verify the export tab is visible and accessible
        $this->get('/app/settings/export')
            ->assertOk()
            ->assertSee('Ekspor Data Usaha');

        Livewire::test(DataExport::class)
            ->call('export')
            ->assertSet('isExporting', false)
            ->assertSet('downloadUrl', route('settings.export.download', ['company' => $company->id]));

        Queue::assertPushed(BuildCompanyExport::class);
    }

    public function test_download_returns_404_if_no_file_exists(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'slug' => 'demo-company',
        ]);

        $this->actingAs($user);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        $this->get('/app/settings/export/download')
            ->assertNotFound();
    }

    public function test_non_owner_cannot_download_export_even_when_current_company_is_set(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $owner->id,
            'slug' => 'demo-company',
        ]);

        $staff = User::factory()->create([
            'current_company_id' => $company->id,
        ]);

        $this->actingAs($staff)
            ->withSession(['active_company' => (string) $company->id])
            ->get('/app/settings/export/download')
            ->assertForbidden();
    }
}
