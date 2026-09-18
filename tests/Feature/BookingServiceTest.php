<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Company;
use App\Models\Project;
use App\Models\Resource;
use App\Services\Booking\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Resource $resource;

    private BookingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['name' => 'Test Company']);
        $this->resource = Resource::factory()->create([
            'company_id' => $this->company->id,
            'type' => 'room',
            'name' => 'Meeting Room A',
        ]);

        $this->service = app(BookingService::class);
    }

    public function test_create_booking_successfully(): void
    {
        $startsAt = Carbon::now()->addDay()->setHour(10)->setMinute(0);
        $endsAt = $startsAt->copy()->addHours(2);

        $booking = $this->service->create([
            'company_id' => $this->company->id,
            'resource_id' => $this->resource->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'stage' => 'draft',
        ]);

        $this->assertInstanceOf(Booking::class, $booking);
        $this->assertEquals($this->resource->id, $booking->resource_id);
    }

    public function test_overlap_same_resource_active_stage_throws_exception(): void
    {
        $startsAt = Carbon::now()->addDay()->setHour(10)->setMinute(0);
        $endsAt = $startsAt->copy()->addHours(2);

        // Existing booking: 10:00 to 12:00
        Booking::create([
            'company_id' => $this->company->id,
            'resource_id' => $this->resource->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'stage' => 'confirmed',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Booking overlap detected/');

        // Attempt booking overlapping: 11:00 to 13:00
        $this->service->create([
            'company_id' => $this->company->id,
            'resource_id' => $this->resource->id,
            'starts_at' => $startsAt->copy()->addHours(1)->toDateTimeString(),
            'ends_at' => $endsAt->copy()->addHours(1)->toDateTimeString(),
            'stage' => 'draft',
        ]);
    }

    public function test_overlap_existing_cancelled_returned_allowed(): void
    {
        $startsAt = Carbon::now()->addDay()->setHour(10)->setMinute(0);
        $endsAt = $startsAt->copy()->addHours(2);

        // Existing cancelled booking: 10:00 to 12:00
        Booking::create([
            'company_id' => $this->company->id,
            'resource_id' => $this->resource->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'stage' => 'cancelled',
        ]);

        // Attempt booking exactly same time should succeed
        $booking = $this->service->create([
            'company_id' => $this->company->id,
            'resource_id' => $this->resource->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'stage' => 'draft',
        ]);

        $this->assertNotNull($booking->id);

        // Now try with 'returned' status
        Booking::create([
            'company_id' => $this->company->id,
            'resource_id' => $this->resource->id,
            'starts_at' => $startsAt->copy()->addDays(1)->toDateTimeString(),
            'ends_at' => $endsAt->copy()->addDays(1)->toDateTimeString(),
            'stage' => 'returned',
        ]);

        $booking2 = $this->service->create([
            'company_id' => $this->company->id,
            'resource_id' => $this->resource->id,
            'starts_at' => $startsAt->copy()->addDays(1)->toDateTimeString(),
            'ends_at' => $endsAt->copy()->addDays(1)->toDateTimeString(),
            'stage' => 'draft',
        ]);

        $this->assertNotNull($booking2->id);
    }

    public function test_tenant_isolation_company_ab_separated(): void
    {
        $companyB = Company::factory()->create(['name' => 'Company B']);
        $resourceB = Resource::factory()->create([
            'company_id' => $companyB->id,
            'type' => 'room',
            'name' => 'Meeting Room B',
        ]);

        $startsAt = Carbon::now()->addDay()->setHour(10)->setMinute(0);
        $endsAt = $startsAt->copy()->addHours(2);

        // Existing booking in Company A for Resource A
        Booking::create([
            'company_id' => $this->company->id,
            'resource_id' => $this->resource->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'stage' => 'confirmed',
        ]);

        // Company A + resource A at same slot must be rejected.
        try {
            $this->service->assertNoOverlap(
                $this->company->id,
                $this->resource->id,
                $startsAt->toDateTimeString(),
                $endsAt->toDateTimeString()
            );
            $this->fail('Expected overlap exception for company A / resource A.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Booking overlap detected', $exception->getMessage());
        }

        // Company B + resource B at same slot must pass (tenant isolation).
        $this->service->assertNoOverlap(
            $companyB->id,
            $resourceB->id,
            $startsAt->toDateTimeString(),
            $endsAt->toDateTimeString()
        );

        $bookingB = $this->service->create([
            'company_id' => $companyB->id,
            'resource_id' => $resourceB->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'stage' => 'draft',
        ]);

        $this->assertNotNull($bookingB->id);
        $this->assertEquals($companyB->id, $bookingB->company_id);
    }

    public function test_booking_project_id_nullable_terisi_valid(): void
    {
        $startsAt = Carbon::now()->addDay()->setHour(10)->setMinute(0);
        $endsAt = $startsAt->copy()->addHours(2);

        // Project ID nullable (already tested above, but explicitly testing here)
        $bookingNoProject = $this->service->create([
            'company_id' => $this->company->id,
            'resource_id' => $this->resource->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'project_id' => null,
            'stage' => 'draft',
        ]);

        $this->assertNull($bookingNoProject->project_id);

        // Valid project ID
        $project = Project::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'EO Rundown Project',
            'type' => 'project',
        ]);

        $bookingWithProject = $this->service->create([
            'company_id' => $this->company->id,
            'resource_id' => $this->resource->id,
            'starts_at' => $startsAt->copy()->addDays(1)->toDateTimeString(),
            'ends_at' => $endsAt->copy()->addDays(1)->toDateTimeString(),
            'project_id' => $project->id,
            'stage' => 'draft',
            'type' => 'rundown_item',
        ]);

        $this->assertEquals($project->id, $bookingWithProject->project_id);
    }
}
