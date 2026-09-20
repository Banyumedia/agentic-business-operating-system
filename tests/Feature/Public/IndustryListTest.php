<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\IndustryList;
use App\Models\BusinessPreset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndustryListTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_industry_list_page()
    {
        $response = $this->get('/industri');

        $response->assertStatus(200);
        $response->assertSeeLivewire(IndustryList::class);
    }

    public function test_it_displays_all_business_presets()
    {
        $preset1 = BusinessPreset::create([
            'key' => 'zz-pub-a',
            'name' => 'Preset Publik A',
            'tier' => 'A',
            'definition' => ['description' => 'Deskripsi Preset Publik A'],
        ]);

        $preset2 = BusinessPreset::create([
            'key' => 'zz-pub-b',
            'name' => 'Preset Publik B',
            'tier' => 'A',
            'definition' => ['description' => 'Deskripsi Preset Publik B'],
        ]);

        Livewire::test(IndustryList::class)
            ->assertSee($preset1->name)
            ->assertSee('Deskripsi Preset Publik A')
            ->assertSee($preset2->name)
            ->assertSee('Deskripsi Preset Publik B')
            ->assertSee('/onboarding?preset=zz-pub-a')
            ->assertSee('/onboarding?preset=zz-pub-b');
    }

    public function test_empty_registry_shows_informative_empty_state(): void
    {
        Livewire::test(IndustryList::class)
            ->assertSee('Belum ada jenis usaha yang bisa ditampilkan')
            ->assertDontSee('Gunakan Preset Ini');
    }

    public function test_page_has_cta_to_register_for_guests(): void
    {
        // Halaman publik: tamu yang belum punya akun diarahkan mendaftar
        // (route login), bukan langsung ke onboarding yang butuh auth.
        $this->get('/industri')
            ->assertOk()
            ->assertSee(route('login'))
            ->assertSee('Masuk atau daftar');
    }
}
