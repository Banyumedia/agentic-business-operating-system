<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pengiriman ke Node Hermes
    |--------------------------------------------------------------------------
    |
    | `hermes_nodes.api_url` menyimpan alamat node, dan `api_secret_reference`
    | menyimpan **nama** rahasianya - bukan nilainya (COMMERCIAL §Hermes Profile).
    | Nilainya dipetakan di sini dari environment, jadi basis data tetap bebas
    | kredensial dan config cache tetap bekerja.
    |
    | Contoh: node dengan `api_secret_reference = 'node_lokal'` mengambil
    | nilainya dari `HERMES_NODE_LOKAL_SECRET`.
    |
    */

    'delivery' => [
        // Kontrak nyata bridge WhatsApp Hermes, dibaca dari
        // `scripts/whatsapp-bridge/bridge.js`: `POST /send` dengan
        // `{chatId, message, replyTo?}`, dan `GET /health`.
        'send_path' => env('HERMES_SEND_PATH', '/send'),
        'health_path' => env('HERMES_HEALTH_PATH', '/health'),
        'timeout' => (int) env('HERMES_TIMEOUT', 10),

        // `/health` menjawab **HTTP 200 walau WhatsApp terputus**, dengan
        // `status` berisi keadaan sambungannya. Memeriksa kode HTTP saja akan
        // melaporkan bridge mati sebagai sehat.
        'healthy_statuses' => ['connected'],

        // Nilai referensi rahasia yang berarti "node ini memang tidak punya
        // autentikasi". Bridge WhatsApp Hermes tidak punya token sama sekali di
        // port 3000; memaksa referensi palsu hanya supaya lolos aturan kita
        // adalah kebohongan yang tersimpan di basis data. Hanya sah untuk
        // loopback - lihat `HermesNodeClient`.
        'no_auth_reference' => 'none',

        // Profil `unpaired` belum menempel ke nomor WhatsApp mana pun, jadi ia
        // tidak boleh dianggap siap mengirim.
        //
        // Tiga kata dipakai untuk keadaan "siap" yang sama di tempat berbeda:
        // `paired` (CleanupExpiredTrials), `connected` (factory), dan `active`.
        // Tidak ada yang memvalidasi kosakata ini, jadi daftarnya dibuat
        // permisif **untuk keadaan siap** dan ketat untuk `unpaired`.
        // Menyeragamkannya layak jadi task sendiri; menebak satu kata yang
        // "benar" di sini justru bisa mematikan pengiriman yang sah.
        'ready_statuses' => ['paired', 'connected', 'active'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bot Milik Platform
    |--------------------------------------------------------------------------
    |
    | Kita punya dua nomor: bot dev (`primary`, internal, toolset penuh) dan bot
    | CS (`addon`, publik, baca-saja). Pesan platform ke pelanggan - dunning
    | langganan D-23/D-49 - keluar dari **bot CS**.
    |
    | Memakai bot dev untuk itu punya dua akibat yang tidak bisa ditarik kembali:
    | nomor internal kita beredar ke pelanggan, dan pelanggan mendapat kanal ke
    | bot yang berwenang menjalankan perintah. Karena itu tidak ada jatuh kembali
    | ke `primary` - lihat `PlatformHermesNodeClient`.
    |
    */

    'platform' => [
        'sender_profile_type' => env('HERMES_PLATFORM_SENDER_TYPE', 'addon'),
    ],

    'node_secrets' => array_filter([
        'node_lokal' => env('HERMES_NODE_LOKAL_SECRET'),
        'node_01' => env('HERMES_NODE_01_SECRET'),
    ]),

    /*
    |--------------------------------------------------------------------------
    | Hermes Scoped Profiles & Guardrails Architecture
    |--------------------------------------------------------------------------
    |
    | Menentukan pembatasan wewenang (tool scoping) dan guardrail untuk setiap
    | profil bot WhatsApp.
    |
    | - primary: Asisten internal operasional (Owner japri + tim grup).
    |   Tool hanya API ERP internal, zero OS tools (tanpa terminal/shell/file write).
    | - addon: Asisten CS publik (layanan pelanggan toko).
    |   Read-only tools, 4-layer platform guardrail anti-jailbreak terkunci.
    |
    */

    'profiles' => [
        'primary' => [
            'name' => 'Internal Operations Assistant',
            'audience' => 'internal', // owner DM & staff groups
            'allow_dm_for' => 'owner_only',
            'allow_groups' => true,
            'allowed_tools' => [
                'read_context',
                'create_transaction',
                'check_stock',
                'set_reminder',
                'approve_ticket',
                'generate_report',
                'update_settings',
                'destructive_action',
            ],
            'disallowed_tools' => [
                'terminal',
                'shell',
                'write_file',
                'delete_file',
                'git',
                'process',
            ],
            'temperature' => 0.2,
        ],

        'addon' => [
            'name' => 'Public Customer Service Assistant',
            'audience' => 'public', // direct customer inquiries
            'allow_dm_for' => 'all',
            'allow_groups' => false,
            'allowed_tools' => [
                'read_context',
                'search_catalog',
                'check_my_order',
            ],
            'disallowed_tools' => [
                'terminal',
                'shell',
                'write_file',
                'delete_file',
                'git',
                'process',
                'create_transaction',
                'update_settings',
                'destructive_action',
                'view_accounting',
            ],
            'temperature' => 0.1, // deterministik & kaku
            'guardrails' => [
                'locked_system_prompt' => true,
                'prohibited_topics' => ['jailbreak', 'ignore_instructions', 'politics', 'system_internals'],
                'refusal_message' => 'Maaf, saya hanya dapat membantu memberikan informasi resmi seputar layanan dan produk toko kami.',
            ],
        ],
    ],
];
