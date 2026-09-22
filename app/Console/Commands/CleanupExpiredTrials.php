<?php

namespace App\Console\Commands;

use App\Models\CompanyMembership;
use App\Models\HermesNode;
use App\Models\HermesProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupExpiredTrials extends Command
{
    protected $signature = 'app:cleanup-expired-trials';

    protected $description = 'Soft delete companies with trials expired > 30 days and revoke their Hermes API keys.';

    public function handle(): void
    {
        $threshold = now()->subDays(30);

        // Find expired trials
        $expiredMemberships = CompanyMembership::with('company.owner.companies')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $threshold)
            ->whereNotIn('status', ['active']) // only if not converted to active paid
            ->get();

        $count = 0;
        foreach ($expiredMemberships as $membership) {
            DB::transaction(function () use ($membership, &$count) {
                $company = $membership->company;
                if ($company && ! $company->trashed()) {
                    $company->delete(); // Soft deletes the company
                    $count++;

                    // Check if owner has any other active companies
                    $owner = $company->owner;
                    if ($owner) {
                        $hasActive = $owner->companies()->where('id', '!=', $company->id)->exists();
                        if (! $hasActive) {
                            // Node lama dicatat sebelum pencabutan, karena setelah
                            // `node_id => null` tidak ada lagi jejak ke mana profil ini
                            // pernah menempel.
                            $formerNodeIds = HermesProfile::where('owner_user_id', $owner->id)
                                ->whereNotNull('node_id')
                                ->pluck('node_id')
                                ->unique();

                            // Cabut API key Hermes jika owner tidak punya company aktif lain
                            HermesProfile::where('owner_user_id', $owner->id)->update([
                                'status' => 'unpaired',
                                'node_id' => null,
                                'webhook_secret_reference' => 'revoked_'.uniqid(),
                            ]);

                            // Profil yang dicabut membebaskan kapasitas node lamanya.
                            // Tanpa penyelarasan ini `active_profiles` hanya bisa naik,
                            // dan penjaga kapasitas di `bos:hermes-profile` akan menolak
                            // profil yang sah pada node yang sebenarnya lowong.
                            HermesNode::whereIn('id', $formerNodeIds)
                                ->get()
                                ->each
                                ->syncActiveProfiles();
                        }
                    }
                }
            });
        }

        $this->info("Cleaned up {$count} expired trial companies.");
    }
}
