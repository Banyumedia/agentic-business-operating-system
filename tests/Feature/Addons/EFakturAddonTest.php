<?php

namespace Tests\Feature\Addons;

use App\Contracts\Addons\EFaktur\EFakturGatewayContract;
use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Models\BusinessIdentity;
use App\Models\Company;
use App\Models\MembershipPlan;
use App\Models\ModuleSetting;
use App\Models\Order;
use App\Models\OrderEFaktur;
use App\Models\User;
use App\Services\Addons\EFaktur\DummyEFakturGateway;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\FeatureResolver;
use App\Services\Preset\EloquentPresetSource;
use Database\Seeders\BusinessPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EFakturAddonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
        $this->app->scoped(CompanyContext::class, EloquentCompanyContext::class);
        $this->app->scoped(CompanySettingsStore::class, EloquentCompanySettingsStore::class);
        $this->app->bind(PresetSource::class, EloquentPresetSource::class);
        $this->app->bind(EFakturGatewayContract::class, DummyEFakturGateway::class);

        $this->artisan('db:seed', ['--class' => BusinessPresetSeeder::class]);
    }

    public function test_efaktur_capability_can_be_enabled_for_taxable_companies(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $identity = BusinessIdentity::factory()->create([
            'company_id' => $company->id,
            'tax_mode' => 'taxable',
        ]);

        $plan = MembershipPlan::factory()->create(['features' => ['addon.efaktur']]);
        $company->memberships()->create([
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        ModuleSetting::create([
            'company_id' => $company->id,
            'module_name' => 'features',
            'settings_json' => ['addon.efaktur' => true],
        ]);

        app(CompanyContext::class)->setCurrent((string) $company->id);
        $resolver = app(FeatureResolver::class);
        $this->assertTrue($resolver->enabled('addon.efaktur'));

        $order = Order::factory()->create([
            'company_id' => $company->id,
            'business_identity_id' => $identity->id,
            'tax_amount' => 11000,
        ]);

        $efaktur = OrderEFaktur::factory()->create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'ppn' => 11000,
            'status' => 'belum_dikirim',
        ]);

        $gateway = app(EFakturGatewayContract::class);
        $result = $gateway->submitInvoice($order);

        $efaktur->update([
            'status' => $result['status'],
            'nomor_seri' => $result['nomor_seri_faktur_pajak'],
        ]);

        $this->assertEquals('terkirim', $efaktur->fresh()->status);
        $this->assertNotNull($efaktur->fresh()->nomor_seri);
    }

    public function test_efaktur_records_are_scoped_to_company_and_do_not_leak_across_tenants(): void
    {
        $ownerA = User::factory()->create();
        $companyA = Company::factory()->create(['owner_user_id' => $ownerA->id]);
        $identityA = BusinessIdentity::factory()->create([
            'company_id' => $companyA->id,
            'tax_mode' => 'taxable',
        ]);
        $orderA = Order::factory()->create([
            'company_id' => $companyA->id,
            'business_identity_id' => $identityA->id,
            'tax_amount' => 11000,
        ]);
        OrderEFaktur::factory()->create([
            'company_id' => $companyA->id,
            'order_id' => $orderA->id,
        ]);

        $ownerB = User::factory()->create();
        $companyB = Company::factory()->create(['owner_user_id' => $ownerB->id]);
        $identityB = BusinessIdentity::factory()->create([
            'company_id' => $companyB->id,
            'tax_mode' => 'taxable',
        ]);
        $orderB = Order::factory()->create([
            'company_id' => $companyB->id,
            'business_identity_id' => $identityB->id,
            'tax_amount' => 22000,
        ]);
        OrderEFaktur::factory()->create([
            'company_id' => $companyB->id,
            'order_id' => $orderB->id,
        ]);

        $this->assertSame(1, OrderEFaktur::query()->where('company_id', $companyA->id)->count());
        $this->assertSame(1, OrderEFaktur::query()->where('company_id', $companyB->id)->count());
        $this->assertSame(2, OrderEFaktur::query()->count());
    }
}
