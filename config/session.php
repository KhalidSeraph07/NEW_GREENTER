<?php

return [
    'driver' => 'file',
    'files' => storage_path('framework/sessions'),
    'cookie' => env('SESSION_COOKIE', 'emisor_h2_session'),
    'http_only' => true,
    'same_site' => 'lax',
    'secure' => false, // Solo HTTP loopback; producción permanece bloqueada.
    'lifetime' => 30,
    'expire_on_close' => true,
];
