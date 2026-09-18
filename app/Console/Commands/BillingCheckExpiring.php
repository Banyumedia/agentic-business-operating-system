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

        foreach ($h3Invoices as $invoice) {
            $company = $invoice->company;
            $owner = $company->owner;
            if ($owner && $owner->wa_number) {
                $hermesClient->sendWhatsApp($owner->wa_number, sprintf(
                    'Tagihan langganan BOS Anda (Invoice %s) akan jatuh tempo dalam 3 hari. Segera lakukan pembayaran.',
                    $invoice->order_id
                ));
            }
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
