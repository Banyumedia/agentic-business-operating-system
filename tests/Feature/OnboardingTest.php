<?php

namespace Tests\Feature;

use App\Contracts\PresetSource;
use App\Livewire\Onboarding;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('company-json');
    }

    public function test_preset_options_come_from_preset_source_not_hardcoded(): void
    {
        $this->app->bind(PresetSource::class, fn () => new class implements PresetSource
        {
            public function all(): array
            {
                return [[
                    'key' => 'zz_fixture',
                    'name' => 'Preset Fixture Onboarding ZZ',
                    'tier' => 'A',
                    'capabilities' => [],
                    'terminology' => [],
                    'workflows' => [],
                    'dashboard' => ['industry_zone' => []],
                    'menus' => ['order' => []],
                ]];
            }

            public function find(string $key): ?array
            {
                return collect($this->all())->firstWhere('key', $key);
            }
        });

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

        $this->assertEmpty(Storage::disk('company-json')->allDirectories('json'));
    }

    public function test_submitting_valid_form_creates_company_folder_with_safe_defaults(): void
    {
        Livewire::test(Onboarding::class)
            ->set('name', 'Toko Baru Sejahtera')
            ->set('preset', 'bengkel')
            ->call('submit')
            ->assertSet('createdSlug', 'toko-baru-sejahtera');

        Storage::disk('company-json')->assertExists('json/toko-baru-sejahtera/business_identity.json');
        $identity = json_decode(
            Storage::disk('company-json')->get('json/toko-baru-sejahtera/business_identity.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('Toko Baru Sejahtera', $identity['name']);
        $this->assertSame('bengkel', $identity['preset']);
        // D-44: default aman, jangan pernah taxable diam-diam.
        $this->assertSame('non_taxable', $identity['tax_mode']);

        Storage::disk('company-json')->assertExists('json/toko-baru-sejahtera/settings.json');
    }

    public function test_duplicate_business_name_gets_an_incrementing_unique_slug(): void
    {
        Livewire::test(Onboarding::class)
            ->set('name', 'Kedai Kopi')
            ->set('preset', 'bengkel')
            ->call('submit')
            ->assertSet('createdSlug', 'kedai-kopi');

        Livewire::test(Onboarding::class)
            ->set('name', 'Kedai Kopi')
            ->set('preset', 'bengkel')
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
        Livewire::test(Onboarding::class)
            ->set('name', 'Usaha Belum Terdaftar')
            ->set('preset', 'bengkel')
            ->call('submit')
            ->assertSet('createdSlug', 'usaha-belum-terdaftar');

        // D-41: folder company baru sengaja tidak reachable lewat ?company=
        // sampai Fase 3 (users.current_company_id + auth). Ini batas yang
        // diterima, bukan bug.
        $this->get('/app/dashboard?company=usaha-belum-terdaftar')->assertNotFound();
    }
}
