<?php

namespace App\Services\Domain;

use App\Models\CashEntry;
use App\Models\Invoice;
use App\Models\ProjectMilestone;
use App\Models\Retention;
use Illuminate\Support\Facades\DB;
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

    /**
     * Cairkan retensi yang sudah memenuhi tanggal rilis (MP-09).
     *
     * Menulis satu baris `cash_entries` (`direction: 'out'`) sebagai jejak kas
     * keluar - sama seperti `ContractScreen::recordPayment()` mencatat kas
     * masuk, uang keluar juga tidak boleh disimpulkan dari kolom status saja.
     * Retensi yang belum dibayar sudah diakui sebagai pendapatan saat tagihan
     * asalnya terbit (T-43/T-44); pencairan di sini murni pergerakan kas dari
     * uang yang sebelumnya ditahan, bukan peristiwa laba-rugi baru - karena
     * itu tidak memposting jurnal akuntansi (`finance.accounting`), berbeda
     * dari `OrderService::checkoutPos()` yang memang mengakui pendapatan baru.
     *
     * Guard memakai `ensureCanBeInvoiced()` yang sudah ada dan sudah teruji:
     * namanya menyesatkan (ditulis untuk kasus "tagih ulang dari retensi")
     * tetapi pemeriksaannya - status harus `held`, tanggal rilis harus
     * terlewati - persis pemeriksaan yang dibutuhkan sebelum uang dicairkan.
     * Retensi yang sudah `released` ditolak oleh guard yang sama (status
     * bukan `held`), sehingga pencairan ganda tidak mungkin lewat jalur ini.
     */
    public function disburse(Retention $retention): CashEntry
    {
        $this->ensureCanBeInvoiced($retention);

        return DB::transaction(function () use ($retention): CashEntry {
            $entry = CashEntry::create([
                'company_id' => $retention->company_id,
                'entry_date' => now()->toDateString(),
                'direction' => 'out',
                'amount' => $retention->amount,
                'category' => 'pencairan retensi',
                'description' => 'Pencairan retensi proyek #'.$retention->project_id,
                'project_id' => $retention->project_id,
                'source_type' => 'retention',
                'source_id' => $retention->id,
            ]);

            $retention->status = 'released';
            $retention->released_at = now();
            $retention->save();

            return $entry;
        });
    }
}
