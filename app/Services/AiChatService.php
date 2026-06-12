<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\AiChatSession;
use App\Models\AiKnowledgeBase;
use App\Models\UnansweredQuestion;
use App\Services\Chat\ChatOrchestrator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AiChatService
{
    protected AiSetting $settings;
    protected User $user;
    protected GroqChatService|GroqChatServiceV2|GroqChatServiceV3|null $groqService = null;
    protected ?ChatOrchestrator $orchestrator = null;
    protected ?OpenAIAssistantService $assistantService = null;
    protected bool $useAssistantMode = false;

    // Service version selection:
    // 1 = Original GroqChatService (legacy, Groq API)
    // 2 = GroqChatServiceV2 (bug fixes, Groq API)
    // 3 = ChatOrchestrator (NEW: OpenAI, tool-calling, state machine)
    protected int $serviceVersion = 3;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->settings = $user->aiSetting ?? new AiSetting();

        // Select service version based on configuration
        // Can be overridden by AI settings: $this->settings->service_version ?? 3
        $configVersion = $this->settings->service_version ?? $this->serviceVersion;

        switch ($configVersion) {
            case 3:
                // New ChatOrchestrator (OpenAI, tool-calling, state machine)
                $this->orchestrator = app(ChatOrchestrator::class);
                Log::info('AiChatService: Using ChatOrchestrator (new AI system)');
                break;
            case 2:
                // V2: Bug fixes for cart, orders, quantity updates
                $this->groqService = new GroqChatServiceV2($user);
                Log::info('AiChatService: Using GroqChatServiceV2 with bug fixes');
                break;
            default:
                // V1: Original service (legacy)
                $this->groqService = new GroqChatService($user);
                Log::info('AiChatService: Using GroqChatService (legacy)');
        }

        // Check if OpenAI Assistant mode is enabled (saves ~40% tokens)
        // Enable when: ai_provider is 'openai' AND use_assistant_mode is true
        // NOTE: Assistant mode only works with V1/V2, not V3
        $this->useAssistantMode = $configVersion < 3
            && $this->settings->ai_provider === 'openai'
            && ($this->settings->use_assistant_mode ?? false);

        if ($this->useAssistantMode) {
            try {
                $this->assistantService = new OpenAIAssistantService($user);
                Log::info('AiChatService: Using OpenAI Assistant mode (token-optimized)');
            } catch (\Exception $e) {
                Log::warning('AiChatService: Failed to init Assistant mode, falling back to standard', [
                    'error' => $e->getMessage(),
                ]);
                $this->useAssistantMode = false;
            }
        }
    }

    /**
     * Process incoming message and generate AI response
     * Supports both standard chat completions and OpenAI Assistant mode
     * IMPROVED: Assistant mode saves ~40% tokens with built-in memory
     * V3: State-driven architecture with modular services
     */
    public function processMessage(Conversation $conversation, string $message): ?string
    {
        if (!$this->settings->ai_enabled || !$this->settings->auto_reply_enabled) {
            return null;
        }

        $response = null;

        try {
            // Ensure lead exists first
            $lead = $this->ensureLead($conversation);

            // New ChatOrchestrator — handles everything (intent, tools, state, AI reply)
            if ($this->orchestrator) {
                return $this->processWithOrchestrator($conversation, $lead, $message);
            }

            // Check if customer is asking about ORDER STATUS - provide direct answer
            $orderStatusResponse = $this->checkOrderStatus($conversation, $lead, $message);
            if ($orderStatusResponse) {
                return $this->removeEmojis($orderStatusResponse);
            }

            // Check if message requires IMMEDIATE human intervention (bypass AI)
            if ($this->requiresImmediateHumanResponse($message)) {
                $waitingMessage = $this->notifyHumanAgent($conversation, $lead, $message);

                // Mark conversation as needing human attention
                $conversation->needs_human_reply = true;
                $conversation->save();

                return $this->removeEmojis($waitingMessage);
            }

            // REMOVED: Fast Replies cache - we want fresh AI responses
            // REMOVED: Knowledge Base cache - AI should generate contextual responses

            // Use appropriate AI service based on mode
            if ($this->useAssistantMode && $this->assistantService) {
                // OpenAI Assistant mode - built-in memory, function calling, ~40% token savings
                Log::info('AiChatService: Processing with OpenAI Assistant mode');
                $result = $this->assistantService->processMessage($conversation, $lead, $message);
            } else {
                // Standard chat completions mode (Groq or OpenAI)
                $result = $this->groqService->processMessage($conversation, $lead, $message);
            }

            $response = $result['reply'] ?? null;
            $intent = $result['intent'] ?? null;
            $actionRequired = $result['action_required'] ?? null;
            $fastReply = $result['fast_reply'] ?? false;
            $cart = $result['cart'] ?? [];
            $customerData = $result['customer_data'] ?? [];
            $currentOrder = $result['current_order'] ?? null;
            $productsMentioned = $result['products_mentioned'] ?? [];

            // Remove emojis from response
            $response = $this->removeEmojis($response);

            if ($response) {
                Log::info('GroqChatService Response', [
                    'intent' => $intent,
                    'fast_reply' => $fastReply,
                    'cart_items' => count($cart),
                    'has_order' => !empty($currentOrder),
                ]);

                // Check if human agent is needed
                if ($actionRequired === 'human_agent_needed') {
                    $waitingMessage = $this->notifyHumanAgent($conversation, $lead, $message);
                    // Override response with waiting message if no response was provided
                    if (empty($response)) {
                        $response = $this->removeEmojis($waitingMessage);
                    }
                }

                // Update lead with customer data from session
                $this->updateLeadFromSession($lead, $customerData);

                // Handle order confirmation (intent === 'order_confirmed' with current_order)
                // NOTE: Order is already created in GroqChatService::handleConfirmation()
                // This is now ONLY for updating conversation context, NOT creating duplicate orders
                Log::info('AiChatService: Checking order confirmation', [
                    'intent' => $intent,
                    'has_current_order' => !empty($currentOrder),
                    'order_id' => $currentOrder['order_id'] ?? null,
                ]);

                if ($intent === 'order_confirmed' && !empty($currentOrder)) {
                    // Order already created in GroqChatService, just update context
                    if (!empty($currentOrder['order_id'])) {
                        Log::info('AiChatService: Order already created in GroqChatService', [
                            'order_id' => $currentOrder['order_id'],
                            'order_number' => $currentOrder['order_number'] ?? null,
                        ]);

                        // Update conversation context with order info
                        $context = $conversation->ai_context ?? [];
                        $context['collected_data']['order_created'] = true;
                        $context['collected_data']['order_id'] = $currentOrder['order_id'];
                        $context['collected_data']['order_number'] = $currentOrder['order_number'] ?? null;
                        $context['collected_data']['order_created_at'] = now()->toDateTimeString();
                        $conversation->ai_context = $context;
                        $conversation->save();
                    } else {
                        // Fallback: Only create if no order_id exists (should be rare now)
                        Log::info('AiChatService: Creating order in database (fallback)');
                        $this->handleOrderConfirmationV2($conversation, $lead, $currentOrder);
                    }
                }

                // Update conversation context with metadata
                $this->updateConversationContext($conversation, $lead, $message, $response, [
                    'intent' => $intent,
                    'cart' => $cart,
                    'customer_data' => $customerData,
                    'products_mentioned' => $productsMentioned,
                ]);

                // Update lead statistics
                $lead->increment('total_messages');
                $lead->last_contact_at = now();
                $lead->save();
            } else {
                // No response from AI - this should be RARE!
                Log::warning('AI did not respond to message', [
                    'message' => $message,
                    'user_id' => $this->user->id,
                    'result' => $result,
                ]);

                // Only log if it's truly an important question
                $this->logUnansweredQuestion($message);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('AI Chat Service Error: ' . $e->getMessage(), [
                'message' => $message,
                'trace' => $e->getTraceAsString()
            ]);

            // Log as unanswered question
            try {
                $this->logUnansweredQuestion($message);
            } catch (\Exception $logError) {
                Log::error('Failed to log unanswered question in exception', [
                    'error' => $logError->getMessage()
                ]);
            }

            // Never fail silently after we already computed a reply.
            if (!empty($response)) {
                return $response;
            }

            // Fallback to a minimal store-scoped message.
            return 'عذرًا صار خلل بسيط. ممكن تعيد رسالتك؟';
        }
    }

    /**
     * Process message using V3 state-driven architecture
     *
     * V3 uses a completely different flow:
     * 1. IntentAnalyzer detects intent
     * 2. StateMachine handles state transitions
     * 3. Business logic services (Cart, Order) handle operations
     * 4. ResponseGenerator creates natural responses
     */
    /**
     * Process a message through the new ChatOrchestrator (OpenAI, tool-calling, state machine).
     */
    protected function processWithOrchestrator(Conversation $conversation, Lead $lead, string $message): ?string
    {
        try {
            $result = $this->orchestrator->processMessage(
                $this->user->id,
                $lead->id,
                $message,
                $conversation->participant_id ?? null,
                $conversation->platform ?? 'web',
            );

            $response = $result['reply'] ?? null;

            if ($response) {
                // Update lead statistics
                $lead->increment('total_messages');
                $lead->last_contact_at = now();
                $lead->save();

                // Persist pending images in conversation context for webhook to send
                $images = $result['images'] ?? [];
                if (!empty($images)) {
                    $ctx = $conversation->ai_context ?? [];
                    $ctx['pending_images'] = $images;
                    $conversation->ai_context = $ctx;
                    $conversation->save();
                }

                // Persist any order-created actions
                $actions = $result['actions'] ?? [];
                if (!empty($actions['order_created'])) {
                    $ctx = $conversation->ai_context ?? [];
                    $ctx['collected_data']['order_created'] = true;
                    $ctx['collected_data']['order_id'] = $actions['order_created'];
                    $ctx['collected_data']['order_created_at'] = now()->toDateTimeString();
                    $conversation->ai_context = $ctx;
                    $conversation->save();
                }

                Log::info('AiChatService Orchestrator: Message processed', [
                    'conversation_id' => $conversation->id,
                    'session_id'      => $result['session_id'] ?? null,
                ]);
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('AiChatService Orchestrator Error: ' . $e->getMessage(), [
                'message' => $message,
                'trace'   => $e->getTraceAsString(),
            ]);

            return 'عذرًا صار خلل بسيط. ممكن تعيد رسالتك؟';
        }
    }

    /**
     * Process incoming message and return full result (with products to show for images)
     * FIX #27: Support both products_to_show (IDs) and images (URLs) from GroqChatServiceV2
     */
    public function processMessageWithProducts(Conversation $conversation, string $message): array
    {
        if (!$this->settings->ai_enabled || !$this->settings->auto_reply_enabled) {
            return ['reply' => null, 'products_to_show' => [], 'images' => []];
        }

        try {
            $lead = $this->ensureLead($conversation);

            // Defensive check: ensure $this->user is actually a User, not a Lead or other model
            if (!$this->user instanceof User) {
                Log::error('AiChatService: $this->user is not a User instance', [
                    'user_type' => get_class($this->user),
                    'user_is_lead' => $this->user instanceof Lead,
                ]);
                return ['reply' => null, 'products_to_show' => [], 'images' => []];
            }

            // New ChatOrchestrator — single unified path
            if ($this->orchestrator) {
                $orchResult = $this->orchestrator->processMessage(
                    $this->user->id,
                    $lead->id,
                    $message,
                    $conversation->participant_id ?? null,
                    $conversation->platform ?? 'web',
                );

                // Update lead statistics
                if (!empty($orchResult['reply'])) {
                    $lead->increment('total_messages');
                    $lead->last_contact_at = now();
                    $lead->save();
                }

                return [
                    'reply'            => $orchResult['reply'] ?? null,
                    'messages'         => $orchResult['messages'] ?? [],
                    'products_to_show' => $orchResult['products'] ?? [],
                    'images'           => $orchResult['images'] ?? [],
                    'intent'           => null,
                ];
            }

            // V1/V2 fallback
            $result = $this->groqService->processMessage($conversation, $lead, $message);

            $response = $result['reply'] ?? null;
            $productsToShow = $result['products_to_show'] ?? [];
            $images = $result['images'] ?? [];

            // V1/V2 return images as arrays with product_id — collect IDs for sendMultipleProductImages
            foreach ($images as $image) {
                if (is_array($image) && !empty($image['product_id']) && !in_array($image['product_id'], $productsToShow)) {
                    $productsToShow[] = $image['product_id'];
                }
            }

            $response = $this->removeEmojis($response);

            return [
                'reply'            => $response,
                'products_to_show' => $productsToShow,
                'images'           => $images,
                'intent'           => $result['intent'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('AI Chat Service Error: ' . $e->getMessage());
            return ['reply' => 'عذرًا صار خلل بسيط. ممكن تعيد رسالتك؟', 'products_to_show' => [], 'images' => []];
        }
    }

    /**
     * Update lead from session customer data
     */
    protected function updateLeadFromSession(Lead $lead, array $customerData): void
    {
        $updated = false;

        if (!empty($customerData['name']) && empty($lead->name)) {
            $lead->name = $customerData['name'];
            $updated = true;
        }

        if (!empty($customerData['phone']) && empty($lead->phone)) {
            $lead->phone = $customerData['phone'];
            $updated = true;
        }

        if (!empty($customerData['address']) && empty($lead->address)) {
            $lead->address = $customerData['address'];
            $updated = true;
        }

        if ($updated) {
            $lead->save();
        }
    }

    /**
     * Remove all emojis from text
     * Covers Unicode emoji ranges
     */
    protected function removeEmojis(?string $text): ?string
    {
        if (empty($text)) {
            return $text;
        }

        // Remove emoji unicode ranges
        $patterns = [
            '/[\x{1F600}-\x{1F64F}]/u', // Emoticons
            '/[\x{1F300}-\x{1F5FF}]/u', // Misc Symbols and Pictographs
            '/[\x{1F680}-\x{1F6FF}]/u', // Transport and Map
            '/[\x{1F700}-\x{1F77F}]/u', // Alchemical Symbols
            '/[\x{1F780}-\x{1F7FF}]/u', // Geometric Shapes Extended
            '/[\x{1F800}-\x{1F8FF}]/u', // Supplemental Arrows-C
            '/[\x{1F900}-\x{1F9FF}]/u', // Supplemental Symbols and Pictographs
            '/[\x{1FA00}-\x{1FA6F}]/u', // Chess Symbols
            '/[\x{1FA70}-\x{1FAFF}]/u', // Symbols and Pictographs Extended-A
            '/[\x{2600}-\x{26FF}]/u',   // Misc symbols
            '/[\x{2700}-\x{27BF}]/u',   // Dingbats
            '/[\x{FE00}-\x{FE0F}]/u',   // Variation Selectors
            '/[\x{1F1E0}-\x{1F1FF}]/u', // Flags
            '/[\x{200D}]/u',            // Zero Width Joiner
            '/[\x{20E3}]/u',            // Combining Enclosing Keycap
            '/[\x{E0020}-\x{E007F}]/u', // Tags
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }

        // Clean up multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n", $text);

        return trim($text);
    }

    /**
     * Handle order confirmation from Groq service (v2 - native)
     * Creates OnlineOrder with proper items from cart
     */
    protected function handleOrderConfirmationV2(Conversation $conversation, Lead $lead, array $orderData): void
    {
        try {
            $items = $orderData['items'] ?? [];
            $customer = $orderData['customer'] ?? [];
            $totalPrice = $orderData['total_price'] ?? 0;

            $currentOrderHash = $this->computeOrderHash($orderData);

            if (empty($items)) {
                Log::warning('Order confirmation without items', ['order_data' => $orderData]);
                return;
            }

            // Check if order already created for this session
            $context = $conversation->ai_context ?? [];
            if (!empty($context['collected_data']['order_created'])) {
                $existingOrderId = $context['collected_data']['order_id'] ?? null;
                $existingOrderHash = $context['collected_data']['order_hash'] ?? null;

                // Only skip if it's truly the same confirmation payload.
                if (!empty($existingOrderId) && !empty($existingOrderHash) && hash_equals($existingOrderHash, $currentOrderHash)) {
                    $existingOrder = OnlineOrder::where('id', $existingOrderId)->first();
                    if ($existingOrder
                        && (int) $existingOrder->user_id === (int) $this->user->id
                        && (int) $existingOrder->conversation_id === (int) $conversation->id) {
                        Log::info('Order already created, skipping duplicate', ['order_id' => $existingOrderId]);
                        return;
                    }
                }

                // If we got here, the stored flag is stale (different payload, missing hash, or wrong linkage).
                unset($context['collected_data']['order_created'], $context['collected_data']['order_id'], $context['collected_data']['order_number'], $context['collected_data']['order_hash'], $context['collected_data']['order_created_at']);
                $conversation->ai_context = $context;
                $conversation->save();
                Log::warning('Cleared stale order_created flag (allowing new order)');
            }

            // Normalize order source to allowed enum values
            $allowedSources = ['facebook', 'instagram', 'whatsapp', 'ai_chat', 'manual'];
            $source = $conversation->platform ?? 'ai_chat';
            if (!in_array($source, $allowedSources, true)) {
                $source = 'ai_chat';
            }

            // Update lead with customer data from order
            if (!empty($customer['name'])) {
                $lead->name = $customer['name'];
            }
            if (!empty($customer['phone'])) {
                $lead->phone = $customer['phone'];
            }
            if (!empty($customer['address'])) {
                $lead->address = $customer['address'];
            }
            $lead->save();

            // Create the online order
            $order = OnlineOrder::create([
                'user_id' => $this->user->id,
                'lead_id' => $lead->id,
                'conversation_id' => $conversation->id,
                'customer_name' => $customer['name'] ?? $lead->name ?? 'زبون',
                'customer_phone' => $customer['phone'] ?? $lead->phone ?? '',
                'customer_address' => $customer['address'] ?? $lead->address ?? '',
                'customer_city' => $lead->city,
                'subtotal' => $totalPrice,
                'shipping_cost' => 0,
                'discount' => 0,
                'total' => $totalPrice,
                'status' => 'pending',
                'source' => $source,
                'customer_notes' => 'طلب من الذكاء الاصطناعي - ' . count($items) . ' منتج',
                'meta_data' => [
                    'created_by' => 'groq_ai',
                    'items_count' => count($items),
                    // From now on: inventory is deducted when store confirms the order.
                    'inventory_strategy' => 'on_confirm',
                ],
            ]);

            // Create order items - FIX: Use correct column names
            foreach ($items as $item) {
                $productName = $item['name'] ?? '';
                $quantity = $item['quantity'] ?? 1;
                $price = $item['price'] ?? 0;

                // Try to find product in database
                $product = Product::where('user_id', $this->user->id)
                    ->where(function($q) use ($productName) {
                        $q->where('name', $productName)
                          ->orWhere('name', 'LIKE', '%' . $productName . '%');
                    })
                    ->first();

                // FIX: Use 'unit_price' not 'price' (matches OnlineOrderItem model)
                OnlineOrderItem::create([
                    'online_order_id' => $order->id,
                    'product_id' => $product->id ?? null,
                    'product_name' => $productName,
                    'unit_price' => $price,  // FIXED: was 'price'
                    'quantity' => $quantity,
                    'total' => $price * $quantity,
                ]);
            }

            // Recalculate order totals
            $order->calculateTotals();

            // Update conversation context
            $context['collected_data']['order_created'] = true;
            $context['collected_data']['order_id'] = $order->id;
            $context['collected_data']['order_number'] = $order->order_number;
            $context['collected_data']['order_hash'] = $currentOrderHash;
            $context['collected_data']['order_created_at'] = now()->toIso8601String();
            $context['collected_data']['cart'] = $items;
            $conversation->ai_context = $context;
            $conversation->save();

            // Update lead status
            // Leads.status enum does not include "customer"; use "converted" after a successful order.
            $lead->status = 'converted';
            $lead->save();

            // Clear session cart after successful order
            $this->groqService->clearSessionCart($lead);

            Log::info('Order created successfully', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'lead_id' => $lead->id,
                'items_count' => count($items),
                'total' => $totalPrice,
            ]);

            // Send notification to store owner
            Notification::create([
                'user_id' => $this->user->id,
                'type' => 'new_order',
                'title' => 'طلب جديد من الذكاء الاصطناعي',
                'message' => "طلب جديد من {$lead->name} - المجموع: {$totalPrice} دينار - {$order->order_number}",
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'lead_id' => $lead->id,
                    'total' => $totalPrice,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Order confirmation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'order_data' => $orderData,
            ]);
        }
    }

    protected function computeOrderHash(array $orderData): string
    {
        $items = $orderData['items'] ?? [];
        if (is_array($items)) {
            usort($items, function ($a, $b) {
                $an = is_array($a) ? (string)($a['name'] ?? '') : '';
                $bn = is_array($b) ? (string)($b['name'] ?? '') : '';
                return $an <=> $bn;
            });
        }

        $payload = [
            'items' => array_map(function ($item) {
                if (!is_array($item)) {
                    return $item;
                }
                return [
                    'name' => (string)($item['name'] ?? ''),
                    'price' => (int)($item['price'] ?? 0),
                    'quantity' => (int)($item['quantity'] ?? 1),
                ];
            }, $items),
            'customer' => [
                'name' => (string)(($orderData['customer']['name'] ?? '') ?? ''),
                'phone' => (string)(($orderData['customer']['phone'] ?? '') ?? ''),
                'address' => (string)(($orderData['customer']['address'] ?? '') ?? ''),
            ],
            'total_price' => (int)($orderData['total_price'] ?? 0),
        ];

        return sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Notify human agent when AI needs help
     * Returns a polite waiting message for the customer
     */
    protected function notifyHumanAgent(Conversation $conversation, Lead $lead, string $message): string
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'human_agent_needed',
            'title' => 'تحتاج محادثة إلى تدخل بشري',
            'message' => "المحادثة مع {$lead->name} تحتاج إلى موظف. آخر رسالة: {$message}",
            'data' => [
                'conversation_id' => $conversation->id,
                'lead_id' => $lead->id,
                'customer_message' => $message,
            ],
        ]);

        Log::info('Human agent notification sent', [
            'conversation_id' => $conversation->id,
            'lead_id' => $lead->id,
        ]);

        // Return a polite waiting message based on question type
        return $this->getWaitingMessage($message);
    }

    /**
     * Get appropriate waiting message based on customer question
     */
    protected function getWaitingMessage(string $message): string
    {
        $messageLower = mb_strtolower($message);

        // Order status related questions
        if (mb_strpos($messageLower, 'وين طلبي') !== false ||
            mb_strpos($messageLower, 'يمته يوصل') !== false ||
            mb_strpos($messageLower, 'متى يوصل') !== false ||
            mb_strpos($messageLower, 'كم وصل') !== false) {
            return "لحظات أتأكد من حالة الطلب مالتك... 📦";
        }

        // Product availability questions
        if (mb_strpos($messageLower, 'يتوفر') !== false ||
            mb_strpos($messageLower, 'متوفر') !== false ||
            mb_strpos($messageLower, 'موجود') !== false) {
            return "ثواني أتأكد إذا متوفر... 🔍";
        }

        // Price questions
        if (mb_strpos($messageLower, 'بكم') !== false ||
            mb_strpos($messageLower, 'شنو السعر') !== false ||
            mb_strpos($messageLower, 'كم السعر') !== false) {
            return "لحظة أشوف السعر الكلي... 💰";
        }

        // Return or refund questions
        if (mb_strpos($messageLower, 'استرجاع') !== false ||
            mb_strpos($messageLower, 'ارجاع') !== false ||
            mb_strpos($messageLower, 'استبدال') !== false) {
            return "لحظات أتأكد من سياسة الاسترجاع... 📋";
        }

        // Default waiting message
        $waitingMessages = [
            "لحظات، دعني أتأكد وأرد عليك... ⏳",
            "ثواني أتحقق من المعلومات... 🔍",
            "دقيقة واحدة أتأكد... ⌛",
            "لحظة، خليني أشوف... 👀",
        ];

        return $waitingMessages[array_rand($waitingMessages)];
    }

    /**
     * Check if message requires IMMEDIATE human response (bypass AI)
     * These are complex questions or complaints that need direct human attention
     */
    protected function requiresImmediateHumanResponse(string $message): bool
    {
        $messageLower = mb_strtolower($message);

        // Complaints and problems
        $complaintKeywords = [
            'شكوى',
            'مشكلة',
            'غلط',
            'خطأ',
            'ما وصلني',
            'ما جاني',
            'متأخر',
            'تالف',
            'مكسور',
            'غير صحيح',
            'اريد استرجاع',
            'اريد ارجاع',
            'اريد استبدال',
            'ناقص',
            'نزين',
            'ميجينيش',
        ];

        foreach ($complaintKeywords as $keyword) {
            if (mb_strpos($messageLower, $keyword) !== false) {
                Log::info("Message requires human response - Complaint detected: {$keyword}");
                return true;
            }
        }

        // Custom or special requests
        $specialRequestKeywords = [
            'تخصيص',
            'تصميم خاص',
            'طلب خاص',
            'شرط خاص',
            'اريد تغيير',
            'ممكن تعدل',
            'طباعة اسمي',
            'اضافة صورة',
        ];

        foreach ($specialRequestKeywords as $keyword) {
            if (mb_strpos($messageLower, $keyword) !== false) {
                Log::info("Message requires human response - Special request: {$keyword}");
                return true;
            }
        }

        // Questions about payment methods or banking
        $paymentKeywords = [
            'دفع فيزا',
            'بطاقة ائتمان',
            'زين كاش',
            'كي كارد',
            'تقسيط',
            'دفعات',
        ];

        foreach ($paymentKeywords as $keyword) {
            if (mb_strpos($messageLower, $keyword) !== false) {
                Log::info("Message requires human response - Payment question: {$keyword}");
                return true;
            }
        }

        // Wholesale or bulk orders
        if (mb_strpos($messageLower, 'جملة') !== false ||
            mb_strpos($messageLower, 'بالجملة') !== false ||
            preg_match('/\d{2,}\s*(قطعة|حبة|عدد)/', $messageLower)) {
            Log::info("Message requires human response - Bulk order detected");
            return true;
        }

        return false;
    }

    /**
     * Check if customer is asking about order status and provide DIRECT answer from database
     * This bypasses AI to give accurate, real-time order information
     */
    protected function checkOrderStatus(Conversation $conversation, Lead $lead, string $message): ?string
    {
        $messageLower = mb_strtolower($message);

        // Keywords for order status inquiries
        $orderStatusKeywords = [
            'وين طلبي',
            'يمته يوصل',
            'متى يوصل',
            'كم وصل',
            'شنو تفاصيل طلبي',
            'تفاصيل طلبي',
            'اريد طلبي',
            'طلبي الحالي',
            'طلبي الحال',
            'حالة الطلب',
            'حالة طلبي',
            'وين وصل',
            'وصل الطلب',
        ];

        $isOrderStatusQuestion = false;
        foreach ($orderStatusKeywords as $keyword) {
            if (mb_strpos($messageLower, $keyword) !== false) {
                $isOrderStatusQuestion = true;
                break;
            }
        }

        if (!$isOrderStatusQuestion) {
            return null; // Not an order status question, continue to AI
        }

        // Get customer's latest order
        $latestOrder = OnlineOrder::where(function($query) use ($conversation, $lead) {
                $query->where('conversation_id', $conversation->id)
                      ->orWhere('lead_id', $lead->id);
            })
            ->with('items')
            ->latest()
            ->first();

        // No order found
        if (!$latestOrder) {
            return "ما عندك طلب مسجل لحد الآن يا {$lead->name}. تحب تطلب شي؟ 😊";
        }

        // Build response based on order status
        $statusLabels = [
            'pending' => 'قيد الانتظار',
            'confirmed' => 'مؤكد',
            'processing' => 'قيد التجهيز',
            'shipped' => 'تم الشحن',
            'delivered' => 'تم التوصيل',
            'cancelled' => 'ملغي',
        ];

        $statusLabel = $statusLabels[$latestOrder->status] ?? $latestOrder->status;
        $orderNumber = $latestOrder->order_number;
        $total = number_format($latestOrder->total ?? $latestOrder->subtotal ?? 0);
        $currency = 'دينار';

        // Get order items
        $items = $latestOrder->items->map(function($item) {
            return "{$item->product_name} × {$item->quantity}";
        })->implode('، ');

        // Determine response based on question type and status
        if (mb_strpos($messageLower, 'يمته') !== false || mb_strpos($messageLower, 'متى') !== false) {
            // Question about delivery time
            switch ($latestOrder->status) {
                case 'pending':
                case 'confirmed':
                    return "طلبك #{$orderNumber} مؤكد وقيد التجهيز 📦\nراح يوصلك خلال 1-3 أيام إن شاء الله\n\nالمنتجات: {$items}";

                case 'processing':
                    return "طلبك #{$orderNumber} قيد التجهيز حالياً 👨‍💼\nراح يشحن قريباً جداً وراح يوصلك خلال 1-2 يوم\n\nالمنتجات: {$items}";

                case 'shipped':
                    return "طلبك #{$orderNumber} بالطريق إليك! 🚚\nراح يوصلك خلال 24 ساعة إن شاء الله\n\nالمنتجات: {$items}";

                case 'delivered':
                    return "طلبك #{$orderNumber} وصلك! ✅\nنتمنى يعجبك المنتج 😊\n\nالمنتجات: {$items}";

                case 'cancelled':
                    return "طلبك #{$orderNumber} ملغي ❌\nإذا تحب تطلب مرة ثانية، خبرني!";
            }
        }

        // Default: Full order details
        $response = "📋 تفاصيل طلبك:\n\n";
        $response .= "🔢 رقم الطلب: #{$orderNumber}\n";
        $response .= "📦 المنتجات: {$items}\n";
        $response .= "💰 المبلغ الكلي: {$total} {$currency}\n";
        $response .= "📊 الحالة: {$statusLabel}\n";
        $response .= "📅 تاريخ الطلب: " . $latestOrder->created_at->format('Y-m-d') . "\n\n";

        // Add delivery estimate based on status
        switch ($latestOrder->status) {
            case 'pending':
            case 'confirmed':
                $response .= "⏰ التوصيل المتوقع: خلال 1-3 أيام";
                break;
            case 'processing':
                $response .= "⏰ التوصيل المتوقع: خلال 1-2 يوم";
                break;
            case 'shipped':
                $response .= "🚚 الطلب بالطريق - يوصلك خلال 24 ساعة";
                break;
            case 'delivered':
                $response .= "✅ تم التوصيل بنجاح!";
                break;
        }

        Log::info("Direct order status check provided response", [
            'order_id' => $latestOrder->id,
            'order_number' => $orderNumber,
            'status' => $latestOrder->status,
        ]);

        return $response;
    }

    /**
     * Extract customer data from API metadata (new v3 feature)
     */
    protected function extractCustomerDataFromMetadata(Lead $lead, array $metadata): void
    {
        $entities = $metadata['entities'] ?? [];
        $customerData = $entities['customer_data'] ?? [];

        if (empty($customerData)) {
            return;
        }

        $updated = false;

        // Extract name
        if (!empty($customerData['name']) && !$lead->name) {
            $lead->name = $customerData['name'];
            $updated = true;
            Log::info('AI extracted name', ['name' => $customerData['name']]);
        }

        // Extract phone
        if (!empty($customerData['phone']) && !$lead->phone) {
            $lead->phone = $customerData['phone'];
            $updated = true;
            Log::info('AI extracted phone', ['phone' => $customerData['phone']]);
        }

        // Extract address
        if (!empty($customerData['address']) && !$lead->address) {
            $lead->address = $customerData['address'];
            $updated = true;
            Log::info('AI extracted address', ['address' => $customerData['address']]);
        }

        // Extract city
        if (!empty($customerData['city']) && !$lead->city) {
            $lead->city = $customerData['city'];
            $updated = true;
            Log::info('AI extracted city', ['city' => $customerData['city']]);
        }

        if ($updated) {
            $lead->save();

            // Also update conversation context
            $conversation = $lead->conversation;
            if ($conversation) {
                $context = $conversation->ai_context ?? [];
                $context['collected_data'] = array_merge(
                    $context['collected_data'] ?? [],
                    $customerData
                );
                $conversation->ai_context = $context;
                $conversation->save();
            }
        }
    }

    /**
     * Extract product interests from API metadata (new v3 feature)
     */
    protected function extractProductInterestsFromMetadata(Lead $lead, array $metadata): void
    {
        $entities = $metadata['entities'] ?? [];
        $products = $entities['products'] ?? [];

        if (empty($products)) {
            return;
        }

        $conversation = $lead->conversation;
        if (!$conversation) {
            return;
        }

        $context = $conversation->ai_context ?? [];
        $collectedData = $context['collected_data'] ?? [];
        $interestedProducts = $collectedData['interested_products'] ?? [];

        foreach ($products as $product) {
            $productName = $product['name'] ?? null;
            if ($productName && !in_array($productName, $interestedProducts)) {
                $interestedProducts[] = $productName;
                Log::info('AI detected product interest', ['product' => $productName]);
            }
        }

        if (!empty($interestedProducts)) {
            $collectedData['interested_products'] = array_unique($interestedProducts);
            $context['collected_data'] = $collectedData;
            $conversation->ai_context = $context;
            $conversation->save();
        }
    }

    /**
     * Handle InvenGPT v6 order confirmation
     * Processes confirmed orders with multi-item cart and creates OnlineOrder
     */
    protected function handleOrderConfirmation(Conversation $conversation, Lead $lead, array $orderData): void
    {
        try {
            $items = $orderData['items'] ?? [];
            $customer = $orderData['customer'] ?? [];
            $totalPrice = $orderData['total_price'] ?? 0;
            $createdAt = $orderData['created_at'] ?? now();

            if (empty($items)) {
                Log::warning('InvenGPT v6: Order confirmation without items', ['order_data' => $orderData]);
                return;
            }

            // Update lead with customer data from order
            if (!empty($customer['name'])) {
                $lead->name = $customer['name'];
            }
            if (!empty($customer['phone'])) {
                $lead->phone = $customer['phone'];
            }
            if (!empty($customer['address'])) {
                $lead->address = $customer['address'];
            }
            $lead->save();

            // Create the online order
            $order = OnlineOrder::create([
                'user_id' => $this->user->id,
                'lead_id' => $lead->id,
                'conversation_id' => $conversation->id,
                'customer_name' => $customer['name'] ?? $lead->name,
                'customer_phone' => $customer['phone'] ?? $lead->phone,
                'customer_address' => $customer['address'] ?? $lead->address,
                'customer_city' => $lead->city,
                'subtotal' => $totalPrice,
                'shipping_cost' => 0,
                'discount' => 0,
                'total' => $totalPrice,
                'status' => 'pending',
                'source' => $conversation->platform ?? 'facebook',
                'notes' => 'طلب من InvenGPT v6 - ' . count($items) . ' منتج',
                'created_at' => $createdAt,
            ]);

            // Create order items
            foreach ($items as $item) {
                $productName = $item['name'] ?? '';
                $quantity = $item['quantity'] ?? 1;
                $price = $item['price'] ?? 0;

                // Try to find product in database
                $product = Product::where('user_id', $this->user->id)
                    ->where('name', 'LIKE', '%' . $productName . '%')
                    ->first();

                OnlineOrderItem::create([
                    'online_order_id' => $order->id,
                    'product_id' => $product->id ?? null,
                    'product_name' => $productName,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $price * $quantity,
                ]);

                // Update product stock if found
                if ($product && $product->quantity >= $quantity) {
                    $product->decrement('quantity', $quantity);
                    Log::info('InvenGPT v6: Stock updated', [
                        'product' => $productName,
                        'quantity_sold' => $quantity,
                        'remaining' => $product->quantity - $quantity,
                    ]);
                }
            }

            // Update conversation context
            $context = $conversation->ai_context ?? [];
            $context['collected_data']['order_created'] = true;
            $context['collected_data']['order_id'] = $order->id;
            $context['collected_data']['order_number'] = $order->id;
            $context['collected_data']['cart'] = $items; // Store cart info
            $conversation->ai_context = $context;
            $conversation->save();

            // Update lead status
            // Leads.status enum does not include "customer"; use "converted" after a successful order.
            $lead->status = 'converted';
            $lead->save();

            Log::info('InvenGPT v6: Order created successfully', [
                'order_id' => $order->id,
                'lead_id' => $lead->id,
                'items_count' => count($items),
                'total' => $totalPrice,
            ]);

            // Send notification to store owner
            Notification::create([
                'user_id' => $this->user->id,
                'type' => 'new_order',
                'title' => 'طلب جديد من InvenGPT',
                'message' => "طلب جديد من {$lead->name} - المجموع: {$totalPrice} دينار",
                'data' => [
                    'order_id' => $order->id,
                    'lead_id' => $lead->id,
                    'total' => $totalPrice,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('InvenGPT v6: Order confirmation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'order_data' => $orderData,
            ]);
        }
    }

    /**
     * Check if user is asking for product images and send them
     * Returns the product if images were sent, null otherwise
     */
    public function checkAndSendProductImages(Conversation $conversation, string $message): ?Product
    {
        // Image request keywords
        $imageKeywords = ['صور', 'صورة', 'صوره', 'شوفني', 'ارني', 'أرني', 'عرضلي', 'بصور', 'صور المنتج', 'صورته', 'شكله'];

        $messageLower = mb_strtolower($message);
        $wantsImage = false;

        foreach ($imageKeywords as $keyword) {
            if (mb_stripos($messageLower, $keyword) !== false) {
                $wantsImage = true;
                break;
            }
        }

        if (!$wantsImage) {
            return null;
        }

        // Find which product they want images of
        $products = Product::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->with('images')
            ->get();

        $matchedProduct = null;

        foreach ($products as $product) {
            $productNameLower = mb_strtolower($product->name);

            // Check direct match
            if (mb_strpos($messageLower, $productNameLower) !== false) {
                $matchedProduct = $product;
                break;
            }

            // Check common variations
            $variations = [
                'شيرت' => ['تشيرت', 'تيشرت', 'تيشيرت', 'الشيرت', 'شيريت', 'تشيريت'],
                'master' => ['ماستر'],
                'counter' => ['كاونتر', 'كونتر'],
            ];

            foreach ($variations as $english => $arabicVariants) {
                if (mb_stripos($productNameLower, $english) !== false) {
                    foreach ($arabicVariants as $arabic) {
                        if (mb_strpos($messageLower, $arabic) !== false) {
                            $matchedProduct = $product;
                            break 3;
                        }
                    }
                }
            }
        }

        // If no specific product mentioned but asking for images, try to get from context
        if (!$matchedProduct) {
            $context = $conversation->ai_context ?? [];
            $collectedData = $context['collected_data'] ?? [];
            $interestedProducts = $collectedData['interested_products'] ?? [];

            if (!empty($interestedProducts)) {
                $lastProduct = end($interestedProducts);
                $matchedProduct = Product::where('user_id', $this->user->id)
                    ->where('name', 'like', "%{$lastProduct}%")
                    ->with('images')
                    ->first();
            }
        }

        if ($matchedProduct && $matchedProduct->images->count() > 0) {
            return $matchedProduct;
        }

        return null;
    }

    /**
     * Get the URL for a product's primary image
     */
    public function getProductImageUrl(Product $product): ?string
    {
        $image = $product->images->first();
        if ($image && $image->image_path) {
            return url('storage/' . $image->image_path);
        }
        return null;
    }

    /**
     * Get relevant products based on conversation context
     * CRITICAL: Limits to avoid token overflow (only 5-10 products)
     */
    protected function getRelevantProducts(Conversation $conversation, int $limit = 8)
    {
        // Get last few messages to extract keywords
        $lastMessages = $conversation->messages()
            ->where('role', 'user')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->pluck('content')
            ->implode(' ');

        // Extract keywords (product names, categories)
        $keywords = $this->extractKeywords($lastMessages);

        // Start with base query
        $query = Product::where('user_id', $this->user->id)
            ->where('is_active', true);

        // If keywords found, filter by them
        if (!empty($keywords)) {
            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $q->orWhere('name', 'like', "%{$keyword}%")
                      ->orWhere('description', 'like', "%{$keyword}%");
                }
            });
        }

        // Get limited results
        $products = $query->limit($limit)->get();

        // If no products found with keywords, get popular ones
        if ($products->isEmpty()) {
            $products = Product::where('user_id', $this->user->id)
                ->where('is_active', true)
                ->orderBy('quantity', 'desc')  // Products with most stock
                ->limit($limit)
                ->get();
        }

        return $products;
    }

    /**
     * Extract keywords from text (simple Arabic keyword extraction)
     */
    protected function extractKeywords(string $text): array
    {
        // Common Arabic product-related words
        $keywords = [];

        // Remove common stop words
        $stopWords = ['في', 'من', 'إلى', 'على', 'عن', 'هل', 'ما', 'كيف', 'اريد', 'ابي', 'شنو', 'وين'];

        // Split into words
        $words = preg_split('/\s+/', $text);

        foreach ($words as $word) {
            $word = trim($word);
            // Keep words longer than 2 chars and not in stop words
            if (strlen($word) > 2 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }

        return array_unique(array_slice($keywords, 0, 5)); // Max 5 keywords
    }

    /**
     * Build context string with store, product, and COLLECTED customer information
     * CRITICAL: Only send relevant products to avoid token overflow!
     */
    protected function buildContext(Conversation $conversation, Lead $lead): string
    {
        $context = [];

        // Store info
        $context[] = "=== معلومات المتجر ===";
        $context[] = "اسم المتجر: " . $this->user->name;

        if ($this->settings->store_description) {
            $context[] = "وصف المتجر: " . $this->settings->store_description;
        }

        // Products - FILTER to only 5-10 relevant ones!
        $products = $this->getRelevantProducts($conversation, 8);

        if ($products->count() > 0) {
            $context[] = "\n=== المنتجات المتوفرة ===";
            foreach ($products as $product) {
                $available = $product->quantity > 0 ? 'متوفر (' . $product->quantity . ' قطعة)' : 'غير متوفر';
                $context[] = sprintf(
                    "• %s | السعر: %s %s | الحالة: %s",
                    $product->name,
                    number_format($product->price),
                    $product->currency ?? 'IQD',
                    $available
                );
            }
        }

        // IMPORTANT: Add collected customer data so AI remembers it
        $context[] = "\n=== معلومات الزبون المجمعة (تذكرها!) ===";
        $collectedData = $conversation->ai_context['collected_data'] ?? [];

        if ($lead->name || isset($collectedData['name'])) {
            $context[] = "✓ الاسم: " . ($lead->name ?? $collectedData['name'] ?? 'غير معروف');
        } else {
            $context[] = "✗ الاسم: لم يُجمع بعد";
        }

        if ($lead->phone || isset($collectedData['phone'])) {
            $context[] = "✓ رقم الهاتف: " . ($lead->phone ?? $collectedData['phone'] ?? 'غير معروف');
        } else {
            $context[] = "✗ رقم الهاتف: لم يُجمع بعد";
        }

        if ($lead->city || isset($collectedData['city'])) {
            $context[] = "✓ المدينة: " . ($lead->city ?? $collectedData['city'] ?? 'غير معروف');
        } else {
            $context[] = "✗ المدينة: لم تُجمع بعد";
        }

        if ($lead->address || isset($collectedData['address'])) {
            $context[] = "✓ العنوان: " . ($lead->address ?? $collectedData['address'] ?? 'غير معروف');
        } else {
            $context[] = "✗ العنوان: لم يُجمع بعد";
        }

        // Product interests / what they want to buy
        if (isset($collectedData['interested_products']) && !empty($collectedData['interested_products'])) {
            $context[] = "✓ المنتجات المطلوبة: " . implode(', ', $collectedData['interested_products']);
        }

        // Previous orders info - Allow new orders!
        $previousOrders = OnlineOrder::where('conversation_id', $conversation->id)
            ->orWhere('lead_id', $lead->id)
            ->latest()
            ->take(3)
            ->get();

        if ($previousOrders->count() > 0) {
            $context[] = "\n=== الطلبات السابقة للزبون ===";
            foreach ($previousOrders as $prevOrder) {
                $statusLabels = [
                    'pending' => 'قيد الانتظار',
                    'confirmed' => 'مؤكد',
                    'processing' => 'قيد التجهيز',
                    'shipped' => 'تم الشحن',
                    'delivered' => 'تم التوصيل',
                    'cancelled' => 'ملغي',
                ];
                $statusLabel = $statusLabels[$prevOrder->status] ?? $prevOrder->status;
                $items = $prevOrder->items->pluck('product_name')->implode(', ');
                $total = number_format($prevOrder->total ?? $prevOrder->subtotal ?? 0);
                $currency = 'دينار';

                $context[] = "• طلب رقم: #{$prevOrder->order_number}";
                $context[] = "  - الحالة: {$statusLabel}";
                $context[] = "  - المنتجات: {$items}";
                $context[] = "  - المبلغ الكلي: {$total} {$currency}";
                $context[] = "  - تاريخ الطلب: " . $prevOrder->created_at->format('Y-m-d');
                $context[] = "";
            }
            $context[] = "✓ يمكن للزبون طلب منتجات جديدة في أي وقت!";
            $context[] = "✓ إذا سأل عن طلبه السابق، أخبره بالتفاصيل الكاملة من القائمة أعلاه.";
            $context[] = "✓ إذا سأل 'يمته يوصل؟' أو 'وقت التوصيل؟'، استخدم حالة الطلب للرد.";
        }

        // Delivery information
        $context[] = "\n=== معلومات التوصيل ===";
        $context[] = "• التوصيل: متوفر";
        $context[] = "• سعر التوصيل: 5,000 دينار";
        $context[] = "• وقت التوصيل: نفس اليوم أو خلال 1-2 يوم عمل";
        $context[] = "• التغطية: جميع أنحاء العراق";

        // Instructions for AI
        $context[] = "\n=== تعليمات مهمة ===";
        $context[] = "• إذا الزبون ذكر اسمه أو رقمه أو عنوانه، تذكرها ولا تسأل عنها مرة ثانية!";
        $context[] = "• عندما تكتمل جميع المعلومات (الاسم + الهاتف + العنوان)، أكد الطلب مباشرة.";
        $context[] = "• لا تكرر السؤال عن معلومات الزبون أعطاك إياها من قبل.";
        $context[] = "• إذا سأل عن التوصيل، استخدم المعلومات من قسم 'معلومات التوصيل'.";
        $context[] = "• إذا سأل عن طلبه أو وقت الوصول، استخدم قسم 'الطلبات السابقة'.";

        return implode("\n", $context);
    }

    /**
     * Get conversation history for AI context
     */
    protected function getConversationHistory(Conversation $conversation): array
    {
        $history = [];

        $messages = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit(30) // Increased to 30 for better memory
            ->get()
            ->reverse();

        foreach ($messages as $msg) {
            $role = $msg->direction === 'incoming' ? 'user' : 'model';
            $history[] = [
                'role' => $role,
                'content' => $msg->content ?? '',
            ];
        }

        return $history;
    }

    /**
     * Call the Flask AI API (trained Iraqi Arabic model)
     */
    protected function callAiApi(string $message, string $context, array $history): ?string
    {
        $systemInstruction = $this->getEnhancedSystemInstruction();

        // Add context to system instruction
        $fullSystemInstruction = $systemInstruction . "\n\n" . $context;

        // Format payload for Flask API
        $payload = [
            'message' => $message,
            'context' => $fullSystemInstruction,
            'conversation_history' => $history,
            'max_tokens' => (int) ($this->settings->max_output_tokens ?? 150),
            'temperature' => (float) ($this->settings->temperature ?? 0.7),
            'top_p' => (float) ($this->settings->top_p ?? 0.85),
            'top_k' => (int) ($this->settings->top_k ?? 40),
        ];

        Log::info('Calling Flask AI API', [
            'url' => $this->settings->ai_api_url,
            'message_length' => mb_strlen($message),
            'context_length' => mb_strlen($fullSystemInstruction),
        ]);

        try {
            $response = Http::timeout(45)->post(
                rtrim($this->settings->ai_api_url, '/') . '/chat',
                $payload
            );

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Flask API Response', [
                    'success' => $data['success'] ?? false,
                    'has_response' => isset($data['response']),
                    'has_thought' => isset($data['thought']),
                    'has_actions' => isset($data['actions']),
                ]);

                if ($data['success'] ?? false) {
                    // Extract the main response text
                    $aiResponse = $data['response'] ?? null;

                    // Log internal thought for debugging (not shown to user)
                    if (!empty($data['thought'])) {
                        Log::info('AI Internal Thought', ['thought' => $data['thought']]);
                    }

                    // Process actions if any
                    if (!empty($data['actions']) && is_array($data['actions'])) {
                        $this->processAiActions($data['actions']);
                    }

                    return $aiResponse;
                }
            }

            Log::warning('Flask API call failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Flask API Exception', [
                'error' => $e->getMessage(),
                'trace' => mb_substr($e->getTraceAsString(), 0, 500),
            ]);
            return null;
        }
    }

    /**
     * Process actions returned by AI model
     */
    protected function processAiActions(array $actions): void
    {
        foreach ($actions as $action) {
            $type = $action['type'] ?? null;

            Log::info('Processing AI Action', ['action' => $action]);

            switch ($type) {
                case 'provide_tracking':
                    // Handle tracking code provision
                    $orderId = $action['order_id'] ?? null;
                    $trackingCode = $action['tracking_code'] ?? null;
                    if ($orderId && $trackingCode) {
                        Log::info("Tracking requested for order: {$orderId}");
                        // TODO: Fetch and provide tracking info
                    }
                    break;

                case 'initiate_refund':
                    // Handle refund request
                    $orderId = $action['order_id'] ?? null;
                    if ($orderId) {
                        Log::info("Refund requested for order: {$orderId}");
                        // TODO: Integrate with your refund system
                        // Example: $this->processRefund($orderId);
                    }
                    break;

                case 'offer_refund_or_replace':
                    // Customer complaint - offer resolution
                    Log::info('Customer complaint detected - offer refund/replace');
                    // TODO: Escalate to support team
                    break;

                case 'confirm_cancel':
                    // Order cancellation confirmation
                    Log::info('Order cancellation confirmation needed');
                    // TODO: Cancel order logic
                    break;

                case 'start_price_match':
                    // Price matching request
                    Log::info('Price match request initiated');
                    // TODO: Notify pricing team
                    break;

                default:
                    Log::info("Unknown action type: {$type}");
            }
        }
    }

    /**
     * Get enhanced system instruction
     */
    protected function getEnhancedSystemInstruction(): string
    {
        $baseInstruction = $this->settings->system_instruction ?? AiSetting::getDefaultSystemInstruction();

        $enhancedInstruction = $baseInstruction . "\n\n";
        $enhancedInstruction .= "=== قواعد مهمة للذاكرة والطلبات ===\n";
        $enhancedInstruction .= "1. عندما الزبون يعطيك معلومة (اسم، رقم، عنوان)، احفظها ولا تسأل عنها مرة ثانية أبداً!\n";
        $enhancedInstruction .= "2. راجع قسم 'معلومات الزبون المجمعة' قبل كل رد لتعرف شنو عندك من معلومات.\n";
        $enhancedInstruction .= "3. إذا كل المعلومات موجودة (اسم ✓ + هاتف ✓ + عنوان ✓)، أكد الطلب مباشرة بدون أسئلة إضافية.\n";
        $enhancedInstruction .= "4. كون مختصر وواضح، لا تكرر نفس الكلام.\n";
        $enhancedInstruction .= "5. إذا الزبون كرر معلوماته، اعتذر واشكره وأكمل.\n";
        $enhancedInstruction .= "6. عند تأكيد الطلب، استخدم إحدى هذه العبارات: 'تم تأكيد طلبك'، 'راح نوصله لك'، 'التوصيل مجاني'، 'صار تدلل! تأكد طلبك'.\n";
        $enhancedInstruction .= "7. لا تغير المنتج اللي طلبه الزبون! إذا طلب تيشرت، لا تحوله لـ Master Counter والعكس صحيح.\n";
        $enhancedInstruction .= "8. إذا الزبون قال 'اكدلي' أو 'أي نعم' أو 'تمام'، هذا يعني موافق على الطلب - أكد الطلب فوراً!\n";
        $enhancedInstruction .= "9. الزبون يمكنه طلب منتجات جديدة حتى لو عنده طلبات سابقة - لا تمنعه!\n";
        $enhancedInstruction .= "10. إذا سأل الزبون 'وين طلبي' أو 'وين وصل'، أخبره بحالة طلبه من قسم 'الطلبات السابقة'.\n";
        $enhancedInstruction .= "11. إذا طلب صورة منتج، قل له أنك سترسلها له أو أن الصور غير متوفرة حالياً.\n";
        $enhancedInstruction .= "12. إذا أراد طلب كمية معينة (مثل 'قطعتين' أو '2 تشيرت')، سجل الكمية المطلوبة.\n";
        $enhancedInstruction .= "\n=== قواعد التعامل مع استعلامات الزبون عن الطلبات ===\n";
        $enhancedInstruction .= "13. إذا سأل الزبون 'يمته يوصل طلبي؟' أو 'متى يوصل الطلب؟' أو 'كم وقت التوصيل؟':\n";
        $enhancedInstruction .= "    - شوف قسم 'الطلبات السابقة' وأخبره بحالة طلبه\n";
        $enhancedInstruction .= "    - إذا الطلب 'قيد الانتظار' أو 'مؤكد'، قل: 'طلبك مؤكد وراح يوصلك خلال [1-3] أيام'\n";
        $enhancedInstruction .= "    - إذا الطلب 'قيد التجهيز'، قل: 'طلبك قيد التجهيز وراح يشحن قريباً'\n";
        $enhancedInstruction .= "    - إذا الطلب 'تم الشحن'، قل: 'طلبك بالطريق إليك وراح يوصل خلال 24 ساعة'\n";
        $enhancedInstruction .= "    - إذا ما عنده طلب سابق، قل: 'ما عندك طلب لحد الآن، تحب تطلب؟'\n";
        $enhancedInstruction .= "14. إذا سأل 'شنو تفاصيل طلبي؟' أو 'وين طلبي الحالي؟' أو 'اريد طلبي الحال':\n";
        $enhancedInstruction .= "    - شوف قسم 'الطلبات السابقة' واعرض له تفاصيل آخر طلب\n";
        $enhancedInstruction .= "    - اذكر: رقم الطلب، المنتجات، الحالة، المجموع\n";
        $enhancedInstruction .= "    - مثال: 'طلبك #123 يحتوي على قميص × 1 بسعر 15000 دينار، الحالة: قيد التجهيز'\n";
        $enhancedInstruction .= "    - إذا ما عنده طلب، قل: 'ما عندك طلب مسجل لحد الآن، تحب تطلب؟'\n";
        $enhancedInstruction .= "15. إذا سأل 'يتوفر توصيل؟' أو 'كم سعر التوصيل؟':\n";
        $enhancedInstruction .= "    - قل: 'نعم يتوفر توصيل! سعر التوصيل 5000 دينار ووقت التوصيل نفس اليوم أو خلال 24 ساعة'\n";
        $enhancedInstruction .= "16. لا ترد بـ 'شنو تحب تطلب هالمره؟' إذا الزبون يسأل عن:\n";
        $enhancedInstruction .= "    - وقت التوصيل أو وصول الطلب\n";
        $enhancedInstruction .= "    - تفاصيل طلبه الحالي أو السابق\n";
        $enhancedInstruction .= "    - حالة الطلب أو الاستعلام\n";
        $enhancedInstruction .= "    - توفر التوصيل أو سعر التوصيل\n";


        return $enhancedInstruction;
    }

    /**
     * Ensure a lead exists for this conversation
     */
    protected function ensureLead(Conversation $conversation): Lead
    {
        $allowedLeadSources = ['facebook', 'instagram', 'whatsapp', 'manual'];
        $leadSource = $conversation->platform ?? 'manual';
        if (!in_array($leadSource, $allowedLeadSources, true)) {
            $leadSource = 'manual';
        }

        if ($conversation->lead) {
            $linkedLead = $conversation->lead;
            $sameParticipant = (string) ($linkedLead->platform_user_id ?? '') === (string) ($conversation->participant_id ?? '');
            $sameSource = ($linkedLead->source ?? 'manual') === $leadSource;

            if ($sameParticipant && $sameSource) {
                return $linkedLead;
            }

            Log::warning('AiChatService: conversation linked to mismatched lead, relinking', [
                'conversation_id' => $conversation->id,
                'current_lead_id' => $linkedLead->id,
                'current_lead_source' => $linkedLead->source,
                'current_platform_user_id' => $linkedLead->platform_user_id,
                'expected_source' => $leadSource,
                'expected_platform_user_id' => $conversation->participant_id,
            ]);
        }

        // Reuse an existing lead for the same participant on the same platform when available.
        $lead = Lead::where('user_id', $this->user->id)
            ->where('source', $leadSource)
            ->where('platform_user_id', $conversation->participant_id)
            ->first();

        if ($lead) {
            if ((int) ($lead->conversation_id ?? 0) !== (int) $conversation->id) {
                $lead->conversation_id = $conversation->id;
                $lead->save();
            }

            if ((int) ($conversation->lead_id ?? 0) !== (int) $lead->id) {
                $conversation->lead_id = $lead->id;
                $conversation->save();
            }

            return $lead;
        }

        $lead = Lead::create([
            'user_id' => $this->user->id,
            'conversation_id' => $conversation->id,
            'source' => $leadSource,
            'platform_user_id' => $conversation->participant_id,
            'name' => $conversation->participant_name,
            'status' => 'new',
            'first_contact_at' => now(),
            'last_contact_at' => now(),
        ]);

        $conversation->lead_id = $lead->id;
        $conversation->save();

        return $lead;
    }

    /**
     * Detect product interests from message
     */
    protected function detectProductInterests(Lead $lead, string $message): void
    {
        $products = Product::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->get();

        $conversation = $lead->conversation;
        $context = $conversation->ai_context ?? [];
        $collectedData = $context['collected_data'] ?? [];
        $interestedProducts = $collectedData['interested_products'] ?? [];
        $currentOrderProduct = null; // Track the product mentioned in THIS message

        $messageLower = mb_strtolower($message);

        // Common Arabic transliterations for matching
        $transliterations = [
            'master' => ['ماستر', 'ماسطر', 'مستر'],
            'counter' => ['كونتر', 'كاونتر', 'كونترر'],
            'shirt' => ['شيرت', 'تشيرت', 'تيشرت', 'تيشيرت'],
            't-shirt' => ['تيشرت', 'تيشيرت', 'تشيرت'],
        ];
        // Extra Arabic aliases for popular items
        $keywordAliases = [
            'master' => ['ماستر', 'ماستر كاونتر'],
            'counter' => ['كاونتر', 'كونتر'],
            'shirt' => ['شيرت', 'تشيرت', 'تيشرت', 'تيشيرت', 'قميص'],
            't-shirt' => ['شيرت', 'تشيرت', 'تيشرت', 'تيشيرت', 'قميص'],
        ];

        foreach ($products as $product) {
            $productNameLower = mb_strtolower($product->name);
            $matched = false;

            // Direct match
            if (mb_strpos($messageLower, $productNameLower) !== false) {
                $matched = true;
            }

            // Check transliterations
            if (!$matched) {
                foreach ($transliterations as $english => $arabicVariants) {
                    if (mb_stripos($productNameLower, $english) !== false) {
                        foreach ($arabicVariants as $arabic) {
                            if (mb_strpos($messageLower, $arabic) !== false) {
                                $matched = true;
                                Log::info("Product matched via transliteration: {$product->name} <- {$arabic}");
                                break 2;
                            }
                        }
                    }
                }
            }

            // Also check if product name words appear separately
            if (!$matched) {
                $productWords = preg_split('/\s+/', $productNameLower);
                foreach ($productWords as $word) {
                    if (mb_strlen($word) > 3 && mb_strpos($messageLower, $word) !== false) {
                        $matched = true;
                        break;
                    }
                }
            }

            // Check explicit Arabic aliases tied to product keywords
            if (!$matched) {
                foreach ($keywordAliases as $keyword => $aliases) {
                    if (mb_stripos($productNameLower, $keyword) !== false) {
                        foreach ($aliases as $alias) {
                            if (mb_stripos($messageLower, mb_strtolower($alias)) !== false) {
                                $matched = true;
                                Log::info("Product matched via alias: {$product->name} <- {$alias}");
                                break 2;
                            }
                        }
                    }
                }
            }

            if ($matched) {
                // Track the current product being mentioned
                $currentOrderProduct = $product->name;

                if (!in_array($product->name, $interestedProducts)) {
                    $interestedProducts[] = $product->name;
                    Log::info("Product interest detected: {$product->name}");

                    // Also save to lead interests
                    if (method_exists($lead, 'addInterest')) {
                        $lead->addInterest($product->name, $product->id);
                    }
                }
            }
        }

        // Save interested products to context AND current order product
        $dataChanged = false;

        if (!empty($interestedProducts)) {
            $collectedData['interested_products'] = $interestedProducts;
            $dataChanged = true;
        }

        // Track the current product being ordered (most recent mention)
        if (!empty($currentOrderProduct)) {
            $collectedData['current_order_product'] = $currentOrderProduct;
            $dataChanged = true;
            Log::info("Current order product set to: {$currentOrderProduct}");
        }

        if ($dataChanged) {
            $context['collected_data'] = $collectedData;
            $conversation->ai_context = $context;
            $conversation->save();
            Log::info("Saved interested products: " . implode(', ', $interestedProducts));
        }
    }

    /**
     * Extract customer info from message - IMPROVED
     */
    protected function extractCustomerInfo(Lead $lead, string $message): void
    {
        $conversation = $lead->conversation;
        $context = $conversation->ai_context ?? [];
        $collectedData = $context['collected_data'] ?? [];
        $updated = false;

        // Phone number patterns (Iraqi format)
        if (preg_match('/07[3-9]\d{7,8}/', $message, $matches)) {
            $phone = $matches[0];
            if (!$lead->phone || $lead->phone !== $phone) {
                $lead->phone = $phone;
                $collectedData['phone'] = $phone;
                $updated = true;
                Log::info("Extracted phone: {$phone}");
            }
        }

        // Extract name patterns (common Arabic name patterns)
        $namePatterns = [
            '/اسمي\s+([^\n\r،,]+)/u',
            '/الاسم[:\s]+([^\n\r،,]+)/u',
            '/انا\s+([^\n\r،,]+)/u',
        ];

        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $name = trim($matches[1]);
                // Clean up the name (remove common filler words)
                $name = preg_replace('/^(هو|انه|يكون)\s*/u', '', $name);
                if (mb_strlen($name) > 2 && mb_strlen($name) < 50) {
                    if (!$lead->name || $lead->name !== $name) {
                        $lead->name = $name;
                        $collectedData['name'] = $name;
                        $updated = true;
                        Log::info("Extracted name: {$name}");
                    }
                    break;
                }
            }
        }

        // Check for common Iraqi cities
        $cities = [
            'بغداد', 'البصرة', 'الموصل', 'أربيل', 'اربيل', 'النجف', 'كربلاء',
            'الحلة', 'الكوت', 'الديوانية', 'السماوة', 'الناصرية', 'العمارة',
            'الرمادي', 'تكريت', 'كركوك', 'السليمانية', 'دهوك', 'واسط',
            'ديالى', 'الانبار', 'صلاح الدين', 'ميسان', 'ذي قار', 'المثنى'
        ];

        foreach ($cities as $city) {
            if (mb_stripos($message, $city) !== false) {
                if (!$lead->city || $lead->city !== $city) {
                    $lead->city = $city;
                    $collectedData['city'] = $city;
                    $updated = true;
                    Log::info("Extracted city: {$city}");
                }
                break;
            }
        }

        // Extract address patterns - MORE FLEXIBLE
        $addressPatterns = [
            '/عنوان[ي]?[:\s]+([^\n\r]+)/u',
            '/العنوان[:\s]+([^\n\r]+)/u',
            '/بيت[ي]?\s+([^\n\r]+)/u',
            '/سكن[ي]?\s+([^\n\r]+)/u',
            '/منطق[ةه][:\s]+([^\n\r]+)/u',  // area/zone
            '/حي[:\s]+([^\n\r]+)/u',  // neighborhood
        ];

        foreach ($addressPatterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $address = trim($matches[1]);
                if (mb_strlen($address) > 5) {
                    if (!$lead->address || $lead->address !== $address) {
                        $lead->address = $address;
                        $collectedData['address'] = $address;
                        $updated = true;
                        Log::info("Extracted address: {$address}");
                    }
                    break;
                }
            }
        }

        // Check for city/area format like "بغداد / الكفاح" or "بغداد - الكفاح"
        if (!$lead->address && preg_match('/([^\n\r\/\-]+)\s*[\/\-]\s*([^\n\r]+)/u', $message, $matches)) {
            $part1 = trim($matches[1]);
            $part2 = trim($matches[2]);

            // Check if first part is a city
            foreach ($cities as $city) {
                if (mb_stripos($part1, $city) !== false || mb_stripos($part2, $city) !== false) {
                    $address = $part1 . ' / ' . $part2;
                    $lead->address = $address;
                    $collectedData['address'] = $address;
                    $updated = true;
                    Log::info("Extracted address from city/area format: {$address}");
                    break;
                }
            }
        }

        // Also look for "أقرب نقطة دالة" or landmark
        if (preg_match('/أقرب\s+نقط[ةه]\s+دال[ةه][:\s]+([^\n\r]+)/u', $message, $matches)) {
            $landmark = trim($matches[1]);
            $existingAddress = $lead->address ?? '';
            if (!empty($landmark) && mb_strpos($existingAddress, $landmark) === false) {
                $lead->address = $existingAddress . ' - أقرب نقطة دالة: ' . $landmark;
                $collectedData['address'] = $lead->address;
                $collectedData['landmark'] = $landmark;
                $updated = true;
            }
        }

        if ($updated) {
            $lead->save();
            $context['collected_data'] = $collectedData;
            $conversation->ai_context = $context;
            $conversation->save();
        }
    }

    /**
     * Check if we should create an order and create it
     */
    protected function checkAndCreateOrder(Conversation $conversation, Lead $lead, string $userMessage, string $aiResponse): void
    {
        // Refresh to get latest ai_context (may have been updated by detectProductInterests)
        $conversation->refresh();
        $lead->refresh();

        $context = $conversation->ai_context ?? [];
        $collectedData = $context['collected_data'] ?? [];

        // IMPORTANT: Sync interested_products from lead interests if not in collected_data
        if (empty($collectedData['interested_products']) && !empty($lead->interests)) {
            $productNames = [];
            foreach ($lead->interests as $interest) {
                $name = is_array($interest) ? ($interest['product_name'] ?? '') : $interest;
                if (!empty($name) && !in_array($name, $productNames)) {
                    $productNames[] = $name;
                }
            }
            if (!empty($productNames)) {
                $collectedData['interested_products'] = $productNames;
                $context['collected_data'] = $collectedData;
                $conversation->ai_context = $context;
                $conversation->save();
            }
        }

        Log::info("checkAndCreateOrder called", [
            'lead_id' => $lead->id,
            'lead_name' => $lead->name,
            'lead_phone' => $lead->phone,
            'lead_city' => $lead->city,
            'lead_address' => $lead->address,
            'collected_data' => $collectedData,
            'lead_interests' => $lead->interests,
        ]);

        // Check if user wants a NEW order (reset order_created flag if they explicitly ask for new order)
        $newOrderKeywords = ['طلب جديد', 'من جديد', 'اطلب مرة', 'طلب ثاني', 'طلب اخر', 'طلب آخر', 'قطعتين', 'قطعة ثانية', '2 ', 'اثنين', 'ثلاث', 'أربع'];
        $wantsNewOrder = false;
        foreach ($newOrderKeywords as $keyword) {
            if (mb_stripos($userMessage, $keyword) !== false) {
                $wantsNewOrder = true;
                Log::info("User wants new order, keyword found: {$keyword}");
                break;
            }
        }

        // If user wants new order, reset the flag
        if ($wantsNewOrder && isset($collectedData['order_created'])) {
            unset($collectedData['order_created']);
            unset($collectedData['order_id']);
            unset($collectedData['order_number']);
            $context['collected_data'] = $collectedData;
            $conversation->ai_context = $context;
            $conversation->save();
            Log::info("Reset order_created flag for new order");
        }

        // Skip if already created in THIS session (not wanting new order)
        if (!$wantsNewOrder && isset($collectedData['order_created']) && $collectedData['order_created']) {
            Log::info("Order already created in this session, skipping");
            return;
        }

        // Check if we have all required info
        $hasName = !empty($lead->name);
        $hasPhone = !empty($lead->phone);
        $hasAddress = !empty($lead->address) || !empty($lead->city);

        // Check for product interest - should now be populated
        $hasProduct = !empty($collectedData['interested_products']);

        // If still no product, try detecting from AI response
        if (!$hasProduct) {
            $this->detectProductInterests($lead, $aiResponse);
            $conversation->refresh();
            $context = $conversation->ai_context ?? [];
            $collectedData = $context['collected_data'] ?? [];
            $hasProduct = !empty($collectedData['interested_products']);
        }

        // Check for order confirmation keywords in AI response
        $confirmationKeywords = [
            'تم تأكيد',
            'راح نرسل',
            'تم تسجيل',
            'طلبك جاهز',
            'شكراً لثقتك',
            'راح يوصلك',
            'تم إنشاء طلب',
            'طلبك بالطريق',
            'تم تأكيد طلبك',
            'طلبك مؤكد',
            'تم تأكيد الطلب',
            'الطلب مؤكد',
            'راح نثبت',
            'نثبت طلبك',
            'تأكد طلبك',
            'صار تدلل',
            'راح نوصله',
            'راح نوصل',
            'سيتم توصيل',
            'التوصيل مجاني',
            'order confirmed',
            'order placed',
        ];
        $aiConfirmed = false;
        foreach ($confirmationKeywords as $keyword) {
            if (mb_stripos($aiResponse, $keyword) !== false) {
                $aiConfirmed = true;
                Log::info("Found confirmation keyword: {$keyword}");
                break;
            }
        }

        // Also check if user confirmed the order
        $userConfirmationKeywords = [
            'اكد',
            'أكد',
            'اكدلي',
            'أكدلي',
            'نعم',
            'اي',
            'أي',
            'اوكي',
            'ok',
            'yes',
            'تمام',
            'موافق',
            'اطلب',
            'أطلب',
            'طلب',
            'اريد',
            'أريد',
        ];
        $userConfirmed = false;
        foreach ($userConfirmationKeywords as $keyword) {
            if (mb_stripos($userMessage, $keyword) !== false) {
                $userConfirmed = true;
                Log::info("Found user confirmation keyword: {$keyword}");
                break;
            }
        }

        // If user confirmed and AI also confirmed or mentioned the product info
        if (!$aiConfirmed && $userConfirmed) {
            // Check if AI mentioned order-related info
            $orderRelatedPhrases = [
                'التوصيل',
                'الطلب',
                'العنوان',
                'معلومات',
                'سعر',
            ];
            foreach ($orderRelatedPhrases as $phrase) {
                if (mb_stripos($aiResponse, $phrase) !== false) {
                    $aiConfirmed = true;
                    Log::info("User confirmed + AI mentioned order-related: {$phrase}");
                    break;
                }
            }
        }

        Log::info("Order creation conditions", [
            'hasName' => $hasName,
            'hasPhone' => $hasPhone,
            'hasAddress' => $hasAddress,
            'hasProduct' => $hasProduct,
            'aiConfirmed' => $aiConfirmed,
            'aiResponse_snippet' => mb_substr($aiResponse, 0, 200),
        ]);

        // Create order if all conditions met
        if ($hasName && $hasPhone && $hasAddress && $hasProduct && $aiConfirmed) {
            Log::info("Creating order for lead {$lead->id}", [
                'name' => $lead->name,
                'phone' => $lead->phone,
                'city' => $lead->city,
                'address' => $lead->address,
                'products' => $collectedData['interested_products'] ?? [],
            ]);

            try {
                $order = $this->createOrderFromContext($conversation, $lead, $collectedData);

                if ($order) {
                    // Mark order as created
                    $collectedData['order_created'] = true;
                    $collectedData['order_id'] = $order->id;
                    $collectedData['order_number'] = $order->order_number;
                    $context['collected_data'] = $collectedData;
                    $conversation->ai_context = $context;
                    $conversation->save();

                    // Update lead status
                    $lead->status = 'converted';
                    $lead->save();

                    Log::info("Order created successfully: {$order->order_number}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to create order: " . $e->getMessage());
            }
        }
    }

    /**
     * Create order from collected context
     */
    protected function createOrderFromContext(Conversation $conversation, Lead $lead, array $collectedData): ?OnlineOrder
    {
        // Get products from interests - prefer current_order_product if set
        $interestedProducts = [];

        // Check if there's a current order product (most recently mentioned)
        if (!empty($collectedData['current_order_product'])) {
            $interestedProducts = [$collectedData['current_order_product']];
        } elseif (!empty($collectedData['interested_products'])) {
            // Fall back to the last interested product
            $interestedProducts = [end($collectedData['interested_products'])];
        }

        if (empty($interestedProducts)) {
            return null;
        }

        // Create the order
        $order = OnlineOrder::create([
            'user_id' => $this->user->id,
            'lead_id' => $lead->id,
            'conversation_id' => $conversation->id,
            'customer_name' => $lead->name ?? 'زبون',
            'customer_phone' => $lead->phone ?? '',
            'customer_address' => $lead->address ?? '',
            'customer_city' => $lead->city,
            'customer_area' => $lead->area ?? $collectedData['landmark'] ?? null,
            'source' => 'ai_chat',
            'status' => 'pending',
            'payment_status' => 'pending',
            'customer_notes' => 'طلب من الذكاء الاصطناعي',
            'meta_data' => [
                'platform' => $conversation->platform,
                'created_by' => 'ai_chat',
            ],
        ]);

        // Add items based on interested products
        foreach ($interestedProducts as $productName) {
            $product = Product::where('user_id', $this->user->id)
                ->where('name', 'like', "%{$productName}%")
                ->first();

            if ($product) {
                OnlineOrderItem::create([
                    'online_order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => 1,
                    'total' => $product->price,
                ]);
            } else {
                // Create item without product reference
                OnlineOrderItem::create([
                    'online_order_id' => $order->id,
                    'product_id' => null,
                    'product_name' => $productName,
                    'unit_price' => 0,
                    'quantity' => 1,
                    'total' => 0,
                ]);
            }
        }

        // Calculate totals
        $order->calculateTotals();

        // Notify account owner about the new AI order
        Notification::notify(
            $this->user->id,
            'online_order_created',
            'تم إنشاء طلب جديد بواسطة الشات الآلي',
            "تم إنشاء الطلب رقم {$order->order_number} تلقائياً من المحادثة.",
            route('customer.online-orders.show', $order->id),
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'source' => 'ai_chat',
            ]
        );

        return $order;
    }

    /**
     * Create order manually from conversation
     */
    public function createOrder(Conversation $conversation, array $items, array $customerInfo): ?OnlineOrder
    {
        $lead = $conversation->lead ?? $this->ensureLead($conversation);

        // Update lead with customer info
        $lead->fill([
            'name' => $customerInfo['name'] ?? $lead->name,
            'phone' => $customerInfo['phone'] ?? $lead->phone,
            'address' => $customerInfo['address'] ?? $lead->address,
            'city' => $customerInfo['city'] ?? $lead->city,
            'area' => $customerInfo['area'] ?? $lead->area,
        ]);
        $lead->save();

        // Create order
        $order = OnlineOrder::create([
            'user_id' => $this->user->id,
            'lead_id' => $lead->id,
            'conversation_id' => $conversation->id,
            'customer_name' => $lead->name ?? 'زبون',
            'customer_phone' => $lead->phone ?? '',
            'customer_address' => $lead->address ?? '',
            'customer_city' => $lead->city,
            'customer_area' => $lead->area,
            'source' => $conversation->platform === 'instagram' ? 'instagram' : 'facebook',
            'customer_notes' => $customerInfo['notes'] ?? null,
        ]);

        // Add items
        foreach ($items as $item) {
            $product = Product::find($item['product_id'] ?? 0);

            OnlineOrderItem::create([
                'online_order_id' => $order->id,
                'product_id' => $product?->id,
                'product_name' => $product?->name ?? $item['name'] ?? 'منتج',
                'unit_price' => $product?->price ?? $item['price'] ?? 0,
                'quantity' => $item['quantity'] ?? 1,
                'total' => ($product?->price ?? $item['price'] ?? 0) * ($item['quantity'] ?? 1),
            ]);
        }

        $order->calculateTotals();

        return $order;
    }

    /**
     * Update conversation AI context
     */
    protected function updateConversationContext(Conversation $conversation, Lead $lead, string $userMessage, string $aiResponse, array $metadata = []): void
    {
        // Refresh the conversation to get latest ai_context (may have been updated by detectProductInterests)
        $conversation->refresh();

        $context = $conversation->ai_context ?? [];

        // Keep exchange history
        $exchanges = $context['exchanges'] ?? [];
        $exchanges[] = [
            'user' => $userMessage,
            'ai' => $aiResponse,
            'timestamp' => now()->toDateTimeString(),
            'intent' => $metadata['intent'] ?? null,
        ];
        $context['exchanges'] = array_slice($exchanges, -15); // Keep last 15 exchanges

        // Update last activity
        $context['last_activity'] = now()->toDateTimeString();

        // Store last detected intent
        if (!empty($metadata['intent'])) {
            $context['last_intent'] = $metadata['intent'];
        }

        // Store conversation context from metadata
        if (!empty($metadata['conversation_context'])) {
            $context['ai_conversation_context'] = $metadata['conversation_context'];
        }

        // Preserve collected_data (may have been updated by detectProductInterests)
        // It should already be in $context from the refresh, but ensure we don't lose it
        if (!isset($context['collected_data'])) {
            $context['collected_data'] = [];
        }

        $conversation->ai_context = $context;
        $conversation->save();
    }

    /**
     * Test AI connection
     */
    public function testConnection(): array
    {
        try {
            // Test Groq API connection
            $apiKey = $this->settings->groq_api_key ?? config('services.groq.api_key');

            if (empty($apiKey)) {
                return [
                    'success' => false,
                    'message' => 'مفتاح Groq API غير موجود. يرجى إضافته في إعدادات الذكاء الاصطناعي.',
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(10)->get('https://api.groq.com/openai/v1/models');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'الاتصال ناجح - Groq API',
                    'api_version' => 'Groq (Native Laravel)',
                    'model' => $this->settings->groq_model ?? 'llama-3.3-70b-versatile',
                ];
            }

            return [
                'success' => false,
                'message' => 'فشل الاتصال بخادم Groq: ' . $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'خطأ: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get available models from Groq
     */
    public function getAvailableModels(): array
    {
        return [
            [
                'id' => 'llama-3.3-70b-versatile',
                'name' => 'Llama 3.3 70B Versatile',
                'description' => 'أقوى نموذج - مناسب للمحادثات المعقدة',
            ],
            [
                'id' => 'llama-3.1-70b-versatile',
                'name' => 'Llama 3.1 70B Versatile',
                'description' => 'نموذج قوي ومستقر',
            ],
            [
                'id' => 'llama-3.1-8b-instant',
                'name' => 'Llama 3.1 8B Instant',
                'description' => 'سريع جداً - للردود البسيطة',
            ],
            [
                'id' => 'mixtral-8x7b-32768',
                'name' => 'Mixtral 8x7B',
                'description' => 'توازن بين السرعة والجودة',
            ],
            [
                'id' => 'gemma2-9b-it',
                'name' => 'Gemma 2 9B',
                'description' => 'نموذج Google خفيف',
            ],
        ];
    }

    /**
     * Check Knowledge Base for similar questions
     * Returns answer if found, null otherwise
     */
    protected function checkKnowledgeBase(string $question): ?string
    {
        // Normalize question for better matching
        $questionLower = mb_strtolower(trim($question));

        // Remove common question marks and extra spaces
        $questionNormalized = preg_replace('/[؟?]+/', '', $questionLower);
        $questionNormalized = preg_replace('/\s+/', ' ', $questionNormalized);

        // Get active knowledge base entries for this user
        $kbEntries = \App\Models\AiKnowledgeBase::where('user_id', $this->user->id)
            ->where('status', 'active')
            ->orderBy('priority', 'desc')
            ->orderBy('usage_count', 'desc')
            ->get();

        foreach ($kbEntries as $entry) {
            $entryQuestion = mb_strtolower(trim($entry->question));
            $entryQuestionNormalized = preg_replace('/[؟?]+/', '', $entryQuestion);
            $entryQuestionNormalized = preg_replace('/\s+/', ' ', $entryQuestionNormalized);

            // Check for exact match (normalized)
            if ($questionNormalized === $entryQuestionNormalized) {
                // Increment usage count
                $entry->incrementUsage();

                return $entry->answer;
            }

            // Check for partial match (contains all keywords)
            $keywords = $entry->keywords ?? [];
            if (!empty($keywords)) {
                $matchCount = 0;
                foreach ($keywords as $keyword) {
                    if (mb_strpos($questionNormalized, mb_strtolower($keyword)) !== false) {
                        $matchCount++;
                    }
                }

                // If 70% or more keywords match, consider it a match
                if (count($keywords) > 0 && ($matchCount / count($keywords)) >= 0.7) {
                    $entry->incrementUsage();
                    return $entry->answer;
                }
            }

            // Check for similarity using common words
            $questionWords = explode(' ', $entryQuestionNormalized);
            $inputWords = explode(' ', $questionNormalized);

            $commonWords = array_intersect($questionWords, $inputWords);
            $similarity = count($commonWords) / max(count($questionWords), count($inputWords));

            // If 80% similar, consider it a match
            if ($similarity >= 0.8) {
                $entry->incrementUsage();
                return $entry->answer;
            }
        }

        // No match found - DON'T log yet, AI hasn't tried yet
        return null;
    }

    /**
     * Check Fast Replies for matching keywords
     */
    protected function checkFastReplies(string $message): ?string
    {
        $messageLower = mb_strtolower(trim($message));

        // Get active fast replies for this user
        $fastReplies = \App\Models\AiFastReply::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        foreach ($fastReplies as $reply) {
            $keywords = $reply->trigger_keywords ?? [];

            if (empty($keywords)) {
                continue;
            }

            foreach ($keywords as $keyword) {
                if (mb_strpos($messageLower, mb_strtolower($keyword)) !== false) {
                    // Increment usage count
                    $reply->increment('usage_count');
                    return $reply->reply;
                }
            }
        }

        return null;
    }

    /**
     * Log unanswered question for admin review (SMART FILTERING)
     */
    protected function logUnansweredQuestion(string $question): void
    {
        // SMART FILTER: Don't log simple/common phrases
        if ($this->isSimplePhrase($question)) {
            Log::info('Skipping logging simple phrase', ['question' => $question]);
            return;
        }

        // SMART FILTER: Only log if it's a real question
        if (!$this->isRealQuestion($question)) {
            Log::info('Not a real question, skipping', ['question' => $question]);
            return;
        }

        try {
            \App\Models\UnansweredQuestion::findOrCreate([
                'user_id' => $this->user->id,
                'question' => $question,
                'context' => 'من المحادثة',
                'status' => 'pending',
                'is_reviewed' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log unanswered question', [
                'question' => $question,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if message is a simple phrase (greeting, thanks, etc.)
     */
    protected function isSimplePhrase(string $message): bool
    {
        $messageLower = mb_strtolower(trim($message));

        // Simple greetings
        $simpleGreetings = [
            'السلام عليكم', 'سلام', 'مرحبا', 'مرحبا', 'هلا', 'هاي', 'اهلا',
            'صباح الخير', 'مساء الخير', 'يا هلا', 'الو', 'هالو',
            'شكراً', 'شكرا', 'يسلمو', 'تسلم', 'ممتاز', 'رائع', 'جيد', 'تمام',
            'باي', 'مع السلامة', 'الى اللقاء', 'وداعا', 'بس', 'خلاص',
            'ok', 'okay', 'ok thanks', 'thanks', 'hi', 'hello', 'hey', 'bye',
        ];

        foreach ($simpleGreetings as $greeting) {
            if ($messageLower === mb_strtolower($greeting)) {
                return true;
            }
        }

        // Very short messages (less than 3 chars)
        if (mb_strlen($messageLower) < 3) {
            return true;
        }

        return false;
    }

    /**
     * Check if message is a real question worth logging
     */
    protected function isRealQuestion(string $message): bool
    {
        $messageLower = mb_strtolower(trim($message));

        // Must have minimum length
        if (mb_strlen($messageLower) < 5) {
            return false;
        }

        // Check for question indicators (not required, but helps)
        $questionWords = ['شنو', 'شكد', 'كيف', 'متى', 'وين', 'هل', 'ليش', 'ما', 'عندكم', 'يتوفر', 'موجود'];
        $hasQuestionWord = false;

        foreach ($questionWords as $word) {
            if (mb_strpos($messageLower, $word) !== false) {
                $hasQuestionWord = true;
                break;
            }
        }

        // If has question word, it's a real question
        if ($hasQuestionWord) {
            return true;
        }

        // Check for question marks
        if (mb_strpos($message, '؟') !== false || mb_strpos($message, '?') !== false) {
            return true;
        }

        // If message is longer than 10 chars and has multiple words, consider it a question
        $wordCount = count(explode(' ', $messageLower));
        if (mb_strlen($messageLower) >= 10 && $wordCount >= 2) {
            return true;
        }

        return false;
    }
}
