<?php

namespace Tests\Feature;

use App\Models\CompanyMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_company_membership()
    {
        $membership = CompanyMembership::factory()->create();
        $this->assertDatabaseHas('company_memberships', ['id' => $membership->id]);
    }
}
