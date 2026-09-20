<?php

namespace App\Livewire\Settings;

use App\Contracts\CompanyContext;
use App\Models\CompanyMembership;
use App\Models\HermesConversationContext;
use App\Services\Billing\TokenQuotaGate;
use App\Services\Billing\WaGroupQuotaGate;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * Tab "Penggunaan & Paket" (W2): indikator kuota + peringatan dini.
 *
 * Semua angka dibaca dari gate/config (D-60), bukan hardcode. Klien harus
 * bisa melihat sisa kuota SEBELUM habis (D-61); banner peringatan muncul
 * saat saldo < billing.usage.low_balance_ratio (default 20%).
 */
class UsageAndPlan extends Component
{
    public function render(
        CompanyContext $companyContext,
        TokenQuotaGate $tokens,
        WaGroupQuotaGate $waGroups,
    ): View {
        $companyId = $companyContext->current();

        $tokenQuota = $tokens->getMonthlyTokenQuota();
        $tokenBalance = $tokens->getCurrentTokenBalance();
        $maxWaGroups = $waGroups->maxAllowedGroups();

        $membership = $this->latestMembership($companyId);

        $lowRatio = (float) config('billing.usage.low_balance_ratio', 0.2);
        // Peringatan dini hanya bila masih ADA sisa: saldo 0 sudah "habis"
        // (D-48), bukan "menipis" — jangan tampilkan banner (tersisa 0%).
        $isBalanceLow = $tokenQuota > 0
            && $tokenBalance > 0
            && ($tokenBalance / $tokenQuota) < $lowRatio;

        return view('livewire.settings.usage-and-plan', [
            'tokenQuota' => $tokenQuota,
            'tokenBalance' => $tokenBalance,
            'tokenPercent' => $tokenQuota > 0
                ? (int) round(max(0, min(1, $tokenBalance / $tokenQuota)) * 100)
                : 0,
            'isBalanceLow' => $isBalanceLow,
            'maxWaGroups' => $maxWaGroups,
            'waGroupsUsed' => $this->waGroupsUsed($companyId),
            'isFreeTier' => $membership === null,
            // Tier label dari membership -> plan (D-52), bukan dari preset/nama usaha (D-31).
            'tierLabel' => $membership?->plan?->name ?? 'Tier Gratis',
            'membershipInactive' => $membership !== null && $membership->status !== 'active',
            'plansUrl' => (string) config('billing.usage.plans_url', ''),
        ]);
    }

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
        } catch (QueryException) {
            // Fail-closed (D-49): kegagalan baca = tier gratis tampil tanpa angka palsu.
            return null;
        }
    }

    /**
     * ponytail: belum ada tabel registrasi grup WA (W-lane lain); satu-satunya
     * jejak persisten adalah context percakapan grup (chat_id JID '@g.us').
     * Ganti ke registry grup saat lane itu mendarat.
     */
    private function waGroupsUsed(string $companyId): int
    {
        if (! Schema::hasTable('hermes_conversation_contexts')) {
            return 0;
        }

        try {
            return HermesConversationContext::query()
                ->where('channel', 'whatsapp')
                ->where('chat_id', 'like', '%@g.us')
                ->where('active_company_id', $companyId)
                ->count();
        } catch (QueryException) {
            return 0;
        }
    }
}
