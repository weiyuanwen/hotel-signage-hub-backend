<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'vietqr' => [
        'bank_id' => env('VIETQR_BANK_ID', 'TPB'),
        'account_no' => env('VIETQR_ACCOUNT_NO'),
        'account_name' => env('VIETQR_ACCOUNT_NAME'),
        'template' => env('VIETQR_TEMPLATE', 'compact2'),
    ],

    'histbank' => [
        'url' => env('HISTBANK_URL'),
        'min_interval_seconds' => (int) env('HISTBANK_MIN_INTERVAL', 60),
        'initial_delay_seconds' => (int) env('HISTBANK_INITIAL_DELAY', 30),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'stripe' => [
        'enabled' => env('STRIPE_ENABLED', false),
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'billing' => [
        'cms_url' => env('CMS_URL', 'https://signagehub.online'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
