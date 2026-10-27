<?php
return ['mode' => 'fictitious', 'evidence_root' => env('APP_ENV') === 'testing'
    ? storage_path('framework/testing/h5c1') : storage_path('app/private/h5c1')];
