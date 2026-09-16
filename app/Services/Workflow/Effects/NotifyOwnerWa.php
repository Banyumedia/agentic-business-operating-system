<?php

namespace App\Services\Workflow\Effects;

class NotifyOwnerWa implements WorkflowEffect
{
    public function key(): string
    {
        return 'notify.owner_wa';
    }

    public function execute(array $context): array
    {
        return [
            'effect' => $this->key(),
            'status' => 'fake',
        ];
    }
}
