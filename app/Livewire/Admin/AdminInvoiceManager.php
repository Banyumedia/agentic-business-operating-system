<?php

namespace App\Livewire\Admin;

use App\Models\Invoice;
use App\Services\Manual\InvoiceConfirmationService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Panel admin untuk manage invoice pembayaran manual (PAY-1)
 *
 * Fitur:
 * - Daftar invoice pending untuk semua company
 * - Tombol "Konfirmasi Lunas" per invoice
 * - Transaction + lock untuk mencegah double-submit (D-52)
 * - Set invoice.payment_status = paid + buat/perpanjang company_memberships
 *
 * Otorisasi:
 * - Hanya super admin yang bisa akses
 * - Middleware RequireSuperAdmin di route
 */
class AdminInvoiceManager extends Component
{
    use WithPagination;

    public function render(): View
    {
        $pendingInvoices = Invoice::query()
            ->where('type', 'subscription')
            ->where('payment_status', 'pending')
            ->with(['company', 'membership.plan'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('livewire.admin.admin-invoice-manager', [
            'pendingInvoices' => $pendingInvoices,
        ])->layout('components.layouts.module', [
            'title' => 'Konfirmasi Invoice · Admin Panel',
        ]);
    }

    /**
     * Action: Konfirmasi pembayaran invoice
     *
     * - Lock invoice row
     * - Transisi status pending → paid
     * - Buat/perpanjang company_membership menjadi active
     * - Idempoten: double-submit hanya mengkredit sekali (D-52)
     */
    public function confirmPayment(Invoice $invoice): void
    {
        try {
            $service = app(InvoiceConfirmationService::class);
            $service->confirmPayment($invoice);

            $this->dispatch('invoice-confirmed', invoiceId: $invoice->id);
            session()->flash('success', 'Invoice berhasil dikonfirmasi sebagai lunas.');
            $this->resetPage();
        } catch (ValidationException $e) {
            $this->addError('confirmation', $e->errors()['invoice_status'][0] ?? 'Gagal mengkonfirmasi invoice.');
        } catch (\Throwable $e) {
            \Log::error('Invoice confirmation error', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            $this->addError('confirmation', 'Terjadi kesalahan saat mengkonfirmasi invoice.');
        }
    }
}
