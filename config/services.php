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

    'claude' => [
        'api_url' => env('CLAUDE_API_URL', 'https://api.anthropic.com/v1/messages'),
        'api_key' => env('CLAUDE_API_KEY'),
        'model' => env('CLAUDE_MODEL', 'claude-3.5-sonnet'),
        'version' => env('CLAUDE_VERSION', '2023-06-01'),
    ],

    'square' => [
        'application_id' => env('SQUARE_APPLICATION_ID'),
        'access_token' => env('SQUARE_ACCESS_TOKEN'),
        'environment' => env('SQUARE_ENV', 'production'), // 'sandbox' or 'production'
        'location_id' => env('SQUARE_LOCATION_ID'), // Optional default location
    ],
    'stripe' => [
    'key' => env('STRIPE_MODE') === 'live' ? env('STRIPE_LIVE_PUBLIC') : env('STRIPE_TEST_PUBLIC'),
    'secret' => env('STRIPE_MODE') === 'live' ? env('STRIPE_LIVE_SECRET') : env('STRIPE_TEST_SECRET'),
    ],

    'tamara' => [
    'url' => env('TAMARA_API_URL'),
    'token' => env('TAMARA_API_TOKEN'),
    'public_key' => env('TAMARA_PUBLIC_KEY'),
    'notification_token' => env('TAMARA_NOTIFICATION_TOKEN'),
    ],

    'google_maps' => [
    'key' => env('GOOGLE_MAPS_API_KEY'),
    ],
    'taxjar' => [
        'api_key' => env('TAXJAR_API_KEY'),
        'api_url' => env('TAXJAR_API_URL', 'https://api.taxjar.com'),
        'sandbox' => env('TAXJAR_SANDBOX', false),
        'cache_enabled' => env('TAXJAR_CACHE_ENABLED', true),
        'cache_ttl' => env('TAXJAR_CACHE_TTL', 3600), // 1 hour
    ],
    'google_place' => [
    'key' => env('GOOGLE_PLACE_API_KEY'),
    'place_id' => env('GOOGLE_PLACE_ID'),
    ],

     'nofraud' => [
        'api_key' => env('NOFRAUD_API_KEY'),
        'api_url' => env('NOFRAUD_API_URL', 'https://api.nofraud.com/'),
    ],



];
