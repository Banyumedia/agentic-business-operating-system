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

    /*
    |--------------------------------------------------------------------------
    | Penempatan Profil ke Node (T-105)
    |--------------------------------------------------------------------------
    |
    | Satu bridge WhatsApp = satu nomor = **satu port** (T-81). Selama port hanya
    | disimpan sebagai bagian bebas dari `api_url`, dua profil pada satu node bisa
    | memakai port yang sama dan saling menendang tanpa pesan yang jelas. `NodePlacement`
    | menjadikan port sumber daya yang dialokasikan: memilih port bebas terkecil dalam
    | rentang ini yang belum dipakai profil lain di node yang sama.
    |
    | Rentang dibuat dapat dikonfigurasi karena tiap host bisa punya kebijakan port
    | berbeda; bawaannya 3000-3099 sejalan dengan port bridge bawaan Hermes (3000).
    |
    | Catatan batas: kita **tidak** membandingkan alokasi ini dengan port yang
    | dilaporkan `/api/status` node (mis. webhook Cloud API di 8090). Alasannya
    | ditulis di `NodePlacement`: menyeret penempatan ke pemanggilan control plane
    | membuat jalur yang wajib cepat dan tanpa jaringan menjadi bergantung pada
    | proses lain yang bisa lambat atau mati. Jaminan tabrakan port ditegakkan oleh
    | unique-per-node di basis data + rentang yang dijaga di luar port platform.
    |
    */
    'placement' => [
        'port_range' => [
            'start' => (int) env('HERMES_PLACEMENT_PORT_START', 3000),
            'end' => (int) env('HERMES_PLACEMENT_PORT_END', 3099),
        ],
    ],

    'node_secrets' => array_filter([
        'node_lokal' => env('HERMES_NODE_LOKAL_SECRET'),
        'node_01' => env('HERMES_NODE_01_SECRET'),
    ]),

    /*
    |--------------------------------------------------------------------------
    | Control Plane (dashboard API Hermes)
    |--------------------------------------------------------------------------
    |
    | Alamat dan rahasia control plane **terpisah** dari bridge (D-72 butir 4).
    | `hermes_nodes.api_url` adalah bridge WhatsApp: loopback, tanpa autentikasi,
    | satu port per nomor. Dashboard API adalah proses lain, di port lain, dan
    | butuh token bearer - token yang setara eksekusi kode di host Hermes, karena
    | port yang sama juga menyajikan tulis-berkas dan terminal.
    |
    | Karena itu: nilai rahasia **tidak pernah** di basis data, hanya namanya;
    | daftar path yang boleh dipanggil adalah konstanta di `ControlPlanePaths`,
    | bukan konfigurasi yang bisa diubah lewat `.env`.
    |
    */

    'control' => [
        'timeout' => (int) env('HERMES_CONTROL_TIMEOUT', 10),

        // Umur cache cermin profil (T-83), dalam **detik**.
        //
        // Sengaja pendek. Cermin bersifat read-through justru supaya tidak ada salinan
        // yang bisa menyimpang diam-diam (D-72 butir 2); nilai yang besar mengubah
        // cache ini menjadi tabel bayangan dengan nama lain, dan halaman akan
        // menampilkan keadaan yang sudah lewat tanpa mengatakannya. Yang perlu ditahan
        // hanyalah beberapa panggilan dalam satu render halaman. Tombol "segarkan"
        // selalu menembusnya, dan setiap hasil membawa stempel `diambil_pada`.
        'mirror_ttl' => (int) env('HERMES_CONTROL_MIRROR_TTL', 20),
    ],

    'control_secrets' => array_filter([
        'control_lokal' => env('HERMES_CONTROL_LOKAL_SECRET'),
        'control_01' => env('HERMES_CONTROL_01_SECRET'),
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
