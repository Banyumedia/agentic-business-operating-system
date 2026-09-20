<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Livewire\Settings\UsageOverview;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\MembershipPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * W2 jalur DB (membership): label paket, kuota membership, peringatan
 * dini < 20%, dan fail-closed D-49 untuk membership non-aktif.
 * Jalur JSON/tier gratis diuji di SettingsUsageIndicatorTest.
 */
class SettingsUsageMembershipTest extends TestCase
{
    use RefreshDatabase;

    private function bindContext(Company $company): void
    {
        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);
    }

    private function createMembership(Company $company, array $attributes): void
    {
        $plan = MembershipPlan::factory()->create([
            'name' => $attributes['plan_name'] ?? 'Paket Uji',
            'monthly_token_quota' => $attributes['monthly_token_quota'] ?? 10_000,
        ]);

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => $attributes['status'] ?? 'active',
            'monthly_token_quota' => $attributes['monthly_token_quota'] ?? 10_000,
            'current_token_balance' => $attributes['current_token_balance'] ?? 10_000,
            'max_wa_groups' => $attributes['max_wa_groups'] ?? 1,
        ]);
    }

    public function test_component_shows_active_plan_name_and_membership_quota(): void
    {
        $company = Company::factory()->create();
        $this->createMembership($company, [
            'plan_name' => 'Paket Bengkel Pro',
            'current_token_balance' => 9_500,
            'max_wa_groups' => 5,
        ]);
        $this->bindContext($company);

        $component = Livewire::test(UsageOverview::class)
            ->assertSet('tokenBalance', 9500)
            ->assertSet('tokenQuota', 10000)
            ->assertSet('maxWaGroups', 5)
            ->assertSet('planName', 'Paket Bengkel Pro')
            ->assertSet('isFreeTier', false)
            ->assertSet('lowBalance', false);

        $html = $component->html();
        $this->assertStringContainsString('Paket Bengkel Pro', $html);
        $this->assertStringNotContainsString('Tier Gratis', $html);
        $this->assertStringContainsString('maksimal 5 grup', $html);
        $this->assertStringNotContainsString('Sisa kuota AI menipis', $html);
    }

    public function test_low_balance_banner_appears_below_twenty_percent_and_is_polite(): void
    {
        $company = Company::factory()->create();
        $this->createMembership($company, ['current_token_balance' => 1_500]); // 15% < 20%
        $this->bindContext($company);

        $component = Livewire::test(UsageOverview::class)
            ->assertSet('lowBalance', true);

        $html = $component->html();
        // Banner lembut + bahasa awam + tidak menakutkan.
        $this->assertStringContainsString('Sisa kuota AI menipis', $html);
        $this->assertStringContainsString('asisten AI Anda masih jalan', $html);
        // Polite: warning-soft, bukan danger.
        $this->assertStringContainsString('bg-[var(--erp-warning-soft)]', $html);
    }

    public function test_banner_absent_at_or_above_twenty_percent(): void
    {
        $company = Company::factory()->create();
        $this->createMembership($company, ['current_token_balance' => 2_000]); // tepat 20%
        $this->bindContext($company);

        Livewire::test(UsageOverview::class)
            ->assertSet('lowBalance', false)
            ->assertDontSee('Sisa kuota AI menipis');
    }

    /**
     * D-49 fail-closed: membership non-aktif -> saldo 0 dan kuota grup 0,
     * tanpa banner peringatan dini, dan label bukan "Tier Gratis".
     */
    public function test_inactive_membership_shows_zero_balance_fail_closed(): void
    {
        $company = Company::factory()->create();
        $this->createMembership($company, [
            'plan_name' => 'Paket Lama',
            'status' => 'cancelled',
            'current_token_balance' => 9_000,
        ]);
        $this->bindContext($company);

        Livewire::test(UsageOverview::class)
            ->assertSet('tokenBalance', 0)
            ->assertSet('tokenQuota', 0)
            ->assertSet('maxWaGroups', 0)
            ->assertSet('lowBalance', false)
            ->assertSet('isFreeTier', false)
            ->assertSet('planName', 'Paket Lama')
            ->assertDontSee('Sisa kuota AI menipis')
            ->assertDontSee('Tier Gratis');
    }
}
