<?php

namespace Tests\Feature;

use App\Models\MembershipPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UR-04: kontrak seed plan produksi - idempoten + tidak menimpa harga yang
 * sudah diedit manual Bos (D-05: harga adalah data).
 */
class SeedPlansCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_creates_three_plans(): void
    {
        $this->artisan('bos:seed-plans')->assertExitCode(0);

        $this->assertSame(3, MembershipPlan::count());
        $this->assertTrue(MembershipPlan::where('slug', 'starter')->where('is_active', true)->exists());
        $this->assertTrue(MembershipPlan::where('slug', 'pro')->exists());
        $this->assertTrue(MembershipPlan::where('slug', 'enterprise')->exists());
    }

    public function test_seed_is_idempotent(): void
    {
        $this->artisan('bos:seed-plans');
        $this->artisan('bos:seed-plans');

        $this->assertSame(3, MembershipPlan::count());
    }

    public function test_seed_does_not_overwrite_manually_edited_price(): void
    {
        $this->artisan('bos:seed-plans');

        // Bos edit harga manual via DB/Super Admin
        MembershipPlan::where('slug', 'starter')->update(['monthly_price' => 123000]);

        $this->artisan('bos:seed-plans');

        $this->assertSame(123000.0, (float) MembershipPlan::where('slug', 'starter')->value('monthly_price'));
    }

    public function test_seed_force_overwrites(): void
    {
        $this->artisan('bos:seed-plans');
        MembershipPlan::where('slug', 'starter')->update(['monthly_price' => 123000]);

        $this->artisan('bos:seed-plans', ['--force' => true]);

        $this->assertSame(750000.0, (float) MembershipPlan::where('slug', 'starter')->value('monthly_price'));
    }
}
