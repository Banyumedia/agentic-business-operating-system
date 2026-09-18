<?php

namespace Tests\Feature\Livewire\Public;

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
            'key' => 'klinik-a',
            'name' => 'Klinik A',
            'tier' => 'A',
            'definition' => ['description' => 'Deskripsi Klinik A'],
        ]);

        $preset2 = BusinessPreset::create([
            'key' => 'salon-b',
            'name' => 'Salon B',
            'tier' => 'A',
            'definition' => ['description' => 'Deskripsi Salon B'],
        ]);

        Livewire::test(IndustryList::class)
            ->assertSee($preset1->name)
            ->assertSee('Deskripsi Klinik A')
            ->assertSee($preset2->name)
            ->assertSee('Deskripsi Salon B')
            ->assertSee('/onboarding?preset=klinik-a')
            ->assertSee('/onboarding?preset=salon-b');
    }
}
