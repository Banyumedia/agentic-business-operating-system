<?php

namespace Tests\Feature;

use App\Models\BusinessIdentity;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_multiple_companies_and_one_current_company(): void
    {
        $user = User::factory()->create();

        $company1 = Company::factory()->create(['owner_user_id' => $user->id]);
        $company2 = Company::factory()->create(['owner_user_id' => $user->id]);

        $user->update(['current_company_id' => $company1->id]);

        $this->assertCount(2, $user->companies);
        $this->assertTrue($user->companies->contains($company1));
        $this->assertTrue($user->companies->contains($company2));

        $this->assertEquals($company1->id, $user->currentCompany->id);
    }

    public function test_company_has_business_identity(): void
    {
        $company = Company::factory()->create();

        $identity = BusinessIdentity::factory()->create([
            'company_id' => $company->id,
            'legal_name' => 'PT Test',
            'tax_mode' => 'taxable',
            'tax_rate' => 11.00,
        ]);

        $this->assertEquals($company->id, $identity->company->id);

        $companyIdentity = $company->defaultIdentity;
        $this->assertNotNull($companyIdentity);
        $this->assertEquals('PT Test', $companyIdentity->legal_name);
        $this->assertEquals('taxable', $companyIdentity->tax_mode);
        $this->assertEquals(11.00, $companyIdentity->tax_rate);
    }

    public function test_users_table_has_whatsapp_columns(): void
    {
        $user = User::factory()->create([
            'wa_number' => '628123456789',
            'wa_is_verified' => true,
        ]);

        $this->assertEquals('628123456789', $user->wa_number);
        $this->assertTrue($user->wa_is_verified);
    }
}
