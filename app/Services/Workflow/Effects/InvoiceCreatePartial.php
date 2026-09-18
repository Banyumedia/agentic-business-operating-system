<?php

namespace App\Services\Workflow\Effects;

use App\Models\ProjectMilestone;

class InvoiceCreatePartial implements WorkflowEffect
{
    public function key(): string
    {
        return 'invoice.create_partial';
    }

    public function execute(array $context): array
    {
        // Update ProjectMilestone status based on context
        // This is a partial invoice effect. The actual invoice record
        // will be created when the invoices table exists (Task T-12).

        $milestoneId = $context['milestone_id'] ?? null;

        if ($milestoneId) {
            $milestone = ProjectMilestone::find($milestoneId);
            if ($milestone && $milestone->status === 'pending') {
                $milestone->status = 'invoiced';
                $milestone->achieved_at = now();
                $milestone->save();

                return [
                    'effect' => $this->key(),
                    'status' => 'success',
                    'milestone_id' => $milestoneId,
                    'note' => 'Project milestone marked as invoiced. Invoice creation skipped pending invoices table (T-12).',
                ];
            }
        }

        return [
            'effect' => $this->key(),
            'status' => 'failed',
            'reason' => 'Invalid or missing milestone_id in context, or milestone already invoiced.',
        ];
    }
}
