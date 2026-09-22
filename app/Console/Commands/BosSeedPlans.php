<?php

namespace App\Console\Commands;

use App\Models\MembershipPlan;
use Illuminate\Console\Command;

/**
 * UR-04: seed plan membership produksi (idempoten).
 *
 * Harga acuan COMMERCIAL_AND_AI_AGENTIC_SPEC 2.3 + kapabilitas D-52.
 * D-05: nilai adalah DATA, bukan hardcode - Bos dapat mengubah kapan saja
 * langsung di DB / Super Admin; command ini hanya menyediakan titik awal
 * dan tidak pernah menimpa harga yang sudah diubah manual (kecuali --force).
 */
class BosSeedPlans extends Command
{
    protected $signature = 'bos:seed-plans
        {--force : Timpa harga/kuota plan yang sudah ada (berbahaya: mengubah data)}';

    protected $description = 'Seed 3 plan membership (Starter/Pro/Enterprise) - idempoten, tidak menimpa harga yang sudah diedit';

    /** @var array<int, array<string, mixed>> */
    private const PLANS = [
        [
            'name' => 'Starter', 'slug' => 'starter',
            'monthly_price' => 750000, 'annual_price' => 7500000,
            'max_wa_groups' => 1, 'max_users' => 3,
            'monthly_token_quota' => 500000, 'emergency_token_quota' => 25000, 'trial_token_quota' => 50000,
            'features' => ['contacts', 'deals', 'scheduling', 'bookings', 'inventory', 'pos', 'quotations', 'finance.cashbook', 'hr.employees', 'approval_flow', 'system.ai_agent'],
        ],
        [
            'name' => 'Pro', 'slug' => 'pro',
            'monthly_price' => 2750000, 'annual_price' => 27500000,
            'max_wa_groups' => 3, 'max_users' => 10,
            'monthly_token_quota' => 3000000, 'emergency_token_quota' => 150000, 'trial_token_quota' => 50000,
            'features' => ['contacts', 'deals', 'projects', 'milestone_billing', 'scheduling', 'bookings', 'bookings.deposit', 'inventory', 'inventory.batch_expiry', 'pos', 'pos.tables', 'quotations', 'timesheet', 'finance.cashbook', 'hr.employees', 'hr.payroll', 'approval_flow', 'system.ai_agent'],
        ],
        [
            'name' => 'Enterprise', 'slug' => 'enterprise',
            'monthly_price' => 10000000, 'annual_price' => 100000000,
            'max_wa_groups' => 100, 'max_users' => 100,
            'monthly_token_quota' => 15000000, 'emergency_token_quota' => 750000, 'trial_token_quota' => 50000,
            'features' => ['contacts', 'deals', 'projects', 'projects.progress_billing', 'milestone_billing', 'scheduling', 'bookings', 'bookings.deposit', 'inventory', 'inventory.batch_expiry', 'inventory.bom', 'pos', 'pos.tables', 'quotations', 'timesheet', 'finance.cashbook', 'finance.accounting', 'hr.employees', 'hr.payroll', 'approval_flow', 'system.ai_agent', 'pharmacy.prescription', 'construction.retention'],
        ],
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        foreach (self::PLANS as $data) {
            $existing = MembershipPlan::query()->where('slug', $data['slug'])->first();

            if ($existing && ! $force) {
                $this->line("SKIP {$data['name']}: sudah ada (harga terpasang: Rp ".number_format((float) $existing->monthly_price, 0, ',', '.').'/bln).');

                continue;
            }

            if ($existing) {
                $existing->update($data);
                $this->info("UPDATE {$data['name']} (force).");

                continue;
            }

            MembershipPlan::create($data + ['is_active' => true]);
            $this->info("CREATE {$data['name']}: Rp ".number_format((float) $data['monthly_price'], 0, ',', '.').'/bln, '.count($data['features']).' kapabilitas.');
        }

        $total = MembershipPlan::query()->count();
        $this->info("Total plan aktif: {$total}");

        return self::SUCCESS;
    }
}
