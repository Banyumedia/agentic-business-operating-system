<?php

namespace App\Services\Workflow\Effects;

use App\Models\ApprovalTicket;

class ApprovalRequest implements WorkflowEffect
{
    public function key(): string
    {
        return 'approval.request';
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
                'entity_type' => $context['entity'],
                'entity_id' => $context['record_id'],
                'from_stage' => $context['from'],
                'to_stage' => $context['to'],
                'requested_by_user_id' => auth()->id() ?? '1',
                'status' => 'pending',
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
