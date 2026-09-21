<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\AiPricingManager;
use App\Livewire\Admin\PlanManager;
use App\Livewire\Admin\SupportTicketManager;
use App\Models\AiModelPricing;
use App\Models\Company;
use App\Models\MembershipPlan;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuperAdminModulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_platform_admin' => true,
        ]);

        $this->nonAdmin = User::factory()->create([
            'is_platform_admin' => false,
        ]);
    }

    public function test_non_admin_cannot_access_new_admin_routes(): void
    {
        $this->actingAs($this->nonAdmin);

        $this->get(route('admin.ai-pricings'))->assertForbidden();
        $this->get(route('admin.plans'))->assertForbidden();
        $this->get(route('admin.support-tickets'))->assertForbidden();
    }

    public function test_admin_can_access_new_admin_routes(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.ai-pricings'))->assertOk();
        $this->get(route('admin.plans'))->assertOk();
        $this->get(route('admin.support-tickets'))->assertOk();
    }

    public function test_ai_pricing_can_be_updated(): void
    {
        $pricing = AiModelPricing::create([
            'model_name' => 'test-model-4o',
            'input_multiplier' => 1.0,
            'output_multiplier' => 3.0,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test(AiPricingManager::class)
            ->call('edit', $pricing->id)
            ->set('inputMultiplier', 1.5)
            ->set('outputMultiplier', 4.5)
            ->call('save')
            ->assertHasNoErrors();

        $pricing->refresh();
        $this->assertEquals(1.5, (float) $pricing->input_multiplier);
        $this->assertEquals(4.5, (float) $pricing->output_multiplier);
    }

    public function test_membership_plan_can_be_updated(): void
    {
        $plan = MembershipPlan::create([
            'name' => 'Starter Pro',
            'slug' => 'starter-pro',
            'monthly_price' => 150000,
            'max_wa_groups' => 2,
            'monthly_token_quota' => 500000,
            'emergency_token_quota' => 25000,
            'trial_token_quota' => 50000,
            'features' => ['contacts', 'pos'],
            'is_active' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test(PlanManager::class)
            ->call('edit', $plan->id)
            ->set('price', 200000)
            ->set('monthlyTokenQuota', 750000)
            ->call('save')
            ->assertHasNoErrors();

        $plan->refresh();
        $this->assertEquals(200000, (float) $plan->monthly_price);
        $this->assertEquals(750000, (int) $plan->monthly_token_quota);
    }

    public function test_support_ticket_can_be_resolved(): void
    {
        $company = Company::factory()->create();

        $ticket = SupportTicket::create([
            'ticket_number' => 'TCK-2026-0001',
            'company_id' => $company->id,
            'subject' => 'Printer kasir error',
            'description' => 'Tidak bisa cetak struk dari HP',
            'status' => 'open',
            'priority' => 'high',
        ]);

        Livewire::actingAs($this->admin)
            ->test(SupportTicketManager::class)
            ->call('startResolve', $ticket->id)
            ->set('resolutionNote', 'Konfigurasi driver printer bluetooth diperbarui.')
            ->call('resolve')
            ->assertHasNoErrors();

        $ticket->refresh();
        $this->assertEquals('resolved', $ticket->status);
    }
}
