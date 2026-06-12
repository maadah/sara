<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * EntityExtractor — extracts structured entities from user messages using gpt-4.1-nano.
 *
 * Entities include: product_name, category_name, quantity, color, size,
 * selection_number, customer_name, customer_phone, customer_address,
 * customer_city, faq_topic.
 *
 * Phone numbers are validated against Iraqi format (07[3-9]XXXXXXXX).
 * Quantities in Arabic words are normalised to integers.
 * Max tokens: 200.
 */
class EntityExtractor
{
    public function __construct(
        private readonly PromptBuilder $promptBuilder,
    ) {}

    /**
     * Extract entities from the user's message.
     *
     * @param  string $message Current user message.
     * @param  string $state   Current conversation state string.
     * @param  array  $config  Optional AI configuration (api_key, base_url, model).
     * @return array  Associative array of extracted entities (empty if none).
     */
    public function extract(string $message, string $state = 'idle', array $config = []): array
    {
        try {
            $messages = $this->promptBuilder->buildEntityPrompt($message, $state);

            $raw = $this->callApi($messages, $config);

            $entities = $this->parseAndValidate($raw);

            Log::info('Chat: entities extracted', [
                'entities' => $entities,
                'message'  => mb_substr($message, 0, 80),
            ]);

            return $entities;
        } catch (\Throwable $e) {
            Log::error('Chat: entity extraction failed', [
                'error'   => $e->getMessage(),
                'message' => mb_substr($message, 0, 80),
            ]);

            return [];
        }
    }

    /* ------------------------------------------------------------------ */
    /* Private                                                             */
    /* ------------------------------------------------------------------ */

    private function callApi(array $messages, array $config = []): string
    {
        $model     = $config['model'] ?? config('chat.models.classification', 'gpt-4.1-nano');
        $maxTokens = config('chat.tokens.entity_extraction', 200);
        $timeout   = config('chat.openai.timeout', 30);
        $apiKey    = $config['api_key'] ?? config('chat.openai.api_key');
        $baseUrl   = $config['base_url'] ?? config('chat.openai.base_url', 'https://api.openai.com/v1');

        if (empty($apiKey)) {
            Log::error('Chat: entity extraction skipped - NO API KEY');
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
                'temperature' => 0.0,
            ]);

        $body = $response->json();

        $usage = $body['usage'] ?? [];
        Log::debug('Chat: entity extractor tokens', [
            'model'              => $model,
            'prompt_tokens'      => $usage['prompt_tokens'] ?? 0,
            'completion_tokens'  => $usage['completion_tokens'] ?? 0,
        ]);

        return $body['choices'][0]['message']['content'] ?? '{}';
    }

    private function parseAndValidate(string $raw): array
    {
        // Strip markdown code fences
        $raw = preg_replace('/```json?\s*|```/', '', $raw);
        $raw = trim($raw);

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return [];
        }

        $entities = [];

        // Map only known entity keys
        $allowedKeys = [
            'product_name', 'category_name', 'quantity', 'color', 'size',
            'selection_number', 'customer_name', 'customer_phone',
            'customer_address', 'customer_city', 'faq_topic',
        ];

        foreach ($allowedKeys as $key) {
            if (isset($data[$key]) && $data[$key] !== '' && $data[$key] !== null) {
                $entities[$key] = $data[$key];
            }
        }

        // Normalise quantity to integer
        if (isset($entities['quantity'])) {
            $entities['quantity'] = $this->normaliseQuantity($entities['quantity']);
        }

        // Validate Iraqi phone format
        if (isset($entities['customer_phone'])) {
            $entities['customer_phone'] = $this->validatePhone($entities['customer_phone']);
        }

        // Normalise selection_number to integer
        if (isset($entities['selection_number'])) {
            $entities['selection_number'] = $this->normaliseSelectionNumber($entities['selection_number']);
        }

        return $entities;
    }

    /**
     * Normalise Arabic quantity words to integer.
     */
    private function normaliseQuantity(mixed $value): int
    {
        if (is_numeric($value)) {
            return max(1, (int) $value);
        }

        $map = [
            'واحد'   => 1, 'وحده'  => 1, 'قطعه'   => 1, 'قطعة' => 1,
            'اثنين'  => 2, 'ثنين'  => 2, 'قطعتين' => 2, 'زوج'  => 2, 'جوز' => 2,
            'ثلاث'   => 3, 'ثلاثه' => 3, 'ثلاثة'  => 3,
            'اربع'   => 4, 'اربعه' => 4, 'اربعة'  => 4,
            'خمس'    => 5, 'خمسه'  => 5, 'خمسة'   => 5,
            'ست'     => 6, 'سته'   => 6, 'ستة'    => 6,
            'سبع'    => 7, 'سبعه'  => 7, 'سبعة'   => 7,
            'ثمان'   => 8, 'ثمانيه' => 8, 'ثمانية' => 8,
            'تسع'    => 9, 'تسعه'  => 9, 'تسعة'   => 9,
            'عشر'    => 10, 'عشره' => 10, 'عشرة'  => 10,
        ];

        $normalised = $this->normaliseArabic((string) $value);

        return $map[$normalised] ?? 1;
    }

    /**
     * Validate and clean Iraqi phone number. Returns null if invalid.
     */
    private function validatePhone(string $phone): ?string
    {
        // Remove spaces, dashes
        $phone = preg_replace('/[\s\-\(\)]+/', '', $phone);

        // Prefix +964 → 0
        if (str_starts_with($phone, '+964')) {
            $phone = '0' . substr($phone, 4);
        }
        if (str_starts_with($phone, '964')) {
            $phone = '0' . substr($phone, 3);
        }

        $pattern = config('chat.phone.pattern', '/^07[3-9]\d{8}$/');

        return preg_match($pattern, $phone) ? $phone : null;
    }

    /**
     * Normalise Arabic ordinals / selection numbers to integer.
     */
    private function normaliseSelectionNumber(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        $map = [
            'الاول'  => 1, 'اول'   => 1, 'الأول' => 1,
            'الثاني' => 2, 'ثاني'  => 2,
            'الثالث' => 3, 'ثالث'  => 3,
            'الرابع' => 4, 'رابع'  => 4,
            'الخامس' => 5, 'خامس'  => 5,
        ];

        $normalised = $this->normaliseArabic((string) $value);

        // Try #N format
        if (preg_match('/(\d+)/', (string) $value, $m)) {
            return (int) $m[1];
        }

        return $map[$normalised] ?? 1;
    }

    /**
     * Basic Arabic normalisation (used for matching).
     */
    private function normaliseArabic(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
        $text = str_replace('ة', 'ه', $text);
        $text = str_replace('ى', 'ي', $text);

        return $text;
    }
}
