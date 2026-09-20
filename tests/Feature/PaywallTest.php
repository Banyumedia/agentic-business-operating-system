<?php

namespace Tests\Feature;

use App\Exceptions\Billing\InsufficientTokenQuotaException;
use App\Exceptions\Billing\WaGroupQuotaExceededException;
use App\Livewire\Paywall;
use App\Models\Company;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Providers\DataSourceServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feature tests D-61: paywall saat kuota gratis habis.
 *
 * Test that:
 * 1. Halaman paywall render dengan kuota config (bukan hardcode).
 * 2. Daftar paket aktif tampil dengan harga + kuota; tombol pilih paket ada.
 * 3. Tanpa paket aktif → pesan hubungi admin (bukan error).
 * 4. Exception kuota di request web → redirect ke paywall (fail-closed: aksi tetap ditolak).
 * 5. Exception kuota di API/JSON → error 402 (bukan redirect).
 * 6. Guest yang kena exception → redirect ke login.
 */
class PaywallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
        (new DataSourceServiceProvider($this->app))->register();

        // Rute uji sementara: memprovokasi render() exception lewat HTTP
        // sungguhan tanpa menyentuh rute produksi.
        Route::get('/_test/token-quota-exceeded', fn (): never => throw new InsufficientTokenQuotaException(10, 5));
        Route::get('/_test/wa-group-quota-exceeded', fn (): never => throw new WaGroupQuotaExceededException(1));
    }

    private function ownerWithCompany(): array
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $user->current_company_id = $company->id;
        $user->save();
        session(['active_company' => (string) $company->id]);

        return [$user, $company];
    }

    public function test_paywall_shows_free_quota_from_config(): void
    {
        [$user] = $this->ownerWithCompany();
        config(['billing.free_tier.token_quota' => 777, 'billing.free_tier.max_wa_groups' => 1]);

        Livewire::actingAs($user)
            ->test(Paywall::class)
            ->assertOk()
            ->assertSee('777')
            ->assertSee((string) config('billing.free_tier.max_wa_groups'));
    }

    public function test_paywall_lists_active_plans_with_price_and_quota(): void
    {
        [$user] = $this->ownerWithCompany();

        $plan = MembershipPlan::factory()->create([
            'name' => 'Starter Plus',
            'monthly_price' => 150000,
            'annual_price' => 1500000,
            'max_wa_groups' => 5,
            'monthly_token_quota' => 50000,
            'is_active' => true,
        ]);
        MembershipPlan::factory()->create([
            'name' => 'Paket Nonaktif',
            'is_active' => false,
        ]);

        Livewire::actingAs($user)
            ->test(Paywall::class)
            ->assertOk()
            ->assertSee('Starter Plus')
            ->assertSee('Rp 150.000')
            ->assertSee('Rp 1.500.000')
            ->assertSee('50.000')
            ->assertSee('Pilih paket Starter Plus')
            ->assertDontSee('Paket Nonaktif');
    }

    public function test_paywall_without_active_plans_shows_contact_admin_message(): void
    {
        [$user] = $this->ownerWithCompany();
        MembershipPlan::factory()->create(['is_active' => false]);

        Livewire::actingAs($user)
            ->test(Paywall::class)
            ->assertOk()
            ->assertSee('Belum ada paket yang bisa dipilih')
            ->assertSee('hubungi admin');
    }

    public function test_token_quota_exception_redirects_web_user_to_paywall(): void
    {
        [$user] = $this->ownerWithCompany();

        $response = $this->actingAs($user)->get('/_test/token-quota-exceeded');

        // Fail-closed: bukan 200 sukses; pengunjung diarahkan ke paywall.
        $response->assertRedirect(route('app.paywall'));
        $this->assertSame('token_quota', session('paywall_reason'));
    }

    public function test_wa_group_quota_exception_redirects_web_user_to_paywall(): void
    {
        [$user] = $this->ownerWithCompany();

        $response = $this->actingAs($user)->get('/_test/wa-group-quota-exceeded');

        $response->assertRedirect(route('app.paywall'));
        $this->assertSame('wa_group_quota', session('paywall_reason'));
    }

    public function test_quota_exception_returns_402_json_for_api_requests(): void
    {
        [$user] = $this->ownerWithCompany();

        $response = $this->actingAs($user)
            ->getJson('/_test/token-quota-exceeded');

        $response->assertStatus(402)
            ->assertJsonPath('reason', 'insufficient_token_quota');
    }

    public function test_quota_exception_redirects_guest_to_login(): void
    {
        $response = $this->get('/_test/token-quota-exceeded');

        $response->assertRedirect(route('login'));
    }

    public function test_paywall_reason_flash_shows_targeted_message(): void
    {
        [$user] = $this->ownerWithCompany();
        session(['paywall_reason' => 'token_quota']);

        Livewire::actingAs($user)
            ->test(Paywall::class)
            ->assertOk()
            ->assertSee('Kuota token AI habis');
    }

    public function test_paywall_route_requires_authentication(): void
    {
        $response = $this->get('/app/paywall');

        $response->assertRedirect(route('login'));
    }
}
