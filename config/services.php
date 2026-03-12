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

    'bayzat' => [
        'default_api_url' => env('BAYZAT_DEFAULT_API_URL', 'https://integration.bayzat.com/attendance'),
        'rate_limit_delay' => env('BAYZAT_RATE_LIMIT_DELAY', 1), // seconds between requests
        'max_records_per_request' => env('BAYZAT_MAX_RECORDS_PER_REQUEST', 20),
        'max_retry_attempts' => env('BAYZAT_MAX_RETRY_ATTEMPTS', 5),
    ],

    'fingerprint_device' => [
        'base_url' => env('FINGERPRINT_DEVICE_URL', 'http://192.168.10.74:8081'),
        'token' => env('FINGERPRINT_DEVICE_TOKEN'),
        'timeout' => env('FINGERPRINT_DEVICE_TIMEOUT', 15),
    ],

];
