<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ambang Analisis Kesehatan Usaha
    |--------------------------------------------------------------------------
    | Angka-angka di baca oleh BusinessHealthAnalyzer. Jangan hardcode
    | nilai ini di service (aturan proyek: config-driven).
    */

    'trend_threshold_percent' => (float) env('ANALYTICS_TREND_THRESHOLD_PERCENT', 3.0),

    'min_data_points' => (int) env('ANALYTICS_MIN_DATA_POINTS', 2),

    'highlights' => [
        'top_expenses' => 3,
        'top_contacts' => 3,
    ],

];
