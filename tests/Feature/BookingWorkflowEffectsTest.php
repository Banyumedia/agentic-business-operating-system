<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\PresetSource;
use App\Models\Booking;
use App\Models\BusinessPreset;
use App\Models\Company;
use App\Models\Resource;
use App\Models\WorkflowDefinition;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Preset\EloquentPresetSource;
use App\Services\Workflow\Effects\BookingsDepositCollect;
use App\Services\Workflow\Effects\BookingsDepositSettle;
use App\Services\Workflow\Effects\BookingsLateFeeCompute;
use App\Services\Workflow\WorkflowEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingWorkflowEffectsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Resource $resource;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['business_preset' => 'clinic']);

        BusinessPreset::create([
            'key' => 'clinic',
            'name' => 'Clinic',
            'tier' => 'pro',
            'definition' => [
                'capabilities' => [
                    'bookings' => true,
                    'bookings.deposit' => true,
                ],
                'workflows' => [
                    'bookings' => [
                        'entity' => 'bookings',
                        'stages' => [
                            ['code' => 'draft', 'label' => 'Draft'],
                            ['code' => 'confirmed', 'label' => 'Confirmed'],
                        ],
                        'terminal' => [],
                        'transitions' => [
                            [
                                'from' => 'draft',
                                'to' => 'confirmed',
                                'label' => 'Confirm',
                                'requires_approval' => false,
                                'roles' => ['owner', 'staff'],
                                'effects' => ['bookings.deposit.collect'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->resource = Resource::factory()->create(['company_id' => $this->company->id]);

        $startsAt = Carbon::now();
        $endsAt = $startsAt->copy()->addHours(2);

        $this->booking = Booking::factory()->create([
            'company_id' => $this->company->id,
            'resource_id' => $this->resource->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'stage' => 'draft',
            'deposit_amount' => 150000,
            'late_fee_per_unit' => 50000,
        ]);
    }

    public function test_late_fee_compute_hitung_benar_saat_terlambat(): void
    {
        $effect = app(BookingsLateFeeCompute::class);

        // Not late
        $this->booking->update(['actual_ends_at' => $this->booking->ends_at->copy()->subMinutes(10)]);
        $result = $effect->execute([
            'entity' => 'bookings',
            'record' => $this->booking,
            'company' => (string) $this->company->id,
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(0, $result['units']);
        $this->assertEquals(0, $result['total']);

        // Refresh booking to check DB changes
        $this->booking->refresh();
        $this->assertEquals(0, $this->booking->late_fee_total);

        // Late by 65 minutes (ceil(65/60) = 2 units)
        $this->booking->update(['actual_ends_at' => $this->booking->ends_at->copy()->addMinutes(65)]);
        $result2 = $effect->execute([
            'entity' => 'bookings',
            'record' => $this->booking,
            'company' => (string) $this->company->id,
        ]);

        $this->assertEquals('success', $result2['status']);
        $this->assertEquals(2, $result2['units']);
        $this->assertEquals(100000, $result2['total']);

        $this->booking->refresh();
        $this->assertEquals(100000, $this->booking->late_fee_total);
    }

    public function test_late_fee_compute_rejects_booking_from_another_company(): void
    {
        $effect = app(BookingsLateFeeCompute::class);
        $foreignCompany = Company::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Booking not found.');

        $effect->execute([
            'entity' => 'bookings',
            'record' => $this->booking,
            'company' => (string) $foreignCompany->id,
        ]);
    }

    public function test_deposit_collect_gagal_deposit_0_sukses_jika_lebih(): void
    {
        $effect = app(BookingsDepositCollect::class);

        // Success condition
        $result = $effect->execute([
            'deposit_amount' => 150000,
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(150000, $result['amount']);

        // Failed condition (0)
        $resultZero = $effect->execute([
            'deposit_amount' => 0,
        ]);

        $this->assertEquals('failed', $resultZero['status']);

        // Failed condition (negative)
        $resultNegative = $effect->execute([
            'deposit_amount' => -100,
        ]);

        $this->assertEquals('failed', $resultNegative['status']);
    }

    public function test_deposit_settle_return_sukses_sesuai_deposit(): void
    {
        $effect = app(BookingsDepositSettle::class);

        $result = $effect->execute([
            'deposit_amount' => 150000,
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(150000, $result['settled_amount']);
    }

    public function test_workflow_engine_rejects_deposit_effect_without_amount_context(): void
    {
        // Define workflow
        WorkflowDefinition::create([
            'company_id' => $this->company->id,
            'entity' => 'bookings',
            'version' => 1,
            'is_active' => true,
            'definition' => [
                'stages' => [
                    ['code' => 'draft', 'label' => 'Draft'],
                    ['code' => 'confirmed', 'label' => 'Confirmed'],
                ],
                'transitions' => [
                    'draft' => [
                        'confirmed' => [
                            'label' => 'Confirm',
                            'requires_approval' => false,
                            'effects' => ['bookings.deposit.collect'],
                        ],
                    ],
                ],
            ],
        ]);

        // Use EloquentCompanyContext which relies on DB
        $companyContext = app(EloquentCompanyContext::class);
        $companyContext->setCurrent((string) $this->company->id);
        $this->app->instance(CompanyContext::class, $companyContext);

        $this->app->instance(PresetSource::class, app(EloquentPresetSource::class));

        // Re-resolve workflow engine so it gets the mocked context and preset source
        $engine = app(WorkflowEngine::class);

        try {
            $engine->transition($this->booking, 'confirmed', 'owner');
            $this->fail('Deposit tanpa amount context harus membatalkan transisi.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('bookings.deposit.collect', $exception->getMessage());
        }

        $this->booking->refresh();
        $this->assertEquals('draft', $this->booking->stage);
        $this->assertDatabaseMissing('workflow_transitions_log', [
            'entity' => 'bookings',
            'entity_id' => (string) $this->booking->id,
        ]);
    }
}
