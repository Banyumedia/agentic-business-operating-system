<?php

namespace Tests\Feature\Api\MasterBot;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Invoice;
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
        Config::set('services.master_bot.secret', 'test-master-secret');
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

    public function test_rejects_wrong_key_without_leaking_it(): void
    {
        $response = $this->postJson('/api/bot/master/tickets', [
            'wa_number' => '6281234567890',
            'subject' => 'Test',
            'description' => 'Test',
        ], [
            'X-Master-Bot-Key' => 'wrong-key',
        ]);

        $response->assertStatus(401);
        $this->assertStringNotContainsString('test-master-secret', $response->getContent());
    }

    public function test_rejects_every_key_when_secret_is_empty_fail_closed(): void
    {
        Config::set('services.master_bot.secret', '');

        $response = $this->postJson('/api/bot/master/tickets', [
            'wa_number' => '6281234567890',
            'subject' => 'Test',
            'description' => 'Test',
        ], [
            'X-Master-Bot-Key' => 'fake-master-secret',
        ]);

        // Default lama "fake-master-secret" tidak boleh lolos saat secret kosong.
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

        // BS-02: invoice topup WAJIB membawa grant token sejak dibuat. Tanpa ini,
        // settlement-nya jatuh ke fallback `?? 1` di webhook - dulu tersembunyi,
        // sekarang fail-closed 422. Grant diturunkan dari nominal lewat mapping,
        // bukan dikarang saat settlement.
        $invoice = Invoice::where('type', 'topup')->first();
        $this->assertNotNull($invoice->token_amount_granted);
        $this->assertGreaterThan(0, (int) $invoice->token_amount_granted);
    }

    public function test_negative_a_topup_with_a_nonpositive_amount_is_refused(): void
    {
        // Nominal nol atau negatif tidak punya arti sebagai pembelian token, dan
        // membiarkannya lolos akan membuat grant nol lalu ditolak jauh di belakang
        // saat settlement. Tolak di pintu masuk.
        $company = Company::factory()->create();
        User::factory()->create([
            'wa_number' => '6281234567890',
            'current_company_id' => $company->id,
        ]);
        $plan = MembershipPlan::factory()->create();
        CompanyMembership::factory()->create(['company_id' => $company->id, 'plan_id' => $plan->id]);

        foreach ([0, -5000] as $amount) {
            $this->postJson('/api/bot/master/topup', [
                'wa_number' => '6281234567890',
                'amount' => $amount,
            ], ['X-Master-Bot-Key' => 'test-master-secret'])->assertStatus(422);
        }

        $this->assertSame(0, Invoice::where('type', 'topup')->count());
    }

    public function test_the_topup_grant_follows_the_amount(): void
    {
        // Dua nominal berbeda harus menghasilkan grant berbeda, dan yang lebih besar
        // mendapat lebih banyak - kalau tidak, mappingnya bukan mapping.
        $company = Company::factory()->create();
        User::factory()->create([
            'wa_number' => '6281234567890',
            'current_company_id' => $company->id,
        ]);
        $plan = MembershipPlan::factory()->create();
        CompanyMembership::factory()->create(['company_id' => $company->id, 'plan_id' => $plan->id]);

        $this->postJson('/api/bot/master/topup', ['wa_number' => '6281234567890', 'amount' => 50000], ['X-Master-Bot-Key' => 'test-master-secret'])->assertOk();
        $kecil = (int) Invoice::where('type', 'topup')->latest('id')->first()->token_amount_granted;

        $this->postJson('/api/bot/master/topup', ['wa_number' => '6281234567890', 'amount' => 200000], ['X-Master-Bot-Key' => 'test-master-secret'])->assertOk();
        $besar = (int) Invoice::where('type', 'topup')->latest('id')->first()->token_amount_granted;

        $this->assertGreaterThan($kecil, $besar);
    }
}
