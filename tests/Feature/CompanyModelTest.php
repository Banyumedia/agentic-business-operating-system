<?php

namespace Tests\Feature;

use App\Models\BusinessIdentity;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_has_owner(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $this->assertInstanceOf(User::class, $company->owner);
        $this->assertEquals($owner->id, $company->owner->id);
    }

    public function test_company_has_many_business_identities(): void
    {
        $company = Company::factory()->create();

        $identity1 = BusinessIdentity::create([
            'company_id' => $company->id,
            'legal_name' => 'PT Test 1',
            'is_default' => true,
        ]);

        $identity2 = BusinessIdentity::create([
            'company_id' => $company->id,
            'legal_name' => 'PT Test 2',
            'is_default' => false,
        ]);

        $this->assertCount(2, $company->identities);
        $this->assertEquals($identity1->id, $company->defaultIdentity->id);
    }
}
