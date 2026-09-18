<?php

namespace App\Services\Addons\Loyalty;

use App\Models\LoyaltyPoint;
use App\Models\LoyaltyRule;
use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Menghitung poin loyalty dari transaksi sesuai aturan PER-COMPANY (D-59).
 * Tidak ada rumus hardcode: rasio, mode, dan expiry seluruhnya dibaca dari
 * `loyalty_rules` milik company. Company tanpa rule aktif tidak menghasilkan
 * poin apa pun (fail-closed).
 */
class LoyaltyPointsCalculator
{
    public function awardForOrder(Order $order): ?LoyaltyPoint
    {
        $rule = LoyaltyRule::query()
            ->where('company_id', $order->company_id)
            ->where('is_active', true)
            ->first();

        if (! $rule) {
            return null;
        }

        $points = $this->calculatePoints($rule, $order);

        if ($points <= 0) {
            return null;
        }

        return LoyaltyPoint::create([
            'company_id' => $order->company_id,
            'contact_id' => $order->contact_id,
            'order_id' => $order->id,
            'points' => $points,
            'source_type' => 'earned_'.$this->modeLabel($rule),
            'notes' => "Poin dari order #{$order->id}",
            'expires_at' => $this->expiresAt($rule),
        ]);
    }

    private function calculatePoints(LoyaltyRule $rule, Order $order): int
    {
        $points = 0;

        // Mode nominal: aktif hanya bila company mengisi nominal_per_point.
        if ($rule->nominal_per_point !== null && $rule->nominal_per_point > 0) {
            $points += intdiv((int) $order->grand_total, (int) $rule->nominal_per_point);
        }

        // Mode per-item/kategori: aktif hanya bila company mengisi mapping.
        if (! empty($rule->item_point_rates)) {
            foreach ($order->lines as $line) {
                $rate = $rule->item_point_rates[(string) $line->item_id] ?? null;
                if ($rate !== null) {
                    $points += ((int) $rate) * (int) $line->qty;
                }
            }
        }

        return $points;
    }

    private function modeLabel(LoyaltyRule $rule): string
    {
        $hasNominal = $rule->nominal_per_point !== null && $rule->nominal_per_point > 0;
        $hasItem = ! empty($rule->item_point_rates);

        return match (true) {
            $hasNominal && $hasItem => 'both',
            $hasItem => 'item',
            default => 'nominal',
        };
    }

    private function expiresAt(LoyaltyRule $rule): ?Carbon
    {
        if ($rule->expiry_months === null) {
            return null;
        }

        return Carbon::now()->addMonths((int) $rule->expiry_months);
    }

    /**
     * Saldo poin aktif (belum expired) milik satu contact di satu company.
     */
    public function activeBalance(int $companyId, int $contactId): int
    {
        return (int) LoyaltyPoint::query()
            ->where('company_id', $companyId)
            ->where('contact_id', $contactId)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->sum('points');
    }
}
