<?php

namespace App\Services\Manual;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\MembershipPlan;
use Illuminate\Validation\ValidationException;

/**
 * Service untuk membuat invoice pembayaran manual (transfer/QRIS)
 *
 * Validasi:
 * - Hanya owner company yang bisa membuat invoice (D-26, requirement wajib)
 * - Tidak boleh membuat invoice baru saat ada invoice pending untuk company yang sama (anti-spam)
 * - plan_id harus valid dan is_active=true (validasi server-side D-52)
 * - Invoices.type = subscription, payment_status = pending
 */
class InvoiceCreationService
{
    /**
     * Buat invoice baru untuk subscription paket
     *
     * @throws ValidationException Jika validasi gagal
     * @throws \Throwable Jika penyimpanan gagal
     */
    public function createSubscriptionInvoice(Company $company, MembershipPlan $plan, int $userId): Invoice
    {
        // Validasi 1: Hanya owner yang bisa membuat invoice (D-26)
        if ((int) $company->owner_user_id !== (int) $userId) {
            throw ValidationException::withMessages([
                'authorization' => 'Hanya owner perusahaan yang dapat membuat invoice.',
            ]);
        }

        // Validasi 2: Anti-spam - tidak boleh membuat invoice baru saat ada yang pending
        $existingPending = Invoice::query()
            ->where('company_id', $company->id)
            ->where('type', 'subscription')
            ->where('payment_status', 'pending')
            ->exists();

        if ($existingPending) {
            throw ValidationException::withMessages([
                'pending_invoice' => 'Masih ada invoice pending untuk perusahaan ini. Selesaikan pembayaran terlebih dahulu.',
            ]);
        }

        // Validasi 3: plan_id harus valid dan is_active=true (D-52)
        if (! $plan->is_active) {
            throw ValidationException::withMessages([
                'plan' => 'Paket yang dipilih tidak tersedia.',
            ]);
        }

        // Generate order_id unik (format: INV-{company_id}-{timestamp}-{random})
        $orderId = $this->generateUniqueOrderId($company->id);

        // Create invoice dengan status pending
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'type' => 'subscription',
            'payment_status' => 'pending',
            'order_id' => $orderId,
            'amount' => $plan->monthly_price,
            'company_membership_id' => null, // Akan di-set saat pembayaran dikonfirmasi
            'due_date' => now()->addHours((int) config('billing.manual_payment.expiration_hours', 24))->toDate(),
        ]);

        return $invoice;
    }

    /**
     * Generate unique order_id
     *
     * Format: INV-{company_id}-{timestamp}-{random}
     */
    private function generateUniqueOrderId(int $companyId): string
    {
        do {
            $orderId = sprintf(
                'INV-%d-%s-%s',
                $companyId,
                now()->timestamp,
                str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            );
        } while (Invoice::where('order_id', $orderId)->exists());

        return $orderId;
    }
}
