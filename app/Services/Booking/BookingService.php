<?php

namespace App\Services\Booking;

use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BookingService
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function create(array $data): Booking
    {
        return DB::transaction(function () use ($data) {
            $companyId = $data['company_id'];
            $resourceId = $data['resource_id'];
            $startsAt = $data['starts_at'];
            $endsAt = $data['ends_at'];

            $this->assertNoOverlap($companyId, $resourceId, $startsAt, $endsAt);

            return Booking::create($data);
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertNoOverlap(int $companyId, int $resourceId, string $startsAt, string $endsAt, ?int $ignoreBookingId = null): void
    {
        $query = Booking::where('company_id', $companyId)
            ->where('resource_id', $resourceId)
            ->whereNotIn('stage', ['cancelled', 'returned'])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($ignoreBookingId !== null) {
            $query->where('id', '!=', $ignoreBookingId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException("Booking overlap detected for resource {$resourceId} between {$startsAt} and {$endsAt}.");
        }
    }
}
