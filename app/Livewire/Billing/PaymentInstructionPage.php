<?php

namespace App\Livewire\Billing;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Models\Invoice;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Halaman instruksi pembayaran manual (transfer bank + QRIS statis) (PAY-1)
 *
 * Ditampilkan setelah klien memilih paket dan invoice dibuat.
 *
 * Isi:
 * - Nomor rekening + nama bank + atas nama dari config billing.manual_payment.*
 * - Nominal persis (dari invoice.amount)
 * - Gambar QRIS statis dari config billing.manual_payment.qris_path
 * - Instruksi pembayaran
 *
 * Semua config dari .env, JANGAN hardcode nomor rekening/path di kode (D-52)
 */
class PaymentInstructionPage extends Component
{
    #[Locked]
    public int $invoiceId = 0;

    #[Locked]
    public string $orderId = '';

    #[Locked]
    public string $amount = '';

    #[Locked]
    public string $bankName = '';

    #[Locked]
    public string $bankAccount = '';

    #[Locked]
    public string $bankHolder = '';

    #[Locked]
    public string $qrisPath = '';

    #[Locked]
    public string $theme = 'a';

    public function mount(Invoice $invoice, CompanyContext $companyContext, CompanySettingsStore $settings): void
    {
        // Validasi: invoice milik company yang sedang aktif
        $company = $companyContext->current();

        if ((int) $invoice->company_id !== (int) $company->id) {
            abort(403, 'Invoice tidak milik perusahaan Anda.');
        }

        // Validasi: invoice harus status pending
        if ($invoice->payment_status !== 'pending') {
            abort(403, 'Invoice sudah diproses atau tidak lagi pending.');
        }

        // Ambil tema company
        try {
            $theme = (string) ($settings->read($company->id)['theme'] ?? 'a');
            $this->theme = $theme;
        } catch (\Throwable) {
            $this->theme = 'a';
        }

        // Dari invoice
        $this->invoiceId = (int) $invoice->id;
        $this->orderId = (string) $invoice->order_id;
        $this->amount = 'Rp '.number_format((float) $invoice->amount, 0, ',', '.');

        // Dari config billing.manual_payment.* (D-52: config-driven, tidak hardcode)
        $this->bankName = (string) config('billing.manual_payment.bank.name', 'BCA');
        $this->bankAccount = (string) config('billing.manual_payment.bank.account_number', '');
        $this->bankHolder = (string) config('billing.manual_payment.bank.account_holder', '');
        $this->qrisPath = (string) config('billing.manual_payment.qris_path', '');
    }

    public function render(): View
    {
        return view('livewire.billing.payment-instruction-page')->layout('components.layouts.module', [
            'title' => 'Instruksi Pembayaran · Agentic BOS',
            'theme' => $this->theme,
        ]);
    }
}
