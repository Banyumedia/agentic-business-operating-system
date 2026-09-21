<?php

return [
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
                'create_transaction',
                'check_stock',
                'set_reminder',
                'approve_ticket',
                'generate_report',
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
                'search_catalog',
                'check_my_order',
                'create_support_ticket',
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
