<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ConversationEngine — builds and executes the main AI conversation call (gpt-4.1-mini).
 *
 * This class owns Steps 4-7 of the message processing flow:
 * 4. Context assembly (history + filtered tools).
 * 5. AI call (gpt-4.1-mini with tools).
 * 6. Tool execution loop (max 3 iterations).
 * 7. Response validation.
 *
 * Returns the final validated reply along with any images or product IDs to show.
 */
class ConversationEngine
{
    public function __construct(
        private readonly PromptBuilder $promptBuilder,
        private readonly StoreContextBuilder $storeContext,
        private readonly ToolExecutor $toolExecutor,
        private readonly ResponseValidator $validator,
        private readonly CustomerProfileManager $profileManager,
    ) {}

    /**
     * Generate the AI reply for a user message within a session.
     *
     * @param  ChatSession $session      The loaded session.
     * @param  string      $userMessage  The current user message.
     * @param  string      $intentValue  Classified intent value (e.g. 'browse_general').
     * @param  array       $entities     Extracted entities.
     * @param  array       $config       Optional AI configuration (api_key, base_url, model).
     * @return array{reply: string, images: array, products: array, tool_calls: array, tokens_used: int}
     */
    public function generateReply(
        ChatSession $session,
        string $userMessage,
        string $intentValue,
        array $entities = [],
        array $config = [],
    ): array {
        // Step 4 — Context assembly
        $systemPrompt = $this->storeContext->getSystemPrompt($session->store_id);
        $history       = $session->history ?? [];
        $contextHint   = $this->buildContextHint($session, $intentValue, $entities);
        $tools         = $this->promptBuilder->getToolsForState($session->state);

        $messages = $this->promptBuilder->buildConversationMessages(
            $systemPrompt,
            array_slice($history, -(config('chat.conversation.max_history_pairs', 10) * 2)),
            $userMessage,
            $contextHint,
        );

        // Step 5 — AI call
        $maxLoops   = config('chat.conversation.max_tool_loops', 3);
        $totalTokens = 0;
        $allToolCalls = [];
        $allToolResults = [];
        $reply       = '';
        $images      = [];
        $productIds  = [];

        for ($loop = 0; $loop < $maxLoops; $loop++) {
            $response = $this->callOpenAI($messages, $tools, $config);

            if ($response === null) {
                // Fallback: API failure after retries
                return $this->fallbackResponse();
            }

            $totalTokens += $response['usage']['total_tokens'] ?? 0;
            $choice       = $response['choices'][0] ?? [];
            $message       = $choice['message'] ?? [];
            $finishReason  = $choice['finish_reason'] ?? 'stop';

            // Step 6 — Tool execution loop
            if ($finishReason === 'tool_calls' || ! empty($message['tool_calls'])) {
                $toolCalls = $message['tool_calls'] ?? [];
                $allToolCalls = array_merge($allToolCalls, $toolCalls);

                // Add assistant message with tool calls
                $messages[] = $message;

                // Execute tools and inject results
                $results = $this->toolExecutor->executeMany($toolCalls, $session);
                $allToolResults = array_merge($allToolResults, $results);

                foreach ($results as $callId => $res) {
                    $messages[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $callId,
                        'content'      => json_encode($res['result'], JSON_UNESCAPED_UNICODE),
                    ];

                    // Collect product IDs and images from results
                    $this->collectProductData($res['name'] ?? '', $res['result'], $productIds, $images);

                    // ── Persist last search results for context recovery ──
                    // Saves product IDs alongside names so the AI can always call
                    // add_to_cart with the correct product_id even after history truncation.
                    if (($res['name'] ?? '') === 'search_products'
                        && ($res['result']['status'] ?? '') === 'found'
                        && ! empty($res['result']['products'])
                    ) {
                        $lines = [];
                        foreach ($res['result']['products'] as $i => $p) {
                            $stock   = $p['quantity'] > 0 ? "متوفر: {$p['quantity']}" : 'نفذ المخزون';
                            // Include [id:X] so AI always has the correct product_id for add_to_cart
                            $lines[] = '#' . ($i + 1) . ' [id:' . $p['id'] . '] ' . $p['name'] . ' — ' . $p['price'] . ' د.ع — ' . $stock;
                        }
                        $session->setMeta('last_search_results', implode("\n", $lines));

                        // Save product interests to Lead CRM for admin visibility
                        $this->saveProductInterests($session, $res['result']['products']);
                    }

                    // ── Sync demographic profile data into session meta ──
                    if (($res['name'] ?? '') === 'save_customer_data'
                        && ($res['result']['status'] ?? '') === 'saved'
                    ) {
                        $this->syncDemographicsToMeta($session);
                    }
                }

                continue; // Go back to AI with tool results
            }

            // No more tool calls — we have the text reply
            $reply = $message['content'] ?? '';
            break;
        }

        // Step 7 — Response validation
        $outOfScope = $this->getOutOfScopeMessage($session->store_id);
        $validated  = $this->validator->validate($reply, $allToolResults, $outOfScope);

        if ($validated['modified']) {
            Log::info('Chat: response modified by validator', [
                'session_id' => $session->id,
                'checks'     => $validated['checks'],
            ]);
        }

        return [
            'reply'       => $validated['reply'],
            'images'      => array_unique($images),
            'products'    => array_unique($productIds),
            'tool_calls'  => $allToolCalls,
            'tokens_used' => $totalTokens,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* OpenAI API Call                                                      */
    /* ------------------------------------------------------------------ */

    private function callOpenAI(array $messages, array $tools, array $config = []): ?array
    {
        $model     = $config['model'] ?? config('chat.models.conversation', 'gpt-4.1-mini');
        $maxTokens = config('chat.tokens.conversation_reply', 600);
        $temp      = config('chat.conversation.temperature', 0.4);
        $timeout   = config('chat.openai.timeout', 30);
        $apiKey    = $config['api_key'] ?? config('chat.openai.api_key');
        $baseUrl   = $config['base_url'] ?? config('chat.openai.base_url', 'https://api.openai.com/v1');

        if (empty($apiKey)) {
            Log::error('Chat: conversation reply skipped - NO API KEY');
            return null;
        }

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => $temp,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
        }

        $retryTimes = config('chat.openai.retry.times', 1);
        $retryDelay = config('chat.openai.retry.delay', 2000);

        for ($attempt = 0; $attempt <= $retryTimes; $attempt++) {
            try {
                $response = Http::timeout($timeout)
                    ->withoutVerifying()
                    ->withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                        'Content-Type'  => 'application/json',
                    ])
                    ->post("{$baseUrl}/chat/completions", $payload);

                if ($response->successful()) {
                    $body = $response->json();

                    Log::debug('Chat: AI call tokens', [
                        'model'              => $model,
                        'prompt_tokens'      => $body['usage']['prompt_tokens'] ?? 0,
                        'completion_tokens'  => $body['usage']['completion_tokens'] ?? 0,
                        'total_tokens'       => $body['usage']['total_tokens'] ?? 0,
                        'attempt'            => $attempt + 1,
                    ]);

                    return $body;
                }

                Log::warning('Chat: OpenAI API non-success', [
                    'status'  => $response->status(),
                    'body'    => mb_substr($response->body(), 0, 500),
                    'attempt' => $attempt + 1,
                ]);
            } catch (\Throwable $e) {
                Log::error('Chat: OpenAI API call failed', [
                    'error'   => $e->getMessage(),
                    'attempt' => $attempt + 1,
                ]);
            }

            if ($attempt < $retryTimes) {
                usleep($retryDelay * 1000);
            }
        }

        return null;
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Build a concise context hint to inject alongside the user message.
     */
    private function buildContextHint(ChatSession $session, string $intent, array $entities): ?string
    {
        $hints = [];

        $hints[] = "نية الزبون: {$intent}";

        if (! empty($entities)) {
            $entityStr = collect($entities)
                ->map(fn ($v, $k) => "{$k}: {$v}")
                ->implode(', ');
            $hints[] = "كيانات مستخرجة: {$entityStr}";
        }

        // When the customer is asking about a specific product or category, force the AI to
        // call search_products before composing any reply — prevents hallucinating
        // "we don't have X" without actually checking the catalogue.
        $searchIntents = ['ask_price', 'search_product', 'browse_category', 'ask_availability', 'add_to_cart'];
        if (in_array($intent, $searchIntents) && ! empty($entities['product_name'])) {
            $hints[] = "⚠️ يجب استدعاء search_products(query='{$entities['product_name']}') الآن قبل كتابة أي رد. إذا رجع not_found، جرب search_products بكلمة مفردة أقصر أو استدعِ get_categories لرؤية ما هو متوفر";
        }
        if (in_array($intent, $searchIntents) && ! empty($entities['category_name'])) {
            $hints[] = "⚠️ الزبون يبحث عن فئة: '{$entities['category_name']}' — استدعِ search_products(query='{$entities['category_name']}') الآن. إذا رجع not_found، استدعِ get_categories لإيجاد الفئة المناسبة ثم ابحث من جديد قبل أي رد";
        }

        $hints[] = "حالة المحادثة: {$session->state->value}";

        if ($session->hasCartItems()) {
            $cartCount = count($session->cart['items'] ?? []);
            $cartTotal = $session->cart['grand_total'] ?? 0;
            $hints[]   = "السلة: {$cartCount} منتج، المجموع: {$cartTotal} د.ع";
        }

        // Cart reminder
        if ($session->wasCartAbandoned()) {
            $hints[] = '⚠️ الزبون رجع وعنده سلة مهملة — ذكره بها';
        }

        // ── Customer data: always show what's collected ──
        $data = $session->customer_data ?? [];
        $collected = [];
        if (! empty($data['name']))    $collected[] = 'الاسم: ' . $data['name'];
        if (! empty($data['phone']))   $collected[] = 'الهاتف: ' . $data['phone'];
        if (! empty($data['address'])) $collected[] = 'العنوان: ' . $data['address'];
        if (! empty($collected)) {
            $hints[] = 'بيانات الزبون المحفوظة: ' . implode('، ', $collected);
        }
        $missing = [];
        if (empty($data['name']))    $missing[] = 'الاسم';
        if (empty($data['phone']))   $missing[] = 'الهاتف';
        if (empty($data['address'])) $missing[] = 'العنوان';
        if (! empty($missing) && ($session->state->isOrderFlow() || $session->hasCartItems())) {
            $hints[] = 'بيانات ناقصة للطلب: ' . implode('، ', $missing);
        }

        // ── Demographic profile collected so far ──
        $demo = $session->getMeta('demographics', []);
        if (! empty($demo)) {
            $demoStr = collect($demo)
                ->map(fn ($v, $k) => "{$k}: " . (is_array($v) ? implode('، ', $v) : $v))
                ->implode(' | ');
            $hints[] = "الملف الديموغرافي: {$demoStr}";
        }

        // ── Smart demographic opportunity detection ──
        $orderFlowStates = ['awaiting_confirmation', 'order_confirmed', 'order_cancelled', 'pending_payment'];
        $inOrderFlow     = in_array($session->state->value, $orderFlowStates);

        if ($inOrderFlow) {
            $hints[] = '⛔ الطلب في مرحلة إتمام — لا تسأل أسئلة شخصية. ركز على إتمام الطلب فقط.';
        } else {
            // Conversation depth: how many messages have been exchanged this session
            $msgDepth = intdiv(count($session->history ?? []), 2); // pairs

            // All tracked demographic fields, in priority order
            $demoFields = [
                // field_key => [label, min_depth, trigger_intents, ask_example, ask_context]
                'gender'         => ['الجنس',              1, ['search_product', 'browse_category', 'ask_price'],
                                     'هل البحث لك شخصياً أم لشخص آخر؟',
                                     'when customer is browsing/searching products'],
                'age'            => ['العمر',               2, ['search_product', 'add_to_cart', 'browse_general'],
                                     'كم عمرك تقريباً؟ — أريد أقدم لك الأنسب',
                                     'when customer seems to be buying for self'],
                'budget_max'     => ['الميزانية',           1, ['search_product', 'ask_price', 'browse_category', 'browse_general'],
                                     'ما ميزانيتك التقريبية؟',
                                     'when customer hesitates between products or asks for price'],
                'interests'      => ['الاهتمامات',          3, ['browse_general', 'feedback', 'unknown'],
                                     'ما اهتماماتك؟ حتى أقترح عليك الأنسب',
                                     'in a friendly flow after some back-and-forth'],
                'occupation'     => ['المهنة',              4, ['browse_general', 'feedback'],
                                     'ما طبيعة عملك؟ — قد يساعدني في اقتراح الأنسب',
                                     'only in a very natural friendly flow'],
                'marital_status' => ['الحالة الاجتماعية',  5, ['search_product', 'browse_category'],
                                     'هل للعائلة أم لك شخصياً؟',
                                     'when buying items that differ by family vs. individual use'],
            ];

            // Find the best question to suggest this turn
            $suggestedField = null;
            $suggestedHint  = null;
            foreach ($demoFields as $col => [$label, $minDepth, $triggerIntents, $askExample, $askCtx]) {
                // Skip already-collected fields
                if (! empty($demo[$col])) {
                    continue;
                }
                // Skip if conversation is too new for this field
                if ($msgDepth < $minDepth) {
                    continue;
                }
                // Prefer fields whose trigger intent matches the current intent
                $intentMatches = in_array($intent, $triggerIntents);
                if ($intentMatches && $suggestedField === null) {
                    $suggestedField = $col;
                    $suggestedHint  = "💡 فرصة لجمع [{$label}]: ({$askCtx}) — صياغة مقترحة: \"" . $askExample . '"';
                }
            }

            // List all missing fields so AI is aware without being told to ask them all
            $allMissingDemo = [];
            foreach ($demoFields as $col => [$label]) {
                if (empty($demo[$col])) {
                    $allMissingDemo[] = $label;
                }
            }

            if ($suggestedHint) {
                $hints[] = $suggestedHint;
                $hints[] = '⚠️ اسأل فقط هذا السؤال الواحد — لا تسأل عن بقية البيانات في نفس الرد.';
            } elseif (! empty($allMissingDemo)) {
                // No perfect trigger match — just mention what's missing for background awareness
                $hints[] = '📋 بيانات ديموغرافية ناقصة (انتظر الفرصة الطبيعية المناسبة): ' . implode('، ', $allMissingDemo);
            }
        }

        // ── Farewell hint: order confirmed + customer saying thanks/goodbye ──
        if ($session->state->value === 'order_confirmed'
            && in_array($intent, ['feedback', 'greeting', 'unknown'])
        ) {
            $hints[] = '✅ الطلب تم تأكيده مسبقاً. إذا قال الزبون شكراً أو وداعاً، ودعه بحرارة وأخبره أن الفريق سيتواصل معه قريباً للتوصيل. لا تسأله أي شيء آخر.';
        }

        // ── Past product interests from Lead CRM ──
        if (! empty($session->lead_id)) {
            try {
                $lead = \App\Models\Lead::find($session->lead_id);
                if ($lead && ! empty($lead->interests)) {
                    $recentInterests = collect($lead->interests)
                        ->sortByDesc('date')->take(5)
                        ->pluck('product_name')->unique()->values()->toArray();
                    if (! empty($recentInterests)) {
                        $hints[] = 'منتجات اهتم بها الزبون في محادثات سابقة: ' . implode('، ', $recentInterests);
                    }
                }
            } catch (\Throwable $e) {
                // Non-critical — skip silently
            }
        }

        // ── Last search results (context persistence) ──
        // Prevents the AI from "forgetting" products it showed earlier in the conversation.
        $lastSearch = $session->getMeta('last_search_results');
        if ($lastSearch) {
            $hints[] = "آخر منتجات عُرضت للزبون:\n{$lastSearch}";
        }

        // Upsell shown
        $upsellShown = $session->getMeta('upsell_shown', []);
        if (! empty($upsellShown)) {
            $hints[] = 'منتجات upsell سبق عرضها: ' . implode(', ', $upsellShown);
        }

        // Customer tone
        $tone = $session->getMeta('tone', 'neutral');
        if ($tone !== 'neutral') {
            $hints[] = "نبرة الزبون: {$tone}";
        }

        return implode("\n", $hints);
    }

    /**
     * Collect product IDs and image URLs from a tool result.
     * For search_products, only auto-send images when exactly 1 product matched
     * (prevents flooding customer with images of every matching t-shirt).
     */
    private function collectProductData(string $toolName, array $result, array &$productIds, array &$images): void
    {
        // From search results — only attach images for a single-product result
        if (isset($result['products'])) {
            if ($toolName !== 'search_products' || count($result['products']) === 1) {
                foreach ($result['products'] as $p) {
                    $productIds[] = $p['id'] ?? null;
                }
            }
        }

        // From product details
        if (isset($result['id'])) {
            $productIds[] = $result['id'];
        }
        if (isset($result['images'])) {
            foreach ($result['images'] as $img) {
                $images[] = $img['url'] ?? '';
            }
        }

        // From cart
        if (isset($result['items'])) {
            foreach ($result['items'] as $item) {
                $productIds[] = $item['product_id'] ?? null;
            }
        }

        $productIds = array_filter($productIds);
        $images     = array_filter($images);
    }

    private function getOutOfScopeMessage(int $storeId): string
    {
        $settings = \App\Models\AiSetting::where('user_id', $storeId)->first();

        return $settings?->out_of_scope_message ?? __('chat.out_of_scope_default');
    }

    private function fallbackResponse(): array
    {
        return [
            'reply'       => __('chat.error_generic'),
            'images'      => [],
            'products'    => [],
            'tool_calls'  => [],
            'tokens_used' => 0,
        ];
    }

    /**
     * Save shown products as interests in the Lead CRM model.
     * Deduplicates within a 2-hour window to avoid spam entries.
     */
    private function saveProductInterests(ChatSession $session, array $products): void
    {
        if (empty($session->lead_id) || empty($products)) {
            return;
        }

        try {
            $lead = \App\Models\Lead::find($session->lead_id);
            if (! $lead) {
                return;
            }

            foreach ($products as $product) {
                if (empty($product['name'])) {
                    continue;
                }

                // Skip if same product was already saved within the last 2 hours
                $alreadySaved = collect($lead->interests ?? [])
                    ->contains(function ($interest) use ($product) {
                        return ($interest['product_name'] ?? '') === $product['name']
                            && isset($interest['date'])
                            && now()->parse($interest['date'])->diffInHours(now()) < 2;
                    });

                if (! $alreadySaved) {
                    $lead->addInterest($product['name'], $product['id'] ?? null);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Chat: Failed to save product interests to Lead', [
                'lead_id' => $session->lead_id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pull demographic fields from the CustomerProfile and store them in
     * session meta so they are available in subsequent context hints
     * even when old history messages have been truncated.
     */
    private function syncDemographicsToMeta(ChatSession $session): void
    {
        $profile = \App\Models\CustomerProfile::where('store_id', $session->store_id)
            ->where('lead_id', $session->lead_id)
            ->first();

        if (! $profile) {
            return;
        }

        $demo = array_filter([
            'age'            => $profile->age,
            'gender'         => $profile->gender,
            'budget_max'     => $profile->budget_max,
            'occupation'     => $profile->occupation,
            'marital_status' => $profile->marital_status,
            'interests'      => $profile->interests,
        ], fn ($v) => $v !== null && $v !== []);

        if (! empty($demo)) {
            $session->setMeta('demographics', $demo);
        }
    }
}
