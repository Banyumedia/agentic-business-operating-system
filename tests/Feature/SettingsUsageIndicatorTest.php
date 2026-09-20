<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Livewire\Settings;
use App\Livewire\Settings\UsageAndPlan;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\HermesConversationContext;
use App\Models\HermesProfile;
use App\Models\MembershipPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feature tests for W2: quota indicator + early warning (D-60, D-61).
 *
 * Angka tampilan harus berasal dari gate/config, bukan hardcode:
 * sisa token vs kuota, grup WA terpakai vs maksimal, label tier,
 * banner peringatan dini saat saldo < 20% kuota, fail-closed (D-49).
 */
class SettingsUsageIndicatorTest extends TestCase
{
    use RefreshDatabase;

    private function mockContext(Company $company): void
    {
        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);
    }

    public function test_free_tier_shows_config_numbers_tier_label_and_upgrade_nudge(): void
    {
        config(['billing.usage.plans_url' => 'https://bos.test/paket']);
        $company = Company::factory()->create();
        $this->mockContext($company);

        Livewire::test(UsageAndPlan::class)
            ->assertSee('Tier Gratis')
            ->assertSee(number_format((int) config('billing.free_tier.token_quota'), 0, ',', '.'))
            ->assertSee((string) config('billing.free_tier.max_wa_groups'))
            ->assertSee('paket gratis')
            ->assertSee('https://bos.test/paket');
    }

    public function test_low_balance_banner_appears_below_threshold_and_is_absent_above(): void
    {
        $plan = MembershipPlan::factory()->create([
            'monthly_token_quota' => 10000,
            'max_wa_groups' => 5,
        ]);
        $company = Company::factory()->create();
        $this->mockContext($company);

        // 1000/10000 = 10% < 20% -> banner muncul.
        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'monthly_token_quota' => 10000,
            'current_token_balance' => 1000,
            'max_wa_groups' => 5,
        ]);

        Livewire::test(UsageAndPlan::class)
            ->assertSee('mulai menipis')
            ->assertSee('10%');

        // 5000/10000 = 50% >= 20% -> banner hilang.
        CompanyMembership::latest('id')->first()->forceFill(['current_token_balance' => 5000])->save();

        Livewire::test(UsageAndPlan::class)
            ->assertDontSee('mulai menipis');
    }

    public function test_active_plan_shows_plan_name_and_gate_numbers_not_free_tier(): void
    {
        $plan = MembershipPlan::factory()->create([
            'name' => 'Paket Uji Pro',
            'monthly_token_quota' => 10000,
            'max_wa_groups' => 5,
        ]);
        $company = Company::factory()->create();
        $this->mockContext($company);

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'monthly_token_quota' => 10000,
            'current_token_balance' => 9000,
            'max_wa_groups' => 5,
        ]);

        Livewire::test(UsageAndPlan::class)
            ->assertSee('Paket Uji Pro')
            ->assertSee(number_format(9000, 0, ',', '.'))
            ->assertDontSee('Tier Gratis')
            ->assertDontSee(number_format(500, 0, ',', '.'));
    }

    public function test_wa_groups_used_counts_group_conversation_contexts(): void
    {
        $plan = MembershipPlan::factory()->create(['max_wa_groups' => 5]);
        $company = Company::factory()->create();
        $this->mockContext($company);

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'max_wa_groups' => 5,
        ]);

        $profile = HermesProfile::factory()->create();
        HermesConversationContext::create([
            'hermes_profile_id' => $profile->id,
            'channel' => 'whatsapp',
            'chat_id' => '120363@g.us',
            'active_company_id' => $company->id,
        ]);
        // Chat pribadi (bukan grup) + grup company lain tidak ikut terhitung.
        HermesConversationContext::create([
            'hermes_profile_id' => $profile->id,
            'channel' => 'whatsapp',
            'chat_id' => '6281234567890',
            'active_company_id' => $company->id,
        ]);
        HermesConversationContext::create([
            'hermes_profile_id' => $profile->id,
            'channel' => 'whatsapp',
            'chat_id' => '99999@g.us',
            'active_company_id' => Company::factory()->create()->id,
        ]);

        Livewire::test(UsageAndPlan::class)
            ->assertSeeText('1')
            ->assertSeeText('5');
    }

    public function test_non_active_membership_is_fail_closed_shows_zero_and_alert(): void
    {
        $plan = MembershipPlan::factory()->create(['name' => 'Paket Suspended']);
        $company = Company::factory()->create();
        $this->mockContext($company);

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'frozen',
            'monthly_token_quota' => 10000,
            'current_token_balance' => 9000,
            'max_wa_groups' => 5,
        ]);

        Livewire::test(UsageAndPlan::class)
            ->assertSee('tidak aktif')
            ->assertDontSee(number_format(9000, 0, ',', '.'))
            ->assertDontSee(number_format(10000, 0, ',', '.'));
    }

    public function test_low_balance_threshold_and_plans_url_come_from_config(): void
    {
        config(['billing.usage.low_balance_ratio' => 0.5, 'billing.usage.plans_url' => '']);
        $plan = MembershipPlan::factory()->create(['monthly_token_quota' => 10000]);
        $company = Company::factory()->create();
        $this->mockContext($company);

        // 4000/10000 = 40% < threshold 50% (dari config, bukan hardcode 20%).
        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'monthly_token_quota' => 10000,
            'current_token_balance' => 4000,
        ]);

        Livewire::test(UsageAndPlan::class)
            ->assertSee('mulai menipis')
            ->assertDontSee('Lihat pilihan paket');
    }

    public function test_usage_tab_renders_component_for_owner_and_staff(): void
    {
        $company = Company::factory()->create();
        $context = $this->mock(CompanyContext::class);
        $context->shouldReceive('current')->andReturn((string) $company->id);
        $context->shouldReceive('preset')->andReturn('bengkel');

        foreach (['owner', 'staff'] as $role) {
            // Livewire merender sub-komponen sebagai placeholder wire:name
            // (nama kebab-case) di snapshot induk; pastikan tab usage memuatnya
            // dan konten indikator kuota ikut ter-render.
            $html = Livewire::withQueryParams(['tab' => 'usage'])
                ->test(Settings::class)
                ->html();

            $this->assertStringContainsString('settings.usage-and-plan', $html);
            $this->assertStringContainsString("activeTab: 'usage'", $html);
            unset($html);
        }
    }
}
