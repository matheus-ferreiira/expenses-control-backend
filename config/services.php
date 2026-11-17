<?php

return [

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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', 'http://localhost'),
    ],

    // Conexão bancária (Open Finance via MeuPluggy). Credenciais só no backend.
    'pluggy' => [
        'client_id' => env('PLUGGY_CLIENT_ID'),
        'client_secret' => env('PLUGGY_CLIENT_SECRET'),
        'base_url' => env('PLUGGY_BASE_URL', 'https://api.pluggy.ai'),
        // Mostra os bancos de teste da Pluggy no widget (só para desenvolvimento)
        'include_sandbox' => (bool) env('PLUGGY_INCLUDE_SANDBOX', false),
    ],

];
