<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Livewire\Dashboard;
use App\Services\Dashboard\DashboardComposer;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardQuickActionsTest extends TestCase
{
    public function test_dashboard_composer_generates_contextual_quick_actions(): void
    {
        $context = app(CompanyContext::class);
        $context->setCurrent('laundry-bersih');

        $composer = app(DashboardComposer::class);
        $data = $composer->compose();

        $this->assertArrayHasKey('quick_actions', $data);
        $this->assertNotEmpty($data['quick_actions']);
        $this->assertLessThanOrEqual(3, count($data['quick_actions']));

        // First action should be marked as primary
        $this->assertTrue($data['quick_actions'][0]['primary']);

        // Laundry has pos capability -> should have Buka Kasir
        $keys = array_column($data['quick_actions'], 'key');
        $this->assertContains('pos', $keys);
        $this->assertContains('cashbook', $keys);
        $this->assertContains('contacts', $keys);
    }

    public function test_dashboard_renders_quick_actions_bar_in_dom(): void
    {
        $context = app(CompanyContext::class);
        $context->setCurrent('bengkel-arka');

        Livewire::test(Dashboard::class)
            ->assertSee('Aksi cepat')
            ->assertSee('Catat Kas')
            ->assertSeeHtml('href="'.url('/app/accounting').'"');
    }
}
