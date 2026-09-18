<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\HermesProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class HermesProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_have_one_profile_with_multiple_companies(): void
    {
        $owner = User::factory()->create();

        $company1 = Company::factory()->create(['owner_user_id' => $owner->id]);
        $company2 = Company::factory()->create(['owner_user_id' => $owner->id]);

        $profile = HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'type' => 'primary',
        ]);

        $profile->companies()->attach($company1->id, ['role' => 'owner', 'is_default' => true]);
        $profile->companies()->attach($company2->id, ['role' => 'owner', 'is_default' => false]);

        $this->assertEquals(1, HermesProfile::count());
        $this->assertEquals(2, $profile->companies()->count());
        $this->assertEquals($company1->id, $profile->companies()->wherePivot('is_default', true)->first()->id);
    }

    public function test_model_rejects_multiple_primary_profiles_for_same_owner(): void
    {
        $owner = User::factory()->create();

        HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'type' => 'primary',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An owner can only have one primary profile.');

        HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'type' => 'primary',
        ]);
    }

    public function test_model_rejects_addon_profile_without_billing_addon_id(): void
    {
        $owner = User::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An addon profile must have a billing_addon_id.');

        HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'type' => 'addon',
            'billing_addon_id' => null,
        ]);
    }

    public function test_owner_can_have_primary_and_addon_profiles(): void
    {
        $owner = User::factory()->create();

        HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'type' => 'primary',
        ]);

        HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'type' => 'addon',
            'billing_addon_id' => 123,
        ]);

        $this->assertEquals(2, HermesProfile::where('owner_user_id', $owner->id)->count());
    }
}
