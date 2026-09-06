<?php

return [
    'public_url' => env('PUBLIC_CLIENT_URL'),
    'public_host' => env('PUBLIC_CLIENT_HOST'),
    'internal_hosts' => array_values(array_filter(array_map('trim', explode(',', env('INTERNAL_HOSTS', 'localhost,127.0.0.1,imprint-hub'))))),
    'backup_path' => env('LOCAL_BACKUP_PATH') ?: storage_path('app/backups'),
    'nas_backup_path' => env('NAS_BACKUP_PATH'),

    // How many archives to keep locally. A month of daily backups is enough to
    // notice and recover from a mistake made a few weeks ago.
    'backup_keep' => (int) env('BACKUP_KEEP', 30),
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],
];
