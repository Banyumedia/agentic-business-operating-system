<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Livewire\Dashboard;
use App\Models\Company;
use App\Models\User;
use App\Providers\DataSourceServiceProvider;
use App\Services\Dashboard\WidgetRegistry;
use Database\Seeders\BusinessPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/**
 * MQ-01C2: otorisasi tenant fail-closed, dibuktikan lewat request nyata
 * (GET route + request Livewire) dan pemanggilan service langsung.
 */
class LivewireTenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
        (new DataSourceServiceProvider($this->app))->register();
        $this->seed(BusinessPresetSeeder::class);
    }

    /**
     * Request nyata: user tanpa current_company_id + session active_company
     * dipalsukan menunjuk company milik orang lain. Route /app/* harus
     * fail-closed 403, bukan merender dashboard company orang lain.
     */
    public function test_route_rejects_forged_active_company_session(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $otherCompany = Company::factory()->create(['owner_user_id' => $otherOwner->id, 'business_preset' => 'bengkel']);

        $this->actingAs($owner) // current_company_id null
            ->withSession(['active_company' => $otherCompany->id])
            ->get('/app/dashboard')
            ->assertStatus(403);
    }

    /**
     * Session `active_company` adalah input tak tepercaya: context wajib
     * menolak keras session yang menunjuk company milik orang lain,
     * bukan mengembalikannya (fail-closed di lapisan service, bukan
     * hanya middleware HTTP).
     */
    public function test_context_rejects_forged_session_with_hard_exception(): void
    {
        $owner = User::factory()->create();
        $ownCompany = Company::factory()->create(['owner_user_id' => $owner->id, 'business_preset' => 'bengkel']);
        $otherOwner = User::factory()->create();
        $otherCompany = Company::factory()->create(['owner_user_id' => $otherOwner->id, 'business_preset' => 'bengkel']);
        $owner->update(['current_company_id' => $ownCompany->id]);
        $this->actingAs($owner);
        session(['active_company' => $otherCompany->id]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Akses lintas company ditolak.');

        app(CompanyContext::class)->current();
    }

    /**
     * Kepemilikan dicabut pasca-mount: user yang sudah membuka dashboard
     * tidak boleh lanjut memakai company itu. Request reload nyata via
     * Livewire harus gagal terkontrol, bukan sukses diam-diam.
     */
    public function test_livewire_reload_fails_closed_after_ownership_revoked(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id, 'business_preset' => 'bengkel']);
        $owner->update(['current_company_id' => $company->id]);
        session(['active_company' => $company->id]);

        // Mount sebagai owner sah.
        $component = Livewire::actingAs($owner)->test(Dashboard::class);
        $component->assertOk();

        // Kepemilikan dicabut pasca-mount.
        $company->update(['owner_user_id' => $newOwner->id]);
        session(['active_company' => $company->id]);
        $owner->update(['current_company_id' => null]);

        $component->call('reload');
        $this->assertNotNull(
            $component->get('loadError'),
            'Reload setelah kepemilikan dicabut harus gagal terkontrol, bukan sukses diam-diam.'
        );
    }

    /**
     * Capability tidak aktif: widget tidak tersedia dan compose() menolak
     * keras - deklarasi widget tanpa capability tidak pernah dirender.
     */
    public function test_widget_composition_fails_closed_when_company_has_no_capability(): void
    {
        $owner = User::factory()->create();
        // Preset bengkel tidak mengaktifkan capability deals.
        $company = Company::factory()->create(['owner_user_id' => $owner->id, 'business_preset' => 'bengkel']);
        $owner->update(['current_company_id' => $company->id]);
        $this->actingAs($owner);
        session(['active_company' => $company->id]);

        $registry = app(WidgetRegistry::class);

        $this->assertFalse($registry->available('deals_pipeline'));

        $this->expectException(InvalidArgumentException::class);
        $registry->compose('deals_pipeline');
    }
}
