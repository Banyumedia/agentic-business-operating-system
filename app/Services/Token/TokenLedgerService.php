<?php

namespace App\Services\Token;

use App\Models\CompanyMembership;
use App\Models\TokenLedgerEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TokenLedgerService
{
    public function recordTransaction(
        CompanyMembership $membership,
        string $direction,
        int $amount,
        string $source,
        string $idempotencyKey,
        array $metadata = []
    ): TokenLedgerEntry {
        if (! in_array($direction, ['credit', 'debit'])) {
            throw new InvalidArgumentException('Direction must be credit or debit');
        }

        if ($amount < 0) {
            throw new InvalidArgumentException('Amount must be positive');
        }

        return DB::transaction(function () use ($membership, $direction, $amount, $source, $idempotencyKey, $metadata) {
            // Lock the membership row
            $membership = CompanyMembership::where('id', $membership->id)->lockForUpdate()->firstOrFail();

            // Check if already processed
            $existing = TokenLedgerEntry::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            if ($direction === 'debit') {
                if ($membership->current_token_balance >= $amount) {
                    $newBalance = $membership->current_token_balance - $amount;
                    $membership->current_token_balance = $newBalance;
                } elseif ($membership->current_token_balance <= 0 && $membership->emergency_balance >= $amount) {
                    // deduct from emergency balance
                    $newBalance = $membership->emergency_balance - $amount;
                    $membership->emergency_balance = $newBalance;
                } else {
                    throw new \Exception('Insufficient token balance');
                }
            } else {
                $newBalance = $membership->current_token_balance + $amount;
                $membership->current_token_balance = $newBalance;
            }

            $membership->save();

            return TokenLedgerEntry::create([
                'company_id' => $membership->company_id,
                'company_membership_id' => $membership->id,
                'direction' => $direction,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'source' => $source,
                'idempotency_key' => $idempotencyKey,
                'metadata' => $metadata,
            ]);
        });
    }
}
