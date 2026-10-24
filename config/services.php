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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'click_to_chat'),
        'cloud_token' => env('WHATSAPP_CLOUD_TOKEN'),
        'cloud_phone_number_id' => env('WHATSAPP_CLOUD_PHONE_NUMBER_ID'),
    ],

    // Online payments (M11B, D42). Keys live here only; staff switch each method online in the admin.
    'daraja' => [
        'env' => env('DARAJA_ENV', 'sandbox'),
        'consumer_key' => env('DARAJA_CONSUMER_KEY'),
        'consumer_secret' => env('DARAJA_CONSUMER_SECRET'),
        // The paybill, or the store (head office) number when type is till.
        'shortcode' => env('DARAJA_SHORTCODE'),
        'passkey' => env('DARAJA_PASSKEY'),
        'type' => env('DARAJA_TYPE', 'paybill'),
        'till_number' => env('DARAJA_TILL_NUMBER'),
        // The secret path segment of the callback URLs Safaricom calls.
        'callback_secret' => env('DARAJA_CALLBACK_SECRET'),
        'allowed_ips' => array_values(array_filter(explode(',', (string) env('DARAJA_ALLOWED_IPS', '')))),
    ],

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
    ],

    // The provider is chosen in the admin (FxSettings::provider); only its key lives here.
    'fx' => [
        'api_key' => env('FX_API_KEY'),
    ],

];
