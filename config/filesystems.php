<?php

// config/filesystems.php — MACIF CHICKEN
// Ajouter le disk 'r2' pour Cloudflare R2 (compatible S3)

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Disk utilisé pour les uploads (avatars, photos stocks)
    | Valeur : 'r2' en prod, 'public' en local/test
    |--------------------------------------------------------------------------
    */
    'default_upload_disk' => env('UPLOAD_DISK', 'public'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app/private'),
            'serve'  => true,
            'throw'  => false,
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw'      => false,
        ],

        // ─── Cloudflare R2 (production) ──────────────────────────
        // Compatible S3 — utiliser les mêmes clés AWS_*
        'r2' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket'                  => env('AWS_BUCKET'),
            'url'                     => env('AWS_URL'),           // URL publique CDN
            'endpoint'                => env('AWS_ENDPOINT'),      // URL R2
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'visibility'              => 'public',
            'throw'                   => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];