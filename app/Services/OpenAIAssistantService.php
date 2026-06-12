<?php

namespace App\Services;

use App\Models\AiChatSession;
use App\Models\AiSetting;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI Assistants API Service
 *
 * BENEFITS:
 * - Built-in conversation memory via Threads (no need to send history)
 * - Function calling for structured product/order operations
 * - ~40-60% token savings compared to regular chat completions
 * - Better context retention across long conversations
 * - Automatic prompt caching
 *
 * FLOW:
 * 1. Create/get Assistant (cached per store)
 * 2. Create/get Thread (per conversation/lead)
 * 3. Add message to thread
 * 4. Run assistant on thread
 * 5. Handle function calls if any
 * 6. Return response
 */
class OpenAIAssistantService
{
    protected User $user;
    protected AiSetting $settings;
    protected string $apiKey;
    protected string $baseUrl = 'https://api.openai.com/v1';
    protected string $model;
    protected ?string $assistantId = null;

    // Function definitions for the assistant
    protected array $functions = [];

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->settings = $user->aiSetting ?? new AiSetting();
        $this->apiKey = $this->settings->openai_api_key ?? config('services.openai.api_key');
        $this->model = $this->settings->openai_model ?? 'gpt-4.1-mini';

        $this->defineFunctions();
    }

    /**
     * Define functions the assistant can call
     * This gives structured, reliable responses
     */
    protected function defineFunctions(): void
    {
        $this->functions = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Search for products in the store by name, category, or keywords. Use when customer asks about products, prices, or availability.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Product name or search keywords in Arabic',
                            ],
                            'category' => [
                                'type' => 'string',
                                'description' => 'Optional category name to filter by',
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
                    'description' => 'Get detailed information about a specific product including price, stock, and description.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'The product ID',
                            ],
                            'product_name' => [
                                'type' => 'string',
                                'description' => 'The product name if ID is unknown',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'add_to_cart',
                    'description' => 'Add a product to the customer cart. Use when customer wants to buy/order a product.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'The product ID to add',
                            ],
                            'product_name' => [
                                'type' => 'string',
                                'description' => 'Product name if ID unknown',
                            ],
                            'quantity' => [
                                'type' => 'integer',
                                'description' => 'Quantity to add (default 1)',
                                'default' => 1,
                            ],
                        ],
                        'required' => ['quantity'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_cart',
                    'description' => 'Get current cart contents and total',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'collect_customer_info',
                    'description' => 'Extract and save customer information (name, phone, address) from message',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'Customer full name',
                            ],
                            'phone' => [
                                'type' => 'string',
                                'description' => 'Phone number (Iraqi format)',
                            ],
                            'address' => [
                                'type' => 'string',
                                'description' => 'Delivery address',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'confirm_order',
                    'description' => 'Confirm and create the order. Use only when cart has items AND customer info is complete.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'confirmed' => [
                                'type' => 'boolean',
                                'description' => 'Whether customer confirmed the order',
                            ],
                        ],
                        'required' => ['confirmed'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_categories',
                    'description' => 'Get list of product categories in the store',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_store_info',
                    'description' => 'Get store information like delivery cost, working hours, return policy',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'info_type' => [
                                'type' => 'string',
                                'enum' => ['delivery', 'hours', 'return_policy', 'payment'],
                                'description' => 'Type of information requested',
                            ],
                        ],
                        'required' => ['info_type'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'show_product_image',
                    'description' => 'Request to show product image to customer',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'Product ID to show image for',
                            ],
                            'product_name' => [
                                'type' => 'string',
                                'description' => 'Product name if ID unknown',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get or create assistant for this store
     * Prioritizes pre-created assistant with Vector Store
     */
    protected function getOrCreateAssistant(): string
    {
        // First, check if we have a pre-synced assistant with Vector Store
        if ($this->settings->openai_assistant_id) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'OpenAI-Beta' => 'assistants=v2',
                ])->get("{$this->baseUrl}/assistants/{$this->settings->openai_assistant_id}");

                if ($response->successful()) {
                    Log::info('OpenAI: Using pre-synced assistant with Vector Store', [
                        'assistant_id' => $this->settings->openai_assistant_id,
                        'vector_store_id' => $this->settings->openai_vector_store_id,
                    ]);
                    return $this->settings->openai_assistant_id;
                }
            } catch (\Exception $e) {
                Log::warning('OpenAI: Pre-synced assistant not found', ['error' => $e->getMessage()]);
            }
        }

        $cacheKey = "openai_assistant_{$this->user->id}";

        // Check cache first
        $assistantId = Cache::get($cacheKey);
        if ($assistantId) {
            // Verify assistant still exists
            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'OpenAI-Beta' => 'assistants=v2',
                ])->get("{$this->baseUrl}/assistants/{$assistantId}");

                if ($response->successful()) {
                    return $assistantId;
                }
            } catch (\Exception $e) {
                Log::warning('OpenAI: Cached assistant not found, creating new', ['error' => $e->getMessage()]);
            }
        }

        // Create new assistant
        $storeName = $this->user->name ?? 'المتجر';
        $deliveryCost = $this->settings->delivery_cost ?? 5000;
        $workingHours = $this->settings->working_hours ?? '9am - 10pm';
        $returnPolicy = $this->settings->store_policies ?? 'استرجاع خلال 7 أيام';

        $instructions = <<<PROMPT
أنت مساعد مبيعات ذكي لمتجر "{$storeName}" العراقي. تتحدث باللهجة العراقية.

قواعد مهمة:
1. لا تستخدم ايموجي نهائياً
2. ردودك قصيرة (جملة أو جملتين)
3. استخدم الدوال المتاحة للبحث عن المنتجات والأسعار - لا تخترع منتجات
4. عند السؤال عن منتج، استخدم search_products للتحقق من توفره
5. عند طلب صورة، استخدم show_product_image
6. عند جمع معلومات الزبون، استخدم collect_customer_info
7. لا تؤكد الطلب إلا بعد: سلة غير فارغة + معلومات كاملة + موافقة الزبون

معلومات المتجر:
- التوصيل: {$deliveryCost} دينار
- ساعات العمل: {$workingHours}
- سياسة الاسترجاع: {$returnPolicy}
- الدفع: كاش عند التوصيل فقط

تدفق المحادثة:
1. ترحيب قصير
2. مساعدة في اختيار المنتج
3. إضافة للسلة
4. جمع معلومات (اسم، رقم، عنوان)
5. تأكيد الطلب
PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2',
            ])->post("{$this->baseUrl}/assistants", [
                'model' => $this->model,
                'name' => "Store Assistant - {$storeName}",
                'instructions' => $instructions,
                'tools' => $this->functions,
            ]);

            if ($response->successful()) {
                $assistantId = $response->json('id');
                Cache::put($cacheKey, $assistantId, now()->addHours(24));

                Log::info('OpenAI: Created new assistant', [
                    'assistant_id' => $assistantId,
                    'store' => $storeName,
                ]);

                return $assistantId;
            }

            Log::error('OpenAI: Failed to create assistant', [
                'error' => $response->json(),
            ]);
            throw new \Exception('Failed to create assistant');

        } catch (\Exception $e) {
            Log::error('OpenAI: Assistant creation error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get or create thread for this conversation
     * Stored in AiChatSession
     */
    protected function getOrCreateThread(AiChatSession $session): string
    {
        $storeContext = $session->store_context ?? [];

        if (!empty($storeContext['openai_thread_id'])) {
            return $storeContext['openai_thread_id'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2',
            ])->post("{$this->baseUrl}/threads", [
                'metadata' => [
                    'user_id' => (string) $this->user->id,
                    'session_id' => (string) $session->id,
                ],
            ]);

            if ($response->successful()) {
                $threadId = $response->json('id');

                $storeContext['openai_thread_id'] = $threadId;
                $session->store_context = $storeContext;
                $session->save();

                Log::info('OpenAI: Created new thread', ['thread_id' => $threadId]);

                return $threadId;
            }

            throw new \Exception('Failed to create thread');

        } catch (\Exception $e) {
            Log::error('OpenAI: Thread creation error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Main entry point - process a message
     */
    public function processMessage(Conversation $conversation, Lead $lead, string $message): array
    {
        try {
            // Get or create session (reuse existing GroqChatService session logic)
            $session = $this->getOrCreateSession($lead, $conversation);

            // Get assistant and thread
            $assistantId = $this->getOrCreateAssistant();
            $threadId = $this->getOrCreateThread($session);

            // Add message to thread
            $this->addMessageToThread($threadId, $message);

            // Run assistant
            $runId = $this->createRun($threadId, $assistantId);

            // Wait for completion and handle function calls
            $response = $this->waitForRunCompletion($threadId, $runId, $session, $lead);

            // Store messages in session for compatibility
            $session->addMessage('user', $message);
            $session->addMessage('assistant', $response['reply']);

            return $response;

        } catch (\Exception $e) {
            Log::error('OpenAI Assistant: Error processing message', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);

            // Fallback response
            return [
                'reply' => 'عذراً، حصل خطأ. شلون أكدر أساعدك؟',
                'intent' => 'error',
                'products_to_show' => null,
            ];
        }
    }

    /**
     * Add message to thread
     */
    protected function addMessageToThread(string $threadId, string $content): void
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'OpenAI-Beta' => 'assistants=v2',
        ])->post("{$this->baseUrl}/threads/{$threadId}/messages", [
            'role' => 'user',
            'content' => $content,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to add message to thread');
        }
    }

    /**
     * Create a run on the thread
     */
    protected function createRun(string $threadId, string $assistantId): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'OpenAI-Beta' => 'assistants=v2',
        ])->post("{$this->baseUrl}/threads/{$threadId}/runs", [
            'assistant_id' => $assistantId,
        ]);

        if ($response->successful()) {
            return $response->json('id');
        }

        throw new \Exception('Failed to create run');
    }

    /**
     * Wait for run completion, handling function calls
     */
    protected function waitForRunCompletion(
        string $threadId,
        string $runId,
        AiChatSession $session,
        Lead $lead,
        int $maxAttempts = 30
    ): array {
        $productsToShow = null;

        for ($i = 0; $i < $maxAttempts; $i++) {
            usleep(500000); // 0.5 second delay

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'OpenAI-Beta' => 'assistants=v2',
            ])->get("{$this->baseUrl}/threads/{$threadId}/runs/{$runId}");

            if (!$response->successful()) {
                continue;
            }

            $status = $response->json('status');

            if ($status === 'completed') {
                // Get the assistant's response
                $reply = $this->getLatestAssistantMessage($threadId);
                return [
                    'reply' => $this->removeEmojis($reply),
                    'intent' => 'assistant_response',
                    'session' => $session,
                    'products_to_show' => $productsToShow,
                ];
            }

            if ($status === 'requires_action') {
                // Handle function calls
                $toolCalls = $response->json('required_action.submit_tool_outputs.tool_calls', []);
                $toolOutputs = [];

                foreach ($toolCalls as $toolCall) {
                    $functionName = $toolCall['function']['name'];
                    $arguments = json_decode($toolCall['function']['arguments'], true) ?? [];

                    Log::info('OpenAI: Function call', [
                        'function' => $functionName,
                        'arguments' => $arguments,
                    ]);

                    $result = $this->executeFunction($functionName, $arguments, $session, $lead);

                    // Check if function wants to show products
                    if (!empty($result['_products_to_show'])) {
                        $productsToShow = $result['_products_to_show'];
                        unset($result['_products_to_show']);
                    }

                    $toolOutputs[] = [
                        'tool_call_id' => $toolCall['id'],
                        'output' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ];
                }

                // Submit function results
                $this->submitToolOutputs($threadId, $runId, $toolOutputs);
            }

            if (in_array($status, ['failed', 'cancelled', 'expired'])) {
                Log::error('OpenAI: Run failed', ['status' => $status, 'run' => $response->json()]);
                throw new \Exception("Run {$status}");
            }
        }

        throw new \Exception('Run timed out');
    }

    /**
     * Submit tool outputs back to the run
     */
    protected function submitToolOutputs(string $threadId, string $runId, array $toolOutputs): void
    {
        Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'OpenAI-Beta' => 'assistants=v2',
        ])->post("{$this->baseUrl}/threads/{$threadId}/runs/{$runId}/submit_tool_outputs", [
            'tool_outputs' => $toolOutputs,
        ]);
    }

    /**
     * Get the latest assistant message from thread
     */
    protected function getLatestAssistantMessage(string $threadId): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'OpenAI-Beta' => 'assistants=v2',
        ])->get("{$this->baseUrl}/threads/{$threadId}/messages", [
            'limit' => 1,
            'order' => 'desc',
        ]);

        if ($response->successful()) {
            $messages = $response->json('data', []);
            if (!empty($messages) && $messages[0]['role'] === 'assistant') {
                $content = $messages[0]['content'][0]['text']['value'] ?? '';
                return $content;
            }
        }

        return 'شلون أكدر أساعدك؟';
    }

    /**
     * Execute a function call
     */
    protected function executeFunction(
        string $name,
        array $arguments,
        AiChatSession $session,
        Lead $lead
    ): array {
        return match ($name) {
            'search_products' => $this->functionSearchProducts($arguments),
            'get_product_details' => $this->functionGetProductDetails($arguments),
            'add_to_cart' => $this->functionAddToCart($arguments, $session),
            'get_cart' => $this->functionGetCart($session),
            'collect_customer_info' => $this->functionCollectCustomerInfo($arguments, $session, $lead),
            'confirm_order' => $this->functionConfirmOrder($arguments, $session, $lead),
            'get_categories' => $this->functionGetCategories(),
            'get_store_info' => $this->functionGetStoreInfo($arguments),
            'show_product_image' => $this->functionShowProductImage($arguments, $session),
            default => ['error' => 'Unknown function'],
        };
    }

    // ============ FUNCTION IMPLEMENTATIONS ============

    protected function functionSearchProducts(array $args): array
    {
        $query = $args['query'] ?? '';
        $categoryName = $args['category'] ?? null;

        $queryBuilder = Product::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0);

        if ($categoryName) {
            $queryBuilder->whereHas('category', function($q) use ($categoryName) {
                $q->where('name', 'LIKE', "%{$categoryName}%");
            });
        }

        // Search by name
        $queryBuilder->where(function($q) use ($query) {
            $q->where('name', 'LIKE', "%{$query}%")
              ->orWhere('description', 'LIKE', "%{$query}%");
        });

        $products = $queryBuilder->limit(5)->get();

        if ($products->isEmpty()) {
            return [
                'found' => false,
                'message' => 'لم يتم العثور على منتجات',
            ];
        }

        return [
            'found' => true,
            'products' => $products->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (int) $p->price,
                'stock' => (int) $p->quantity,
            ])->toArray(),
        ];
    }

    protected function functionGetProductDetails(array $args): array
    {
        $productId = $args['product_id'] ?? null;
        $productName = $args['product_name'] ?? null;

        $query = Product::where('user_id', $this->user->id)
            ->where('is_active', true);

        if ($productId) {
            $query->where('id', $productId);
        } elseif ($productName) {
            $query->where('name', 'LIKE', "%{$productName}%");
        } else {
            return ['error' => 'Product ID or name required'];
        }

        $product = $query->first();

        if (!$product) {
            return ['found' => false, 'message' => 'المنتج غير متوفر'];
        }

        return [
            'found' => true,
            'id' => $product->id,
            'name' => $product->name,
            'price' => (int) $product->price,
            'stock' => (int) $product->quantity,
            'description' => $product->description,
            'in_stock' => $product->quantity > 0,
        ];
    }

    protected function functionAddToCart(array $args, AiChatSession $session): array
    {
        $productId = $args['product_id'] ?? null;
        $productName = $args['product_name'] ?? null;
        $quantity = $args['quantity'] ?? 1;

        // Find product
        $query = Product::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0);

        if ($productId) {
            $query->where('id', $productId);
        } elseif ($productName) {
            $query->where('name', 'LIKE', "%{$productName}%");
        } else {
            return ['success' => false, 'error' => 'Product not specified'];
        }

        $product = $query->first();

        if (!$product) {
            return ['success' => false, 'error' => 'المنتج غير متوفر'];
        }

        if ($product->quantity < $quantity) {
            return [
                'success' => false,
                'error' => "الكمية المتوفرة {$product->quantity} فقط",
            ];
        }

        // Add to cart
        $session->addToCart($product->name, (int) $product->price, $quantity);

        // Update context
        $storeContext = $session->store_context ?? [];
        $storeContext['current_product_id'] = $product->id;
        $storeContext['current_product'] = [
            'id' => $product->id,
            'name' => $product->name,
            'price' => (int) $product->price,
        ];
        $session->store_context = $storeContext;
        $session->save();

        return [
            'success' => true,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'price' => (int) $product->price,
            'cart_total' => $session->getCartTotal(),
            'cart_items' => count($session->cart ?? []),
        ];
    }

    protected function functionGetCart(AiChatSession $session): array
    {
        $cart = $session->cart ?? [];

        if (empty($cart)) {
            return ['empty' => true, 'message' => 'السلة فارغة'];
        }

        return [
            'empty' => false,
            'items' => $cart,
            'total' => $session->getCartTotal(),
            'items_count' => count($cart),
        ];
    }

    protected function functionCollectCustomerInfo(array $args, AiChatSession $session, Lead $lead): array
    {
        $updates = [];

        if (!empty($args['name'])) {
            $updates['name'] = $args['name'];
            $lead->name = $args['name'];
        }
        if (!empty($args['phone'])) {
            $updates['phone'] = $args['phone'];
            $lead->phone = $args['phone'];
        }
        if (!empty($args['address'])) {
            $updates['address'] = $args['address'];
            $lead->address = $args['address'];
        }

        if (!empty($updates)) {
            $session->updateCustomerData($updates);
            $lead->save();
        }

        $customerData = $session->customer_data ?? [];
        $missing = [];
        if (empty($customerData['name'])) $missing[] = 'الاسم';
        if (empty($customerData['phone'])) $missing[] = 'رقم الهاتف';
        if (empty($customerData['address'])) $missing[] = 'العنوان';

        return [
            'saved' => !empty($updates),
            'current_data' => $customerData,
            'missing_fields' => $missing,
            'is_complete' => empty($missing),
        ];
    }

    protected function functionConfirmOrder(array $args, AiChatSession $session, Lead $lead): array
    {
        if (empty($args['confirmed']) || $args['confirmed'] !== true) {
            return ['confirmed' => false, 'message' => 'الطلب لم يتم تأكيده'];
        }

        $cart = $session->cart ?? [];
        if (empty($cart)) {
            return ['success' => false, 'error' => 'السلة فارغة'];
        }

        if (!$session->hasCompleteCustomerData()) {
            return [
                'success' => false,
                'error' => 'معلومات الزبون غير مكتملة',
                'missing' => $session->getMissingFields(),
            ];
        }

        $customerData = $session->customer_data;
        $total = $session->getCartTotal();

        // Create order
        $order = OnlineOrder::create([
            'user_id' => $this->user->id,
            'lead_id' => $lead->id,
            'conversation_id' => $session->conversation_id,
            'customer_name' => $customerData['name'],
            'customer_phone' => $customerData['phone'],
            'customer_address' => $customerData['address'],
            'subtotal' => $total,
            'shipping_cost' => 0,
            'total' => $total,
            'status' => 'pending',
            'source' => 'ai_chat',
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'pending',
        ]);

        // Add items
        foreach ($cart as $item) {
            $product = Product::where('user_id', $this->user->id)
                ->where('name', $item['name'])
                ->first();

            if ($product) {
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'total_price' => $item['price'] * $item['quantity'],
                ]);
            }
        }

        // Clear cart
        $session->clearCart();
        $session->current_order = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ];
        $session->save();

        return [
            'success' => true,
            'order_number' => $order->order_number,
            'total' => $total,
        ];
    }

    protected function functionGetCategories(): array
    {
        $categories = \App\Models\Category::where('user_id', $this->user->id)
            ->whereHas('products', function($q) {
                $q->where('is_active', true)->where('quantity', '>', 0);
            })
            ->get(['id', 'name']);

        return [
            'categories' => $categories->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ])->toArray(),
        ];
    }

    protected function functionGetStoreInfo(array $args): array
    {
        $type = $args['info_type'] ?? 'delivery';

        return match ($type) {
            'delivery' => [
                'cost' => $this->settings->delivery_cost ?? 5000,
                'time' => $this->settings->delivery_time ?? 'نفس اليوم',
            ],
            'hours' => [
                'working_hours' => $this->settings->working_hours ?? '9am - 10pm',
            ],
            'return_policy' => [
                'policy' => $this->settings->store_policies ?? 'استرجاع خلال 7 أيام',
            ],
            'payment' => [
                'methods' => ['كاش عند التوصيل'],
            ],
            default => ['error' => 'Unknown info type'],
        };
    }

    protected function functionShowProductImage(array $args, AiChatSession $session): array
    {
        $productId = $args['product_id'] ?? null;
        $productName = $args['product_name'] ?? null;

        $query = Product::where('user_id', $this->user->id);

        if ($productId) {
            $query->where('id', $productId);
        } elseif ($productName) {
            $query->where('name', 'LIKE', "%{$productName}%");
        } else {
            // Use current product from context
            $storeContext = $session->store_context ?? [];
            $productId = $storeContext['current_product_id'] ?? null;
            if ($productId) {
                $query->where('id', $productId);
            } else {
                return ['error' => 'No product specified'];
            }
        }

        $product = $query->first();

        if (!$product) {
            return ['found' => false];
        }

        return [
            'found' => true,
            'product_id' => $product->id,
            'product_name' => $product->name,
            '_products_to_show' => [$product->id], // Special key for image display
        ];
    }

    // ============ HELPER METHODS ============

    protected function getOrCreateSession(Lead $lead, Conversation $conversation): AiChatSession
    {
        $session = AiChatSession::where('user_id', $this->user->id)
            ->where('lead_id', $lead->id)
            ->where('expires_at', '>', now())
            ->first();

        if ($session) {
            $session->touchActivity();
            return $session;
        }

        return AiChatSession::create([
            'user_id' => $this->user->id,
            'lead_id' => $lead->id,
            'conversation_id' => $conversation->id,
            'store_context' => [
                'name' => $this->user->name,
                'provider' => 'openai_assistant',
            ],
            'messages' => [],
            'cart' => [],
            'customer_data' => [
                'name' => $lead->name,
                'phone' => $lead->phone,
                'address' => $lead->address,
            ],
            'expires_at' => now()->addMinutes(1440),
        ]);
    }

    protected function removeEmojis(string $text): string
    {
        return preg_replace('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', '', $text);
    }
}
