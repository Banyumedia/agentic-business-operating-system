<?php

namespace App\Livewire\Settings;

use App\Contracts\CompanyContext;
use App\Models\CompanyMembership;
use App\Services\Billing\TokenQuotaGate;
use App\Services\Billing\WaGroupQuotaGate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * W2: Indikator kuota & peringatan dini di tab "Penggunaan & Paket".
 *
 * D-60/D-61: semua angka dibaca dari gate (config/membership), tanpa
 * hardcode. D-49: membership non-aktif -> 0 (fail-closed). U-05: tampilan
 * informatif, bukan CTA jualan. Peringatan dini hanya untuk saldo > 0 yang
 * menipis (< 20% kuota) — saldo 0 sudah tidak "menipis", itu habis.
 */
class UsageOverview extends Component
{
    #[Locked]
    public int $tokenBalance = 0;

    #[Locked]
    public int $tokenQuota = 0;

    #[Locked]
    public int $maxWaGroups = 0;

    #[Locked]
    public bool $isFreeTier = true;

    #[Locked]
    public ?string $planName = null;

    #[Locked]
    public bool $lowBalance = false;

    /**
     * Ambang peringatan dini: sisa < 20% kuota (spesifikasi W2).
     */
    private const LOW_BALANCE_RATIO = 0.2;

    public function mount(CompanyContext $companyContext): void
    {
        $companyId = $companyContext->current();

        $tokenGate = new TokenQuotaGate($companyContext);
        $waGate = new WaGroupQuotaGate($companyContext);

        $this->tokenBalance = $tokenGate->getCurrentTokenBalance();
        $this->tokenQuota = $tokenGate->getMonthlyTokenQuota();
        $this->maxWaGroups = $waGate->maxAllowedGroups();

        $membership = $this->latestMembership($companyId);
        $this->isFreeTier = $membership === null;
        $this->planName = $membership?->plan?->name;

        // Peringatan dini: hanya bila masih ada sisa token dan kuota > 0.
        // Saldo 0 (habis/non-aktif) tidak pakai banner "menipis" (D-49).
        $this->lowBalance = $this->tokenQuota > 0
            && $this->tokenBalance > 0
            && $this->tokenBalance < self::LOW_BALANCE_RATIO * $this->tokenQuota;
    }

    public function render()
    {
        return view('livewire.settings.usage-overview');
    }

    /**
     * Membership terbaru untuk company aktif. Null berarti tier gratis
     * (termasuk jalur JSON tanpa tabel) — mencerminkan logika gate.
     */
    private function latestMembership(string $companyId): ?CompanyMembership
    {
        if (! Schema::hasTable('company_memberships')) {
            return null;
        }

        try {
            return CompanyMembership::with('plan')
                ->where('company_id', $companyId)
                ->latest('id')
                ->first();
        } catch (QueryException|Throwable) {
            return null;
        }
    }
}
