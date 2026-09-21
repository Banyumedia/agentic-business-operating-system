<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Models\Company;
use App\Models\User;
use App\Providers\DataSourceServiceProvider;
use App\Services\Dashboard\DashboardComposer;
use Database\Seeders\BusinessPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MQ-01C4: parity identitas - dashboard Eloquent harus menampilkan nama
 * company (kolom name), bukan ID numerik; kontrak tampilan setara dengan
 * datasource JSON (nama ter-cased, bukan identifier mentah).
 */
class CompanyDisplayNameParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
        (new DataSourceServiceProvider($this->app))->register();
        $this->seed(BusinessPresetSeeder::class);
    }

    public function test_eloquent_dashboard_shows_company_name_not_numeric_id(): void
    {
        $user = User::factory()->create();
        $company = Company::create([
            'name' => 'Bengkel Arka Jaya',
            'slug' => 'bengkel-arka',
            'owner_user_id' => $user->id,
            'business_preset' => 'bengkel',
            'module_settings' => [],
        ]);
        $user->update(['current_company_id' => $company->id]);

        $response = $this->actingAs($user)
            ->withSession(['active_company' => $company->id])
            ->get('/app/dashboard');

        $response->assertOk();
        $response->assertSee('Bengkel Arka Jaya');
        // ID numerik tidak boleh muncul sebagai label identitas usaha.
        $this->assertStringNotContainsString(
            '>1<',
            $response->getContent(),
            'Label identitas usaha tidak boleh menampilkan ID numerik.',
        );
    }

    public function test_eloquent_composer_exposes_company_display_name(): void
    {
        $user = User::factory()->create();
        $company = Company::create([
            'name' => 'Klinik Sehat Sentosa',
            'slug' => 'klinik-sehat',
            'owner_user_id' => $user->id,
            'business_preset' => 'klinik',
            'module_settings' => [],
        ]);
        $user->update(['current_company_id' => $company->id]);

        $this->actingAs($user)->withSession(['active_company' => $company->id]);
        $dashboard = app(DashboardComposer::class)->compose();

        $this->assertSame('Klinik Sehat Sentosa', $dashboard['company']);
    }

    /**
     * Parity JSON: displayName membaca business_identity.json, bukan
     * men-title-case slug - nama usaha apa adanya.
     */
    public function test_eloquent_display_name_falls_back_to_slug_when_name_empty(): void
    {
        $user = User::factory()->create();
        $company = Company::create([
            'name' => '',
            'slug' => 'klinik-sehat-utama',
            'business_preset' => 'rental',
            'owner_user_id' => $user->id,
        ]);

        $this->actingAs($user);
        session(['active_company' => (string) $company->id]);

        $context = app(CompanyContext::class);

        // F3 (QA MQ-01): nama kosong tidak boleh menghasilkan banner kosong.
        $this->assertSame('Klinik Sehat Utama', $context->displayName());
    }
}
