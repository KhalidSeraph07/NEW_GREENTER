<?php

return [
    'mode' => 'fictitious',
    'issuer_ruc' => '00000000000',
    'issuer_name' => 'EMISOR FICTICIO H2 - SIN VALIDEZ FISCAL',
    'receiver_ruc' => '00000000000',
    'receiver_name' => 'RECEPTOR FICTICIO H2',
    'series' => 'F000',
    'currency' => 'PEN',
    'tax_percent' => 18,
    'evidence_root' => env('APP_ENV') === 'testing' ? storage_path('framework/testing/h2') : storage_path('app/private/h2'),
    'operator_email' => 'operador.h2@example.invalid',
    'operator_password' => env('H2_OPERATOR_PASSWORD'),
];
