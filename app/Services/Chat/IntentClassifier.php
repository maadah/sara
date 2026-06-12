<?php

namespace App\Services\Chat;

use App\Enums\Intent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IntentClassifier — classifies the user's intent using gpt-4.1-nano.
 *
 * Sends the current message + last 2 messages + the full intent list
 * to gpt-4.1-nano and receives a JSON response with intent name + confidence.
 * If confidence < threshold, returns Intent::UNKNOWN.
 * Max tokens: 150.
 */
class IntentClassifier
{
    public function __construct(
        private readonly PromptBuilder $promptBuilder,
    ) {}

    /**
     * Classify the user's intent.
     *
     * @param  string  $message       Current user message.
     * @param  array   $lastMessages  Last 2 messages for context.
     * @param  array   $config        Optional AI configuration (api_key, base_url, model).
     * @return array{intent: Intent, confidence: float}
     */
    public function classify(string $message, array $lastMessages = [], array $config = []): array
    {
        try {
            $messages = $this->promptBuilder->buildIntentPrompt($message, $lastMessages);

            $response = $this->callApi($messages, $config);

            $parsed = $this->parseResponse($response);

            Log::info('Chat: intent classified', [
                'intent'     => $parsed['intent']->value,
                'confidence' => $parsed['confidence'],
                'message'    => mb_substr($message, 0, 80),
            ]);

            return $parsed;
        } catch (\Throwable $e) {
            Log::error('Chat: intent classification failed', [
                'error'   => $e->getMessage(),
                'message' => mb_substr($message, 0, 80),
            ]);

            return [
                'intent'     => Intent::UNKNOWN,
                'confidence' => 0.0,
            ];
        }
    }

    /* ------------------------------------------------------------------ */
    /* Private                                                             */
    /* ------------------------------------------------------------------ */

    private function callApi(array $messages, array $config = []): string
    {
        $model     = $config['model'] ?? config('chat.models.classification', 'gpt-4.1-nano');
        $maxTokens = config('chat.tokens.intent_classification', 150);
        $timeout   = config('chat.openai.timeout', 30);
        $apiKey    = $config['api_key'] ?? config('chat.openai.api_key');
        $baseUrl   = $config['base_url'] ?? config('chat.openai.base_url', 'https://api.openai.com/v1');

        if (empty($apiKey)) {
            Log::error('Chat: intent classification skipped - NO API KEY');
            return '{}';
        }

        $response = Http::timeout($timeout)
            ->withoutVerifying()
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json',
            ])
            ->post("{$baseUrl}/chat/completions", [
                'model'       => $model,
                'messages'    => $messages,
                'max_tokens'  => $maxTokens,
                'temperature' => 0.1,
            ]);

        $body = $response->json();

        // Log token usage
        $usage = $body['usage'] ?? [];
        Log::debug('Chat: intent classifier tokens', [
            'model'         => $model,
            'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
            'completion_tokens' => $usage['completion_tokens'] ?? 0,
        ]);

        return $body['choices'][0]['message']['content'] ?? '{}';
    }

    private function parseResponse(string $raw): array
    {
        $threshold = config('chat.conversation.intent_confidence_threshold', 0.50);

        // Strip markdown code fences if present
        $raw = preg_replace('/```json?\s*|```/', '', $raw);
        $raw = trim($raw);

        $data = json_decode($raw, true);

        if (! $data || ! isset($data['intent'])) {
            return ['intent' => Intent::UNKNOWN, 'confidence' => 0.0];
        }

        $intentValue = $data['intent'];
        $confidence  = (float) ($data['confidence'] ?? 0.0);

        // Try to match the returned intent string to our enum
        $intent = Intent::tryFrom($intentValue);

        if (! $intent || $confidence < $threshold) {
            return ['intent' => Intent::UNKNOWN, 'confidence' => $confidence];
        }

        return ['intent' => $intent, 'confidence' => $confidence];
    }
}
