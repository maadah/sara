<?php

namespace App\Services\Chat;

use App\Enums\ConversationState;
use App\Enums\Intent;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\MissedIntent;
use App\Services\Chat\Tools\CartTool;
use App\Services\Chat\Tools\ProductSearchTool;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * ChatOrchestrator — the single entry point for processing an incoming customer message.
 *
 * Coordinates every step of the conversation pipeline:
 * 1. Session load / create / expire check.
 * 2. Rate-limit check.
 * 3. Intent classification (gpt-4.1-nano).
 * 4. Entity extraction (gpt-4.1-nano) — done in parallel with step 3 when possible.
 * 5. AI reply generation (gpt-4.1-mini) via ConversationEngine.
 * 6. State transition via StateMachine.
 * 7. Lead scoring.
 * 8. Upsell / marketing intelligence.
 * 9. Persist messages + session.
 * 10. Return structured response.
 */
class ChatOrchestrator
{
    public function __construct(
        private readonly SessionManager $sessionManager,
        private readonly IntentClassifier $intentClassifier,
        private readonly EntityExtractor $entityExtractor,
        private readonly ConversationEngine $engine,
        private readonly StateMachine $stateMachine,
        private readonly CustomerProfileManager $profileManager,
        private readonly ProductSearchTool $productSearch,
        private readonly CartTool $cartTool,
    ) {}

    /**
     * Process an incoming customer message and return the AI reply.
     *
     * @param  int         $storeId         The store (user) ID.
     * @param  int         $leadId          The lead ID.
     * @param  string      $message         The customer's message text.
     * @param  string|null $conversationId  Social media thread ID (nullable for web).
     * @param  string      $channel         'facebook' | 'instagram' | 'web'
     * @return array{reply: string, images: array, products: array, actions: array, session_id: int}
     */
    public function processMessage(
        int $storeId,
        int $leadId,
        string $message,
        ?string $conversationId = null,
        string $channel = 'web',
    ): array {
        // ── Step 1: Session ──
        $session = $this->sessionManager->loadOrCreate($storeId, $leadId, $conversationId, $channel);

        // ── Step 1.5: AI Config (multi-tenant) ──
        $aiConfig = $this->getStoreAiConfig($storeId);

        // ── Rate limit ──
        $rateLimitKey = "chat:{$storeId}:{$leadId}";
        $maxPerMinute = config('chat.rate_limit.max_per_minute', 20);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxPerMinute)) {
            Log::warning('Chat: rate limit exceeded', [
                'store_id' => $storeId,
                'lead_id'  => $leadId,
            ]);

            return $this->quickReply($session, __('chat.error_rate_limit'));
        }
        RateLimiter::hit($rateLimitKey, 60);

        // ── Step 2 + 3: Intent + Entity (ideally parallel) ──
        $lastMessages = array_slice($session->history ?? [], -4); // last 2 pairs

        $intentResult = $this->intentClassifier->classify($message, $lastMessages, $aiConfig);
        $entities     = $this->entityExtractor->extract($message, $session->state->value, $aiConfig);

        $intent     = $intentResult['intent'];
        $confidence = $intentResult['confidence'];

        // ── Smart intent override: "لا شكر" while awaiting confirmation + full cart = confirm ──
        // In Iraqi dialect, "لا شكر" after "هل تؤكد الطلب؟" means "yes please, proceed"
        if (
            in_array($intent, [Intent::CANCEL_ORDER, Intent::FEEDBACK, Intent::UNKNOWN])
            && $session->state === ConversationState::AWAITING_CONFIRMATION
            && $session->hasCompleteCustomerData()
            && $session->hasCartItems()
            && mb_strlen(trim($message)) < 30
            && preg_match('/شكر|ممنون|مشكور/u', $message)
        ) {
            $intent = Intent::CONFIRM_ORDER;
        }

        // Log missed intent
        if ($intent === Intent::UNKNOWN) {
            $this->logMissedIntent($session, $message);
        }

        // ── Merge entity-based customer data into session ──
        $this->mergeEntitiesIntoSession($session, $entities);

        // ── Step 4: State transition ──
        $this->stateMachine->transition($session, $intent, ['entities' => $entities]);

        // ── Step 5: Lead scoring based on intent ──
        $this->scoreByIntent($session, $intent);

        // ── Step 6: Tone detection ──
        $this->detectTone($session, $message);

        // ── Step 7: Browse nudge tracking ──
        $this->trackBrowseCount($session, $intent);

        // ── Step 8: Generate AI reply via ConversationEngine ──
        $engineResult = $this->engine->generateReply(
            $session,
            $message,
            $intent->value,
            $entities,
            $aiConfig,
        );

        $reply      = $engineResult['reply'];
        $images     = $engineResult['images'];
        $products   = $engineResult['products'];
        $toolCalls  = $engineResult['tool_calls'];
        $tokensUsed = $engineResult['tokens_used'];

        // ── Step 9: Post-processing — upsell, promotion, returning customer ──
        $reply = $this->applyMarketingIntelligence($session, $reply, $intent);

        // ── Split reply into natural message parts ([MSG] separator) ──
        $messageParts = array_values(array_filter(
            array_map('trim', explode('[MSG]', $reply)),
            fn ($p) => $p !== '',
        ));
        if (empty($messageParts)) {
            $messageParts = [$reply];
        }
        $fullReplyText = implode("\n", $messageParts);

        // ── Step 10: Save messages ──
        $this->saveMessages($session, $message, $fullReplyText, $intent, $entities, $toolCalls, $tokensUsed);

        // ── Step 11: Persist session ──
        $this->sessionManager->save($session);

        return [
            'reply'      => $messageParts[0],  // first part (backward compat)
            'messages'   => $messageParts,      // all parts for multi-bubble send
            'images'     => $images,
            'products'   => $products,
            'actions'    => $this->resolveActions($session),
            'session_id' => $session->id,
        ];
    }

    /**
     * Get analytics summary for a session.
     */
    public function getSessionStats(int $sessionId): array
    {
        $session  = ChatSession::findOrFail($sessionId);
        $messages = ChatMessage::where('session_id', $sessionId)->get();

        $totalTokens = $messages->sum('tokens_used');

        // Rough cost estimate: $0.40/1M input + $1.60/1M output for mini
        $estimatedCost = ($totalTokens / 1_000_000) * 1.0;

        $intentDist = $messages
            ->whereNotNull('intent')
            ->groupBy('intent')
            ->map->count()
            ->toArray();

        $toolsCalled = $messages
            ->whereNotNull('tool_calls')
            ->flatMap(function ($m) {
                // Ensure tool_calls is an array or collection before plucking
                $calls = is_array($m->tool_calls) ? $m->tool_calls : [];
                return collect($calls)->pluck('function.name');
            })
            ->countBy()
            ->toArray();

        $outcome = match (true) {
            $session->state === ConversationState::ORDER_CONFIRMED => 'order_placed',
            $session->cart_abandoned_at !== null                   => 'abandoned',
            $session->state === ConversationState::ORDER_CANCELLED => 'cancelled',
            default                                                => 'still_browsing',
        };

        return [
            'session_id'      => $sessionId,
            'total_messages'  => $messages->count(),
            'total_tokens'    => $totalTokens,
            'estimated_cost'  => round($estimatedCost, 4),
            'intent_distribution' => $intentDist,
            'tools_called'    => $toolsCalled,
            'outcome'         => $outcome,
            'state'           => $session->state->value,
        ];
    }

    /* ================================================================== */
    /* Private methods                                                     */
    /* ================================================================== */

    /**
     * Quick reply without going through the full engine (for rate-limit, errors).
     */
    private function quickReply(ChatSession $session, string $text): array
    {
        return [
            'reply'      => $text,
            'images'     => [],
            'products'   => [],
            'actions'    => [],
            'session_id' => $session->id,
        ];
    }

    /**
     * Merge entity-extracted customer data into the session's customer_data bag.
     */
    private function mergeEntitiesIntoSession(ChatSession $session, array $entities): void
    {
        // Core contact fields — synced to both session.customer_data and profile
        $fields = [];
        if (isset($entities['customer_name']))    $fields['name']    = $entities['customer_name'];
        if (isset($entities['customer_phone']))   $fields['phone']   = $entities['customer_phone'];
        if (isset($entities['customer_address'])) $fields['address'] = $entities['customer_address'];
        if (isset($entities['customer_city']))    $fields['city']    = $entities['customer_city'];

        if (! empty($fields)) {
            $this->sessionManager->mergeCustomerData($session, $fields);
        }

        // Demographic fields — saved to profile directly
        $demographics = [];
        if (isset($entities['customer_age']))        $demographics['age']        = (int) $entities['customer_age'];
        if (isset($entities['customer_gender']))     $demographics['gender']     = $entities['customer_gender'];
        if (isset($entities['customer_occupation'])) $demographics['occupation'] = $entities['customer_occupation'];
        if (isset($entities['customer_budget'])) {
            $budget = (int) $entities['customer_budget'];
            $demographics['budget_max'] = $budget;
        }
        if (isset($entities['customer_interests'])) {
            // Interests can be comma-separated string or already an array
            $raw = $entities['customer_interests'];
            $demographics['interests'] = is_array($raw)
                ? $raw
                : array_map('trim', explode(',', $raw));
        }

        // Merge all into profile
        $allFields = array_merge($fields, $demographics);
        if (! empty($allFields)) {
            $this->profileManager->mergeData($session, $allFields);
        }
    }

    /**
     * Award lead score points based on the detected intent.
     */
    private function scoreByIntent(ChatSession $session, Intent $intent): void
    {
        $map = [
            Intent::BROWSE_GENERAL->value      => 'browse_category',
            Intent::BROWSE_CATEGORY->value     => 'browse_category',
            Intent::SEARCH_PRODUCT->value      => 'ask_product',
            Intent::ASK_PRICE->value           => 'ask_price',
            Intent::ASK_DELIVERY->value        => 'ask_delivery',
            Intent::ASK_AVAILABILITY->value    => 'ask_delivery',
            Intent::ADD_TO_CART->value         => 'add_to_cart',
            Intent::CHECKOUT->value            => 'reach_checkout',
            Intent::PROVIDE_NAME->value        => 'provide_info',
            Intent::PROVIDE_PHONE->value       => 'provide_info',
            Intent::PROVIDE_ADDRESS->value     => 'provide_info',
            Intent::CONFIRM_ORDER->value       => 'complete_order',
            Intent::CANCEL_ORDER->value        => 'cancel_order',
        ];

        $event = $map[$intent->value] ?? null;

        if ($event) {
            $this->profileManager->adjustScoreForEvent($session, $event);
        }
    }

    /**
     * Simple customer tone detection from message patterns.
     */
    private function detectTone(ChatSession $session, string $message): void
    {
        $msg = mb_strtolower($message);

        $tone = 'neutral';

        // Excited
        if (preg_match('/[😊🔥💪❤️😍]|حلو|ممتاز|يبه|واو/u', $msg)) {
            $tone = 'excited';
        }
        // Frustrated
        elseif (preg_match('/غالي|مو زين|ما عجب|سيء|خرب|مشكله/u', $msg) || mb_strlen($msg) > 200) {
            $tone = 'frustrated';
        }
        // Hesitant
        elseif (preg_match('/مو عارف|افكر|شايف|ما ادري|وارد|يمكن/u', $msg)) {
            $tone = 'hesitant';
        }

        $session->setMeta('tone', $tone);
    }

    /**
     * Track browse count and inject a nudge when threshold reached.
     */
    private function trackBrowseCount(ChatSession $session, Intent $intent): void
    {
        if ($intent->isBrowsingIntent()) {
            $count = ($session->getMeta('browse_count', 0)) + 1;
            $session->setMeta('browse_count', $count);
        }

        // Reset on add to cart
        if ($intent === Intent::ADD_TO_CART) {
            $session->setMeta('browse_count', 0);
        }
    }

    /**
     * Apply marketing intelligence to the reply:
     * - Promotion injection
     * - Returning customer greeting
     * - Browse nudge
     * - Stock urgency (handled by AI via tool results, but we can add to reply)
     */
    private function applyMarketingIntelligence(ChatSession $session, string $reply, Intent $intent): string
    {
        $profile = $this->profileManager->getOrCreate($session);

        // Returning customer recognition (only on greeting, first message)
        if ($intent === Intent::GREETING && $session->getMeta('browse_count', 0) <= 1) {
            if ($profile->isVip()) {
                $reply = __('chat.greeting_vip') . "\n" . $reply;
            } elseif ($profile->isReturning()) {
                $reply = __('chat.greeting_returning') . "\n" . $reply;
            }
        }

        // Browse nudge
        $browseThreshold = config('chat.upsell.browse_nudge_count', 3);
        if ($session->getMeta('browse_count', 0) === $browseThreshold) {
            $reply .= __('chat.browse_nudge');
        }

        return $reply;
    }

    /**
     * Persist user and assistant messages to the chat_messages table and update history.
     */
    private function saveMessages(
        ChatSession $session,
        string $userMessage,
        string $assistantReply,
        Intent $intent,
        array $entities,
        array $toolCalls,
        int $tokensUsed,
    ): void {
        // Save user message
        ChatMessage::create([
            'session_id' => $session->id,
            'role'       => 'user',
            'content'    => $userMessage,
            'intent'     => $intent->value,
            'entities'   => $entities ?: null,
            'created_at' => now(),
        ]);

        // Save assistant reply
        ChatMessage::create([
            'session_id'  => $session->id,
            'role'        => 'assistant',
            'content'     => $assistantReply,
            'tool_calls'  => $toolCalls ?: null,
            'tokens_used' => $tokensUsed,
            'created_at'  => now(),
        ]);

        // Append to session history (trimmed)
        $this->sessionManager->appendHistory($session, ['role' => 'user', 'content' => $userMessage]);
        $this->sessionManager->appendHistory($session, ['role' => 'assistant', 'content' => $assistantReply]);
    }

    /**
     * Log a missed intent for future training.
     */
    private function logMissedIntent(ChatSession $session, string $message): void
    {
        try {
            MissedIntent::create([
                'store_id'       => $session->store_id,
                'session_id'     => $session->id,
                'raw_message'    => $message,
                'detected_state' => $session->state->value,
            ]);
        } catch (\Throwable $e) {
            Log::debug('Chat: could not log missed intent', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Build action hints for the frontend / API consumer.
     */
    private function resolveActions(ChatSession $session): array
    {
        $actions = [];

        if ($session->state === ConversationState::HUMAN_HANDOVER) {
            $actions[] = 'human_handover';
        }

        if ($session->state === ConversationState::ORDER_CONFIRMED) {
            $actions[] = 'order_confirmed';
        }

        return $actions;
    }

    /**
     * Fetch store-specific AI settings and build a config array for sub-services.
     */
    private function getStoreAiConfig(int $storeId): array
    {
        $settings = \App\Models\AiSetting::where('user_id', $storeId)->first();

        if (! $settings) {
            return []; // Fallback to global config
        }

        $provider = $settings->ai_provider ?? 'openai';
        $apiKey   = null;
        $baseUrl  = null;
        $model    = null;

        // Try to get specific settings first
        if ($provider === 'groq') {
            $apiKey  = $settings->groq_api_key;
            $baseUrl = 'https://api.groq.com/openai/v1';
            $model   = $settings->groq_model ?: 'llama-3.3-70b-versatile';
        } elseif ($provider === 'openrouter') {
            $apiKey  = $settings->openai_api_key;
            $baseUrl = 'https://openrouter.ai/api/v1';
            $model   = $settings->openai_model ?: 'gpt-4.1-mini';
        } else {
            // Default OpenAI
            $apiKey  = $settings->openai_api_key;
            $baseUrl = 'https://api.openai.com/v1';
            $model   = $settings->openai_model ?: 'gpt-4.1-mini';
        }

        // ── FALLBACK: If preferred provider has NO key, try ANY available key ──
        if (empty($apiKey)) {
            if (! empty($settings->groq_api_key)) {
                $apiKey  = $settings->groq_api_key;
                $baseUrl = 'https://api.groq.com/openai/v1';
                $model   = $settings->groq_model ?: 'llama-3.3-70b-versatile';
            } elseif (! empty($settings->openai_api_key)) {
                $apiKey  = $settings->openai_api_key;
                $baseUrl = 'https://api.openai.com/v1';
                $model   = $settings->openai_model ?: 'gpt-4.1-mini';
            }
        }

        return array_filter([
            'api_key'  => $apiKey,
            'base_url' => $baseUrl,
            'model'    => $model,
        ]);
    }
}
