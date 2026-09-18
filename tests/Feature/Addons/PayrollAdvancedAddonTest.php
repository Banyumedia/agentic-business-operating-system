<?php

namespace Tests\Feature\Addons;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Models\Company;
use App\Models\Employee;
use App\Models\MembershipPlan;
use App\Models\ModuleSetting;
use App\Models\Payroll;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\FeatureResolver;
use App\Services\Preset\EloquentPresetSource;
use Database\Seeders\BusinessPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollAdvancedAddonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
        $this->app->scoped(CompanyContext::class, EloquentCompanyContext::class);
        $this->app->scoped(CompanySettingsStore::class, EloquentCompanySettingsStore::class);
        $this->app->bind(PresetSource::class, EloquentPresetSource::class);

        $this->artisan('db:seed', ['--class' => BusinessPresetSeeder::class]);
    }

    public function test_payroll_advanced_capability_adds_fields(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $owner->id,
            'privacy_accepted_at' => now(),
            'privacy_accepted_by_user_id' => $owner->id,
            'privacy_policy_version' => '1.0',
        ]);

        $plan = MembershipPlan::factory()->create(['features' => ['addon.payroll_advanced']]);
        $company->memberships()->create([
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        ModuleSetting::create([
            'company_id' => $company->id,
            'module_name' => 'features',
            'settings_json' => ['addon.payroll_advanced' => true],
        ]);

        app(CompanyContext::class)->setCurrent((string) $company->id);
        $resolver = app(FeatureResolver::class);
        $this->assertTrue($resolver->enabled('addon.payroll_advanced'));

        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $payroll = Payroll::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_month' => '2026-09',
            'gross_salary' => 10000000,
            'bpjs_kesehatan' => 100000,
            'bpjs_ketenagakerjaan' => 200000,
            'pph21' => 500000,
            'net_salary' => 9200000,
        ]);

        $this->assertEquals(100000, $payroll->fresh()->bpjs_kesehatan);
        $this->assertEquals(500000, $payroll->fresh()->pph21);
    }
}
