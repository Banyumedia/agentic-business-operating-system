<?php

namespace App\Services\Workflow\Effects;

use App\Models\ApprovalTicket;

class ApprovalRequest implements WorkflowEffect
{
    public function key(): string
    {
        return 'approval.request';
    }

    private function generateCode(string $companyId): string
    {
        do {
            // 4-6 digits for WA reply (D-27)
            $code = (string) random_int(1000, 999999);

            $exists = ApprovalTicket::where('company_id', $companyId)
                ->where('status', 'pending')
                ->where('code', $code)
                ->exists();
        } while ($exists);

        return $code;
    }

    public function execute(array $context): array
    {
        // Tiket approval hanya dipersist ke tabel DB saat DATA_SOURCE=eloquent
        // (D-42: Fase 2 murni JSON, tidak boleh butuh tabel DB untuk entitas
        // bisnis). Untuk mode JSON, status pending cukup tercatat di log JSON
        // WorkflowEngine (JsonWorkflowLog) sebagai audit trail.
        if (config('datasource.driver') === 'eloquent') {
            $ticket = ApprovalTicket::create([
                'company_id' => $context['company'],
                'code' => $this->generateCode($context['company']),
                'action_type' => 'workflow.transition',
                'subject_type' => null, // We could pass get_class($model) from context later if needed
                'subject_id' => $context['record_id'],
                'payload' => $context,
                'amount' => null, // Transitions usually don't have a specific amount to show
                'requested_by_user_id' => auth()->id() ?? '1',
                'status' => 'pending',
                'channel' => 'whatsapp',
                'expires_at' => now()->addHours(24), // Default 24h expiration for workflow approvals
            ]);

            return [
                'effect' => $this->key(),
                'status' => 'pending',
                'ticket_id' => (string) $ticket->id,
            ];
        }

        return [
            'effect' => $this->key(),
            'status' => 'pending',
            'ticket_id' => null,
        ];
    }
}
