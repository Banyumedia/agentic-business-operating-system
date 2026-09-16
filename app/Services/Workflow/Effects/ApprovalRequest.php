<?php

namespace App\Services\Workflow\Effects;

use Illuminate\Support\Str;

class ApprovalRequest implements WorkflowEffect
{
    public function key(): string
    {
        return 'approval.request';
    }

    public function execute(array $context): array
    {
        return [
            'effect' => $this->key(),
            'status' => 'pending',
            'ticket_id' => (string) Str::uuid(),
        ];
    }
}
