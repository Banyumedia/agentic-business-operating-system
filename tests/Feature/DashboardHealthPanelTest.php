<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\AssistantReport;
use App\Models\CashEntry;
use App\Models\Company;
use App\Models\User;
use App\Providers\DataSourceServiceProvider;
use App\Services\Analytics\BusinessHealthAnalyzer;
use Database\Seeders\BusinessPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Panel "Kesehatan Usaha" (BI-B): tampilan hasil BusinessHealthAnalyzer.
 *
 * D-50: analisis finansial usaha hanya untuk owner company aktif - non-owner
 * tidak boleh melihat panel DAN tidak boleh menerima angkanya lewat snapshot
 * Livewire. Gagal analyzer harus fail-closed, bukan menjatuhkan dashboard.
 */
class DashboardHealthPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
        (new DataSourceServiceProvider($this->app))->register();

        $this->seed(BusinessPresetSeeder::class);
    }

    public function test_owner_with_enough_data_sees_health_panel_with_real_numbers(): void
    {
        $owner = User::factory()->create();
        $company = Company::create([
            'name' => 'Usaha Uji',
            'slug' => 'usaha-uji',
            'owner_user_id' => $owner->id,
            'business_preset' => 'bengkel',
            'module_settings' => [],
        ]);
        AssistantReport::create([
            'company_id' => $company->id,
            'generated_at' => now(),
            'period' => 'Hari Ini',
            'summary' => 'Laporan AI untuk uji panel kesehatan.',
            'highlights' => [],
            'recommended_actions' => [],
        ]);
        $owner->update(['current_company_id' => $company->id]);

        CashEntry::create([
            'company_id' => $company->id,
            'entry_date' => now()->startOfMonth()->addDays(2),
            'direction' => 'in',
            'amount' => 1250000,
            'category' => 'penjualan',
            'description' => 'Omzet penjualan',
            'created_by_user_id' => $owner->id,
        ]);
        CashEntry::create([
            'company_id' => $company->id,
            'entry_date' => now()->startOfMonth()->addDays(3),
            'direction' => 'out',
            'amount' => 400000,
            'category' => 'operasional',
            'description' => 'Belanja bahan',
            'created_by_user_id' => $owner->id,
        ]);
        CashEntry::create([
            'company_id' => $company->id,
            'entry_date' => now()->startOfMonth()->subDay(),
            'direction' => 'in',
            'amount' => 1000000,
            'category' => 'penjualan',
            'description' => 'Omzet bulan lalu',
            'created_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner);
        $component = Livewire::test(Dashboard::class);
        $component
            ->assertSee('Kesehatan Usaha')
            ->assertSee('Bulan ini')
            ->assertSee('Rp 1.250.000')
            ->assertSee('Rp 400.000')
            ->assertSee('Rp 850.000')
            ->assertSee('Naik')
            ->assertSee('Sorotan periode ini');

        $health = $component->get('health');
        $this->assertFalse($health['insufficient_data']);
        $this->assertSame(1250000.0, $health['revenue']);
        $this->assertSame(400000.0, $health['expenses']);
        $this->assertSame(850000.0, $health['margin']);
        $this->assertSame('up', $health['trend']['direction']);
    }

    public function test_owner_with_insufficient_data_sees_polite_empty_state(): void
    {
        $owner = User::factory()->create();
        $company = Company::create([
            'name' => 'Usaha Baru',
            'slug' => 'usaha-baru',
            'owner_user_id' => $owner->id,
            'business_preset' => 'bengkel',
            'module_settings' => [],
        ]);
        AssistantReport::create([
            'company_id' => $company->id,
            'generated_at' => now(),
            'period' => 'Hari Ini',
            'summary' => 'Laporan AI untuk usaha baru.',
            'highlights' => [],
            'recommended_actions' => [],
        ]);
        $owner->update(['current_company_id' => $company->id]);

        $this->actingAs($owner);
        Livewire::test(Dashboard::class)
            ->assertSee('Kesehatan Usaha')
            ->assertSee('Belum cukup data untuk analisis')
            ->assertSee('mulai catat transaksi.')
            ->assertDontSee('Omzet dibanding bulan sebelumnya')
            ->assertDontSee('Sorotan periode ini');
    }

    public function test_non_owner_gets_no_panel_and_no_financial_data(): void
    {
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $company = Company::create([
            'name' => 'Usaha Milik Orang',
            'slug' => 'usaha-milik-orang',
            'owner_user_id' => $owner->id,
            'business_preset' => 'bengkel',
            'module_settings' => [],
        ]);
        AssistantReport::create([
            'company_id' => $company->id,
            'generated_at' => now(),
            'period' => 'Hari Ini',
            'summary' => 'Laporan AI milik owner.',
            'highlights' => [],
            'recommended_actions' => [],
        ]);

        CashEntry::create([
            'company_id' => $company->id,
            'entry_date' => now()->startOfMonth()->addDays(2),
            'direction' => 'in',
            'amount' => 7770000,
            'category' => 'penjualan',
            'description' => 'Omzet rahasia',
            'created_by_user_id' => $owner->id,
        ]);

        // Staf: company aktif bukan miliknya (mis. anggota tim). Livewire
        // mount dijalankan langsung karena HTTP sudah ditolak middleware.
        $this->actingAs($staff);
        session(['active_company' => (string) $company->id]);
        $component = Livewire::test(Dashboard::class);

        // KPI/widget lama memang menampilkan total kas company ke anggota
        // (perilaku pra-BI-B, D-26); yang dicegah D-50 hanya analisis finansial.
        $component
            ->assertDontSee('Kesehatan Usaha')
            ->assertDontSee('Omzet')
            ->assertDontSee('Margin')
            ->assertDontSee('Omzet dibanding bulan sebelumnya')
            ->assertDontSee('Sorotan periode ini')
            ->assertDontSee('Omzet rahasia');

        // D-50: data finansial tidak boleh terkirim ke klien lewat snapshot.
        $this->assertNull($component->get('health'));
    }

    public function test_analyzer_failure_is_fail_closed_and_dashboard_stays_alive(): void
    {
        $owner = User::factory()->create();
        $company = Company::create([
            'name' => 'Usaha Tahan Banting',
            'slug' => 'usaha-tahan-banting',
            'owner_user_id' => $owner->id,
            'business_preset' => 'bengkel',
            'module_settings' => [],
        ]);
        AssistantReport::create([
            'company_id' => $company->id,
            'generated_at' => now(),
            'period' => 'Hari Ini',
            'summary' => 'Laporan AI tetap tampil.',
            'highlights' => [],
            'recommended_actions' => [],
        ]);
        $owner->update(['current_company_id' => $company->id]);

        $this->mock(BusinessHealthAnalyzer::class)
            ->shouldReceive('analyze')
            ->once()
            ->andThrow(new \RuntimeException('analyzer broken'));

        $this->actingAs($owner);
        $component = Livewire::test(Dashboard::class);
        $component
            ->assertDontSee('Kesehatan Usaha')
            ->assertSee('Ringkasan hari ini')
            ->assertSee('Laporan AI tetap tampil.')
            ->assertHasNoErrors();

        $this->assertNull($component->get('health'));
        $this->assertNull($component->get('loadError'));
    }

    public function test_reload_refreshes_health_data(): void
    {
        $owner = User::factory()->create();
        $company = Company::create([
            'name' => 'Usaha Refres',
            'slug' => 'usaha-refresh',
            'owner_user_id' => $owner->id,
            'business_preset' => 'bengkel',
            'module_settings' => [],
        ]);
        AssistantReport::create([
            'company_id' => $company->id,
            'generated_at' => now(),
            'period' => 'Hari Ini',
            'summary' => 'Laporan AI sebelum reload.',
            'highlights' => [],
            'recommended_actions' => [],
        ]);
        $owner->update(['current_company_id' => $company->id]);

        $this->actingAs($owner);
        $component = Livewire::test(Dashboard::class);
        $this->assertNotNull($component->get('health'));
        $this->assertTrue($component->get('health')['insufficient_data']);

        CashEntry::create([
            'company_id' => $company->id,
            'entry_date' => now()->startOfMonth()->addDays(1),
            'direction' => 'in',
            'amount' => 500000,
            'category' => 'penjualan',
            'description' => 'Omzet pertama',
            'created_by_user_id' => $owner->id,
        ]);
        CashEntry::create([
            'company_id' => $company->id,
            'entry_date' => now()->startOfMonth()->subDay(),
            'direction' => 'in',
            'amount' => 400000,
            'category' => 'penjualan',
            'description' => 'Omzet bulan lalu',
            'created_by_user_id' => $owner->id,
        ]);

        $component->call('reload');
        $this->assertFalse($component->get('health')['insufficient_data']);
        $this->assertSame(500000.0, $component->get('health')['revenue']);
        $component->assertSee('Rp 500.000');
    }
}
