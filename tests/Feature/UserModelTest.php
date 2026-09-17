<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_many_companies_as_owner(): void
    {
        $user = User::factory()->create();

        Company::factory()->count(2)->create(['owner_user_id' => $user->id]);

        $this->assertCount(2, $user->companies);
    }

    public function test_user_belongs_to_current_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $user->update(['current_company_id' => $company->id]);

        $this->assertInstanceOf(Company::class, $user->currentCompany);
        $this->assertEquals($company->id, $user->currentCompany->id);
    }
}
