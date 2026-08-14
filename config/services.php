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

    'pokeapi' => [
        'base_url' => env('POKEAPI_BASE_URL', 'https://pokeapi.co/api/v2'),
        'timeout' => (int) env('POKEAPI_TIMEOUT', 8),
        'connect_timeout' => (int) env('POKEAPI_CONNECT_TIMEOUT', 3),
        'cache_ttl' => (int) env('POKEAPI_CACHE_TTL', 86400),
    ],

    'assistant' => [
        'agent_url' => env('AI_AGENT_URL', 'http://ai-agent:3100'),
        'service_secret' => env('AI_SERVICE_SECRET'),
        'context_secret' => env('ASSISTANT_CONTEXT_SECRET', env('APP_KEY')),
        'timeout' => (int) env('AI_AGENT_TIMEOUT', 35),
        'connect_timeout' => (int) env('AI_AGENT_CONNECT_TIMEOUT', 3),
        'history_limit' => (int) env('ASSISTANT_HISTORY_LIMIT', 16),
        'image_history_limit' => (int) env('ASSISTANT_IMAGE_HISTORY_LIMIT', 2),
        'image_context_bytes' => (int) env('ASSISTANT_IMAGE_CONTEXT_BYTES', 12582912),
        'attachment_disk' => env('ASSISTANT_ATTACHMENT_DISK', 'local'),
        'action_ttl' => (int) env('ASSISTANT_ACTION_TTL', 15),
    ],

];
