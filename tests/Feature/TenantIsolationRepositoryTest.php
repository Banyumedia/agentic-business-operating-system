<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use App\Providers\DataSourceServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Bukti isolasi tenant (D-26): repository tidak boleh membocorkan data
 * antar-company, dan akses eksplisit ke company lain harus ditolak.
 *
 * Dijalankan dalam mode Eloquent (driver yang dipakai aplikasi saat ini).
 */
class TenantIsolationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
        (new DataSourceServiceProvider($this->app))->register();
    }

    private function makeCompany(string $name, string $slug): Company
    {
        $owner = User::factory()->create();

        return Company::create([
            'name' => $name,
            'slug' => $slug,
            'owner_user_id' => $owner->id,
            'business_preset' => 'bengkel',
            'module_settings' => [],
            'is_active' => true,
        ]);
    }

    public function test_repository_for_other_company_is_rejected(): void
    {
        $a = $this->makeCompany('Usaha A', 'usaha-a');
        $b = $this->makeCompany('Usaha B', 'usaha-b');

        app(CompanyContext::class)->setCurrent((string) $a->id);

        $this->expectException(LogicException::class);
        app(EntityRepository::class)->for((string) $b->id, 'contacts');
    }

    public function test_repository_never_returns_other_company_rows(): void
    {
        $a = $this->makeCompany('Usaha A', 'usaha-a');
        $b = $this->makeCompany('Usaha B', 'usaha-b');

        Contact::create(['company_id' => $a->id, 'name' => 'Milik A', 'wa_number' => '081']);
        Contact::create(['company_id' => $b->id, 'name' => 'Milik B', 'wa_number' => '082']);

        app(CompanyContext::class)->setCurrent((string) $a->id);
        $rows = app(EntityRepository::class)->for((string) $a->id, 'contacts')->all();

        $this->assertCount(1, $rows);
        $this->assertSame('Milik A', $rows[0]['name']);
        $this->assertSame($a->id, (int) $rows[0]['company_id']);

        // Total di tabel tetap 2 — tapi yang terbaca untuk company A hanya 1.
        $this->assertSame(2, Contact::count());
    }

    public function test_find_with_other_company_id_returns_null_not_leak(): void
    {
        $a = $this->makeCompany('Usaha A', 'usaha-a');
        $b = $this->makeCompany('Usaha B', 'usaha-b');

        $foreign = Contact::create(['company_id' => $b->id, 'name' => 'Milik B', 'wa_number' => '082']);

        app(CompanyContext::class)->setCurrent((string) $a->id);
        $found = app(EntityRepository::class)->for((string) $a->id, 'contacts')->find($foreign->id);

        // Mencari baris milik company lain dari konteks company A harus null,
        // bukan mengembalikan data company B.
        $this->assertNull($found);
    }

    public function test_save_never_writes_into_other_company_scope(): void
    {
        $a = $this->makeCompany('Usaha A', 'usaha-a');
        $b = $this->makeCompany('Usaha B', 'usaha-b');

        app(CompanyContext::class)->setCurrent((string) $a->id);
        $saved = app(EntityRepository::class)->for((string) $a->id, 'contacts')
            ->save(['name' => 'Baru A', 'wa_number' => '083']);

        // company_id yang tertulis harus company aktif, tidak bisa dibelokkan.
        $this->assertSame($a->id, (int) $saved['company_id']);
        $this->assertSame(0, Contact::where('company_id', $b->id)->count());
    }
}
