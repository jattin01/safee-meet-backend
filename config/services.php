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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'didit' => [
        'api_key' => env('DIDIT_API_KEY'),
        'webhook_secret' => env('DIDIT_WEBHOOK_SECRET'),
        'workflow_id' => env('DIDIT_WORKFLOW_ID'),
        'base_url' => env('DIDIT_BASE_URL', 'https://verification.didit.me'),
        'callback_url' => env('DIDIT_CALLBACK_URL'),
        'refresh_incomplete_decisions' => env('DIDIT_REFRESH_INCOMPLETE_DECISIONS', true),
    ],

    'searchbug' => [
        // Kept off until Searchbug supplies the account-specific Criminal
        // Records endpoint and response contract.
        'enabled' => env('SEARCHBUG_ENABLED', false),
        'endpoint' => env('SEARCHBUG_ENDPOINT'),
        'co_code' => env('SEARCHBUG_CO_CODE'),
        'pass' => env('SEARCHBUG_PASS'),
        'type' => env('SEARCHBUG_TYPE', 'api_crm'),
        'timeout' => (int) env('SEARCHBUG_TIMEOUT', 20),
        'consent_version' => env('BACKGROUND_CHECK_CONSENT_VERSION', '2026-08-19'),
        'valid_for_days' => (int) env('BACKGROUND_CHECK_VALID_FOR_DAYS', 365),
    ],

    'telesign' => [
        'customer_id' => env('TELESIGN_CUSTOMER_ID'),
        'api_key' => env('TELESIGN_API_KEY'),      // base64 secret
        'sender_id' => env('TELESIGN_SENDER_ID'),  // optional, if using a branded sender
    ],

];
