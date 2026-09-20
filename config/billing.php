<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Free Tier Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the free tier (company without active membership).
    | Implements D-60: Tier gratis dengan kapabilitas penuh + kuota terbatas.
    |
    */
    'free_tier' => [
        /*
        | Capabilities available in free tier
        | All operational capabilities + system.ai_agent enabled
        */
        'capabilities' => [
            'contacts',
            'deals',
            'projects',
            'scheduling',
            'bookings',
            'inventory',
            'pos',
            'quotations',
            'approval_flow',
            'timesheet',
            'finance.cashbook',
            'hr.employees',
            'system.ai_agent', // AI agent must be enabled to "make users addicted" (D-60)
        ],

        /*
        | Maximum WhatsApp groups allowed for free tier (D-60, D-53)
        | Free tier can only join 1 WA group (Kasir/Gudang/Keuangan)
        */
        'max_wa_groups' => 1,

        /*
        | Token quota for free tier (D-60, D-48)
        | Small quota to let users try the AI (~500 tokens, enough for 1-2 days)
        | Configurable via this key - can be changed by Bos without code changes
        */
        'token_quota' => 500,

        /*
        | Emergency token quota for free tier (D-48)
        | Additional tokens available in "emergency mode" when main quota exhausted
        | Set to 0 to disable emergency mode for free tier
        */
        'emergency_token_quota' => 0,
    ],
];
