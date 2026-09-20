<?php

namespace Tests\Feature;

use App\Livewire\Onboarding;
use App\Models\BusinessIdentity;
use App\Models\BusinessPreset;
use App\Models\Company;
use App\Models\ModuleSetting;
use App\Models\User;
use App\Providers\DataSourceServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use Throwable;

/**
 * Onboarding end-to-end pada driver `eloquent` (D-42): company baru harus
 * langsung punya baris `business_identities` + konteks aktif
 * `users.current_company_id`, tanpa bergantung pada file JSON per-slug.
 */
class OnboardingEloquentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['datasource.driver' => 'eloquent']);
        (new DataSourceServiceProvider($this->app))->register();

        Storage::fake('company-json');

        BusinessPreset::create([
            'key' => 'zz_onboarding_eloquent',
            'name' => 'Preset Onboarding Eloquent',
            'tier' => 'A',
            'definition' => [
                'key' => 'zz_onboarding_eloquent',
                'name' => 'Preset Onboarding Eloquent',
                'tier' => 'A',
                'capabilities' => ['contacts' => true],
                'terminology' => [],
                'workflows' => [],
                'dashboard' => ['industry_zone' => []],
                'menus' => ['order' => []],
            ],
        ]);
    }

    public function test_submit_creates_default_business_identity_activates_context_and_dashboard_renders(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Onboarding::class)
            ->set('name', 'Usaha Eloquent Baru')
            ->set('preset', 'zz_onboarding_eloquent')
            ->set('acceptPrivacyPolicy', true)
            ->call('submit')
            ->assertRedirect(route('app.dashboard'));

        $company = Company::query()->where('slug', 'usaha-eloquent-baru')->first();
        $this->assertNotNull($company);
        $this->assertSame($user->id, $company->owner_user_id);
        $this->assertSame('zz_onboarding_eloquent', $company->business_preset);

        $identity = BusinessIdentity::query()->where('company_id', $company->id)->first();
        $this->assertNotNull($identity);
        $this->assertSame('Usaha Eloquent Baru', $identity->legal_name);
        // D-44: default aman - tidak pernah taxable diam-diam.
        $this->assertSame('non_taxable', $identity->tax_mode);
        $this->assertTrue($identity->price_includes_tax);
        $this->assertTrue($identity->is_default);

        // D-41: konteks aktif adalah kolom users.current_company_id.
        $this->assertSame($company->id, $user->fresh()->current_company_id);

        // Driver eloquent: identitas usaha tidak lagi ditulis sebagai file JSON.
        Storage::disk('company-json')->assertMissing('json/usaha-eloquent-baru/business_identity.json');

        // Dashboard company baru merender 200, bukan error identitas.
        $this->get(route('app.dashboard'))->assertOk();

        // Sidebar modul sesuai preset (U-08/D-12): modul `contacts` aktif
        // dan menampilkan menunya; modul di luar preset ditolak (zero-bloat).
        $this->get('/app/contacts')->assertOk()->assertSee('Daftar Kontak');
        $this->get('/app/pos')->assertForbidden();
        $this->get('/app/accounting')->assertForbidden();
    }

    public function test_submit_without_privacy_consent_writes_nothing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Onboarding::class)
            ->set('name', 'Usaha Tanpa Persetujuan')
            ->set('preset', 'zz_onboarding_eloquent')
            ->set('acceptPrivacyPolicy', false)
            ->call('submit')
            ->assertSee('Anda wajib menyetujui kebijakan privasi terlebih dahulu.');

        $this->assertDatabaseMissing('companies', ['slug' => 'usaha-tanpa-persetujuan']);
        $this->assertSame(0, BusinessIdentity::count());
        $this->assertSame(0, ModuleSetting::count());
        $this->assertEmpty(Storage::disk('company-json')->allDirectories('json'));
    }

    public function test_submit_with_empty_name_fails_clean_without_writing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Onboarding::class)
            ->set('name', '   ')
            ->set('preset', 'zz_onboarding_eloquent')
            ->set('acceptPrivacyPolicy', true)
            ->call('submit')
            ->assertSee('Nama usaha wajib diisi');

        $this->assertSame(0, Company::count());
        $this->assertSame(0, BusinessIdentity::count());
        $this->assertSame(0, ModuleSetting::count());
    }

    public function test_submit_with_invalid_preset_fails_clean_without_writing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Onboarding::class)
            ->set('name', 'Usaha Preset Jahat')
            ->set('preset', 'preset-hantu')
            ->set('acceptPrivacyPolicy', true)
            ->call('submit')
            ->assertSee('Preset bisnis tidak valid');

        $this->assertSame(0, Company::count());
        $this->assertSame(0, BusinessIdentity::count());
        $this->assertSame(0, ModuleSetting::count());
    }

    public function test_submit_rolls_back_company_identity_and_settings_atomically(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Kegagalan tulis `module_settings` harus membatalkan seluruh
        // transaksi: tidak boleh ada company setengah jadi (fail-closed).
        DB::statement("CREATE TRIGGER reject_module_settings BEFORE INSERT ON module_settings BEGIN SELECT RAISE(FAIL, 'forced settings failure'); END");

        try {
            Livewire::test(Onboarding::class)
                ->set('name', 'Usaha Setengah Jadi')
                ->set('preset', 'zz_onboarding_eloquent')
                ->set('acceptPrivacyPolicy', true)
                ->call('submit');

            $this->fail('Kegagalan settings harus menggagalkan onboarding Eloquent.');
        } catch (Throwable) {
            $this->assertSame(0, Company::count());
            $this->assertSame(0, BusinessIdentity::count());
            $this->assertSame(0, ModuleSetting::count());
            $this->assertNull($user->fresh()->current_company_id);
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS reject_module_settings');
        }
    }

    public function test_consecutive_onboarding_by_same_owner_creates_two_usable_companies_with_unique_slugs(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Onboarding::class)
            ->set('name', 'Cabang Pertama')
            ->set('preset', 'zz_onboarding_eloquent')
            ->set('acceptPrivacyPolicy', true)
            ->call('submit')
            ->assertRedirect(route('app.dashboard'));

        Livewire::test(Onboarding::class)
            ->set('name', 'Cabang Pertama')
            ->set('preset', 'zz_onboarding_eloquent')
            ->set('acceptPrivacyPolicy', true)
            ->call('submit')
            ->assertRedirect(route('app.dashboard'));

        $this->assertSame(2, Company::query()->where('owner_user_id', $user->id)->count());
        $this->assertSame(
            ['cabang-pertama', 'cabang-pertama-2'],
            Company::query()->where('owner_user_id', $user->id)->orderBy('id')->pluck('slug')->all(),
        );

        // Masing-masing company langsung usable: identitas + settings
        // utuh, dan dashboard merender 200 untuk konteks aktif (company kedua).
        foreach (Company::query()->where('owner_user_id', $user->id)->get() as $company) {
            $this->assertSame(1, BusinessIdentity::query()->where('company_id', $company->id)->count());
            $this->assertSame(1, ModuleSetting::query()->where('company_id', $company->id)->where('module_name', 'features')->count());
        }

        $this->assertSame(
            'cabang-pertama-2',
            Company::query()->findOrFail($user->fresh()->current_company_id)->slug,
        );
        $this->get(route('app.dashboard'))->assertOk();
    }
}
