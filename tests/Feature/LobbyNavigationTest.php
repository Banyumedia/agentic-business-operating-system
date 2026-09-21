<?php

namespace Tests\Feature;

use Tests\TestCase;

class LobbyNavigationTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        session(['active_company' => 'bengkel-arka']);
    }

    public function test_lobby_page_renders_only_visible_application_links(): void
    {
        $html = $this->get('/app/lobby?company=klinik-sehat')->assertOk()->assertSee('Agentic BOS')->getContent();

        foreach (['dashboard', 'contacts', 'bookings', 'accounting', 'hrd', 'settings'] as $module) {
            $this->assertStringContainsString('/app/'.$module, $html);
        }

        $this->assertStringNotContainsString('/app/projects', $html);
        $this->assertStringNotContainsString('/app/inventory', $html);
        $this->assertStringNotContainsString('/app/pos', $html);
    }

    public function test_lobby_has_no_placeholder_links(): void
    {
        $html = $this->get(route('lobby'))->assertOk()->getContent();

        $this->assertStringNotContainsString('href="#"', $html);
    }

    public function test_clicking_through_to_an_enabled_module_returns_its_screen(): void
    {
        $this->get(route('app.module', ['module' => 'projects']))
            ->assertOk()
            ->assertSee('Pekerjaan');
    }
}
