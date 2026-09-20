<?php

namespace App\Services\Manual;

use App\Models\CompanyMembership;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service untuk admin konfirmasi invoice pembayaran manual
 *
 * Validasi:
 * - Hanya admin yang bisa konfirmasi (D-26, requirement wajib)
 * - Transisi hanya dari status 'pending' → 'paid' (idempoten approval, D-52)
 * - Idempoten dengan lockForUpdate: double-submit hanya mengkredit sekali (D-52)
 * - Dalam 1 DB::transaction + lock
 * - Setelah paid: buat/perpanjang company_membership menjadi active
 */
class InvoiceConfirmationService
{
    /**
     * Konfirmasi pembayaran invoice (admin action)
     *
     * @throws ValidationException Jika validasi gagal
     * @throws \Throwable Jika transaksi gagal
     */
    public function confirmPayment(Invoice $invoice): Invoice
    {
        // Validasi 1: Invoice harus status 'pending'
        if ($invoice->payment_status !== 'pending') {
            throw ValidationException::withMessages([
                'invoice_status' => sprintf(
                    'Invoice hanya dapat dikonfirmasi dari status pending. Status saat ini: %s',
                    $invoice->payment_status,
                ),
            ]);
        }

        return DB::transaction(function () use ($invoice): Invoice {
            // Lock invoice row untuk mencegah double-submit concurrent
            $lockedInvoice = Invoice::query()
                ->where('id', $invoice->id)
                ->lockForUpdate()
                ->first();

            // Double-check status (idempoten)
            if ($lockedInvoice->payment_status !== 'pending') {
                return $lockedInvoice; // Sudah di-confirm oleh request sebelumnya, return as-is
            }

            // Update invoice → paid
            $lockedInvoice->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
            ]);

            // Buat atau perpanjang company_membership → active
            $membership = CompanyMembership::query()
                ->where('company_id', $lockedInvoice->company_id)
                ->where('plan_id', $lockedInvoice->membership?->plan_id ?? null)
                ->lockForUpdate()
                ->first();

            if ($membership) {
                // Perpanjang membership yang ada
                $membership->update([
                    'status' => 'active',
                    'expires_at' => now()->addMonth(),
                ]);
            } else {
                // Buat membership baru (jika tidak ada)
                $plan = $lockedInvoice->membership?->plan;

                if ($plan) {
                    CompanyMembership::create([
                        'company_id' => $lockedInvoice->company_id,
                        'plan_id' => $plan->id,
                        'status' => 'active',
                        'starts_at' => now(),
                        'expires_at' => now()->addMonth(),
                        'max_wa_groups' => $plan->max_wa_groups,
                        'monthly_token_quota' => $plan->monthly_token_quota,
                        'emergency_token_quota' => $plan->emergency_token_quota ?? 0,
                        'current_token_balance' => $plan->monthly_token_quota,
                    ]);

                    $lockedInvoice->update([
                        'company_membership_id' => CompanyMembership::where('company_id', $lockedInvoice->company_id)
                            ->where('plan_id', $plan->id)
                            ->latest('id')
                            ->value('id'),
                    ]);
                }
            }

            return $lockedInvoice;
        });
    }
}
