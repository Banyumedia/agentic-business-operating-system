<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\EntityRepository;
use App\Contracts\PresetSource;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\Eloquent\EloquentEntityRepository;
use App\Services\Preset\EloquentPresetSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UR-04 defect: SubscribePage gagal resolve class Company (use import hilang
 * -> PHP mencari App\Livewire\Billing\Company -> 500 di produksi).
 * Test ini menjaga alur pilih paket via komponen Livewire penuh.
 */
class SubscribePagePlanSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            EntityRepository::class => EloquentEntityRepository::class,
            PresetSource::class => EloquentPresetSource::class,
            CompanyContext::class => EloquentCompanyContext::class,
            CompanySettingsStore::class => EloquentCompanySettingsStore::class,
        ] as $contract => $implementation) {
            $this->app->forgetInstance($contract);
            $this->app->scoped($contract, $implementation);
        }
    }

    public function test_owner_selects_plan_and_gets_pending_invoice(): void
    {
        $owner = User::create(['name' => 'Owner', 'email' => 'owner-sub@t.test', 'password' => 'secret123']);
        $company = Company::create([
            'name' => 'Usaha Sub', 'slug' => 'usaha-sub', 'business_preset' => 'custom', 'owner_user_id' => $owner->id,
        ]);
        $plan = MembershipPlan::create([
            'name' => 'Starter', 'slug' => 'starter', 'monthly_price' => 750000, 'annual_price' => 7500000,
            'is_active' => true,
        ]);

        $this->actingAs($owner);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        Livewire::test('billing.subscribe-page')
            ->call('selectPlan', $plan->id)
            ->assertRedirect(route('billing.payment-instruction', ['invoice' => Invoice::latest('id')->value('id')], absolute: false));

        $invoice = Invoice::latest('id')->first();
        $this->assertSame('subscription', $invoice->type);
        $this->assertSame('pending', $invoice->payment_status);
        $this->assertSame((float) $plan->monthly_price, (float) $invoice->amount);
    }
}
