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

    /*
    |--------------------------------------------------------------------------
    | Facebook / Meta OAuth
    |--------------------------------------------------------------------------
    */
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/auth/facebook/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Instagram OAuth (via Facebook/Meta)
    |--------------------------------------------------------------------------
    */
    'instagram' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'), // Same as Facebook (Meta)
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('INSTAGRAM_REDIRECT_URI', '/auth/instagram/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta Platform (Facebook & Instagram) Webhooks & API
    |--------------------------------------------------------------------------
    */
    'meta' => [
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
        'app_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'graph_api_version' => 'v21.0',
        // Set FACEBOOK_ENABLE_COMMENTS=true to request & use comment-reply permissions.
        // These require Facebook App Review — keep false until your app is approved.
        'enable_comments' => env('FACEBOOK_ENABLE_COMMENTS', false),

        // Set WHATSAPP_ENABLE=true to request WhatsApp Business permissions during OAuth
        // and enable WhatsApp messaging. Requires WhatsApp Business API access.
        'enable_whatsapp' => env('WHATSAPP_ENABLE', false),

        /*
        |----------------------------------------------------------------------
        | Connection Mode: "direct" or "proxy"
        |----------------------------------------------------------------------
        | direct = This instance connects to Facebook Graph API directly
        |          (requires its own approved Facebook App).
        | proxy  = This instance uses another server's proxy to reach Facebook
        |          (no Facebook App needed — just set the proxy credentials).
        */
        'connection_mode' => env('META_CONNECTION_MODE', 'direct'),

        // Proxy client settings (only used when connection_mode = proxy)
        'proxy_url'        => env('META_PROXY_URL'),        // e.g. https://rehla-ai.com
        'proxy_api_key'    => env('META_PROXY_API_KEY'),
        'proxy_api_secret' => env('META_PROXY_API_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | InvenGPT AI Service (Legacy - kept for backward compatibility)
    |--------------------------------------------------------------------------
    */
    'invengpt' => [
        'url' => env('INVENGPT_API_URL', 'http://127.0.0.1:5000'),
        'timeout' => env('INVENGPT_TIMEOUT', 60),
        'laravel_api_url' => env('APP_URL', 'http://127.0.0.1:8001'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Groq AI API (Legacy - Backup AI Service)
    |--------------------------------------------------------------------------
    */
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'api_url' => env('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions'),
        'timeout' => env('GROQ_TIMEOUT', 15),
        'retries' => env('GROQ_RETRIES', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI / ChatGPT API (Primary AI Service)
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
        'api_url' => env('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions'),
        'timeout' => env('OPENAI_TIMEOUT', 30),
        'retries' => env('OPENAI_RETRIES', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'default_provider' => env('AI_PROVIDER', 'openai'), // openai or groq
        'fallback_enabled' => env('AI_FALLBACK_ENABLED', true),

        // Available OpenAI Models
        'openai_models' => [
            [
                'id' => 'gpt-4.1-mini',
                'name' => 'GPT-4.1 Mini',
                'description' => 'Recommended - balanced speed, quality, and cost',
                'recommended' => true,
                'pricing' => ['input' => 0.15, 'output' => 0.60], // per 1M tokens
            ],
            [
                'id' => 'gpt-4o-mini',
                'name' => 'GPT-4o Mini',
                'description' => 'موصى به - توازن بين السرعة والجودة والتكلفة',
                'recommended' => false,
                'pricing' => ['input' => 0.15, 'output' => 0.60], // per 1M tokens
            ],
            [
                'id' => 'gpt-4o',
                'name' => 'GPT-4o',
                'description' => 'أعلى جودة - للمحادثات المعقدة',
                'recommended' => false,
                'pricing' => ['input' => 2.50, 'output' => 10.00],
            ],
            [
                'id' => 'gpt-4-turbo',
                'name' => 'GPT-4 Turbo',
                'description' => 'نموذج قوي - أغلى سعراً',
                'recommended' => false,
                'pricing' => ['input' => 10.00, 'output' => 30.00],
            ],
            [
                'id' => 'gpt-3.5-turbo',
                'name' => 'GPT-3.5 Turbo',
                'description' => 'اقتصادي - للمحادثات البسيطة',
                'recommended' => false,
                'pricing' => ['input' => 0.50, 'output' => 1.50],
            ],
        ],

        // Available Groq Models
        'groq_models' => [
            [
                'id' => 'llama-3.3-70b-versatile',
                'name' => 'Llama 3.3 70B Versatile',
                'description' => 'أقوى نموذج - مناسب للمحادثات المعقدة',
                'recommended' => true,
                'pricing' => ['input' => 0.59, 'output' => 0.79],
            ],
            [
                'id' => 'llama-3.1-70b-versatile',
                'name' => 'Llama 3.1 70B Versatile',
                'description' => 'نموذج قوي ومستقر',
                'recommended' => false,
                'pricing' => ['input' => 0.59, 'output' => 0.79],
            ],
            [
                'id' => 'llama-3.1-8b-instant',
                'name' => 'Llama 3.1 8B Instant',
                'description' => 'سريع جداً - للردود البسيطة',
                'recommended' => false,
                'pricing' => ['input' => 0.05, 'output' => 0.08],
            ],
            [
                'id' => 'mixtral-8x7b-32768',
                'name' => 'Mixtral 8x7B',
                'description' => 'توازن بين السرعة والجودة',
                'recommended' => false,
                'pricing' => ['input' => 0.24, 'output' => 0.24],
            ],
            [
                'id' => 'gemma2-9b-it',
                'name' => 'Gemma 2 9B',
                'description' => 'نموذج Google خفيف',
                'recommended' => false,
                'pricing' => ['input' => 0.20, 'output' => 0.20],
            ],
        ],
    ],

];

