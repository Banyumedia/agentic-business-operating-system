<?php

namespace App\Services\Dashboard;

use UnexpectedValueException;

class CashFlowCalculator
{
    // Above 2^46, adjacent IEEE-754 values are farther than one cent apart.
    // Larger valid DECIMAL values must arrive as strings (as Eloquent does).
    private const MAX_EXACT_FLOAT_UNITS = 70368744177663.99;

    /**
     * Satu kontrak kalkulasi arus kas untuk KPI dan widget. Nominal diubah
     * menjadi sen sebagai integer sebelum agregasi agar DECIMAL(18,2) tidak
     * kehilangan presisi. Data invalid atau agregat overflow ditolak.
     *
     * @param  iterable<array<string, mixed>>  $entries
     * @return array{incoming_cents: int, outgoing_cents: int, balance_cents: int}
     */
    public function calculate(iterable $entries): array
    {
        $incoming = 0;
        $outgoing = 0;

        foreach ($entries as $entry) {
            $direction = $entry['direction'] ?? null;
            $amount = $entry['amount'] ?? null;

            if (! in_array($direction, ['in', 'out'], true)) {
                throw new UnexpectedValueException('Arah arus kas tidak valid.');
            }

            $cents = $this->toCents($amount);

            if ($direction === 'in') {
                $incoming = $this->addWithoutOverflow($incoming, $cents);
            } else {
                $outgoing = $this->addWithoutOverflow($outgoing, $cents);
            }
        }

        return [
            'incoming_cents' => $incoming,
            'outgoing_cents' => $outgoing,
            'balance_cents' => $incoming - $outgoing,
        ];
    }

    public function formatRupiah(int $cents): string
    {
        if ($cents === PHP_INT_MIN) {
            throw new UnexpectedValueException('Nominal arus kas melampaui batas aman.');
        }

        $negative = $cents < 0;
        $absolute = abs($cents);
        $units = intdiv($absolute, 100);
        $fraction = $absolute % 100;
        $formatted = number_format($units, 0, ',', '.');

        if ($fraction !== 0) {
            $formatted .= ','.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
        }

        return 'Rp '.($negative ? '-' : '').$formatted;
    }

    private function toCents(mixed $amount): int
    {
        if (is_int($amount)) {
            $normalized = (string) $amount;
        } elseif (is_string($amount)) {
            $normalized = $amount;
        } elseif (is_float($amount) && is_finite($amount) && $amount <= self::MAX_EXACT_FLOAT_UNITS) {
            $scaled = $amount * 100;
            if (abs($scaled - round($scaled)) > 0.0000001) {
                throw new UnexpectedValueException('Nominal arus kas tidak valid.');
            }

            $normalized = number_format($amount, 2, '.', '');
        } else {
            throw new UnexpectedValueException('Nominal arus kas tidak valid.');
        }

        if (! preg_match('/^(0|[1-9]\d*)(?:\.(\d{1,2}))?$/', $normalized, $matches)) {
            throw new UnexpectedValueException('Nominal arus kas tidak valid.');
        }

        $whole = $matches[1];
        $fraction = str_pad($matches[2] ?? '', 2, '0');
        $limit = (string) PHP_INT_MAX;
        $digits = $whole.$fraction;
        $digits = ltrim($digits, '0') ?: '0';

        if (strlen($digits) > 18
            || strlen($digits) > strlen($limit)
            || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            throw new UnexpectedValueException('Nominal arus kas melampaui batas aman.');
        }

        return (int) $digits;
    }

    private function addWithoutOverflow(int $total, int $amount): int
    {
        if ($amount > PHP_INT_MAX - $total) {
            throw new UnexpectedValueException('Total arus kas melampaui batas aman.');
        }

        return $total + $amount;
    }
}
