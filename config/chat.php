<?php

/**
 * Chat System Configuration
 *
 * Central configuration for the AI marketing chatbot system.
 * All model settings, token limits, timeouts, and feature flags live here.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Settings
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'api_key'  => env('OPENAI_API_KEY', ''),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout'  => 30, // seconds
        'retry'    => [
            'times' => 1,
            'delay' => 2000, // ms
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    | Two models, two jobs:
    |   - conversation_model: main replies (gpt-4.1-mini)
    |   - classification_model: intent & entity extraction (gpt-4.1-nano)
    */
    'models' => [
        'conversation'   => env('CHAT_CONVERSATION_MODEL', 'gpt-4.1-mini'),
        'classification' => env('CHAT_CLASSIFICATION_MODEL', 'gpt-4.1-nano'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Limits
    |--------------------------------------------------------------------------
    */
    'tokens' => [
        'intent_classification' => 150,
        'entity_extraction'     => 200,
        'conversation_reply'    => 600,
        'order_confirmation'    => 400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation Settings
    |--------------------------------------------------------------------------
    */
    'conversation' => [
        'temperature'          => 0.4,
        'max_history_pairs'    => 10,
        'max_tool_loops'       => 3,
        'session_timeout_hours' => 3,
        'intent_confidence_threshold' => 0.50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart Settings (defaults, overridden per-store via ai_settings)
    |--------------------------------------------------------------------------
    */
    'cart' => [
        'max_items'     => 10,
        'delivery_cost' => 5000, // IQD
        'delivery_time' => '24-48 ساعة',
    ],

    /*
    |--------------------------------------------------------------------------
    | Lead Scoring
    |--------------------------------------------------------------------------
    */
    'scoring' => [
        'browse_category'    => 1,
        'ask_product'        => 2,
        'ask_price'          => 3,
        'ask_delivery'       => 5,
        'add_to_cart'        => 10,
        'reach_checkout'     => 15,
        'provide_info'       => 20,
        'complete_order'     => 25,
        'abandon_cart'       => -5,
        'cancel_order'       => -2,
    ],

    'score_categories' => [
        'cold'  => [0, 9],
        'warm'  => [10, 24],
        'hot'   => [25, 49],
        'vip'   => [50, PHP_INT_MAX],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'max_per_minute' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'system_prompt_ttl' => 1800, // 30 minutes in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Upsell Settings
    |--------------------------------------------------------------------------
    */
    'upsell' => [
        'max_suggestions'    => 2,
        'price_ratio'        => 0.30, // max 30% of added product price
        'browse_nudge_count' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Stock Urgency Thresholds
    |--------------------------------------------------------------------------
    */
    'stock' => [
        'urgent_threshold' => 3,
        'popular_threshold' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Phone Validation (Iraqi)
    |--------------------------------------------------------------------------
    */
    'phone' => [
        // Accept 10-digit (07XXXXXXXX) and 11-digit (07XXXXXXXXX) Iraqi mobile numbers.
        // Some users type numbers without a leading 0 digit, others include it.
        'pattern' => '/^07[3-9]\d{7,8}$/',
    ],

];
