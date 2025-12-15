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
        'region' => env('AWS_SES_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
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
        'mode' => env('STRIPE_MODE', 'test'),
        'key' => env('STRIPE_MODE') === 'live' ? env('STRIPE_LIVE_PUBLIC') : env('STRIPE_TEST_PUBLIC'),
        'secret' => env('STRIPE_MODE') === 'live' ? env('STRIPE_LIVE_SECRET') : env('STRIPE_TEST_SECRET'),
        'webhook_secret' => env('STRIPE_MODE') === 'live' ? env('STRIPE_LIVE_WEBHOOK_SECRET') : env('STRIPE_TEST_WEBHOOK_SECRET'),

        // Individual keys for direct access if needed
        'live' => [
            'public' => env('STRIPE_LIVE_PUBLIC'),
            'secret' => env('STRIPE_LIVE_SECRET'),
            'webhook_secret' => env('STRIPE_LIVE_WEBHOOK_SECRET'),
        ],
        'test' => [
            'public' => env('STRIPE_TEST_PUBLIC'),
            'secret' => env('STRIPE_TEST_SECRET'),
            'webhook_secret' => env('STRIPE_TEST_WEBHOOK_SECRET'),
        ],
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

     'stax' => [
        'base_url' => env('STAX_BASE_URL', 'https://apiprod.fattlabs.com'),
        'api_key' => env('STAX_API_KEY'),
        'public_key' => env('STAX_PUBLIC_KEY'),
    ],
    'paymob' => [
    'base_url'       => env('PAYMOB_MODE') === 'live' ? 'https://accept.paymobsolutions.com/api' : 'https://uae.paymob.com/api',
    'api_key'        => env('PAYMOB_API_KEY'),
    'integration_id' => env('PAYMOB_INTEGRATION_ID'),
    'iframe_id'      => env('PAYMOB_IFRAME_ID'),
    'hmac'           => env('PAYMOB_HMAC'),
    'secret_key'     => env('PAYMOB_SECRET_KEY'),
    'public_key'     => env('PAYMOB_PUBLIC_KEY'),
],
    'tql' => [
    'base_url'       => env('TQL_BASE_URL', 'https://api.tql.com/v1'),
    'api_key'        => env('TQL_API_KEY'),
    'subscription_key'        => env('TQL_SUBSCRIPTION_KEY'),
    'client_id'        => env('TQL_CLIENT_ID'),
    'client_secret'        => env('TQL_CLIENT_SECRET'),
    'username'        => env('TQL_USERNAME'),
    'password'        => env('TQL_PASSWORD'),
    'token_url'        => env('TQL_TOKEN_URL'),

],





];