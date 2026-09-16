<?php

namespace Tests\Feature;

use App\Livewire\Lobby;
use Tests\TestCase;

class LobbyNavigationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['active_company' => 'bengkel-arka']);
    }

    public function test_lobby_page_renders(): void
    {
        $this->get(route('lobby'))
            ->assertOk()
            ->assertSee('Agentic BOS');
    }

    public function test_every_lobby_app_card_links_to_its_module_route(): void
    {
        $response = $this->get(route('lobby'))->assertOk();
        $html = $response->getContent();

        foreach ((new Lobby)->apps as $app) {
            $expectedHref = 'href="'.route('app.module', ['module' => $app['slug']]).'"';

            $this->assertStringContainsString(
                $expectedHref,
                $html,
                "Kartu aplikasi [{$app['name']}] harus menautkan ke route modulnya."
            );
        }
    }

    public function test_lobby_has_no_placeholder_links(): void
    {
        $html = $this->get(route('lobby'))->assertOk()->getContent();

        $this->assertStringNotContainsString(
            'href="#"',
            $html,
            'Lobby tidak boleh memakai placeholder href="#"; setiap kartu harus dapat dinavigasi.'
        );
    }

    public function test_clicking_through_to_a_module_returns_that_module_screen(): void
    {
        $this->get(route('app.module', ['module' => 'hrd']))
            ->assertOk()
            ->assertSee('HRD', false);
    }
}
