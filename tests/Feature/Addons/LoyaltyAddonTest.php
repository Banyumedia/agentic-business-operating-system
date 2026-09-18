<?php

namespace Tests\Feature\Addons;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Models\Company;
use App\Models\Contact;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyRule;
use App\Models\MembershipPlan;
use App\Models\ModuleSetting;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use App\Services\Addons\Loyalty\LoyaltyPointsCalculator;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\FeatureResolver;
use App\Services\Preset\EloquentPresetSource;
use Database\Seeders\BusinessPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoyaltyAddonTest extends TestCase
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

    private function makeCompanyWithLoyaltyAddon(bool $enabled = true): Company
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $plan = MembershipPlan::factory()->create(['features' => ['addon.loyalty']]);
        $company->memberships()->create([
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        if ($enabled) {
            ModuleSetting::create([
                'company_id' => $company->id,
                'module_name' => 'features',
                'settings_json' => ['addon.loyalty' => true],
            ]);
        }

        return $company;
    }

    public function test_loyalty_capability_is_off_by_default_and_no_points_are_awarded(): void
    {
        $company = $this->makeCompanyWithLoyaltyAddon(enabled: false);
        app(CompanyContext::class)->setCurrent((string) $company->id);
        $this->assertFalse(app(FeatureResolver::class)->enabled('addon.loyalty'));

        $contact = Contact::factory()->create(['company_id' => $company->id]);
        $order = Order::factory()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'grand_total' => 100000,
        ]);

        // Tidak ada rule aktif → tidak ada poin, meski transaksi terjadi.
        $point = app(LoyaltyPointsCalculator::class)->awardForOrder($order);

        $this->assertNull($point);
        $this->assertSame(0, LoyaltyPoint::query()->where('company_id', $company->id)->count());
    }

    public function test_nominal_mode_ratio_is_read_from_company_data_not_hardcoded(): void
    {
        $companyA = $this->makeCompanyWithLoyaltyAddon();
        $companyB = $this->makeCompanyWithLoyaltyAddon();

        LoyaltyRule::factory()->create(['company_id' => $companyA->id, 'nominal_per_point' => 10000]);
        LoyaltyRule::factory()->create(['company_id' => $companyB->id, 'nominal_per_point' => 20000]);

        $contactA = Contact::factory()->create(['company_id' => $companyA->id]);
        $contactB = Contact::factory()->create(['company_id' => $companyB->id]);

        $orderA = Order::factory()->create(['company_id' => $companyA->id, 'contact_id' => $contactA->id, 'grand_total' => 100000]);
        $orderB = Order::factory()->create(['company_id' => $companyB->id, 'contact_id' => $contactB->id, 'grand_total' => 100000]);

        $calculator = app(LoyaltyPointsCalculator::class);

        $pointA = $calculator->awardForOrder($orderA);
        $pointB = $calculator->awardForOrder($orderB);

        // Rasio berbeda per company harus menghasilkan poin berbeda dari transaksi identik.
        $this->assertSame(10, $pointA->points);
        $this->assertSame(5, $pointB->points);
    }

    public function test_per_item_mode_uses_company_configured_mapping(): void
    {
        $company = $this->makeCompanyWithLoyaltyAddon();
        $contact = Contact::factory()->create(['company_id' => $company->id]);

        $order = Order::factory()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'grand_total' => 50000,
        ]);
        $line = OrderLine::create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'item_id' => 42,
            'description' => 'Item A',
            'qty' => 3,
            'unit_price' => 1000,
            'line_total' => 3000,
        ]);
        $order->setRelation('lines', collect([$line]));

        LoyaltyRule::factory()->create([
            'company_id' => $company->id,
            'nominal_per_point' => null,
            'item_point_rates' => [(string) $line->item_id => 5],
        ]);

        $point = app(LoyaltyPointsCalculator::class)->awardForOrder($order);

        $this->assertSame(15, $point->points); // 5 poin x 3 qty
        $this->assertSame('earned_item', $point->source_type);
    }

    public function test_both_modes_accumulate_points_from_both_sources(): void
    {
        $company = $this->makeCompanyWithLoyaltyAddon();
        $contact = Contact::factory()->create(['company_id' => $company->id]);

        $order = Order::factory()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'grand_total' => 100000,
        ]);
        $line = OrderLine::create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'item_id' => 7,
            'description' => 'Item B',
            'qty' => 2,
            'unit_price' => 2000,
            'line_total' => 4000,
        ]);
        $order->setRelation('lines', collect([$line]));

        LoyaltyRule::factory()->create([
            'company_id' => $company->id,
            'nominal_per_point' => 10000, // 10 poin dari nominal
            'item_point_rates' => [(string) $line->item_id => 4], // 8 poin dari item
        ]);

        $point = app(LoyaltyPointsCalculator::class)->awardForOrder($order);

        $this->assertSame(18, $point->points);
        $this->assertSame('earned_both', $point->source_type);
    }

    public function test_expiry_is_optional_per_company(): void
    {
        Carbon::setTestNow('2026-01-01 00:00:00');

        $companyWithExpiry = $this->makeCompanyWithLoyaltyAddon();
        $companyWithoutExpiry = $this->makeCompanyWithLoyaltyAddon();

        LoyaltyRule::factory()->create([
            'company_id' => $companyWithExpiry->id,
            'nominal_per_point' => 10000,
            'expiry_months' => 6,
        ]);
        LoyaltyRule::factory()->create([
            'company_id' => $companyWithoutExpiry->id,
            'nominal_per_point' => 10000,
            'expiry_months' => null,
        ]);

        $contactA = Contact::factory()->create(['company_id' => $companyWithExpiry->id]);
        $contactB = Contact::factory()->create(['company_id' => $companyWithoutExpiry->id]);

        $orderA = Order::factory()->create(['company_id' => $companyWithExpiry->id, 'contact_id' => $contactA->id, 'grand_total' => 100000]);
        $orderB = Order::factory()->create(['company_id' => $companyWithoutExpiry->id, 'contact_id' => $contactB->id, 'grand_total' => 100000]);

        $calculator = app(LoyaltyPointsCalculator::class);
        $pointA = $calculator->awardForOrder($orderA);
        $pointB = $calculator->awardForOrder($orderB);

        $this->assertNotNull($pointA->expires_at);
        $this->assertNull($pointB->expires_at);

        // Lompat 7 bulan: poin company dengan expiry 6 bulan sudah lewat, yang tanpa expiry tetap dihitung.
        Carbon::setTestNow('2026-08-01 00:00:00');

        $this->assertSame(0, $calculator->activeBalance($companyWithExpiry->id, $contactA->id));
        $this->assertSame(10, $calculator->activeBalance($companyWithoutExpiry->id, $contactB->id));

        Carbon::setTestNow();
    }

    public function test_loyalty_rules_and_points_are_isolated_per_tenant(): void
    {
        $companyA = $this->makeCompanyWithLoyaltyAddon();
        $companyB = $this->makeCompanyWithLoyaltyAddon();

        LoyaltyRule::factory()->create(['company_id' => $companyA->id, 'nominal_per_point' => 1000]);
        LoyaltyRule::factory()->create(['company_id' => $companyB->id, 'nominal_per_point' => 5000]);

        $contactA = Contact::factory()->create(['company_id' => $companyA->id]);
        $contactB = Contact::factory()->create(['company_id' => $companyB->id]);

        LoyaltyPoint::factory()->create(['company_id' => $companyA->id, 'contact_id' => $contactA->id, 'points' => 10]);
        LoyaltyPoint::factory()->create(['company_id' => $companyB->id, 'contact_id' => $contactB->id, 'points' => 20]);

        $this->assertSame(1, LoyaltyRule::query()->where('company_id', $companyA->id)->count());
        $this->assertSame(10, LoyaltyPoint::query()->where('company_id', $companyA->id)->sum('points'));
        $this->assertSame(20, LoyaltyPoint::query()->where('company_id', $companyB->id)->sum('points'));

        // Saldo aktif company A tidak boleh ikut menjumlah poin company B.
        $calculator = app(LoyaltyPointsCalculator::class);
        $this->assertSame(10, $calculator->activeBalance($companyA->id, $contactA->id));
        $this->assertSame(0, $calculator->activeBalance($companyA->id, $contactB->id));
    }
}
