<?php

namespace App\Livewire\Admin;

use App\Models\CompanyMembership;
use App\Models\Invoice;
use App\Services\Platform\PlatformSettingStore;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Panel Super Admin: pengaturan pembayaran (rekening + QRIS) dan statistik
 * komersial. Perubahan berlaku langsung (DB + cache flush), tanpa restart.
 */
class AdminPaymentSettings extends Component
{
    use WithFileUploads;

    public string $bankName = '';

    public string $bankAccount = '';

    public string $bankHolder = '';

    public bool $paymentEnabled = true;

    /** @var TemporaryUploadedFile|null */
    public $qrisUpload;

    public ?string $qrisCurrentPath = null;

    public function mount(PlatformSettingStore $store): void
    {
        $config = $store->paymentConfig();
        $this->bankName = $config['bank_name'];
        $this->bankAccount = $config['bank_account'];
        $this->bankHolder = $config['bank_holder'];
        $this->paymentEnabled = $config['enabled'];
        $this->qrisCurrentPath = $config['qris_path'] !== '' ? $config['qris_path'] : null;
    }

    public function save(PlatformSettingStore $store): void
    {
        $this->validate([
            'bankName' => ['required', 'string', 'max:64'],
            'bankAccount' => ['required', 'string', 'max:64'],
            'bankHolder' => ['required', 'string', 'max:128'],
        ]);

        if ($this->qrisUpload) {
            $this->validate(['qrisUpload' => ['image', 'max:2048']]); // png/jpg/webp, max 2MB
            $path = $this->qrisUpload->store('qris', 'public');
            $store->put(['payment_qris_path' => "storage/{$path}"]);
            $this->qrisCurrentPath = "storage/{$path}";
        }

        $store->put([
            'payment_enabled' => $this->paymentEnabled ? '1' : '0',
            'payment_bank_name' => $this->bankName,
            'payment_bank_account' => $this->bankAccount,
            'payment_bank_holder' => $this->bankHolder,
        ]);

        session()->flash('success', 'Pengaturan pembayaran tersimpan.');
    }

    public function removeQris(PlatformSettingStore $store): void
    {
        $store->put(['payment_qris_path' => null]);
        $this->qrisCurrentPath = null;
        $this->qrisUpload = null;
        session()->flash('success', 'QRIS dihapus.');
    }

    public function render(): View
    {
        $stats = [
            'invoices_pending' => Invoice::query()->where('type', 'subscription')->where('payment_status', 'pending')->count(),
            'invoices_paid' => Invoice::query()->where('type', 'subscription')->where('payment_status', 'paid')->count(),
            'revenue_total' => (float) Invoice::query()->where('type', 'subscription')->where('payment_status', 'paid')->sum('amount'),
            'revenue_this_month' => (float) Invoice::query()->where('type', 'subscription')->where('payment_status', 'paid')->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('amount'),
            'memberships_active' => CompanyMembership::query()->where('status', 'active')->count(),
            'memberships_expiring_7d' => CompanyMembership::query()->where('status', 'active')->whereBetween('expires_at', [now(), now()->addDays(7)])->count(),
        ];

        return view('livewire.admin.admin-payment-settings', [
            'stats' => $stats,
            'recentPaid' => Invoice::query()->where('type', 'subscription')->where('payment_status', 'paid')->with('company')->orderByDesc('paid_at')->limit(5)->get(),
        ])->layout('components.layouts.module', [
            'title' => 'Pengaturan Pembayaran & Statistik  Admin Panel',
        ]);
    }
}
