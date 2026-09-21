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
        // Menandai termin sebagai sudah ditagih ketika progres proyek mencapai
        // pemicunya. Dokumen tagihannya sendiri diterbitkan dari layar tagihan
        // (`customer_invoices`, D-62) - efek ini tidak membuat dokumen, dan
        // sengaja tidak menyentuh `invoices` yang merupakan tagihan langganan
        // platform (D-23).

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
                    'note' => 'Termin ditandai sudah ditagih. Dokumen tagihan diterbitkan dari layar tagihan pelanggan.',
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
