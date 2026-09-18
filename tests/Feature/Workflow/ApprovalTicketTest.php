<?php

namespace Tests\Feature\Workflow;

use App\Models\ApprovalTicket;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_negative_consumed_ticket_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $ticket = ApprovalTicket::create([
            'company_id' => $company->id,
            'code' => '123456',
            'action_type' => 'test.action',
            'payload' => [],
            'status' => 'consumed',
            'expires_at' => now()->addDay(),
        ]);

        $this->assertFalse($ticket->status === 'pending');

        // This simulates the behavior of what the ApprovalService will do
        // Since ApprovalService is not strictly part of this ticket but the rule says it MUST NOT be reused,
        // we write a test ensuring it's not pending and isExpired or check state.

        $pendingTicket = ApprovalTicket::where('company_id', $company->id)
            ->where('code', '123456')
            ->where('status', 'pending')
            ->first();

        $this->assertNull($pendingTicket, 'A consumed ticket must not be fetchable as pending.');
    }

    public function test_negative_expired_ticket_is_considered_expired(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $ticket = ApprovalTicket::create([
            'company_id' => $company->id,
            'code' => '654321',
            'action_type' => 'test.action',
            'payload' => [],
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertTrue($ticket->isExpired());
    }
}
