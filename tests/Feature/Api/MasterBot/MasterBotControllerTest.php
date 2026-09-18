<?php

namespace Tests\Feature\Api\MasterBot;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MasterBotControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.env', 'testing');
        putenv('MASTER_BOT_SECRET=test-master-secret');
    }

    public function test_rejects_unauthorized_request(): void
    {
        $response = $this->postJson('/api/bot/master/tickets', [
            'wa_number' => '6281234567890',
            'subject' => 'Test',
            'description' => 'Test',
        ]);

        $response->assertStatus(401);
    }

    public function test_rejects_user_without_company(): void
    {
        User::factory()->create([
            'wa_number' => '6281234567890',
            'current_company_id' => null,
        ]);

        $response = $this->postJson('/api/bot/master/tickets', [
            'wa_number' => '6281234567890',
            'subject' => 'Test',
            'description' => 'Test',
        ], [
            'X-Master-Bot-Key' => 'test-master-secret',
        ]);

        $response->assertStatus(403);
    }

    public function test_rejects_unknown_wa_number(): void
    {
        $response = $this->postJson('/api/bot/master/tickets', [
            'wa_number' => '6281234567899',
            'subject' => 'Test',
            'description' => 'Test',
        ], [
            'X-Master-Bot-Key' => 'test-master-secret',
        ]);

        $response->assertStatus(403);
    }

    public function test_creates_ticket_successfully(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'wa_number' => '6281234567890',
            'current_company_id' => $company->id,
        ]);

        $response = $this->postJson('/api/bot/master/tickets', [
            'wa_number' => '6281234567890',
            'subject' => 'System down',
            'description' => 'Cannot login',
        ], [
            'X-Master-Bot-Key' => 'test-master-secret',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'ticket_id']);

        $this->assertDatabaseHas('support_tickets', [
            'company_id' => $company->id,
            'reported_by_user_id' => $user->id,
            'subject' => 'System down',
            'status' => 'open',
        ]);
    }

    public function test_checks_balance_successfully(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'wa_number' => '6281234567890',
            'current_company_id' => $company->id,
        ]);

        $plan = MembershipPlan::factory()->create();
        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'current_token_balance' => 50000,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/bot/master/balance?wa_number=6281234567890', [
            'X-Master-Bot-Key' => 'test-master-secret',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'balance' => 50000,
            'status' => 'active',
        ]);
    }

    public function test_creates_topup_invoice_successfully(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'wa_number' => '6281234567890',
            'current_company_id' => $company->id,
        ]);

        $plan = MembershipPlan::factory()->create();
        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->postJson('/api/bot/master/topup', [
            'wa_number' => '6281234567890',
            'amount' => 150000,
        ], [
            'X-Master-Bot-Key' => 'test-master-secret',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'invoice_id', 'payment_url']);

        $this->assertDatabaseHas('invoices', [
            'company_id' => $company->id,
            'type' => 'topup',
            'amount' => 150000,
            'payment_status' => 'pending',
        ]);
    }
}
