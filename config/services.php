<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
     /*
    |──────────────────────────────────────────────────────────────
    | PayTech — Paiement en ligne Sénégal
    | Wave + Orange Money + Free Money
    | Doc : https://paytech.sn/
    |──────────────────────────────────────────────────────────────
    */
    'paytech' => [
        'api_key'    => env('PAYTECH_API_KEY', ''),
        'api_secret' => env('PAYTECH_API_SECRET', 'test_secret'),
        'env'        => env('PAYTECH_ENV', 'test'),
    ],

];
