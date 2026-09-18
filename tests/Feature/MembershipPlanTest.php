<?php

namespace Tests\Feature;

use App\Models\MembershipPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_membership_plan()
    {
        $plan = MembershipPlan::factory()->create();
        $this->assertDatabaseHas('membership_plans', ['id' => $plan->id]);
    }
}
