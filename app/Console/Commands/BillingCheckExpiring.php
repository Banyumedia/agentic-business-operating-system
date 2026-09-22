<?php

namespace App\Console\Commands;

use App\Contracts\HermesNodeClient;
use App\Models\Invoice;
use App\Services\Billing\DunningLadder;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:check-expiring')]
#[Description('Check for expiring subscriptions and process dunning ladder')]
class BillingCheckExpiring extends Command
{
    public function handle(HermesNodeClient $hermesClient, DunningLadder $dunningLadder)
    {
        // 1. Send H-3 warning
        $h3Invoices = Invoice::where('type', 'subscription')
            ->where('payment_status', 'pending')
            ->whereDate('due_date', Carbon::now()->addDays(3)->toDateString())
            ->get();

        $undelivered = 0;

        foreach ($h3Invoices as $invoice) {
            $company = $invoice->company;
            $owner = $company->owner;
            if ($owner && $owner->wa_number) {
                $delivered = $hermesClient->sendWhatsApp($owner->wa_number, sprintf(
                    'Tagihan langganan BOS Anda (Invoice %s) akan jatuh tempo dalam 3 hari. Segera lakukan pembayaran.',
                    $invoice->order_id
                ));

                // Hasilnya diperiksa, bukan dibuang. Sebelum T-69 lajur platform
                // selalu mengembalikan `true` tanpa mengirim apa pun, jadi peringatan
                // H-3 yang tidak pernah sampai tidak meninggalkan jejak sama sekali.
                $undelivered += $delivered ? 0 : 1;
            }
        }

        if ($undelivered > 0) {
            // Bukan kegagalan perintah: tagihan tetap harus diproses walau
            // notifikasinya gagal, dan penjadwal tidak perlu dibanjiri alarm.
            $this->warn("Peringatan H-3 gagal terkirim untuk {$undelivered} tagihan. Periksa log lajur WhatsApp platform.");
        }

        // 2. Process overdue invoices for Dunning Ladder
        $overdueInvoices = Invoice::where('type', 'subscription')
            ->where('payment_status', 'pending')
            ->where('due_date', '<=', Carbon::now()->toDateString())
            ->get();

        $today = Carbon::now()->startOfDay();
        foreach ($overdueInvoices as $invoice) {
            $dueDate = Carbon::parse($invoice->due_date)->startOfDay();
            $daysOverdue = (int) $today->diffInDays($dueDate, true);
            $dunningLadder->process($invoice->company, $daysOverdue);
        }
    }
}
