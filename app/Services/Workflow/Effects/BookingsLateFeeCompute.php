<?php

namespace App\Services\Workflow\Effects;

use App\Models\Booking;
use Carbon\Carbon;
use RuntimeException;

class BookingsLateFeeCompute implements WorkflowEffect
{
    public function key(): string
    {
        return 'bookings.late_fee.compute';
    }

    public function execute(array $context): array
    {
        $record = $context['record'] ?? null;
        $companyId = $context['company'] ?? null;
        if (($context['entity'] ?? null) !== 'bookings' || ! $record instanceof Booking || ! $companyId) {
            throw new RuntimeException('Booking record or company is missing in context.');
        }

        $booking = Booking::query()
            ->whereKey($record->getKey())
            ->where('company_id', $companyId)
            ->first();
        if (! $booking) {
            throw new RuntimeException('Booking not found.');
        }

        $actualEndsAt = $booking->actual_ends_at ? Carbon::parse($booking->actual_ends_at) : Carbon::now();
        $endsAt = Carbon::parse($booking->ends_at);

        if ($actualEndsAt->lessThanOrEqualTo($endsAt)) {
            return [
                'effect' => $this->key(),
                'status' => 'success',
                'units' => 0,
                'total' => 0,
            ];
        }

        $lateMinutes = $endsAt->diffInMinutes($actualEndsAt);
        $units = (int) ceil($lateMinutes / 60);
        $lateFeeTotal = $units * $booking->late_fee_per_unit;

        $booking->late_fee_total = $lateFeeTotal;
        $booking->save();

        return [
            'effect' => $this->key(),
            'status' => 'success',
            'units' => $units,
            'total' => $lateFeeTotal,
        ];
    }
}
