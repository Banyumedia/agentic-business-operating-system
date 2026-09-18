<?php

namespace Tests\Feature;

use App\Contracts\PresetSource;
use App\Livewire\Onboarding;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\PlanCapabilityGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('company-json');

        $this->app->bind(PresetSource::class, fn () => new class implements PresetSource
        {
            public function all(): array
            {
                return [
                    [
                        'key' => 'bengkel',
                        'name' => 'Bengkel Mobil',
                        'tier' => 'A',
                        'capabilities' => ['contacts' => true],
                        'terminology' => [],
                        'workflows' => [],
                        'dashboard' => ['industry_zone' => []],
                        'menus' => ['order' => []],
                    ],
                    [
                        'key' => 'zz_fixture',
                        'name' => 'Preset Fixture Onboarding ZZ',
                        'tier' => 'A',
                        'capabilities' => ['contacts' => true],
                        'terminology' => [],
                        'workflows' => [],
                        'dashboard' => ['industry_zone' => []],
                        'menus' => ['order' => []],
                    ],
                    [
                        'key' => 'needs_enterprise',
                        'name' => 'Enterprise Preset',
                        'tier' => 'A',
                        'capabilities' => ['inventory.bom' => true, 'finance.accounting' => true],
                        'terminology' => [],
                        'workflows' => [],
                        'dashboard' => ['industry_zone' => []],
                        'menus' => ['order' => []],
                    ],
                ];
            }

            public function find(string $key): ?array
            {
                return collect($this->all())->firstWhere('key', $key);
            }
        });

        // Mock PlanCapabilityGate to bypass DB queries referencing unselected CompanyContext
        $gate = $this->mock(PlanCapabilityGate::class);
        $gate->shouldReceive('allowedCapabilities')->andReturn([]);
        $this->app->instance(PlanCapabilityGate::class, $gate);
    }

    public function test_preset_options_come_from_preset_source_not_hardcoded(): void
    {
        $this->get('/onboarding')
            ->assertOk()
            ->assertSee('Preset Fixture Onboarding ZZ');
    }

    public function test_submitting_empty_name_shows_failure_without_writing_a_folder(): void
    {
        Livewire::test(Onboarding::class)
            ->set('name', '   ')
            ->call('submit')
            ->assertSee('Nama usaha wajib diisi');
    }

    public function test_submitting_valid_form_creates_company_folder_with_safe_defaults(): void
    {
        Livewire::test(Onboarding::class)
            ->set('name', 'Toko Baru Sejahtera')
            ->set('preset', 'bengkel')
            ->set('acceptPrivacyPolicy', true)
            ->call('submit')
            ->assertSet('createdSlug', 'toko-baru-sejahtera');

        $slug = 'toko-baru-sejahtera';
        Storage::disk('company-json')->assertExists("json/{$slug}/business_identity.json");
        $identity = json_decode(
            Storage::disk('company-json')->get("json/{$slug}/business_identity.json"),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('Toko Baru Sejahtera', $identity['name']);
        $this->assertSame('bengkel', $identity['preset']);
        // D-44: default aman, jangan pernah taxable diam-diam.
        $this->assertSame('non_taxable', $identity['tax_mode']);

        Storage::disk('company-json')->assertExists("json/{$slug}/settings.json");
    }

    public function test_duplicate_business_name_gets_an_incrementing_unique_slug(): void
    {
        Livewire::test(Onboarding::class)
            ->set('name', 'Kedai Kopi')
            ->set('preset', 'bengkel')
            ->set('acceptPrivacyPolicy', true)
            ->call('submit')
            ->assertSet('createdSlug', 'kedai-kopi');

        Livewire::test(Onboarding::class)
            ->set('name', 'Kedai Kopi')
            ->set('preset', 'bengkel')
            ->set('acceptPrivacyPolicy', true)
            ->call('submit')
            ->assertSet('createdSlug', 'kedai-kopi-2');

        Storage::disk('company-json')->assertExists('json/kedai-kopi/business_identity.json');
        Storage::disk('company-json')->assertExists('json/kedai-kopi-2/business_identity.json');
    }

    public function test_unknown_preset_is_rejected_without_writing(): void
    {
        Livewire::test(Onboarding::class)
            ->set('name', 'Usaha Tanpa Preset')
            ->set('preset', 'preset-hantu')
            ->call('submit')
            ->assertSee('Preset bisnis tidak valid');

        $this->assertEmpty(Storage::disk('company-json')->allDirectories('json'));
    }

    public function test_new_company_is_not_reachable_via_query_switch_because_it_is_not_on_the_demo_allowlist(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Onboarding::class)
            ->set('name', 'Usaha Belum Terdaftar')
            ->set('preset', 'bengkel')
            ->set('acceptPrivacyPolicy', true)
            ->call('submit')
            ->assertSet('createdSlug', 'usaha-belum-terdaftar');

        // D-41: folder company baru sengaja tidak reachable lewat ?company=
        // sampai Fase 3 (users.current_company_id + auth). Ini batas yang
        // diterima, bukan bug.
        // It returns 403 now because SetCurrentCompany checks DB first
        $this->get('/app/dashboard?company=usaha-belum-terdaftar')->assertNotFound();
    }

    public function test_submit_requires_privacy_consent(): void
    {
        Livewire::test(Onboarding::class)
            ->set('name', 'Usaha Tanpa Persetujuan')
            ->set('preset', 'bengkel')
            ->set('acceptPrivacyPolicy', false)
            ->call('submit')
            ->assertSee('Anda wajib menyetujui kebijakan privasi terlebih dahulu.');

        $this->assertDatabaseMissing('companies', ['slug' => 'usaha-tanpa-persetujuan']);
    }

    public function test_authenticated_owner_consent_is_recorded_on_company(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Onboarding::class)
            ->set('name', 'Klinik Sehat')
            ->set('preset', 'bengkel')
            ->set('acceptPrivacyPolicy', true)
            ->call('submit')
            ->assertSet('createdSlug', 'klinik-sehat');

        $company = Company::query()->where('slug', 'klinik-sehat')->first();

        $this->assertNotNull($company);
        $this->assertNotNull($company->privacy_accepted_at);
        $this->assertSame($user->id, $company->privacy_accepted_by_user_id);
        $this->assertSame('2026-09-18', $company->privacy_policy_version);
    }

    public function test_presets_missing_capabilities_from_plan_are_labeled(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $plan = MembershipPlan::factory()->create([
            'is_active' => true,
            'features' => ['contacts'],
        ]);

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $this->get('/onboarding')
            ->assertOk()
            ->assertSee('Enterprise Preset')
            ->assertSee('perlu paket lebih tinggi: inventory.bom, finance.accounting')
            ->assertDontSee('perlu paket lebih tinggi: contacts');
    }
}
