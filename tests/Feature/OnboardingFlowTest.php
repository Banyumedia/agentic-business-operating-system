<?php

namespace Tests\Feature;

use App\Contracts\PresetSource;
use App\Livewire\Onboarding;
use App\Models\Company;
use App\Models\User;
use App\Services\PlanCapabilityGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Acceptance test alur onboarding multi-langkah (maraton UX lane A):
 * identitas -> pilih preset -> ringkasan -> selesai -> redirect dashboard.
 */
class OnboardingFlowTest extends TestCase
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
                        'key' => 'zz_flow_a',
                        'name' => 'Preset Alur A',
                        'tier' => 'A',
                        'capabilities' => ['contacts' => true],
                        'terminology' => [],
                        'workflows' => [],
                        'dashboard' => ['industry_zone' => []],
                        'menus' => ['order' => []],
                    ],
                    [
                        'key' => 'zz_flow_b',
                        'name' => 'Preset Alur B',
                        'tier' => 'A',
                        'capabilities' => ['contacts' => true],
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

        $gate = $this->mock(PlanCapabilityGate::class);
        $gate->shouldReceive('allowedCapabilities')->andReturn([]);
        $this->app->instance(PlanCapabilityGate::class, $gate);
    }

    public function test_flow_starts_at_step_one_with_progress_indicator(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(Onboarding::class);

        $component
            ->assertSet('step', 1)
            ->assertSee('Nama usaha')
            ->assertSee('Langkah 1 dari 3');
    }

    public function test_step_one_requires_name_before_continuing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Onboarding::class)
            ->set('name', '   ')
            ->call('nextStep')
            ->assertSet('step', 1)
            ->assertSee('Nama usaha wajib diisi');
    }

    public function test_steps_advance_and_go_back_without_losing_input(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(Onboarding::class);
        $component
            ->set('name', 'Usaha Alur Lengkap')
            ->call('nextStep')
            ->assertSet('step', 2)
            ->assertSee('Preset Alur A')
            ->assertSee('Preset Alur B');

        $component
            ->set('preset', 'zz_flow_b')
            ->call('nextStep')
            ->assertSet('step', 3);

        $component
            ->call('previousStep')
            ->assertSet('step', 2)
            ->assertSet('name', 'Usaha Alur Lengkap')
            ->assertSet('preset', 'zz_flow_b');
    }

    public function test_empty_preset_list_shows_informative_empty_state_and_blocks_submit(): void
    {
        $this->app->bind(PresetSource::class, fn () => new class implements PresetSource
        {
            public function all(): array
            {
                return [];
            }

            public function find(string $key): ?array
            {
                return null;
            }
        });

        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(Onboarding::class);
        $component->assertSee('Belum ada preset');

        $component
            ->set('name', 'Usaha Tanpa Registry')
            ->set('acceptPrivacyPolicy', true)
            ->call('submit')
            ->assertSet('createdSlug', null);

        $this->assertDatabaseMissing('companies', ['slug' => 'usaha-tanpa-registry']);
    }

    public function test_finishing_flow_creates_company_and_redirects_to_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Onboarding::class)
            ->set('name', 'Usaha Alur Selesai')
            ->set('preset', 'zz_flow_a')
            ->set('acceptPrivacyPolicy', true)
            ->call('submit')
            ->assertRedirect(route('app.dashboard'));

        $company = Company::query()->where('slug', 'usaha-alur-selesai')->first();
        $this->assertNotNull($company);
        $this->assertSame($user->id, $company->owner_user_id);
        $this->assertSame('zz_flow_a', $company->business_preset);
        Storage::disk('company-json')->assertExists('json/usaha-alur-selesai/business_identity.json');
        Storage::disk('company-json')->assertExists('json/usaha-alur-selesai/settings.json');
    }

    public function test_invalid_preset_in_step_two_is_rejected_at_validation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Onboarding::class)
            ->set('name', 'Usaha Preset Jahat')
            ->set('preset', 'zz_flow_hantu')
            ->set('acceptPrivacyPolicy', true)
            ->call('submit')
            ->assertSee('Preset bisnis tidak valid');

        $this->assertDatabaseMissing('companies', ['slug' => 'usaha-preset-jahat']);
    }
}
