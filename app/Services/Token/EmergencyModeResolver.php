<?php

namespace App\Services\Token;

use App\Models\CompanyMembership;

class EmergencyModeResolver
{
    public function isEmergencyModeActive(CompanyMembership $membership): bool
    {
        return $membership->current_token_balance <= 0 && $membership->emergency_balance > 0;
    }

    public function isDepleted(CompanyMembership $membership): bool
    {
        return $membership->current_token_balance <= 0 && $membership->emergency_balance <= 0;
    }
}
