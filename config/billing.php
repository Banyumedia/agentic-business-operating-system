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
        | Kuota pengguna tier gratis (D-65). Satu orang = owner saja; orang
        | kedua adalah tanda usaha mulai serius, momen paling wajar untuk
        | menaikkan paket (strategi kuota D-60/D-61).
        */
        'max_users' => (int) env('FREE_TIER_MAX_USERS', 1),

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

    /*
    |--------------------------------------------------------------------------
    | Usage Indicator Configuration (W2)
    |--------------------------------------------------------------------------
    | Angka-angka tampilan "Penggunaan & Paket" di Settings, config-driven
    | (D-60) agar Bos bisa mengubahnya tanpa menyentuh kode.
    |
    */
    'usage' => [
        /*
        | Rasio sisa saldo terhadap kuota di bawah nilai ini memunculkan
        | banner peringatan dini yang sopan (default 20%).
        */
        'low_balance_ratio' => 0.2,

        /*
        | Tautan ke halaman paket untuk banner peringatan dan ajakan upgrade.
        | Diisi Bos saat halaman paket (lane terpisah) tersedia; kosong =
        | banner tampil tanpa tautan (fail-closed, tidak mengarang route).
        */
        'plans_url' => env('BILLING_PLANS_URL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Manual Payment Configuration (PAY-1)
    |--------------------------------------------------------------------------
    | Pembayaran manual (transfer bank + static QRIS)
    | Semua konfigurasi dari .env, JANGAN hardcode nomor rekening/path di kode
    |
    */
    'manual_payment' => [
        /*
        | Status pembayaran manual (aktif/nonaktif)
        */
        'enabled' => (bool) env('MANUAL_PAYMENT_ENABLED', true),

        /*
        | Bank transfer details
        */
        'bank' => [
            'name' => env('MANUAL_PAYMENT_BANK_NAME', 'BCA'),
            'account_number' => env('MANUAL_PAYMENT_BANK_ACCOUNT', ''),
            'account_holder' => env('MANUAL_PAYMENT_BANK_HOLDER', ''),
        ],

        /*
        | QRIS static image path (harus di storage/app/public/ atau URL)
        | Contoh: env('MANUAL_PAYMENT_QRIS_PATH', 'storage/app/public/qris.png')
        */
        'qris_path' => env('MANUAL_PAYMENT_QRIS_PATH', ''),

        /*
        | Waktu tunggu (jam) sebelum invoice kadaluarsa jika belum dibayar
        */
        'expiration_hours' => (int) env('MANUAL_PAYMENT_EXPIRATION_HOURS', 24),
    ],
];
