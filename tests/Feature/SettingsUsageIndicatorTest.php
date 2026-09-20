<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * W2 jalur JSON (tanpa tabel memberships): tab "Penggunaan & Paket"
 * menampilkan indikator tier gratis config-driven (D-60, U-05).
 * Jalur DB/membership diuji di SettingsUsageMembershipTest.
 *
 * Semua angka dibaca dari gate/config, tidak ada hardcode di UI.
 */
class SettingsUsageIndicatorTest extends TestCase
{
    public function test_usage_tab_shows_free_tier_indicators_from_config(): void
    {
        $this->assertFalse(Schema::hasTable('company_memberships'));

        $quota = (int) config('billing.free_tier.token_quota', 500);
        $maxGroups = (int) config('billing.free_tier.max_wa_groups', 1);

        $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'staff'])
            ->get('/app/settings/usage')
            ->assertOk()
            ->assertSee('Tier Gratis')
            ->assertSee(number_format($quota, 0, ',', '.'))
            ->assertSee('Sisa token', false)
            ->assertSee('Grup WhatsApp', false)
            ->assertSee('maksimal '.$maxGroups.' grup', false)
            ->assertDontSee('Sisa kuota AI menipis');
    }

    public function test_usage_tab_upsell_is_subtle_and_has_no_dead_plans_link(): void
    {
        $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'staff'])
            ->get('/app/settings/usage')
            ->assertOk()
            // Ajakan upgrade halus (U-05: bukan CTA jualan menakutkan).
            ->assertSee('Butuh kuota lebih besar?')
            // Tautan paket graceful: route W1 belum ada -> tidak dirender
            // sebagai link mati.
            ->assertDontSee('href="/app/plans"', false);
    }

    public function test_component_reads_numbers_from_gates_not_hardcode(): void
    {
        // Ubah config -> UI ikut (bukti tidak hardcode).
        config(['billing.free_tier.token_quota' => 777, 'billing.free_tier.max_wa_groups' => 3]);

        $this->withSession(['active_company' => 'bengkel-arka', 'company_role' => 'staff'])
            ->get('/app/settings/usage')
            ->assertOk()
            ->assertSee(number_format(777, 0, ',', '.'))
            ->assertSee('maksimal 3 grup', false);
    }
}
