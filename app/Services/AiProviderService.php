<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI Provider Service - Handles API calls to OpenAI
 *
 * This service abstracts the AI provider logic and handles:
 * - OpenAI API calls with proper authentication
 * - Token usage tracking and cost calculation
 * - Error handling and logging
 */
class AiProviderService
{
    protected User $user;
    protected AiSetting $settings;
    protected string $provider = 'openai';
    protected string $model;
    protected string $apiKey;
    protected string $apiUrl;
    protected int $timeout;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->settings = $user->aiSetting ?? new AiSetting();
        $this->initializeProvider();
    }

    /**
     * Initialize the AI provider (OpenAI or Groq)
     */
    protected function initializeProvider(): void
    {
        // Get provider from settings or config
        $settingsProvider = $this->settings->ai_provider ?? null;

        // Map 'openrouter' to 'openai' as they use similar API
        if ($settingsProvider === 'openrouter') {
            $settingsProvider = 'openai';
        }

        $defaultProvider = config('services.ai.default_provider', 'openai');
        if ($defaultProvider === 'openai') {
            $settingsProvider = 'openai';
        }

        $this->provider = $settingsProvider ?? $defaultProvider;

        if ($this->provider === 'groq') {
            // Use Groq
            $this->apiKey = $this->getValidApiKey($this->settings->groq_api_key, config('services.groq.api_key'));
            $this->model = $this->settings->groq_model ?? config('services.groq.model', 'llama-3.3-70b-versatile');
            $this->apiUrl = config('services.groq.api_url', 'https://api.groq.com/openai/v1/chat/completions');
            $this->timeout = config('services.groq.timeout', 15);
        } else {
            // Default to OpenAI
            $this->provider = 'openai';
            $this->apiKey = $this->getValidApiKey($this->settings->openai_api_key, config('services.openai.api_key'));
            $this->model = $this->settings->openai_model ?? config('services.openai.model', 'gpt-4.1-mini');
            $this->apiUrl = config('services.openai.api_url', 'https://api.openai.com/v1/chat/completions');
            $this->timeout = config('services.openai.timeout', 30);
        }

        Log::info('AI Provider initialized', [
            'provider' => $this->provider,
            'model' => $this->model,
            'has_api_key' => !empty($this->apiKey),
        ]);
    }

    /**
     * Get a valid API key (filter out placeholder values)
     */
    protected function getValidApiKey(?string $settingsKey, ?string $configKey): ?string
    {
        // Check settings key first
        if (!empty($settingsKey) && $this->isValidApiKey($settingsKey)) {
            return $settingsKey;
        }

        // Fallback to config key
        if (!empty($configKey) && $this->isValidApiKey($configKey)) {
            return $configKey;
        }

        return null;
    }

    /**
     * Check if API key looks valid (not a placeholder)
     */
    protected function isValidApiKey(?string $key): bool
    {
        if (empty($key)) {
            return false;
        }

        // Filter out obvious placeholders
        $placeholders = ['openai_api_key', 'groq_api_key', 'your-api-key', 'sk-xxx', 'gsk_xxx'];
        if (in_array(strtolower($key), $placeholders)) {
            return false;
        }

        // OpenAI keys start with 'sk-'
        // Groq keys start with 'gsk_'
        if (str_starts_with($key, 'sk-') || str_starts_with($key, 'gsk_')) {
            return strlen($key) > 20; // Valid keys are longer
        }

        return strlen($key) > 20; // Generic check for other providers
    }

    /**
     * Get current provider name
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Get current model
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Check if API key is configured
     */
    public function hasValidApiKey(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Make an API call to the AI provider
     */
    public function chat(
        array $messages,
        float $temperature = 0.3,
        int $maxTokens = 500,
        string $requestType = 'chat',
        ?string $conversationId = null,
        ?string $leadId = null
    ): array {
        if (!$this->hasValidApiKey()) {
            Log::error('OpenAI API key not configured', [
                'provider' => $this->provider,
                'model' => $this->model,
            ]);
            return [
                'success' => false,
                'error' => 'API key not configured',
                'content' => null,
            ];
        }

        $startTime = microtime(true);

        Log::info('OpenAI API request', [
            'provider' => $this->provider,
            'model' => $this->model,
            'api_url' => $this->apiUrl,
            'messages_count' => count($messages),
        ]);

        try {
            $response = Http::timeout($this->timeout)
                ->withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl, [
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ]);

            $responseTime = round((microtime(true) - $startTime) * 1000); // ms

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';

                // Extract token usage from response
                $usage = $data['usage'] ?? [];
                $inputTokens = $usage['prompt_tokens'] ?? 0;
                $outputTokens = $usage['completion_tokens'] ?? 0;

                // Log usage for tracking
                $this->logUsage(
                    $inputTokens,
                    $outputTokens,
                    $requestType,
                    $conversationId,
                    $leadId,
                    ['response_time_ms' => $responseTime]
                );

                return [
                    'success' => true,
                    'content' => $content,
                    'usage' => [
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                        'total_tokens' => $inputTokens + $outputTokens,
                        'estimated_cost' => AiUsageLog::calculateCost($this->model, $inputTokens, $outputTokens),
                    ],
                    'response_time_ms' => $responseTime,
                    'provider' => $this->provider,
                    'model' => $this->model,
                ];
            }

            // Try fallback if enabled
            if (config('services.ai.fallback_enabled', true)) {
                return $this->tryFallback($messages, $temperature, $maxTokens, $requestType, $conversationId, $leadId);
            }

            Log::error('AI API call failed', [
                'provider' => $this->provider,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'API call failed: ' . $response->status(),
                'content' => null,
            ];

        } catch (\Exception $e) {
            Log::error('AI API exception', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
            ]);

            // Try fallback if enabled
            if (config('services.ai.fallback_enabled', true)) {
                return $this->tryFallback($messages, $temperature, $maxTokens, $requestType, $conversationId, $leadId);
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'content' => null,
            ];
        }
    }

    /**
     * Try fallback provider on failure
     */
    protected function tryFallback(
        array $messages,
        float $temperature,
        int $maxTokens,
        string $requestType,
        ?string $conversationId,
        ?string $leadId
    ): array {
        // Try again with OpenAI using a different model as fallback
        $fallbackProvider = 'openai';
        $fallbackApiKey = $this->settings->openai_api_key ?? config('services.openai.api_key');
        $fallbackModel = 'gpt-4.1-mini'; // Fallback to reliable model
        $fallbackUrl = config('services.openai.api_url', 'https://api.openai.com/v1/chat/completions');

        if (empty($fallbackApiKey)) {
            return [
                'success' => false,
                'error' => 'Primary provider failed and no fallback API key configured',
                'content' => null,
            ];
        }

        Log::info('Trying fallback provider', ['fallback' => $fallbackProvider]);

        try {
            $response = Http::timeout(30)
                ->withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $fallbackApiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($fallbackUrl, [
                    'model' => $fallbackModel,
                    'messages' => $messages,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';

                $usage = $data['usage'] ?? [];
                $inputTokens = $usage['prompt_tokens'] ?? 0;
                $outputTokens = $usage['completion_tokens'] ?? 0;

                // Log usage with fallback provider
                AiUsageLog::logUsage(
                    $this->user->id,
                    $fallbackProvider,
                    $fallbackModel,
                    $inputTokens,
                    $outputTokens,
                    $requestType,
                    $conversationId,
                    $leadId,
                    ['fallback' => true]
                );

                return [
                    'success' => true,
                    'content' => $content,
                    'usage' => [
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                        'total_tokens' => $inputTokens + $outputTokens,
                    ],
                    'provider' => $fallbackProvider,
                    'model' => $fallbackModel,
                    'fallback' => true,
                ];
            }

            return [
                'success' => false,
                'error' => 'Both primary and fallback providers failed',
                'content' => null,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Fallback provider also failed: ' . $e->getMessage(),
                'content' => null,
            ];
        }
    }

    /**
     * Log API usage
     */
    protected function logUsage(
        int $inputTokens,
        int $outputTokens,
        string $requestType,
        ?string $conversationId,
        ?string $leadId,
        ?array $metadata = null
    ): void {
        try {
            AiUsageLog::logUsage(
                $this->user->id,
                $this->provider,
                $this->model,
                $inputTokens,
                $outputTokens,
                $requestType,
                $conversationId,
                $leadId,
                $metadata
            );
        } catch (\Exception $e) {
            Log::error('Failed to log AI usage', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Test connection to the AI provider
     */
    public function testConnection(): array
    {
        if (!$this->hasValidApiKey()) {
            return [
                'success' => false,
                'message' => 'مفتاح API غير موجود',
                'provider' => $this->provider,
            ];
        }

        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl, [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'user', 'content' => 'test']
                    ],
                    'max_tokens' => 5,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'الاتصال ناجح - OpenAI',
                    'provider' => $this->provider,
                    'model' => $this->model,
                ];
            }

            return [
                'success' => false,
                'message' => 'فشل الاتصال: ' . $response->status(),
                'provider' => $this->provider,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'خطأ في الاتصال: ' . $e->getMessage(),
                'provider' => $this->provider,
            ];
        }
    }

    /**
     * Get available OpenAI models
     */
    public function getAvailableModels(): array
    {
        return config('services.ai.openai_models', [
            'gpt-4.1-mini' => 'GPT-4.1 Mini (balanced)',
            'gpt-4o-mini' => 'GPT-4o Mini (سريع وموفر)',
            'gpt-4o' => 'GPT-4o (متقدم)',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (اقتصادي)',
        ]);
    }

    /**
     * Get all available models
     */
    public static function getAllModels(): array
    {
        return [
            'openai' => config('services.ai.openai_models', [
                'gpt-4.1-mini' => 'GPT-4.1 Mini (balanced - recommended)',
                'gpt-4o-mini' => 'GPT-4o Mini (سريع وموفر - موصى به)',
                'gpt-4o' => 'GPT-4o (متقدم)',
                'gpt-4-turbo' => 'GPT-4 Turbo',
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo (اقتصادي)',
            ]),
        ];
    }

    /**
     * Get usage statistics for the user
     */
    public function getUsageStats(string $period = 'month'): array
    {
        return [
            'today' => AiUsageLog::getTodayUsage($this->user->id),
            'month' => AiUsageLog::getMonthUsage($this->user->id),
            'by_model' => AiUsageLog::getUsageByModel($this->user->id, $period),
            'by_type' => AiUsageLog::getUsageByType($this->user->id, $period),
            'daily' => AiUsageLog::getDailyUsage($this->user->id, 30),
        ];
    }
}
