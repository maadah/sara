<?php

namespace App\Services\AI;

use App\Enums\ConversationState;
use App\Models\AiChatSession;
use App\Models\Lead;
use App\Models\Product;
use App\Models\User;
use App\Services\AiProviderService;
use App\Services\Conversation\ConversationManager;
use App\Services\Orders\CartService;
use App\Services\Orders\ProductService;
use Illuminate\Support\Facades\Log;

/**
 * Chat Agent Service - AI-Powered Conversational Agent
 *
 * This agent uses an LLM to understand customer messages naturally,
 * calls Laravel "tools" to fetch data (products, cart, etc.),
 * and generates natural Iraqi Arabic responses.
 *
 * Flow:
 * 1. Customer sends message
 * 2. Agent sends message + conversation history + available tools to LLM
 * 3. LLM decides which tool(s) to call (or responds directly)
 * 4. Agent executes the tool calls against Laravel services
 * 5. Results are sent back to LLM
 * 6. LLM generates final natural response
 *
 * This replaces rigid keyword matching with true AI understanding.
 */
class ChatAgentService
{
    protected AiProviderService $aiProvider;
    protected ProductService $productService;
    protected CartService $cartService;
    protected ConversationManager $conversationManager;

    /**
     * Maximum tool call iterations to prevent infinite loops
     */
    protected const MAX_ITERATIONS = 5;

    public function __construct(
        ProductService $productService,
        CartService $cartService,
        ConversationManager $conversationManager
    ) {
        $this->productService = $productService;
        $this->cartService = $cartService;
        $this->conversationManager = $conversationManager;
    }

    /**
     * Process a customer message through the AI agent.
     *
     * @param string $message  Customer's raw message
     * @param User $store      Store owner
     * @param User $store      Store owner
     * @param AiChatSession $session  Current chat session
     * @param Lead|null $lead  Customer lead (optional, will be loaded from session if not provided)
     * @return array{reply: string, images: array, products: array, action: string|null}
     */
    public function process(
        string $message,
        User $store,
        AiChatSession $session,
        ?Lead $lead = null
    ): array {
        // Load lead from session if not provided
        if ($lead === null && $session->lead_id) {
            $lead = Lead::find($session->lead_id);
        }

        $this->aiProvider = new AiProviderService($store);

        if (! $this->aiProvider->hasValidApiKey()) {
            Log::warning('ChatAgentService: No valid API key for AI agent');
            return ['reply' => null, 'images' => [], 'products_to_show' => [], 'actions' => []];
        }

        try {
            // Build context
            $systemPrompt = $this->buildSystemPrompt($store, $session, $lead);
            $conversationHistory = $this->conversationManager->getMessagesForAI($session, 10);
            $toolDefinitions = $this->getToolDefinitions();

            // Build messages array for LLM
            $messages = [];
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];

            // Add conversation history (skip last user message, we'll add it fresh)
            foreach ($conversationHistory as $msg) {
                $messages[] = $msg;
            }

            // Add current user message
            $messages[] = ['role' => 'user', 'content' => $message];

            // Agent loop: LLM may call tools, we execute them, then LLM responds
            $iteration = 0;
            $images = [];
            $productIds = [];
            $action = null;

            while ($iteration < self::MAX_ITERATIONS) {
                $iteration++;

                Log::debug('ChatAgentService: Iteration ' . $iteration, [
                    'messages_count' => count($messages),
                ]);

                // Call LLM with tools
                $response = $this->callLLMWithTools($messages, $toolDefinitions);

                if (! $response['success']) {
                    Log::error('ChatAgentService: LLM call failed', ['error' => $response['error'] ?? 'unknown']);
                    return ['reply' => null, 'images' => [], 'products_to_show' => [], 'actions' => []];
                }

                $assistantMessage = $response['message'];

                // Check if LLM wants to call tools
                $toolCalls = $assistantMessage['tool_calls'] ?? [];

                if (empty($toolCalls)) {
                    // LLM is done - return final text response
                    $replyText = $assistantMessage['content'] ?? '';

                    return [
                        'reply' => $replyText,
                        'images' => $images,
                        'products_to_show' => $productIds,
                        'actions' => $action ? [$action] : [],
                    ];
                }

                // Add assistant message with tool calls to conversation
                $messages[] = $assistantMessage;

                // Execute each tool call
                foreach ($toolCalls as $toolCall) {
                    $toolName = $toolCall['function']['name'] ?? '';
                    $toolArgs = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?? [];
                    $toolCallId = $toolCall['id'] ?? '';

                    Log::debug('ChatAgentService: Executing tool', [
                        'tool' => $toolName,
                        'args' => $toolArgs,
                    ]);

                    $toolResult = $this->executeTool(
                        $toolName,
                        $toolArgs,
                        $store,
                        $session,
                        $lead
                    );

                    // Collect images and product IDs from tool results
                    if (! empty($toolResult['_images'])) {
                        $images = array_merge($images, $toolResult['_images']);
                    }
                    if (! empty($toolResult['_product_ids'])) {
                        $productIds = array_merge($productIds, $toolResult['_product_ids']);
                    }
                    if (! empty($toolResult['_action'])) {
                        $action = $toolResult['_action'];
                    }

                    // Remove internal fields before sending to LLM
                    unset($toolResult['_images'], $toolResult['_product_ids'], $toolResult['_action']);

                    // Add tool result to messages
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCallId,
                        'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                    ];
                }
            }

            Log::warning('ChatAgentService: Max iterations reached');
            return ['reply' => null, 'images' => $images, 'products_to_show' => $productIds, 'actions' => $action ? [$action] : []];

        } catch (\Exception $e) {
            Log::error('ChatAgentService: Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['reply' => null, 'images' => [], 'products_to_show' => [], 'actions' => []];
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // SYSTEM PROMPT
    // ═══════════════════════════════════════════════════════════════════

    protected function buildSystemPrompt(User $store, AiChatSession $session, ?Lead $lead): string
    {
        $storeName = $store->name ?? $store->business_name ?? 'المتجر';
        $state = $this->conversationManager->getState($session);
        $cartSummary = $this->cartService->getCartSummary($session);
        $lastProduct = $this->conversationManager->getLastMentionedProduct($session);
        $customerData = $lead ? $this->conversationManager->getCustomerData($session, $lead) : [];
        $deliveryCost = $store->aiSetting->delivery_cost ?? 5000;

        $lastProductInfo = 'لا يوجد';
        if ($lastProduct) {
            $lastProductInfo = $lastProduct['name'] . ' (سعر: ' . number_format($lastProduct['price'] ?? 0) . ' د.ع)';
        }

        $customerInfo = '';
        if (! empty($customerData['name'])) {
            $customerInfo .= "الاسم: {$customerData['name']}\n";
        }
        if (! empty($customerData['phone'])) {
            $customerInfo .= "الهاتف: {$customerData['phone']}\n";
        }
        if (! empty($customerData['address'])) {
            $customerInfo .= "العنوان: {$customerData['address']}\n";
        }
        if (empty($customerInfo)) {
            $customerInfo = 'غير متوفرة';
        }

        return <<<PROMPT
أنت بائع محترف لمتجر "{$storeName}". مهمتك الأولى هي بيع المنتجات وإقناع الزبون بالشراء. تتحدث باللهجة العراقية.

═══ شخصيتك (بياع محترف) ═══
- تتكلم عراقي (شلونك، شقدر اساعدك، اي والله، بس، اكو، ماكو)
- ودود ولطيف، تستخدم إيموجي بشكل معتدل 🌟
- مختصر ومباشر - لا تكتب فقرات طويلة
- تفهم السياق - اذا الزبون ذكر منتج قبل وسأل عنه بالاشارة تفهم شنو يقصد
- أنت بياع ذكي: هدفك الرئيسي إتمام عملية البيع
- كل رد يجب أن ينتهي بسؤال يدفع الزبون للشراء أو خطوة أقرب للطلب
- لا تستسلم بسهولة! إذا الزبون تردد، حاول مرة ثانية بطريقة مختلفة

═══ فن البيع والإقناع ═══
1. **التعامل مع اعتراض السعر (غالي):**
   - لا تعيد عرض المنتج!
   - أكد على الجودة والضمان
   - قل: "يعني والله، بس جودته تسوى. خذه وجربه، ما راح تندم 💪"
   - اقترح بدائل ارخص اذا وجدت

2. **التعامل مع المقارنة (لقيت ارخص بمكان ثاني):**
   ⚠️ هذا اعتراض مبيعات! لا تضيف للسلة!
   - قل: "صحيح اكو اسعار مختلفه بالسوق، بس عدنا ضمان وجوده 💪 والتوصيل سريع"
   - أبداً لا تستخدم add_to_cart عندما الزبون يعترض على السعر

3. **التعامل مع التردد:**
   - استخدم عنصر الاستعجال: "الكمية محدودة!" "عليه طلب كبير هسه"
   - كرر فائدة المنتج
   - قل: "خذه هسه ولو ما عجبك ترجعه بسهولة ✅"

4. **الدفع نحو الشراء:**
   - بعد كل عرض منتج: "تبي اضيفه للسله؟ وكم قطعه تحتاج؟"
   - بعد عرض البدائل: "اي واحد عجبك؟ نحطه بالسله؟"
   - في مراجعة السلة: "تبي تاكد الطلب؟ يوصلك بسرعه! 🚚"
   - اذا الزبون متردد: "تبي اضيفه هسه وتاكد لاحقاً؟"

5. **البيع المتقاطع (Cross-selling):**
   - بعد اضافة منتج: "ممكن هم تحتاج [منتج مكمل]؟"
   - ذكر المنتجات المشابه والمكملة

═══ القواعد الحرجة ═══
⚠️ لا تستخدم add_to_cart إلا إذا الزبون طلب صراحة شراء/اضافة المنتج
⚠️ "حصلت بمكان ثاني بسعر ارخص" = اعتراض مبيعات (لا تضيف للسلة!)
⚠️ "سعر غالي" أو "مكلف" = اعتراض سعر (لا تضيف للسلة! اقنع الزبون)
⚠️ "اشرحلي" أو "فصلي" = طلب معلومات (لا تضيف للسلة!)
⚠️ "لا شكرا" = رفض اضافة المزيد (مو إلغاء!)
⚠️ "الغي الطلب" فقط = إلغاء الطلب

═══ السياق الحالي ═══
حالة المحادثة: {$state->value}
المنتج الأخير المذكور: {$lastProductInfo}
محتوى السلة: {$cartSummary}
كلفة التوصيل: {$deliveryCost} د.ع
معلومات الزبون:
{$customerInfo}

═══ متى تضيف للسلة ═══
✅ "اريد هذا" / "ضيفه" / "حطه بالسله" / "اشتريه" / "نعم" (بعد سؤال "تبي تضيفه؟")
❌ "غالي" / "مكلف" / "لقيت ارخص" / "ما احتاج" / "اشرحلي" / أي اعتراض

═══ تنسيق الردود ═══
- استخدم سطور فاصلة: ━━━━━━━━━━━━━━━
- ارقام المنتجات: 1. اسم المنتج - السعر د.ع
- الاسعار: X,XXX د.ع
- حالة المنتج: ✅ متوفر / ❌ غير متوفر
- ردك يكون رد واحد نظيف - بدون JSON وبدون "`"
PROMPT;
    }

    // ═══════════════════════════════════════════════════════════════════
    // TOOL DEFINITIONS (OpenAI Function Calling Format)
    // ═══════════════════════════════════════════════════════════════════

    protected function getToolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'البحث عن منتجات بالاسم او الوصف. يدعم البحث بالعربي مع كل الاختلافات (ال، ة/ه، ى/ي). مثال: "حزام" يلقى "حزام تصحيح الظهر"',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'كلمة البحث. مهم: اكتب الكلمة الجذرية بدون "ال" التعريف. مثال: "حزام" بدل "الحزام"',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'عدد النتائج المطلوبة (default: 5)',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_product_details',
                    'description' => 'الحصول على تفاصيل منتج محدد بواسطة ID. يرجع الاسم، السعر، الوصف، التوفر، والصور',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'رقم المنتج',
                            ],
                        ],
                        'required' => ['product_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_categories',
                    'description' => 'عرض جميع أقسام المتجر مع عدد المنتجات في كل قسم',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_category_products',
                    'description' => 'عرض منتجات قسم معين',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'category_name' => [
                                'type' => 'string',
                                'description' => 'اسم القسم (مثل: أدوات منزلية، ملابس رجالية)',
                            ],
                        ],
                        'required' => ['category_name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'add_to_cart',
                    'description' => 'إضافة منتج للسلة. يتحقق من التوفر تلقائياً',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'رقم المنتج',
                            ],
                            'quantity' => [
                                'type' => 'integer',
                                'description' => 'الكمية (default: 1)',
                            ],
                        ],
                        'required' => ['product_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'view_cart',
                    'description' => 'عرض محتويات السلة الحالية مع الأسعار والمجموع',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'remove_from_cart',
                    'description' => 'حذف منتج من السلة',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'رقم المنتج المراد حذفه',
                            ],
                        ],
                        'required' => ['product_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_cart_quantity',
                    'description' => 'تعديل كمية منتج في السلة',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'رقم المنتج',
                            ],
                            'quantity' => [
                                'type' => 'integer',
                                'description' => 'الكمية الجديدة',
                            ],
                        ],
                        'required' => ['product_id', 'quantity'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_best_sellers',
                    'description' => 'عرض المنتجات الأكثر مبيعاً',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'عدد المنتجات (default: 5)',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_offers_and_discounts',
                    'description' => 'البحث عن العروض والتخفيضات المتوفرة',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // LLM CALL WITH TOOLS
    // ═══════════════════════════════════════════════════════════════════

    protected function callLLMWithTools(array $messages, array $tools): array
    {
        try {
            // Always use OpenAI for tool calling - gpt-4.1-mini
            $apiUrl = 'https://api.openai.com/v1/chat/completions';
            $model = 'gpt-4.1-mini';

            $payload = [
                'model' => $model,
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'temperature' => 0.3,
                'max_tokens' => 800,
            ];

            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->getApiKey(),
                    'Content-Type' => 'application/json',
                ])
                ->post($apiUrl, $payload);

            if (! $response->successful()) {
                Log::error('ChatAgentService: API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['success' => false, 'error' => 'API call failed: ' . $response->status()];
            }

            $data = $response->json();
            $choice = $data['choices'][0] ?? null;

            if (! $choice) {
                return ['success' => false, 'error' => 'No choice in response'];
            }

            return [
                'success' => true,
                'message' => $choice['message'],
                'finish_reason' => $choice['finish_reason'] ?? 'stop',
            ];

        } catch (\Exception $e) {
            Log::error('ChatAgentService: API exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get API key from the provider
     */
    protected function getApiKey(): string
    {
        // Use reflection to access the protected apiKey property
        $reflection = new \ReflectionClass($this->aiProvider);
        $prop = $reflection->getProperty('apiKey');
        $prop->setAccessible(true);
        return $prop->getValue($this->aiProvider);
    }

    // ═══════════════════════════════════════════════════════════════════
    // TOOL EXECUTION
    // ═══════════════════════════════════════════════════════════════════

    protected function executeTool(
        string $toolName,
        array $args,
        User $store,
        AiChatSession $session,
        ?Lead $lead
    ): array {
        return match ($toolName) {
            'search_products' => $this->toolSearchProducts($store, $session, $args),
            'get_product_details' => $this->toolGetProductDetails($store, $session, $args),
            'get_categories' => $this->toolGetCategories($store),
            'get_category_products' => $this->toolGetCategoryProducts($store, $session, $args),
            'add_to_cart' => $this->toolAddToCart($store, $session, $args),
            'view_cart' => $this->toolViewCart($session),
            'remove_from_cart' => $this->toolRemoveFromCart($session, $args),
            'update_cart_quantity' => $this->toolUpdateCartQuantity($session, $args),
            'get_best_sellers' => $this->toolGetBestSellers($store, $session, $args),
            'get_offers_and_discounts' => $this->toolGetOffers($store),
            default => ['error' => 'Unknown tool: ' . $toolName],
        };
    }

    // ─── TOOL: Search Products ─────────────────────────────────

    protected function toolSearchProducts(User $store, AiChatSession $session, array $args): array
    {
        $query = $args['query'] ?? '';
        $limit = $args['limit'] ?? 5;

        if (empty($query)) {
            return ['error' => 'يجب تحديد كلمة البحث'];
        }

        // Strip Arabic definite article "ال" prefix for better matching
        $cleanQuery = $this->stripArabicPrefix($query);

        // Search with clean query
        $products = $this->productService->search($store, $cleanQuery, $limit);

        // If no results, try original query
        if ($products->isEmpty() && $cleanQuery !== $query) {
            $products = $this->productService->search($store, $query, $limit);
        }

        // If still no results, try Arabic variations
        if ($products->isEmpty()) {
            $variations = $this->productService->getArabicVariations($cleanQuery);
            foreach ($variations as $variation) {
                $products = $this->productService->search($store, $variation, $limit);
                if ($products->isNotEmpty()) {
                    break;
                }
            }
        }

        // Try individual words if multi-word query
        if ($products->isEmpty() && mb_strpos($query, ' ') !== false) {
            $words = preg_split('/\s+/u', $cleanQuery);
            foreach ($words as $word) {
                if (mb_strlen($word) >= 3) {
                    $products = $this->productService->search($store, $word, $limit);
                    if ($products->isNotEmpty()) {
                        break;
                    }
                }
            }
        }

        if ($products->isEmpty()) {
            return [
                'found' => false,
                'message' => 'ما لقيت نتائج',
                'query' => $query,
            ];
        }

        // Store shown products in session context
        $this->conversationManager->setShownProducts($session, $products->toArray());

        if ($products->count() === 1) {
            $this->conversationManager->setLastMentionedProduct($session, $products->first());
        }

        // Collect images
        $images = [];
        $productIds = [];
        $results = [];

        foreach ($products as $product) {
            $img = $this->productService->getPrimaryImageUrl($product);
            if ($img) {
                $images[] = $img;
            }
            $productIds[] = $product->id;

            $results[] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (int) $product->price,
                'price_formatted' => number_format((int) $product->price) . ' د.ع',
                'in_stock' => ($product->quantity ?? 0) > 0,
                'stock' => (int) ($product->quantity ?? 0),
                'description' => mb_substr($product->description ?? '', 0, 100),
                'category' => $product->category?->name ?? '',
            ];
        }

        return [
            'found' => true,
            'count' => count($results),
            'products' => $results,
            '_images' => $images,
            '_product_ids' => $productIds,
        ];
    }

    // ─── TOOL: Get Product Details ─────────────────────────────

    protected function toolGetProductDetails(User $store, AiChatSession $session, array $args): array
    {
        $productId = $args['product_id'] ?? null;
        if (! $productId) {
            return ['error' => 'يجب تحديد رقم المنتج'];
        }

        $product = $this->productService->getById($productId);
        if (! $product || $product->user_id !== $store->id) {
            return ['error' => 'المنتج غير موجود'];
        }

        // Update session context
        $this->conversationManager->setLastMentionedProduct($session, $product);

        $img = $this->productService->getPrimaryImageUrl($product);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => (int) $product->price,
            'price_formatted' => number_format((int) $product->price) . ' د.ع',
            'description' => $product->description ?? '',
            'in_stock' => ($product->quantity ?? 0) > 0,
            'stock' => (int) ($product->quantity ?? 0),
            'category' => $product->category?->name ?? '',
            'has_image' => ! empty($img),
            '_images' => $img ? [$img] : [],
            '_product_ids' => [$product->id],
        ];
    }

    // ─── TOOL: Get Categories ──────────────────────────────────

    protected function toolGetCategories(User $store): array
    {
        $categories = $this->productService->getCategories($store);

        $results = [];
        foreach ($categories as $cat) {
            $results[] = [
                'id' => $cat->id,
                'name' => $cat->name,
                'products_count' => $cat->products_count ?? 0,
            ];
        }

        return [
            'categories' => $results,
            'count' => count($results),
        ];
    }

    // ─── TOOL: Get Category Products ───────────────────────────

    protected function toolGetCategoryProducts(User $store, AiChatSession $session, array $args): array
    {
        $categoryName = $args['category_name'] ?? '';
        if (empty($categoryName)) {
            return ['error' => 'يجب تحديد اسم القسم'];
        }

        $category = $this->productService->findCategory($store, $categoryName);
        if (! $category) {
            // Try to search for similar category names
            $categories = $this->productService->getCategories($store);
            $closest = null;
            $cleanName = $this->stripArabicPrefix($categoryName);

            foreach ($categories as $cat) {
                $catNorm = mb_strtolower($cat->name);
                if (mb_strpos($catNorm, mb_strtolower($cleanName)) !== false ||
                    mb_strpos(mb_strtolower($cleanName), $catNorm) !== false) {
                    $closest = $cat;
                    break;
                }
            }

            if (! $closest) {
                return [
                    'error' => 'القسم غير موجود',
                    'available_categories' => $categories->pluck('name')->toArray(),
                ];
            }
            $category = $closest;
        }

        $products = $this->productService->getByCategory($store, $category->id, 20);

        // Store in session
        $this->conversationManager->setShownProducts($session, $products->toArray());

        $images = [];
        $productIds = [];
        $results = [];

        foreach ($products as $product) {
            $img = $this->productService->getPrimaryImageUrl($product);
            if ($img) {
                $images[] = $img;
            }
            $productIds[] = $product->id;

            $results[] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (int) $product->price,
                'price_formatted' => number_format((int) $product->price) . ' د.ع',
                'in_stock' => ($product->quantity ?? 0) > 0,
            ];
        }

        return [
            'category' => $category->name,
            'count' => count($results),
            'products' => $results,
            '_images' => $images,
            '_product_ids' => $productIds,
        ];
    }

    // ─── TOOL: Add to Cart ─────────────────────────────────────

    protected function toolAddToCart(User $store, AiChatSession $session, array $args): array
    {
        $productId = $args['product_id'] ?? null;
        $quantity = $args['quantity'] ?? 1;

        if (! $productId) {
            return ['error' => 'يجب تحديد رقم المنتج'];
        }

        $product = $this->productService->getById($productId);
        if (! $product || $product->user_id !== $store->id) {
            return ['error' => 'المنتج غير موجود'];
        }

        $result = $this->cartService->addItem($session, $product, $quantity);

        if ($result['success']) {
            // Update session context
            $this->conversationManager->setLastMentionedProduct($session, $product);

            // Move to cart review state
            $session->conversation_state = ConversationState::CART_REVIEW->value;
            $session->save();

            return [
                'success' => true,
                'message' => 'تمت الاضافة',
                'product' => $product->name,
                'quantity' => $quantity,
                'price' => (int) $product->price,
                'cart_total' => $this->cartService->getTotal($session),
                '_action' => 'added_to_cart',
            ];
        }

        return [
            'success' => false,
            'error' => $result['message'] ?? 'فشل في الاضافة',
        ];
    }

    // ─── TOOL: View Cart ───────────────────────────────────────

    protected function toolViewCart(AiChatSession $session): array
    {
        $cart = $this->cartService->getFormattedCart($session);

        if (empty($cart['items'])) {
            return ['empty' => true, 'message' => 'السلة فاضية'];
        }

        return [
            'empty' => false,
            'items' => $cart['items'],
            'total' => $cart['total'],
            'item_count' => count($cart['items']),
        ];
    }

    // ─── TOOL: Remove from Cart ────────────────────────────────

    protected function toolRemoveFromCart(AiChatSession $session, array $args): array
    {
        $productId = $args['product_id'] ?? null;
        if (! $productId) {
            return ['error' => 'يجب تحديد رقم المنتج'];
        }

        $result = $this->cartService->removeItem($session, $productId);

        return [
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? 'تم الحذف',
        ];
    }

    // ─── TOOL: Update Cart Quantity ────────────────────────────

    protected function toolUpdateCartQuantity(AiChatSession $session, array $args): array
    {
        $productId = $args['product_id'] ?? null;
        $quantity = $args['quantity'] ?? 1;

        if (! $productId) {
            return ['error' => 'يجب تحديد رقم المنتج'];
        }

        $result = $this->cartService->updateQuantity($session, $productId, $quantity);

        return [
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? 'تم التحديث',
            'new_quantity' => $quantity,
        ];
    }

    // ─── TOOL: Best Sellers ────────────────────────────────────

    protected function toolGetBestSellers(User $store, AiChatSession $session, array $args): array
    {
        $limit = $args['limit'] ?? 5;
        $products = $this->productService->getBestSellers($store, $limit);

        $this->conversationManager->setShownProducts($session, $products->toArray());

        $images = [];
        $productIds = [];
        $results = [];

        foreach ($products as $product) {
            $img = $this->productService->getPrimaryImageUrl($product);
            if ($img) {
                $images[] = $img;
            }
            $productIds[] = $product->id;

            $results[] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (int) $product->price,
                'price_formatted' => number_format((int) $product->price) . ' د.ع',
                'in_stock' => ($product->quantity ?? 0) > 0,
            ];
        }

        return [
            'count' => count($results),
            'products' => $results,
            '_images' => $images,
            '_product_ids' => $productIds,
        ];
    }

    // ─── TOOL: Offers & Discounts ──────────────────────────────

    protected function toolGetOffers(User $store): array
    {
        // Search for products with special pricing or discounts
        $products = Product::where('user_id', $store->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->where(function ($q) {
                $q->whereNotNull('discount_price')
                  ->where('discount_price', '>', 0);
            })
            ->limit(10)
            ->get();

        if ($products->isEmpty()) {
            // If no discount products, return best sellers as "recommended"
            $bestSellers = $this->productService->getBestSellers($store, 5);
            $results = [];
            foreach ($bestSellers as $product) {
                $results[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (int) $product->price,
                    'price_formatted' => number_format((int) $product->price) . ' د.ع',
                ];
            }
            return [
                'has_offers' => false,
                'message' => 'حالياً ما في عروض خاصة، بس هاي المنتجات الأكثر طلباً',
                'recommended' => $results,
            ];
        }

        $results = [];
        foreach ($products as $product) {
            $results[] = [
                'id' => $product->id,
                'name' => $product->name,
                'original_price' => (int) $product->price,
                'discount_price' => (int) $product->discount_price,
                'savings' => (int) $product->price - (int) $product->discount_price,
            ];
        }

        return [
            'has_offers' => true,
            'count' => count($results),
            'offers' => $results,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Strip Arabic definite article "ال" prefix from search queries
     * "الحزام" → "حزام", "الملابس" → "ملابس"
     */
    protected function stripArabicPrefix(string $text): string
    {
        $text = trim($text);

        // Strip "ال" prefix
        if (mb_strpos($text, 'ال') === 0 && mb_strlen($text) > 3) {
            $stripped = mb_substr($text, 2);
            return $stripped;
        }

        // Also strip from individual words in multi-word queries
        $words = preg_split('/\s+/u', $text);
        $cleaned = [];
        foreach ($words as $word) {
            if (mb_strpos($word, 'ال') === 0 && mb_strlen($word) > 3) {
                $cleaned[] = mb_substr($word, 2);
            } else {
                $cleaned[] = $word;
            }
        }

        return implode(' ', $cleaned);
    }
}
