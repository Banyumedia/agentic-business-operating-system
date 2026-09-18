<?php

namespace Tests\Feature\CompanyGroup;

use App\Models\CashEntry;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Contact;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\CompanyGroup\GroupReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregate_data_includes_root_and_branches_but_not_unrelated_companies(): void
    {
        $owner = User::factory()->create();
        $root = Company::factory()->create(['owner_user_id' => $owner->id, 'name' => 'Root']);
        $branch = Company::factory()->create(['owner_user_id' => $owner->id, 'parent_company_id' => $root->id, 'name' => 'Branch']);
        $unrelated = Company::factory()->create(['name' => 'Unrelated']);

        Contact::factory()->count(3)->create(['company_id' => $root->id]);
        Contact::factory()->count(2)->create(['company_id' => $branch->id]);
        Contact::factory()->count(5)->create(['company_id' => $unrelated->id]);

        CashEntry::factory()->count(1)->create(['company_id' => $root->id]);
        CashEntry::factory()->count(4)->create(['company_id' => $unrelated->id]);

        $service = new GroupReportService;
        $aggregate = $service->getAggregate($root);

        $this->assertEquals(5, $aggregate['total_contacts']); // 3 + 2
        $this->assertEquals(1, $aggregate['total_cash_entries']); // 1 + 0
    }

    public function test_branch_does_not_inherit_parent_membership(): void
    {
        $owner = User::factory()->create();
        $root = Company::factory()->create(['owner_user_id' => $owner->id]);
        $plan = MembershipPlan::factory()->create();

        $parentMembership = CompanyMembership::create([
            'company_id' => $root->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'current_token_balance' => 1000,
            'monthly_token_quota' => 1000,
            'emergency_token_quota' => 0,
            'emergency_balance' => 0,
            'max_wa_groups' => 1,
        ]);

        $branch = Company::factory()->create(['owner_user_id' => $owner->id, 'parent_company_id' => $root->id]);

        // Branch starts with no membership
        $this->assertNull(CompanyMembership::where('company_id', $branch->id)->first());

        // Can create its own membership
        $branchMembership = CompanyMembership::create([
            'company_id' => $branch->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'current_token_balance' => 500,
            'monthly_token_quota' => 500,
            'emergency_token_quota' => 0,
            'emergency_balance' => 0,
            'max_wa_groups' => 1,
        ]);

        $this->assertEquals(1000, $parentMembership->fresh()->current_token_balance);
        $this->assertEquals(500, $branchMembership->current_token_balance);
    }
}
