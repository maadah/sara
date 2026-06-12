<?php

namespace App\Services\Chat;

use App\Enums\ConversationState;
use App\Enums\Intent;
use App\Models\ChatSession;

/**
 * PromptBuilder — assembles all prompts sent to the OpenAI API.
 *
 * Responsibilities:
 * - Build the intent-classification prompt (for gpt-4.1-nano).
 * - Build the entity-extraction prompt (for gpt-4.1-nano).
 * - Build the messages array for the main conversation call (for gpt-4.1-mini).
 * - Filter tools by current conversation state to minimise token usage.
 */
class PromptBuilder
{
    /**
     * Build the prompt for intent classification.
     *
     * @param  string   $message      Current user message.
     * @param  array    $lastMessages Last 2 messages for context.
     * @return array    Messages array for the API call.
     */
    public function buildIntentPrompt(string $message, array $lastMessages = []): array
    {
        $intentList = Intent::classifierList();

        $system = <<<PROMPT
أنت مصنّف نوايا (intent classifier). مهمتك الوحيدة هي تحديد نية الزبون من رسالته.

## القائمة الكاملة للنوايا:
{$intentList}

## القواعد:
- أرجع JSON فقط بهذا الشكل: {"intent": "intent_name", "confidence": 0.85}
- confidence بين 0.0 و 1.0
- اذا ما كدرت تحدد النية بثقة اعلى من 0.50، أرجع intent = "unknown"
- لا تشرح، لا تضيف نص، فقط JSON
PROMPT;

        $messages = [['role' => 'system', 'content' => $system]];

        // Inject last 2 messages for context
        foreach (array_slice($lastMessages, -2) as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }

    /**
     * Build the prompt for entity extraction.
     *
     * @param  string $message Current user message.
     * @param  string $state   Current conversation state (for context hints).
     * @return array  Messages array for the API call.
     */
    public function buildEntityPrompt(string $message, string $state = 'idle'): array
    {
        $system = <<<PROMPT
أنت مستخرج كيانات (entity extractor). استخرج المعلومات التالية من رسالة الزبون اذا موجودة:

- product_name: اسم المنتج
- category_name: اسم الفئة/الصنف
- quantity: الكمية (حولها لرقم: اثنين=2، قطعتين=2، زوج=2، ثلاث=3)
- color: اللون
- size: المقاس (S, M, L, XL, كبير, وسط, صغير)
- selection_number: رقم الاختيار (#1, #2, الاول, الثاني)
- customer_name: اسم الزبون
- customer_phone: رقم الهاتف (يجب ان يطابق 07XXXXXXXXX)
- customer_address: العنوان
- customer_city: المدينة
- faq_topic: الموضوع (delivery/return/warranty/payment)
- customer_age: عمر الزبون بالسنوات (رقم صحيح فقط)
- customer_gender: جنس الزبون (male أو female)
- customer_budget: الميزانية بالدينار العراقي (رقم صحيح)
- customer_occupation: مهنة أو وظيفة الزبون
- customer_interests: اهتمامات الزبون كقائمة مفصولة بفاصلة (رياضة، طبخ، …)

## القواعد:
- أرجع JSON فقط
- اذا الحقل غير موجود بالرسالة، لا تدرجه بالنتيجة
- حالة المحادثة الحالية: {$state}
- لا تشرح، فقط JSON
PROMPT;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $message],
        ];
    }

    /**
     * Build the full messages array for the main conversation call.
     *
     * @param  string       $systemPrompt  Cached store system prompt.
     * @param  array        $history       Last N message pairs from session.
     * @param  string       $userMessage   Current user message.
     * @param  string|null  $contextHint   Extra context (intent hint, entity summary, etc.).
     * @return array        Messages array for the API call.
     */
    public function buildConversationMessages(
        string $systemPrompt,
        array $history,
        string $userMessage,
        ?string $contextHint = null,
    ): array {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        // Append trimmed history
        foreach ($history as $msg) {
            $messages[] = [
                'role'    => $msg['role'] ?? 'user',
                'content' => $msg['content'] ?? '',
            ];
        }

        // Current user message with optional context injection
        $content = $userMessage;
        if ($contextHint) {
            $content .= "\n\n[سياق داخلي — لا تعرضه للزبون]\n" . $contextHint;
        }

        $messages[] = ['role' => 'user', 'content' => $content];

        return $messages;
    }

    /**
     * Return tool definitions filtered by the current conversation state.
     * This saves tokens by only including tools relevant to the current flow.
     *
     * @return array OpenAI-format tool definitions.
     */
    public function getToolsForState(ConversationState $state): array
    {
        $allTools = $this->allToolDefinitions();

        $always = ['get_store_info'];

        $stateTools = match (true) {
            $state === ConversationState::IDLE,
            $state === ConversationState::GREETING      => ['search_products', 'get_categories'],
            $state->isBrowsing()                        => ['search_products', 'get_categories', 'get_product_details', 'add_to_cart', 'get_cart', 'save_customer_data', 'get_customer_profile'],
            $state === ConversationState::ADDING_TO_CART => ['search_products', 'get_product_details', 'add_to_cart', 'get_cart', 'update_cart_item', 'remove_from_cart', 'save_customer_data', 'get_customer_profile'],
            $state === ConversationState::CART_REVIEW    => ['get_cart', 'update_cart_item', 'remove_from_cart', 'clear_cart', 'search_products', 'save_customer_data', 'get_customer_profile'],
            $state->isCollectingInfo()                   => ['get_customer_profile', 'save_customer_data', 'get_cart'],
            $state === ConversationState::AWAITING_CONFIRMATION => ['create_order', 'get_cart', 'get_customer_profile', 'save_customer_data'],
            $state->isOrderFlow()                        => ['create_order', 'get_order_status', 'cancel_order', 'get_customer_profile'],
            default                                      => ['search_products', 'get_categories', 'get_cart', 'get_customer_profile'],
        };

        $needed = array_unique(array_merge($always, $stateTools));

        return array_values(array_filter($allTools, fn (array $tool) => in_array($tool['function']['name'], $needed)));
    }

    /**
     * Complete set of tool definitions in OpenAI function-calling format.
     */
    public function allToolDefinitions(): array
    {
        return [
            $this->tool('search_products', 'Search for products in the store by name, keyword, or description.', [
                'query'       => ['type' => 'string',  'description' => 'Search keyword'],
                'category_id' => ['type' => 'integer', 'description' => 'Filter by category ID'],
                'limit'       => ['type' => 'integer', 'description' => 'Max results (default 5)'],
                'min_price'   => ['type' => 'integer', 'description' => 'Minimum price in IQD (inclusive) — use when customer states a budget range'],
                'max_price'   => ['type' => 'integer', 'description' => 'Maximum price in IQD (inclusive) — use when customer states a budget range'],
            ], ['query']),

            $this->tool('get_categories', 'Get all product categories available in this store.', []),

            $this->tool('get_product_details', 'Get full details of a specific product including variants.', [
                'product_id' => ['type' => 'integer', 'description' => 'Product ID'],
            ], ['product_id']),

            $this->tool('get_cart', 'Get current cart contents for this customer session.', []),

            $this->tool('add_to_cart', 'Add a product to the customer\'s cart.', [
                'product_id' => ['type' => 'integer', 'description' => 'Product ID'],
                'quantity'   => ['type' => 'integer', 'description' => 'Quantity to add'],
                'color'      => ['type' => 'string',  'description' => 'Color variant'],
                'size'       => ['type' => 'string',  'description' => 'Size variant'],
            ], ['product_id', 'quantity']),

            $this->tool('update_cart_item', 'Update quantity of an item already in cart.', [
                'product_id' => ['type' => 'integer', 'description' => 'Product ID'],
                'quantity'   => ['type' => 'integer', 'description' => 'New quantity'],
            ], ['product_id', 'quantity']),

            $this->tool('remove_from_cart', 'Remove a specific item from the cart.', [
                'product_id' => ['type' => 'integer', 'description' => 'Product ID'],
            ], ['product_id']),

            $this->tool('clear_cart', 'Empty the entire cart.', []),

            $this->tool('get_customer_profile', 'Get saved profile data for this customer.', []),

            $this->tool('save_customer_data', 'Save or update customer profile data collected during chat. Call immediately after the customer reveals any personal info — do not wait.', [
                'name'           => ['type' => 'string',  'description' => 'Customer full name'],
                'phone'          => ['type' => 'string',  'description' => 'Phone number (07XXXXXXXXX)'],
                'address'        => ['type' => 'string',  'description' => 'Delivery address'],
                'city'           => ['type' => 'string',  'description' => 'City name'],
                'notes'          => ['type' => 'string',  'description' => 'Attention note or purchase summary to append (e.g. "اشترى قميص × 2 بـ16000 د.ع"). Auto-appended with date.'],
                // Demographics — save any field the moment customer reveals it
                'age'            => ['type' => 'integer', 'description' => 'Customer age in years. Save when customer mentions their age directly or indirectly.'],
                'gender'         => ['type' => 'string',  'description' => '"male" or "female". Infer from name, pronouns, or direct answer.'],
                'budget_min'     => ['type' => 'integer', 'description' => 'Minimum budget in IQD'],
                'budget_max'     => ['type' => 'integer', 'description' => 'Maximum / total budget in IQD. Save when customer mentions price range or says "ميزانيتي".'],
                'occupation'     => ['type' => 'string',  'description' => 'Job or profession. Save when customer mentions their job.'],
                'marital_status' => ['type' => 'string',  'description' => '"single" | "married" | "divorced". Infer when customer mentions spouse, kids, or family.'],
                'interests'      => ['type' => 'array',   'description' => 'Interest/hobby keywords inferred from what they browse or say.',
                                     'items' => ['type' => 'string']],
                'social_platform'=> ['type' => 'string',  'description' => 'facebook | instagram | whatsapp | web'],
            ]),

            $this->tool('create_order', 'Create a new order from the current cart.', [
                'customer_name'    => ['type' => 'string', 'description' => 'Customer name'],
                'customer_phone'   => ['type' => 'string', 'description' => 'Phone number'],
                'customer_address' => ['type' => 'string', 'description' => 'Delivery address'],
                'notes'            => ['type' => 'string', 'description' => 'Order notes'],
            ], ['customer_name', 'customer_phone', 'customer_address']),

            $this->tool('get_order_status', 'Check the status of an existing order.', [
                'order_id' => ['type' => 'integer', 'description' => 'Order ID (latest if omitted)'],
            ]),

            $this->tool('cancel_order', 'Cancel an order if it is still in pending status.', [
                'order_id' => ['type' => 'integer', 'description' => 'Order ID'],
            ], ['order_id']),

            $this->tool('get_store_info', 'Get store settings like delivery cost, delivery time, promotions, policies.', []),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Private helpers                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Build a single tool definition in OpenAI function-calling format.
     */
    private function tool(string $name, string $description, array $properties, array $required = []): array
    {
        $params = ['type' => 'object', 'properties' => (object) $properties];
        if ($required) {
            $params['required'] = $required;
        }

        return [
            'type'     => 'function',
            'function' => [
                'name'        => $name,
                'description' => $description,
                'parameters'  => $params,
            ],
        ];
    }
}
