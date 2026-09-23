<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Livewire\Settings;
use App\Models\BusinessIdentity;
use App\Models\Company;
use App\Models\User;
use App\Services\BusinessIdentityStore;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Json\JsonCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TX-01 (D-74): kunci fiskal + benahi cacat tarif 0%.
 *
 * Data fiskal, jadi test negatif lebih dulu (HERMES). Cacat inti yang dijaga:
 * tenant `taxable` di jalur Eloquent dulu memungut 0% karena kolom `tax_rate`
 * berdefault `0.00`, sementara jalur JSON tanpa `tax_rate` jatuh ke 11% —
 * dua sumber data menjawab pajak berbeda untuk konfigurasi yang sama.
 */
class BusinessIdentityFiscalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Store memaku company aktif dari CompanyContext; untuk jalur Eloquent
        // (company berupa ID numerik) context harus Eloquent, bukan JSON yang
        // membatasi ke allowlist demo.
        app()->scoped(CompanyContext::class, EloquentCompanyContext::class);
    }

    private function makeCompany(): Company
    {
        $user = User::factory()->create();
        $company = Company::create([
            'name' => 'Usaha Uji',
            'slug' => 'usaha-uji',
            'owner_user_id' => $user->id,
            'business_preset' => 'custom',
        ]);

        $user->forceFill(['current_company_id' => $company->id])->save();
        $this->actingAs($user);
        session(['active_company' => $company->id]);

        return $company;
    }

    private function identity(Company $company, array $overrides): BusinessIdentity
    {
        return BusinessIdentity::create(array_merge([
            'company_id' => $company->id,
            'legal_name' => 'Usaha Uji',
            'is_default' => true,
        ], $overrides));
    }

    public function test_taxable_identity_with_null_rate_is_rejected(): void
    {
        $company = $this->makeCompany();
        $this->identity($company, ['tax_mode' => 'taxable', 'tax_rate' => null, 'price_includes_tax' => false]);

        $this->expectException(InvalidArgumentException::class);
        app(BusinessIdentityStore::class)->taxProfile((string) $company->id);
    }

    public function test_taxable_identity_with_zero_rate_is_rejected(): void
    {
        $company = $this->makeCompany();
        $this->identity($company, ['tax_mode' => 'taxable', 'tax_rate' => 0, 'price_includes_tax' => false]);

        // PKP yang memungut 0% adalah konfigurasi mustahil, bukan pilihan sah.
        $this->expectException(InvalidArgumentException::class);
        app(BusinessIdentityStore::class)->taxProfile((string) $company->id);
    }

    public function test_taxable_identity_with_explicit_rate_yields_that_rate(): void
    {
        $company = $this->makeCompany();
        $this->identity($company, ['tax_mode' => 'taxable', 'tax_rate' => 11, 'price_includes_tax' => true]);

        $profile = app(BusinessIdentityStore::class)->taxProfile((string) $company->id);

        $this->assertTrue($profile->taxable);
        $this->assertTrue($profile->priceIncludesTax);
        $this->assertSame(11.0, $profile->rate);
    }

    public function test_non_taxable_identity_needs_no_rate(): void
    {
        $company = $this->makeCompany();
        $this->identity($company, ['tax_mode' => 'non_taxable', 'tax_rate' => null]);

        $profile = app(BusinessIdentityStore::class)->taxProfile((string) $company->id);

        $this->assertFalse($profile->taxable);
        $this->assertSame(0.0, $profile->rate);
    }

    public function test_tax_rate_column_has_no_silent_zero_default(): void
    {
        $company = $this->makeCompany();

        // Membuat identitas tanpa menyebut tax_rate tidak boleh diam-diam
        // menghasilkan 0.00 yang menyamar sebagai tarif sah. Kolom kini nullable
        // tanpa default: ketiadaan tarif terbaca sebagai "belum dikonfigurasi".
        $identity = $this->identity($company, ['tax_mode' => 'non_taxable']);

        $this->assertNull($identity->fresh()->tax_rate);
    }

    public function test_fiscal_lock_marker_defaults_to_unlocked_and_can_be_stamped(): void
    {
        $company = $this->makeCompany();
        $identity = $this->identity($company, ['tax_mode' => 'non_taxable']);

        // Kunci punya sumber kebenaran eksplisit, bukan disimpulkan dari
        // ada-tidaknya data lain (TX-01 acceptance).
        $this->assertNull($identity->fresh()->fiscal_locked_at);

        $identity->update(['fiscal_locked_at' => now()]);
        $this->assertNotNull($identity->fresh()->fiscal_locked_at);
    }

    public function test_existing_zero_rate_rows_are_left_untouched_by_migration(): void
    {
        // TX-01: "identitas lama yang sudah ada tidak berubah arti setelah
        // migration". Baris lama bertarif 0.00 tetap tersimpan apa adanya;
        // yang berubah hanya bahwa store kini MENOLAK-nya untuk taxable
        // alih-alih diam-diam memungut 0%.
        $company = $this->makeCompany();
        $identity = $this->identity($company, ['tax_mode' => 'taxable', 'tax_rate' => 0.00]);

        $this->assertSame('0.00', $identity->fresh()->tax_rate);
    }

    public function test_locked_identity_rejects_changing_tax_mode(): void
    {
        $company = $this->makeCompany();
        $identity = $this->identity($company, [
            'tax_mode' => 'non_taxable',
            'fiscal_locked_at' => now(),
        ]);

        // Kunci menolak di lapisan model, bukan sekadar UI tanpa tombol —
        // sehingga permintaan Livewire rakitan tangan, TenantBot, maupun command
        // tidak punya jalan pintas.
        $this->expectException(\LogicException::class);
        $identity->update(['tax_mode' => 'taxable', 'tax_rate' => 11]);
    }

    public function test_locked_identity_rejects_changing_price_inclusive(): void
    {
        $company = $this->makeCompany();
        $identity = $this->identity($company, [
            'tax_mode' => 'taxable',
            'tax_rate' => 11,
            'price_includes_tax' => false,
            'fiscal_locked_at' => now(),
        ]);

        $this->expectException(\LogicException::class);
        $identity->update(['price_includes_tax' => true]);
    }

    public function test_locked_identity_rejects_changing_tax_rate(): void
    {
        $company = $this->makeCompany();
        $identity = $this->identity($company, [
            'tax_mode' => 'taxable',
            'tax_rate' => 11,
            'fiscal_locked_at' => now(),
        ]);

        $this->expectException(\LogicException::class);
        $identity->update(['tax_rate' => 5]);
    }

    public function test_unlocked_identity_may_still_change_fiscal_fields(): void
    {
        // Identitas lama (sebelum TX-02) belum punya fiscal_locked_at; ia TIDAK
        // ikut terkunci diam-diam. Perubahan tetap boleh sampai dikunci.
        $company = $this->makeCompany();
        $identity = $this->identity($company, [
            'tax_mode' => 'non_taxable',
            'fiscal_locked_at' => null,
        ]);

        $identity->update(['tax_mode' => 'taxable', 'tax_rate' => 11]);

        $this->assertSame('taxable', $identity->fresh()->tax_mode);
    }

    public function test_locking_an_unlocked_identity_is_allowed(): void
    {
        // Mencap kunci itu sendiri harus boleh (itulah cara mengunci).
        $company = $this->makeCompany();
        $identity = $this->identity($company, [
            'tax_mode' => 'taxable',
            'tax_rate' => 11,
            'fiscal_locked_at' => null,
        ]);

        $identity->update(['fiscal_locked_at' => now()]);

        $this->assertNotNull($identity->fresh()->fiscal_locked_at);
    }

    public function test_locked_identity_allows_changing_non_fiscal_fields(): void
    {
        // Kunci hanya untuk field fiskal; nama/alamat tetap bisa diperbaiki.
        $company = $this->makeCompany();
        $identity = $this->identity($company, [
            'tax_mode' => 'non_taxable',
            'fiscal_locked_at' => now(),
        ]);

        $identity->update(['legal_name' => 'Nama Baru PT']);

        $this->assertSame('Nama Baru PT', $identity->fresh()->legal_name);
    }

    public function test_profile_tab_states_the_lock_reason_when_fiscal_is_locked(): void
    {
        // Tab Profil harus menyatakan penguncian eksplisit (D-74): keputusan
        // yang disengaja, bukan fitur yang lupa dibuat. Jalur JSON demo.
        app()->forgetInstance(CompanyContext::class);
        app()->scoped(CompanyContext::class, JsonCompanyContext::class);
        Storage::fake('company-json');
        Storage::disk('company-json')->put(
            'json/salon-ayu/business_identity.json',
            json_encode([
                'id' => 1,
                'name' => 'Salon Ayu',
                'preset' => 'salon',
                'tax_mode' => 'taxable',
                'tax_rate' => 11,
                'price_includes_tax' => true,
                'fiscal_locked_at' => now()->toIso8601String(),
            ], JSON_THROW_ON_ERROR),
        );

        $this->withSession(['active_company' => 'salon-ayu', 'company_role' => 'owner']);

        Livewire::test(Settings::class, ['tab' => 'profile'])
            ->assertOk()
            ->assertSee('Terkunci sejak pendaftaran')
            ->assertSee('sudah termasuk PPN');
    }

    public function test_profile_tab_omits_lock_reason_for_unlocked_identity(): void
    {
        app()->forgetInstance(CompanyContext::class);
        app()->scoped(CompanyContext::class, JsonCompanyContext::class);
        Storage::fake('company-json');
        Storage::disk('company-json')->put(
            'json/salon-ayu/business_identity.json',
            json_encode([
                'id' => 1,
                'name' => 'Salon Ayu',
                'preset' => 'salon',
                'tax_mode' => 'non_taxable',
            ], JSON_THROW_ON_ERROR),
        );

        $this->withSession(['active_company' => 'salon-ayu', 'company_role' => 'owner']);

        Livewire::test(Settings::class, ['tab' => 'profile'])
            ->assertOk()
            ->assertDontSee('Terkunci sejak pendaftaran');
    }

    public function test_json_and_eloquent_paths_yield_identical_tax_profile(): void
    {
        // Cacat nomor 3 dari D-74: paritas dua sumber data untuk konfigurasi
        // yang sama. Eloquent taxable rate 11 == JSON taxable rate 11.
        $company = $this->makeCompany();
        $this->identity($company, ['tax_mode' => 'taxable', 'tax_rate' => 11, 'price_includes_tax' => true]);
        $eloquent = app(BusinessIdentityStore::class)->taxProfile((string) $company->id);

        // Jalur JSON pada slug demo yang diizinkan.
        app()->forgetInstance(CompanyContext::class);
        app()->scoped(CompanyContext::class, JsonCompanyContext::class);
        Storage::fake('company-json');
        Storage::disk('company-json')->put(
            'json/salon-ayu/business_identity.json',
            json_encode([
                'id' => 1,
                'name' => 'Salon Ayu',
                'preset' => 'salon',
                'tax_mode' => 'taxable',
                'tax_rate' => 11,
                'price_includes_tax' => true,
            ], JSON_THROW_ON_ERROR),
        );
        app(CompanyContext::class)->setCurrent('salon-ayu');
        $json = app(BusinessIdentityStore::class)->taxProfile('salon-ayu');

        $this->assertSame($eloquent->taxable, $json->taxable);
        $this->assertSame($eloquent->priceIncludesTax, $json->priceIncludesTax);
        $this->assertSame($eloquent->rate, $json->rate);
    }
}
