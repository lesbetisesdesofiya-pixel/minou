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

    'moneyfusion' => [
        'api_url' => env('MONEYFUSION_API_URL'),
        // Paiement désactivé temporairement : les commandes passent directement
        // en "Payée" (paiement au restaurant), sans redirection MoneyFusion.
        // Réactiver : PAYMENT_DISABLED=false dans .env
        'disabled' => (bool) env('PAYMENT_DISABLED', true),
        'status_url' => env('MONEYFUSION_STATUS_URL', 'https://pay.moneyfusion.net/paiementNotif'),
        // Frais de service répercutés au client : 10% (payin + retrait + service)
        'fee_rate' => (float) env('MONEYFUSION_FEE_RATE', 0.10),
        'payin_rate' => (float) env('MONEYFUSION_PAYIN_RATE', 0.03),
        'withdrawal_rate' => (float) env('MONEYFUSION_WITHDRAWAL_RATE', 0.035),
    ],

    'jwt' => [
        'secret' => env('JWT_SECRET'),
    ],

];
