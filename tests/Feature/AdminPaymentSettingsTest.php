<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\EntityRepository;
use App\Contracts\PresetSource;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Invoice;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\Eloquent\EloquentEntityRepository;
use App\Services\Platform\PlatformSettingStore;
use App\Services\Preset\EloquentPresetSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UR-04: panel Super Admin pengaturan pembayaran (rekening + QRIS) +
 * statistik komersial. Authorization fail-closed + DB override runtime.
 */
class AdminPaymentSettingsTest extends TestCase
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
        Storage::fake('public');
        config()->set('billing.manual_payment.bank.name', 'ENV Bank');
        config()->set('billing.manual_payment.bank.account_number', '000000');
    }

    private function superAdmin(): User
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-ps@t.test', 'password' => 'secret123']);
        $admin->forceFill(['is_platform_admin' => true])->save();

        return $admin;
    }

    public function test_non_admin_gets_403(): void
    {
        $owner = User::create(['name' => 'O', 'email' => 'owner-ps@t.test', 'password' => 'secret123']);

        $this->actingAs($owner)->get('/admin/payment-settings')->assertForbidden();
    }

    public function test_super_admin_can_render_page_with_stats(): void
    {
        $admin = $this->superAdmin();
        $owner = User::create(['name' => 'Ow', 'email' => 'ow-ps@t.test', 'password' => 'secret123']);
        $company = Company::create(['name' => 'C', 'slug' => 'c-ps', 'business_preset' => 'custom', 'owner_user_id' => $owner->id]);
        $plan = MembershipPlan::create(['name' => 'Starter', 'slug' => 'starter', 'monthly_price' => 750000, 'is_active' => true]);
        CompanyMembership::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'expires_at' => now()->addMonth()]);
        Invoice::create([
            'company_id' => $company->id, 'type' => 'subscription', 'payment_status' => 'paid',
            'amount' => 750000, 'paid_at' => now(), 'order_id' => 'test-1',
        ]);

        $response = $this->actingAs($admin)->get('/admin/payment-settings');

        $response->assertOk()
            ->assertSee('Membership Aktif')
            ->assertSee('750.000');
    }

    public function test_save_updates_bank_and_takes_effect_without_env_change(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test('admin.admin-payment-settings')
            ->set('bankName', 'Bank Baru')
            ->set('bankAccount', '999888777')
            ->set('bankHolder', 'Bos Baru')
            ->call('save')
            ->assertHasNoErrors();

        $store = app(PlatformSettingStore::class);
        $config = $store->paymentConfig();
        $this->assertSame('Bank Baru', $config['bank_name']);
        $this->assertSame('999888777', $config['bank_account']);

        // fallback env tetap benar saat DB override dihapus
        $store->put(['payment_bank_name' => null]);
        $this->assertSame('ENV Bank', app(PlatformSettingStore::class)->paymentConfig()['bank_name']);
    }

    public function test_upload_qris_stores_image_and_sets_path(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test('admin.admin-payment-settings')
            ->set('qrisUpload', UploadedFile::fake()->image('qris.png', 200, 200))
            ->set('bankName', 'B')
            ->set('bankAccount', '1')
            ->set('bankHolder', 'H')
            ->call('save')
            ->assertHasNoErrors();

        $path = app(PlatformSettingStore::class)->paymentConfig()['qris_path'];
        $this->assertStringStartsWith('storage/qris/', $path);
        Storage::disk('public')->assertExists(str_replace('storage/', '', $path));
    }

    public function test_remove_qris_clears_path(): void
    {
        $admin = $this->superAdmin();
        app(PlatformSettingStore::class)->put(['payment_qris_path' => 'storage/qris/x.png']);

        Livewire::actingAs($admin)
            ->test('admin.admin-payment-settings')
            ->call('removeQris');

        $this->assertSame('', app(PlatformSettingStore::class)->paymentConfig()['qris_path']);
    }
}
