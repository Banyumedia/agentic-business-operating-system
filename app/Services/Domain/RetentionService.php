<?php

namespace App\Services\Domain;

use App\Models\Invoice;
use App\Models\ProjectMilestone;
use App\Models\Retention;
use LogicException;

class RetentionService
{
    /**
     * Compute and hold retention automatically when a milestone is invoiced.
     */
    public function computeAndHold(ProjectMilestone $milestone, Invoice $invoice): ?Retention
    {
        $pct = $milestone->retention_pct;
        if (! $pct || $pct <= 0) {
            return null;
        }

        // The milestone amount might come from the invoice amount or milestone definition.
        // Assuming invoice amount is the base for retention.
        $retentionAmount = $invoice->amount * ($pct / 100);

        return Retention::create([
            'company_id' => $milestone->company_id,
            'project_id' => $milestone->project_id,
            'milestone_id' => $milestone->id,
            'invoice_id' => $invoice->id,
            'amount' => $retentionAmount,
            'status' => 'held',
        ]);
    }

    /**
     * Prevent invoicing a held retention before its release date.
     */
    public function ensureCanBeInvoiced(Retention $retention): void
    {
        if ($retention->status !== 'held') {
            throw new LogicException("Hanya retensi dengan status 'held' yang dapat ditagihkan.");
        }

        if ($retention->release_on && now()->startOfDay()->lt($retention->release_on)) {
            throw new LogicException("Retensi belum mencapai tanggal rilis ({$retention->release_on->format('Y-m-d')}).");
        }
    }
}
