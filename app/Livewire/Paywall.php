<?php

namespace App\Livewire;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Models\MembershipPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Halaman paywall D-61: kuota gratis habis → klien memilih paket berbayar.
 *
 * Angka kuota tier gratis dari config (D-60), daftar paket dari
 * `membership_plans` (D-52). Tanpa paket aktif → pesan hubungi admin.
 */
class Paywall extends Component
{
    /** @var list<array{slug: string, name: string, monthly_price: string, annual_price: string, max_wa_groups: int, monthly_token_quota: int}> */
    #[Locked]
    public array $plans = [];

    #[Locked]
    public int $freeWaGroups = 1;

    #[Locked]
    public int $freeTokenQuota = 500;

    #[Locked]
    public string $reason = '';

    #[Locked]
    public string $theme = 'a';

    public function mount(CompanyContext $companyContext, CompanySettingsStore $settings): void
    {
        // Konteks company boleh gagal (mis. fixture demo tanpa settings);
        // paywall tetap harus render supaya klien punya jalur keluar.
        try {
            $company = $companyContext->current();
        } catch (\Throwable) {
            $company = '';
        }

        $theme = 'a';
        if ($company !== '') {
            try {
                $theme = (string) ($settings->read($company)['theme'] ?? 'a');
            } catch (\Throwable) {
                $theme = 'a';
            }
        }
        $this->theme = $theme;

        // D-60: kuota gratis config-driven, tidak hardcode di view.
        $this->freeWaGroups = (int) config('billing.free_tier.max_wa_groups', 1);
        $this->freeTokenQuota = (int) config('billing.free_tier.token_quota', 500);

        // Alasan kedatangan (flash dari exception render) - kosong berarti
        // pengguna membuka halaman sendiri; teks default dipakai.
        $this->reason = (string) session('paywall_reason', '');
        session()->forget('paywall_reason');

        if (Schema::hasTable('membership_plans')) {
            $this->plans = MembershipPlan::query()
                ->where('is_active', true)
                ->orderBy('monthly_price')
                ->get()
                ->map(fn (MembershipPlan $plan): array => [
                    'slug' => (string) $plan->slug,
                    'name' => (string) $plan->name,
                    'monthly_price' => 'Rp '.number_format((float) $plan->monthly_price, 0, ',', '.'),
                    'annual_price' => 'Rp '.number_format((float) $plan->annual_price, 0, ',', '.'),
                    'max_wa_groups' => (int) $plan->max_wa_groups,
                    'monthly_token_quota' => (int) $plan->monthly_token_quota,
                ])
                ->all();
        }
    }

    public function render(): View
    {
        return view('livewire.paywall')->layout('components.layouts.module', [
            'title' => 'Pilih Paket · Agentic BOS',
            'theme' => $this->theme,
        ]);
    }
}
