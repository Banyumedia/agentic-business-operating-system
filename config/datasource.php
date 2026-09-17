<?php

return [
    'driver' => env('DATA_SOURCE', 'json'),
    'json_path' => storage_path('app/json'),
    'demo_companies' => [
        'bengkel-arka',
        'klinik-sehat',
        'salon-ayu',
        'laundry-bersih',
    ],
];
