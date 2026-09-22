<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pengingat piutang pelanggan (D-63)
    |--------------------------------------------------------------------------
    |
    | Tahap pengingat dinyatakan sebagai DATA, bukan `if` di kode (pola D-60),
    | sehingga Bos dapat mengubah jadwalnya tanpa menyentuh kelas apa pun.
    |
    | `offset_days` dihitung relatif terhadap tanggal jatuh tempo:
    |   negatif = sebelum jatuh tempo, positif = setelah.
    | `code` dipakai sebagai kunci idempotensi per tagihan, jadi mengubah kode
    | berarti pengingat dianggap tahap baru dan akan terkirim sekali lagi.
    |
    | Pengingat ini TIDAK memakai jalur dunning langganan platform (D-23):
    | yang ditagih di sini pelanggan tenant, bukan tenant oleh platform.
    */
    'reminders' => [
        'enabled' => (bool) env('RECEIVABLE_REMINDERS_ENABLED', true),

        'stages' => [
            ['code' => 'due_minus_1', 'offset_days' => -1],
            ['code' => 'due_plus_1', 'offset_days' => 1],
            ['code' => 'due_plus_7', 'offset_days' => 7],
            ['code' => 'due_plus_30', 'offset_days' => 30],
        ],
    ],
];
