<?php

return [
    'driver' => 'file',
    'files' => storage_path('framework/sessions'),
    'cookie' => 'emisor_h2_session',
    'http_only' => true,
    'same_site' => 'lax',
    'secure' => false,
];
