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
        $bookingId = $context['record_id'] ?? null;
        if (! $bookingId) {
            throw new RuntimeException('Booking ID is missing in context.');
        }

        $booking = Booking::find($bookingId);
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
