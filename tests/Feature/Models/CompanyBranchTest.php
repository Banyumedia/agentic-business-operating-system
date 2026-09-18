<?php

namespace Tests\Feature\Models;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyBranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_company_relationship(): void
    {
        $parent = Company::factory()->create(['name' => 'HQ']);
        $branch1 = Company::factory()->create(['name' => 'Branch 1', 'parent_company_id' => $parent->id]);
        $branch2 = Company::factory()->create(['name' => 'Branch 2', 'parent_company_id' => $parent->id]);

        $this->assertEquals('HQ', $branch1->parentCompany->name);
        $this->assertCount(2, $parent->branches);
        $this->assertTrue($parent->branches->contains($branch1));
        $this->assertTrue($parent->branches->contains($branch2));
    }
}
