<?php

namespace Tests\Feature\Addons;

use App\Contracts\Addons\Marketplace\MarketplaceOrderAdapterContract;
use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Models\BusinessIdentity;
use App\Models\Company;
use App\Models\MembershipPlan;
use App\Models\ModuleSetting;
use App\Models\User;
use App\Services\Addons\Marketplace\DummyMarketplaceAdapter;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\FeatureResolver;
use App\Services\Preset\EloquentPresetSource;
use Database\Seeders\BusinessPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class MarketplaceSyncAddonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
        $this->app->scoped(CompanyContext::class, EloquentCompanyContext::class);
        $this->app->scoped(CompanySettingsStore::class, EloquentCompanySettingsStore::class);
        $this->app->bind(PresetSource::class, EloquentPresetSource::class);
        $this->app->bind(MarketplaceOrderAdapterContract::class, DummyMarketplaceAdapter::class);

        $this->artisan('db:seed', ['--class' => BusinessPresetSeeder::class]);
    }

    public function test_marketplace_sync_capability_can_be_enabled_and_syncs_order(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $identity = BusinessIdentity::factory()->create(['company_id' => $company->id]);

        $plan = MembershipPlan::factory()->create(['features' => ['addon.marketplace_sync']]);
        $company->memberships()->create([
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        ModuleSetting::create([
            'company_id' => $company->id,
            'module_name' => 'features',
            'settings_json' => ['addon.marketplace_sync' => true],
        ]);

        app(CompanyContext::class)->setCurrent((string) $company->id);
        $resolver = app(FeatureResolver::class);
        $this->assertTrue($resolver->enabled('addon.marketplace_sync'));

        $adapter = app(MarketplaceOrderAdapterContract::class);

        $request = Request::create('/webhook', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'external_id' => 'EXT-999',
            'total' => 50000,
        ]));

        $order = $adapter->handleWebhook($request, (string) $company->id, (string) $identity->id);

        $this->assertNotNull($order);
        $this->assertEquals('EXT-999', $order->external_ref);
        $this->assertEquals(50000, $order->grand_total);
        $this->assertEquals($company->id, $order->company_id);
    }

    public function test_same_external_id_creates_separate_orders_per_company(): void
    {
        $ownerA = User::factory()->create();
        $companyA = Company::factory()->create(['owner_user_id' => $ownerA->id]);
        $identityA = BusinessIdentity::factory()->create(['company_id' => $companyA->id]);

        $ownerB = User::factory()->create();
        $companyB = Company::factory()->create(['owner_user_id' => $ownerB->id]);
        $identityB = BusinessIdentity::factory()->create(['company_id' => $companyB->id]);

        $adapter = app(MarketplaceOrderAdapterContract::class);

        $makeRequest = fn (): Request => Request::create('/webhook', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'external_id' => 'EXT-SHARED',
            'total' => 10000,
        ]));

        $orderA = $adapter->handleWebhook($makeRequest(), (string) $companyA->id, (string) $identityA->id);
        $orderB = $adapter->handleWebhook($makeRequest(), (string) $companyB->id, (string) $identityB->id);

        $this->assertNotEquals($orderA->id, $orderB->id);
        $this->assertEquals($companyA->id, $orderA->company_id);
        $this->assertEquals($companyB->id, $orderB->company_id);
    }
}
