<?php

namespace App\Services;

use App\Enums\ConversationState;
use App\Enums\Intent;
use App\Models\AiChatSession;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\User;
use App\Services\AI\ChatAgentService;
use App\Services\AI\IntentAnalyzer;
use App\Services\AI\ResponseGenerator;
use App\Services\Conversation\ConversationManager;
use App\Services\Conversation\StateMachine;
use App\Services\Orders\CartService;
use App\Services\Orders\OrderService;
use App\Services\Orders\ProductService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * GroqChatServiceV3 - State-Driven Order Processing Orchestrator (V2 Rewrite)
 *
 * REWRITTEN:
 * ✅ General browse ("شنو عدكم") → show ONLY categories, no products
 * ✅ Category browse ("كهربائيات") → show 15-20 products from that category
 * ✅ Product search ("ايفون 15") → search and show matches
 * ✅ Cart shows FULL items with: name: qty قطعة (price × qty = subtotal د.ع)
 * ✅ Delivery cost always shown
 * ✅ Number selection from product list (#1, #2)
 * ✅ Warm Iraqi greetings with emojis
 * ✅ Uses new ResponseGenerator and IntentAnalyzer methods
 */
class GroqChatServiceV3
{
    protected IntentAnalyzer $intentAnalyzer;
    protected ResponseGenerator $responseGenerator;
    protected StateMachine $stateMachine;
    protected ConversationManager $conversationManager;
    protected CartService $cartService;
    protected OrderService $orderService;
    protected ProductService $productService;
    protected ChatAgentService $chatAgent;

    protected const RATE_LIMIT = 15;

    public function __construct(
        IntentAnalyzer $intentAnalyzer,
        ResponseGenerator $responseGenerator,
        StateMachine $stateMachine,
        ConversationManager $conversationManager,
        CartService $cartService,
        OrderService $orderService,
        ProductService $productService,
        ChatAgentService $chatAgent
    ) {
        $this->intentAnalyzer = $intentAnalyzer;
        $this->responseGenerator = $responseGenerator;
        $this->stateMachine = $stateMachine;
        $this->conversationManager = $conversationManager;
        $this->cartService = $cartService;
        $this->orderService = $orderService;
        $this->productService = $productService;
        $this->chatAgent = $chatAgent;
    }

    // ═══════════════════════════════════════════════════════════════════
    // MAIN ENTRY POINT
    // ═══════════════════════════════════════════════════════════════════

    public function processMessage(
        string $message,
        User $store,
        Lead $lead,
        ?Conversation $conversation = null
    ): array {
        $startTime = microtime(true);

        if (!$this->checkRateLimit($lead->id)) {
            Log::warning('GroqChatServiceV3: Rate limit exceeded', ['lead_id' => $lead->id]);
            return $this->buildResponse('عذراً، يرجى الانتظار قليلاً قبل إرسال رسالة أخرى.');
        }

        try {
            // Session
            $session = $this->conversationManager->getOrCreateSession($store, $lead, $conversation);
            $this->conversationManager->addMessage($session, 'user', $message);
            $currentState = $this->conversationManager->getState($session);

            Log::info('GroqChatServiceV3: Processing', [
                'session_id' => $session->id,
                'state' => $currentState->value,
                'message' => mb_substr($message, 0, 60),
            ]);

            // STEP 1: Analyze intent
            $intentResult = $this->analyzeIntent($message, $currentState, $session, $store);
            $intent = $intentResult['intent'];
            $entities = $intentResult['entities'];

            Log::debug('GroqChatServiceV3: Intent', [
                'intent' => $intent->value,
                'confidence' => $intentResult['confidence'],
            ]);

            // STEP 2: Execute logic
            $result = $this->executeStateLogic($session, $store, $lead, $currentState, $intent, $entities, $message);

            // Save response in history
            $this->conversationManager->addMessage($session, 'assistant', $result['reply']);

            $duration = round((microtime(true) - $startTime) * 1000);
            Log::info('GroqChatServiceV3: Done', [
                'session_id' => $session->id,
                'duration_ms' => $duration,
                'intent' => $intent->value,
                'new_state' => $this->conversationManager->getState($session)->value,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('GroqChatServiceV3: Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            try {
                return $this->showCategoriesOnly($store);
            } catch (\Exception $fe) {
                return $this->buildResponse($this->responseGenerator->template('error_generic'));
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // INTENT ANALYSIS
    // ═══════════════════════════════════════════════════════════════════

    protected function analyzeIntent(
        string $message,
        ConversationState $state,
        AiChatSession $session,
        User $store
    ): array {
        $context = [
            'cart_summary' => $this->cartService->getCartSummary($session),
            'last_product' => $this->conversationManager->getLastMentionedProduct($session)['name'] ?? null,
        ];

        return $this->intentAnalyzer->analyze($message, $state, $context, $store);
    }

    // ═══════════════════════════════════════════════════════════════════
    // STATE LOGIC ROUTER
    // ═══════════════════════════════════════════════════════════════════

    protected function executeStateLogic(
        AiChatSession $session,
        User $store,
        Lead $lead,
        ConversationState $currentState,
        Intent $intent,
        array $entities,
        string $message
    ): array {
        // ── INTENT-FIRST OVERRIDES (work from ANY state) ──

        // ── CART VIEW: "شنو محتوى سلتي" / "شنو بالسله" / "سلتي" ──
        if ($this->isCartViewRequest($message)) {
            return $this->handleCartView($session, $store);
        }

        // ── CART CLEAR: "فرغ سلتي" / "فضي السله" / "نظف السله" ──
        if ($this->isCartClearRequest($message)) {
            return $this->handleCartClear($session);
        }

        // ── SPECIAL: Info update while in confirmation ──
        // "غير اسمي" / "اسمي هوه زيد" while in WAITING_CONFIRMATION
        if ($currentState === ConversationState::WAITING_CONFIRMATION &&
            ($intent === Intent::PROVIDE_INFO || $this->isInfoUpdateRequest($message))) {
            // Extract updated info
            $extractedInfo = $this->extractCustomerInfo($entities, $message);
            if (!empty($extractedInfo)) {
                $this->conversationManager->collectCustomerInfo($session, $extractedInfo, $lead);
                // Stay in confirmation and show updated summary
                return $this->handleWaitingConfirmation($session, $store, $lead);
            }
        }

        if ($intent === Intent::GREETING) {
            return $this->handleGreeting($session, $currentState);
        }

        if ($intent === Intent::ORDER_STATUS) {
            return $this->handleOrderStatusInquiry($session, $store, $lead);
        }

        if ($intent === Intent::ASK_QUESTION) {
            $entities['original_message'] = $message;
            return $this->handleFaq($session, $store, $entities);
        }

        if ($intent === Intent::REQUEST_IMAGE) {
            return $this->handleImageRequest($session, $store, $entities, $message);
        }

        if ($intent === Intent::UPDATE_QUANTITY) {
            return $this->handleUpdateQuantity($session, $store, $entities, $message);
        }

        if ($intent === Intent::REMOVE_FROM_CART) {
            return $this->handleRemoveFromCart($session, $store, $entities, $message);
        }

        // ── COMPETITOR COMPARISON / SALES OBJECTION: "حصلت بمكان ثاني بسعر ارخص" ──
        // Must intercept BEFORE ChatAgentService to prevent wrongly adding to cart
        if ($this->isSalesObjection($message)) {
            return $this->handleSalesObjection($session, $store, $message);
        }

        // ── OFFER/PROMOTION INQUIRY: "العرض بعده موجود" / "اكو عرض" ──
        if ($this->isOfferInquiry($message)) {
            return $this->handleOfferInquiry($session, $store, $message);
        }

        // ── ALTERNATIVE SEARCH: "اريد بديل" / "شوفلي بديل" / "في بديل" ──
        // Must come BEFORE ADD_TO_CART so it doesn't get treated as a product search
        if (preg_match('/بديل|بدائل|شبيه|مشابه/u', $message)) {
            $lastProduct = $this->conversationManager->getLastMentionedProduct($session);
            $lastProductName = $lastProduct['name'] ?? null;
            $lastProductId   = $lastProduct['id'] ?? null;

            if ($lastProductName) {
                // Search for similar products (same category or keyword)
                $alternatives = $this->productService->search($store, $lastProductName, 8);
                // Exclude the exact same product
                if ($lastProductId) {
                    $alternatives = $alternatives->filter(fn($p) => $p->id !== $lastProductId)->values();
                }
                if ($alternatives->isNotEmpty()) {
                    $this->conversationManager->setShownProducts($session, $alternatives->toArray());
                    $formatted = $this->productService->formatListForDisplay($alternatives);
                    $response = "هاي بعض البدائل لـ{$lastProductName}:\n" . $this->responseGenerator->showProducts($formatted);
                    $images = []; $productIds = [];
                    foreach ($alternatives as $p) {
                        $img = $this->productService->getPrimaryImageUrl($p);
                        if ($img) { $images[] = $img; $productIds[] = $p->id; }
                    }
                    $session->conversation_state = ConversationState::WAITING_PRODUCT_SELECTION->value;
                    $session->save();
                    return $this->buildResponse($response, $images, [], $productIds);
                }
                // No alternatives found
                return $this->buildResponse("مالقيت بديل لـ{$lastProductName} حالياً 😅\nبس تقدر تشوف كل منتجاتنا! شنو تبي;");
            }
            // No context — ask what they're looking for
            return $this->buildResponse("قوللي شنو تبي بديله واشوفلك 😊");
        }

        // ── ASK_PRICE override: works from ANY state ──
        if ($intent === Intent::ASK_PRICE) {
            return $this->handleAskPrice($session, $store, $entities, $message);
        }

        // ── SHOW ALL PRODUCTS: "عددلي كل المنتجات" / "جميع المنتجات" ──
        // Must intercept BEFORE the browse-with-product-name override below
        if ($intent === Intent::BROWSE_PRODUCTS &&
            preg_match('/كل المنتجات|جميع المنتجات|عددلي كل|وريني كل|اعرضلي كل|كل شي عندكم|كل شي عدكم|كلشي عندكم|كلشي عدكم|ماهي المنتجات|المنتجات.*توفر|المنتجات.*متاح/u', $message)) {
            $session->conversation_state = ConversationState::BROWSING_PRODUCTS->value;
            $session->save();
            return $this->showAllProducts($session, $store);
        }

        // ── BROWSE with product name override: "متوفر قميص" / "اكو حزام" ──
        // If BROWSE_PRODUCTS but entities contain a specific product/category name,
        // search for it as a product FIRST (before going through the browse flow)
        if ($intent === Intent::BROWSE_PRODUCTS) {
            $browseProductName = $entities['category_name'] ?? $entities['product_name'] ?? null;
            if ($browseProductName && mb_strlen($browseProductName) >= 2) {
                // Check if it's a real category first
                $realCat = $this->productService->findCategory($store, $browseProductName);
                if (!$realCat) {
                    // Not a category → try product search directly
                    $products = $this->productService->search($store, $browseProductName, 5);
                    if ($products->isEmpty()) {
                        $variations = $this->productService->getArabicVariations($browseProductName);
                        foreach ($variations as $variation) {
                            $products = $this->productService->search($store, $variation, 5);
                            if ($products->isNotEmpty()) break;
                        }
                    }
                    if ($products->isNotEmpty()) {
                        $this->conversationManager->setShownProducts($session, $products->toArray());
                        if ($products->count() === 1) {
                            $this->conversationManager->setLastMentionedProduct($session, $products->first());
                        }
                        $formatted = $this->productService->formatListForDisplay($products);
                        $response = $this->responseGenerator->showProducts($formatted, $browseProductName);
                        $images = [];
                        $productIds = [];
                        foreach ($products as $product) {
                            $imageUrl = $this->productService->getPrimaryImageUrl($product);
                            if ($imageUrl) { $images[] = $imageUrl; $productIds[] = $product->id; }
                        }
                        $session->conversation_state = ConversationState::WAITING_PRODUCT_SELECTION->value;
                        $session->save();
                        return $this->buildResponse($response, $images, [], $productIds);
                    }
                }
            }
        }

        // ── SMART FIX: If ADD_TO_CART in CART_REVIEW with ONLY a quantity (no new product name),
        // treat it as UPDATE_QUANTITY on the last item, not a new add.
        // e.g. "اريد قطعتين" while in cart review = set quantity to 2
        if ($intent === Intent::ADD_TO_CART && $currentState === ConversationState::CART_REVIEW) {
            $qty = $entities['quantity'] ?? null;
            $hasProductName = !empty($entities['product_name']);
            $extractedName = $this->extractProductFromMessage($message);
            if ($qty && !$hasProductName && (!$extractedName || mb_strlen($extractedName) < 2)) {
                // Only a quantity word, no product name → redirect to update quantity
                $intent = Intent::UPDATE_QUANTITY;
            }
        }

        // ── SMART FIX: If ADD_TO_CART, check if user means a specific product or just a category ──
        // "اريد ادوات منزليه" → just category name → BROWSE
        // "اريد طقم ادوات منزليه" → specific product name (has extra words) → keep ADD_TO_CART
        // "اريد قطعتين من طقم ادوات منزليه" → product + quantity → keep ADD_TO_CART
        // "اريد منتج خاص بالاكترونيات" → wants to browse electronics → BROWSE
        if ($intent === Intent::ADD_TO_CART) {
            $productName = $entities['product_name'] ?? $this->extractProductFromMessage($message);

            // FIRST: Check for "خاص ب" / "يخص" / "يتعلق ب" patterns → extract category
            if ($productName) {
                $catFromSpecial = null;
                if (preg_match('/(?:خاص|تخص|يخص|يتعلق|تتعلق|متعلق)\s*(?:ب|بال|في|من)\s*(.+)/u', $productName, $cm)) {
                    $catFromSpecial = trim($cm[1], '؟? ');
                } elseif (preg_match('/(?:خاص|تخص|يخص|يتعلق|تتعلق|متعلق)\s*(?:ب|بال|في|من)\s*(.+)/u', $message, $cm)) {
                    $catFromSpecial = trim($cm[1], '؟? ');
                }
                if ($catFromSpecial && mb_strlen($catFromSpecial) >= 2) {
                    $specialCat = $this->productService->findCategory($store, $catFromSpecial);
                    if ($specialCat) {
                        $intent = Intent::BROWSE_PRODUCTS;
                        $entities['category_name'] = $specialCat->name;
                        Log::info('GroqChatServiceV3: ADD_TO_CART redirected to BROWSE - خاص ب pattern matched category', [
                            'extracted' => $catFromSpecial,
                            'category' => $specialCat->name,
                        ]);
                    } else {
                        // Try Arabic variations
                        $variations = $this->productService->getArabicVariations($catFromSpecial);
                        foreach ($variations as $variation) {
                            $specialCat = $this->productService->findCategory($store, $variation);
                            if ($specialCat) {
                                $intent = Intent::BROWSE_PRODUCTS;
                                $entities['category_name'] = $specialCat->name;
                                break;
                            }
                        }
                    }
                }
            }

            if ($intent === Intent::ADD_TO_CART && $productName) {
                $category = $this->productService->findCategory($store, $productName);
                if ($category) {
                    // Normalize both names to compare word-by-word
                    $normalize = function ($str) {
                        $str = mb_strtolower(trim($str));
                        $str = str_replace(['أ', 'إ', 'آ'], 'ا', $str);
                        $str = str_replace('ة', 'ه', $str);
                        $str = str_replace('ى', 'ي', $str);
                        return $str;
                    };
                    $catNorm = $normalize($category->name);
                    $prodNorm = $normalize($productName);

                    // Check if product name has additional words beyond the category name
                    $catWords = array_filter(preg_split('/\s+/u', $catNorm), fn($w) => mb_strlen($w) >= 2);
                    $prodWords = array_filter(preg_split('/\s+/u', $prodNorm), fn($w) => mb_strlen($w) >= 2);
                    $extraWords = array_diff($prodWords, $catWords);
                    $hasExtraProductWords = !empty($extraWords);
                    $hasQuantity = !empty($entities['quantity']);

                    if ($hasExtraProductWords || $hasQuantity) {
                        // User's name is more specific than category, or has quantity
                        // → try to find the actual product before falling back to browse
                        $productMatch = $this->productService->findBestMatch($store, $productName);
                        if ($productMatch) {
                            Log::info('GroqChatServiceV3: ADD_TO_CART kept - product found despite category match', [
                                'product_name' => $productName,
                                'matched_product' => $productMatch->name,
                                'category' => $category->name,
                            ]);
                        } else {
                            // No product match → browse category
                            $intent = Intent::BROWSE_PRODUCTS;
                            $entities['category_name'] = $category->name;
                            Log::info('GroqChatServiceV3: ADD_TO_CART redirected to BROWSE - no product found', [
                                'product_name' => $productName,
                                'category' => $category->name,
                            ]);
                        }
                    } else {
                        // Product name is essentially just the category name → browse
                        $intent = Intent::BROWSE_PRODUCTS;
                        $entities['category_name'] = $category->name;
                        Log::info('GroqChatServiceV3: ADD_TO_CART redirected to BROWSE - name matches category', [
                            'product_name' => $productName,
                            'category' => $category->name,
                        ]);
                    }
                }
            }
        }

        // ── CRITICAL FIX: Multi-item order detection ──
        // If ADD_TO_CART and message contains "و" connector with multiple product references,
        // handle as multi-item add
        if ($intent === Intent::ADD_TO_CART && $this->isMultiItemMessage($message)) {
            return $this->handleMultiItemAdd($session, $store, $entities, $message);
        }

        // ── RESCUE: Unknown intent in IDLE/BROWSING/WAITING → try to interpret as browse/add ──
        // Skip rescue for conversational messages (price complaints, explanations, etc.)
        // These should always go directly to the AI Agent
        $isConversational = $this->isConversationalMessage($message);

        if ($intent === Intent::UNKNOWN && !$isConversational && in_array($currentState, [
            ConversationState::IDLE,
            ConversationState::BROWSING_PRODUCTS,
            ConversationState::WAITING_PRODUCT_SELECTION,
            ConversationState::CART_REVIEW,
        ])) {
            $normalizedMsg = mb_strtolower(trim($message));
            // Full normalization so alternate spellings (أ/إ/آ→ا, ة→ه, ى→ي) still match
            $normalizedMsg = str_replace(['أ', 'إ', 'آ'], 'ا', $normalizedMsg);
            $normalizedMsg = str_replace('ة', 'ه', $normalizedMsg);
            $normalizedMsg = str_replace('ى', 'ي', $normalizedMsg);
            $productWords = ['منتج', 'متوفر', 'موجود', 'اكو', 'عندكم', 'عدكم', 'اشوف', 'ملابس', 'قمصان', 'احذيه', 'شنط', 'اكسسوار', 'المنتجات', 'كتلوج', 'القائمه', 'تبيعون'];
            foreach ($productWords as $word) {
                if (mb_strpos($normalizedMsg, $word) !== false) {
                    $intent = Intent::BROWSE_PRODUCTS;
                    break;
                }
            }
            // Also try: does the message match a category name in the DB?
            if ($intent === Intent::UNKNOWN) {
                $category = $this->productService->findCategory($store, $message);
                if ($category) {
                    $intent = Intent::BROWSE_PRODUCTS;
                    $entities['category_name'] = $category->name;
                }
            }
            // Also try with extracted product name or stripped message
            if ($intent === Intent::UNKNOWN) {
                $strippedMsg = $this->extractProductFromMessage($message);
                if ($strippedMsg && mb_strlen($strippedMsg) >= 2) {
                    $category = $this->productService->findCategory($store, $strippedMsg);
                    if ($category) {
                        $intent = Intent::BROWSE_PRODUCTS;
                        $entities['category_name'] = $category->name;
                    }
                }
            }
            // Try DB search for a product match
            if ($intent === Intent::UNKNOWN) {
                $searchResult = $this->productService->findBestMatch($store, $message);
                if ($searchResult) {
                    $intent = Intent::BROWSE_PRODUCTS;
                    $entities['product_name'] = $searchResult->name;
                }
            }
            // Try to resolve as ADD_TO_CART if message has product name + quantity
            if ($intent === Intent::UNKNOWN && in_array($currentState, [
                ConversationState::BROWSING_PRODUCTS,
                ConversationState::WAITING_PRODUCT_SELECTION,
            ])) {
                $product = $this->resolveProduct($session, $store, $entities, $message);
                if ($product) {
                    $intent = Intent::ADD_TO_CART;
                }
            }
        }

        // ── AI AGENT FALLBACK: If still UNKNOWN after all rescue attempts, delegate to AI Agent ──
        if ($intent === Intent::UNKNOWN) {
            Log::info('GroqChatServiceV3: Delegating to ChatAgentService', [
                'session_id' => $session->id,
                'message' => mb_substr($message, 0, 60),
                'state' => $currentState->value,
            ]);

            try {
                $agentResult = $this->chatAgent->process($message, $store, $session, $lead);

                if ($agentResult && !empty($agentResult['reply'])) {
                    // If the agent performed cart actions, update session state accordingly
                    if (!$this->cartService->isEmpty($session) && $currentState === ConversationState::IDLE) {
                        $session->conversation_state = ConversationState::CART_REVIEW->value;
                        $session->save();
                    }

                    Log::info('GroqChatServiceV3: ChatAgentService provided response', [
                        'session_id' => $session->id,
                        'reply_length' => mb_strlen($agentResult['reply']),
                    ]);

                    return $agentResult;
                } else {
                    Log::debug('GroqChatServiceV3: ChatAgentService returned empty reply, falling through to state machine', [
                        'session_id' => $session->id,
                        'result' => $agentResult,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('GroqChatServiceV3: ChatAgent failed, using conversational fallback', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // ── CONVERSATIONAL PHP FALLBACK ──
            // Agent failed or returned empty. If this was a conversational message,
            // build a direct response instead of falling through to a generic greeting.
            if ($isConversational) {
                $fallback = $this->handleConversationalFallback($session, $store, $message, $currentState);
                if ($fallback !== null) {
                    return $fallback;
                }
            }

            // ── DISCOUNT/NEGOTIATION PHP FALLBACK ──
            // Handle discount/offer requests that slipped through
            if ($this->isDiscountRequest($message)) {
                $fallback = $this->handleDiscountFallback($session, $store, $message);
                if ($fallback !== null) {
                    return $fallback;
                }
            }
        }

        // ── STATE TRANSITION ──
        // For conversational UNKNOWN messages, stay in current state (don't reset to IDLE)
        if ($intent === Intent::UNKNOWN && $isConversational) {
            $session->conversation_state = $currentState->value;
            $session->save();
            // Show a generic soft reply rather than a greeting
            return $this->buildResponse("ممكن توضح اكثر؟ 😊 انا هنا اساعدك!");
        }

        $stateContext = [
            'cart_empty' => $this->cartService->isEmpty($session),
            'info_complete' => $this->conversationManager->hasCompleteInfo($session, $lead),
        ];

        $newState = $this->stateMachine->getNextState($currentState, $intent, $stateContext);
        $session->conversation_state = $newState->value;
        $session->save();

        // ── STATE-BASED ACTION ──
        return match($newState) {
            ConversationState::IDLE => $this->handleIdle($session, $store, $lead, $intent),
            ConversationState::BROWSING_PRODUCTS => $this->handleBrowsing($session, $store, $entities, $message),
            ConversationState::WAITING_PRODUCT_SELECTION => $this->handleWaitingSelection($session, $store, $entities, $message),
            ConversationState::ADDING_TO_CART => $this->handleAddToCart($session, $store, $entities, $message),
            ConversationState::CART_REVIEW => $this->handleCartReview($session, $store, $intent),
            ConversationState::WAITING_CUSTOMER_INFO => $this->handleWaitingInfo($session, $lead, $entities, $message),
            ConversationState::WAITING_CONFIRMATION => $this->handleWaitingConfirmation($session, $store, $lead),
            ConversationState::ORDER_COMPLETED => $this->handleOrderCompleted($session, $store, $lead),
            ConversationState::CANCELLED => $this->handleCancelled($session),
        };
    }

    // ═══════════════════════════════════════════════════════════════════
    // STATE HANDLERS
    // ═══════════════════════════════════════════════════════════════════

    // ── IDLE ──

    protected function handleIdle(
        AiChatSession $session,
        User $store,
        Lead $lead,
        Intent $intent
    ): array {
        $hasCart = !$this->cartService->isEmpty($session);
        $hasOrder = !empty($this->conversationManager->getCurrentOrder($session));

        // Safety net: if a product-browse intent somehow reached handleIdle,
        // delegate to browsing instead of showing a generic greeting.
        if ($intent === Intent::BROWSE_PRODUCTS) {
            $session->conversation_state = ConversationState::BROWSING_PRODUCTS->value;
            $session->save();
            return $this->handleBrowsing($session, $store, [], '');
        }

        // Safety: if user typed something that matches a category, show that category
        // This handles cases like typing "ملابس" in IDLE state
        return $this->buildResponse($this->responseGenerator->greeting($hasOrder, $hasCart));
    }

    // ── GREETING (from any state) ──

    protected function handleGreeting(AiChatSession $session, ConversationState $currentState): array
    {
        $hasCart = !$this->cartService->isEmpty($session);
        $hasOrder = !empty($this->conversationManager->getCurrentOrder($session));

        // Handle "منو انا" (who am I) - identity question
        $lead = $session->lead_id ? Lead::find($session->lead_id) : null;
        $customerData = $lead ? $this->conversationManager->getCustomerData($session, $lead) : [];
        // Check session meta for customer data too
        $sessionMeta = json_decode($session->meta_data ?? '{}', true);
        if (empty($customerData['name']) && !empty($sessionMeta['customer_name'])) {
            $customerData['name'] = $sessionMeta['customer_name'];
        }

        // Fresh states → normal greeting
        if (in_array($currentState, [ConversationState::IDLE, ConversationState::ORDER_COMPLETED, ConversationState::CANCELLED])) {
            return $this->buildResponse($this->responseGenerator->greeting($hasOrder, $hasCart));
        }

        // Mid-flow greeting → greet + remind context
        $greeting = 'اهلا وسهلا! 🌟';

        if ($currentState === ConversationState::CART_REVIEW && $hasCart) {
            $cart = $this->cartService->getFormattedCart($session);
            $deliveryCost = $session->user->aiSetting->delivery_cost ?? 5000;
            $cartSummary = $this->responseGenerator->cartSummary($cart['items'], $cart['total'], true, $deliveryCost);
            return $this->buildResponse($greeting . "\n" . $cartSummary);
        }

        if ($currentState === ConversationState::WAITING_CUSTOMER_INFO) {
            $missing = $this->conversationManager->getMissingInfo($session);
            $infoRequest = $this->responseGenerator->requestMissingInfo($missing);
            return $this->buildResponse($greeting . ' طلبك موجود بالسله. ' . $infoRequest);
        }

        if ($currentState === ConversationState::WAITING_CONFIRMATION) {
            return $this->buildResponse($greeting . ' طلبك جاهز للتأكيد. تأكد الطلب؟ نعم / لا');
        }

        if ($hasCart) {
            return $this->buildResponse($this->responseGenerator->greeting(false, true));
        }

        return $this->buildResponse($this->responseGenerator->greeting($hasOrder, false));
    }

    // ── BROWSING PRODUCTS ──

    /**
     * Handle BROWSING_PRODUCTS state
     *
     * THREE MODES:
     * 1. General browse ("شنو عدكم") → show ONLY categories
     * 2. Category browse ("كهربائيات") → show 15-20 products from category
     * 3. Product search ("ايفون 15") → search and show matches
     */
    protected function handleBrowsing(
        AiChatSession $session,
        User $store,
        array $entities,
        string $message
    ): array {
        // ── QUICK CHECK: "all products" request → bypass classifyBrowseQuery entirely ──
        if (preg_match('/كل المنتجات|جميع المنتجات|عددلي كل|وريني كل|اعرضلي كل|كل شي عندكم|كل شي عدكم|كلشي عندكم|كلشي عدكم|ماهي المنتجات|المنتجات.*توفر|المنتجات.*متاح/u', $message)) {
            return $this->showAllProducts($session, $store);
        }

        // ── PRIORITY: If entities already have a confirmed category_name, show its products directly ──
        $categoryName = $entities['category_name'] ?? null;
        if ($categoryName) {
            $realCategory = $this->productService->findCategory($store, $categoryName);
            if ($realCategory) {
                return $this->showCategoryProducts($session, $store, $realCategory->name, $realCategory->id);
            }
        }

        // ── PRIORITY: Try to find category directly from message before classifying ──
        $directCategory = $this->productService->findCategory($store, $message);
        if ($directCategory) {
            return $this->showCategoryProducts($session, $store, $directCategory->name, $directCategory->id);
        }

        // IMPROVEMENT: Store browse context in session
        $browseType = $this->intentAnalyzer->classifyBrowseQuery($message, $store);
        $productName = $entities['product_name'] ?? null;

        // Save context for later reference
        $meta = json_decode($session->meta_data ?? '{}', true) ?? [];
        if ($categoryName) {
            $meta['last_browsed_category'] = $categoryName;
        }
        $meta['last_browse_type'] = $browseType;
        $meta['last_browse_time'] = now()->toDateTimeString();
        $session->meta_data = json_encode($meta);
        $session->save();

        // ── MODE 0: ALL products browse → show all products across categories ──
        if ($browseType === 'all') {
            return $this->showAllProducts($session, $store);
        }

        // ── MODE 1: General browse → categories only ──
        if ($browseType === 'general') {
            return $this->showCategoriesOnly($store);
        }

        // ── MODE 2: Product search (DB confirmed it's a product, not a category) ──
        if ($browseType === 'product') {
            $searchTerm = $productName ?? $categoryName ?? $this->extractProductFromMessage($message);
            if ($searchTerm) {
                $products = $this->productService->search($store, $searchTerm, 5);
                if ($products->isEmpty()) {
                    $variations = $this->productService->getArabicVariations($searchTerm);
                    foreach ($variations as $variation) {
                        $products = $this->productService->search($store, $variation, 5);
                        if ($products->isNotEmpty()) break;
                    }
                }
                if ($products->isNotEmpty()) {
                    $this->conversationManager->setShownProducts($session, $products->toArray());
                    if ($products->count() === 1) {
                        $this->conversationManager->setLastMentionedProduct($session, $products->first());
                    }
                    $formatted = $this->productService->formatListForDisplay($products);
                    $response = $this->responseGenerator->showProducts($formatted, $searchTerm);
                    $images = [];
                    $productIds = [];
                    foreach ($products as $product) {
                        $imageUrl = $this->productService->getPrimaryImageUrl($product);
                        if ($imageUrl) { $images[] = $imageUrl; $productIds[] = $product->id; }
                    }
                    $session->conversation_state = ConversationState::WAITING_PRODUCT_SELECTION->value;
                    $session->save();
                    return $this->buildResponse($response, $images, [], $productIds);
                }
            }
            // Product DB search returned nothing → fall through to category/general
        }

        // ── MODE 3: Category browse → use category_name entity or search for category ──
        if ($browseType === 'category' && $categoryName) {
            // Check if this is a REAL category in DB first
            $realCategory = $this->productService->findCategory($store, $categoryName);
            if ($realCategory) {
                return $this->showCategoryProducts($session, $store, $realCategory->name, $realCategory->id);
            }
            // Not a real category → treat as product search (e.g. "متوفر قميص")
            $products = $this->productService->search($store, $categoryName, 5);
            if ($products->isEmpty()) {
                $variations = $this->productService->getArabicVariations($categoryName);
                foreach ($variations as $variation) {
                    $products = $this->productService->search($store, $variation, 5);
                    if ($products->isNotEmpty()) break;
                }
            }
            if ($products->isNotEmpty()) {
                $this->conversationManager->setShownProducts($session, $products->toArray());
                if ($products->count() === 1) {
                    $this->conversationManager->setLastMentionedProduct($session, $products->first());
                }
                $formatted = $this->productService->formatListForDisplay($products);
                $response = $this->responseGenerator->showProducts($formatted, $categoryName);
                $images = [];
                $productIds = [];
                foreach ($products as $product) {
                    $imageUrl = $this->productService->getPrimaryImageUrl($product);
                    if ($imageUrl) { $images[] = $imageUrl; $productIds[] = $product->id; }
                }
                $session->conversation_state = ConversationState::WAITING_PRODUCT_SELECTION->value;
                $session->save();
                return $this->buildResponse($response, $images, [], $productIds);
            }
            // Nothing found → fall through to show categories with not-found message
            $categories = $this->productService->getCategories($store);
            $response = $this->responseGenerator->productNotFound($categoryName, $categories->toArray(), []);
            return $this->buildResponse($response);
        }

        // Try to find a matching category from the message
        $query = $entities['product_name'] ?? $this->extractProductFromMessage($message);

        if ($query === null || mb_strlen(trim($query)) < 2) {
            return $this->showCategoriesOnly($store);
        }

        // Check if the query matches a category name
        $category = $this->productService->findCategory($store, $query);
        if ($category) {
            return $this->showCategoryProducts($session, $store, $category->name, $category->id);
        }

        // ── MODE 3: Product search ──
        $products = $this->productService->search($store, $query, 5);

        // Try Arabic word variations
        if ($products->isEmpty()) {
            $variations = $this->productService->getArabicVariations($query);
            foreach ($variations as $variation) {
                $products = $this->productService->search($store, $variation, 5);
                if ($products->isNotEmpty()) break;

                $varCategory = $this->productService->findCategory($store, $variation);
                if ($varCategory) {
                    return $this->showCategoryProducts($session, $store, $varCategory->name, $varCategory->id);
                }
            }
        }

        // Not found → friendly message with categories
        if ($products->isEmpty()) {
            $categories = $this->productService->getCategories($store);
            $bestSellers = $this->productService->getBestSellers($store, 3);

            $response = $this->responseGenerator->productNotFound(
                $query,
                $categories->toArray(),
                $bestSellers->toArray()
            );

            $images = [];
            $productIds = [];
            foreach ($bestSellers as $p) {
                $img = $this->productService->getPrimaryImageUrl($p);
                if ($img) { $images[] = $img; $productIds[] = $p->id; }
            }

            return $this->buildResponse($response, $images, [], $productIds);
        }

        // Found products
        $this->conversationManager->setShownProducts($session, $products->toArray());

        if ($products->count() === 1) {
            $this->conversationManager->setLastMentionedProduct($session, $products->first());
        }

        $formatted = $this->productService->formatListForDisplay($products);
        $response = $this->responseGenerator->showProducts($formatted, $query);

        $images = [];
        $productIds = [];
        $shownProducts = [];
        foreach ($products as $product) {
            $imageUrl = $this->productService->getPrimaryImageUrl($product);
            if ($imageUrl) { $images[] = $imageUrl; $productIds[] = $product->id; }
            $shownProducts[] = ['id' => $product->id, 'name' => $product->name];
        }

        // Store shown products in session context
        $meta = json_decode($session->meta_data ?? '{}', true) ?? [];
        $meta['last_shown_products'] = $shownProducts;
        $meta['last_shown_time'] = now();
        $session->meta_data = json_encode($meta);

        // Always move to WAITING_PRODUCT_SELECTION after showing products
        $session->conversation_state = ConversationState::WAITING_PRODUCT_SELECTION->value;
        $session->save();

        return $this->buildResponse($response, $images, [], $productIds);
    }

    // ── WAITING PRODUCT SELECTION ──

    /**
     * Normalize a string for fuzzy matching:
     * lowercase, أإآ→ا, ة→ه, ى→ي, strip leading ال
     */
    protected function normalizeForMatch(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = str_replace(['أ', 'إ', 'آ'], 'ا', $s);
        $s = str_replace('ة', 'ه', $s);
        $s = str_replace('ى', 'ي', $s);
        // Strip leading ال from each word
        $words = preg_split('/\s+/u', $s);
        $words = array_map(function($w) {
            if (mb_strpos($w, 'ال') === 0 && mb_strlen($w) > 3) {
                return mb_substr($w, 2);
            }
            return $w;
        }, $words);
        return implode(' ', $words);
    }

    /**
     * Try to match a user query against a list of shown products.
     * Handles: exact substring, keyword-by-keyword, color/attribute word, definite article.
     * Returns the first matching Product model, or null.
     */
    protected function matchShownProduct(array $shownProducts, string $query): ?\App\Models\Product
    {
        $norm = $this->normalizeForMatch($query);
        $queryWords = array_filter(preg_split('/\s+/u', $norm), fn($w) => mb_strlen($w) >= 2);

        // PASS 1: direct substring match (both directions)
        foreach ($shownProducts as $shown) {
            $shownNorm = $this->normalizeForMatch($shown['name']);
            if (mb_strpos($shownNorm, $norm) !== false || mb_strpos($norm, $shownNorm) !== false) {
                $p = $this->productService->getById($shown['id']);
                if ($p) return $p;
            }
        }

        // PASS 2: ALL query keywords appear in product name
        if (!empty($queryWords)) {
            $bestMatch = null;
            $bestScore = 0;
            foreach ($shownProducts as $shown) {
                $shownNorm = $this->normalizeForMatch($shown['name']);
                $hits = 0;
                foreach ($queryWords as $w) {
                    if (mb_strpos($shownNorm, $w) !== false) $hits++;
                }
                if ($hits === count($queryWords) && $hits > $bestScore) {
                    $bestScore = $hits;
                    $bestMatch = $shown;
                }
            }
            if ($bestMatch) {
                $p = $this->productService->getById($bestMatch['id']);
                if ($p) return $p;
            }

            // PASS 3: MOST keywords (>= 60%) match — handles partial descriptions like "قميص ابيض"
            $threshold = max(1, (int) ceil(count($queryWords) * 0.6));
            $bestMatch = null;
            $bestScore = 0;
            foreach ($shownProducts as $shown) {
                $shownNorm = $this->normalizeForMatch($shown['name']);
                $hits = 0;
                foreach ($queryWords as $w) {
                    if (mb_strpos($shownNorm, $w) !== false) $hits++;
                }
                if ($hits >= $threshold && $hits > $bestScore) {
                    $bestScore = $hits;
                    $bestMatch = $shown;
                }
            }
            if ($bestMatch) {
                $p = $this->productService->getById($bestMatch['id']);
                if ($p) return $p;
            }
        }

        return null;
    }

    protected function handleWaitingSelection(
        AiChatSession $session,
        User $store,
        array $entities,
        string $message
    ): array {
        $shownProducts = $this->conversationManager->getShownProducts($session);

        if (empty($shownProducts)) {
            return $this->buildResponse($this->responseGenerator->template('ask_which_product'));
        }

        // Check if customer selected by number (#1, #2, etc.)
        $selectionNum = $entities['selection_number'] ?? null;
        if ($selectionNum !== null && isset($shownProducts[$selectionNum - 1])) {
            $selected = $shownProducts[$selectionNum - 1];
            $product = $this->productService->getById($selected['id']);
            if ($product) {
                $this->conversationManager->setLastMentionedProduct($session, $product);
                $formatted = $this->productService->formatListForDisplay(collect([$product]));
                $response = $this->responseGenerator->showProductDetail($formatted[0] ?? $selected);

                $images = [];
                $productIds = [];
                $img = $this->productService->getPrimaryImageUrl($product);
                if ($img) { $images[] = $img; $productIds[] = $product->id; }

                return $this->buildResponse($response, $images, [], $productIds);
            }
        }

        // Try to match by product name (smart: keyword-by-keyword + definite-article-aware)
        $productName = $entities['product_name'] ?? $this->extractProductFromMessage($message);
        $matchQuery = $productName ?: $message;

        if ($matchQuery && mb_strlen(trim($matchQuery)) >= 2) {
            $product = $this->matchShownProduct($shownProducts, $matchQuery);
            if ($product) {
                $this->conversationManager->setLastMentionedProduct($session, $product);
                $formatted = $this->productService->formatListForDisplay(collect([$product]));
                $response = $this->responseGenerator->showProductDetail($formatted[0]);

                $images = [];
                $productIds = [];
                $img = $this->productService->getPrimaryImageUrl($product);
                if ($img) { $images[] = $img; $productIds[] = $product->id; }

                return $this->buildResponse($response, $images, [], $productIds);
            }

            // PASS 4: Filter shown products by matching word and show a narrowed list
            // e.g. 'الماروني' → list only the maroon shirts
            $norm = $this->normalizeForMatch($matchQuery);
            $qWords = array_filter(preg_split('/\s+/u', $norm), fn($w) => mb_strlen($w) >= 2);
            $filtered = array_filter($shownProducts, function($shown) use ($qWords) {
                $sNorm = $this->normalizeForMatch($shown['name']);
                foreach ($qWords as $w) {
                    if (mb_strpos($sNorm, $w) !== false) return true;
                }
                return false;
            });
            $filtered = array_values($filtered);

            if (!empty($filtered) && count($filtered) < count($shownProducts)) {
                // Update shown products to the narrowed list
                $this->conversationManager->setShownProducts($session, $filtered);
                if (count($filtered) === 1) {
                    $p = $this->productService->getById($filtered[0]['id']);
                    if ($p) {
                        $this->conversationManager->setLastMentionedProduct($session, $p);
                        $fmt = $this->productService->formatListForDisplay(collect([$p]));
                        $resp = $this->responseGenerator->showProductDetail($fmt[0]);
                        $images = [];
                        $productIds = [];
                        $img = $this->productService->getPrimaryImageUrl($p);
                        if ($img) { $images[] = $img; $productIds[] = $p->id; }
                        return $this->buildResponse($resp, $images, [], $productIds);
                    }
                }
                $response = $this->responseGenerator->showProducts($filtered, $matchQuery);
                return $this->buildResponse($response);
            }
        }

        // Nothing matched — check if user typed a category name (e.g., after being shown categories)
        $category = $this->productService->findCategory($store, $message);
        if ($category) {
            return $this->showCategoryProducts($session, $store, $category->name, $category->id);
        }

        // Also try with the extracted product name as a category
        if ($productName && mb_strlen($productName) >= 2) {
            $category = $this->productService->findCategory($store, $productName);
            if ($category) {
                return $this->showCategoryProducts($session, $store, $category->name, $category->id);
            }
        }

        // Nothing matched — show the list again
        $response = $this->responseGenerator->showProducts($shownProducts);
        return $this->buildResponse($response);
    }

    // ── ADD TO CART ──

    protected function handleAddToCart(
        AiChatSession $session,
        User $store,
        array $entities,
        string $message
    ): array {
        $product = $this->resolveProduct($session, $store, $entities, $message);

        if (!$product) {
            return $this->buildResponse($this->responseGenerator->template('product_not_found'));
        }

        $quantity = $entities['quantity'] ?? 1;

        $attributes = [];
        if (!empty($entities['color'])) $attributes['color'] = $entities['color'];
        if (!empty($entities['size'])) $attributes['size'] = $entities['size'];

        $result = $this->cartService->addItem($session, $product, $quantity, $attributes);

        if (!$result['success']) {
            return $this->buildResponse($result['message']);
        }

        $this->conversationManager->setLastMentionedProduct($session, $product);

        // Get FULL cart for display
        $cart = $this->cartService->getFormattedCart($session);
        $deliveryCost = $store->aiSetting->delivery_cost ?? 5000;

        // Transition to cart review
        $session->conversation_state = ConversationState::CART_REVIEW->value;
        $session->save();

        // Show FULL cart with all items + delivery cost
        $response = $this->responseGenerator->itemAdded($cart['items'], $cart['total'], 0, $deliveryCost);

        return $this->buildResponse($response);
    }

    // ── SALES OBJECTION HANDLING ──

    /**
     * Detect sales objections: "حصلت بمكان ثاني بسعر ارخص", "ما احتاج", "مو محتاج"
     * These should NEVER trigger add-to-cart.
     */
    protected function isSalesObjection(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));
        $normalized = str_replace(['أ', 'إ', 'آ'], 'ا', $normalized);
        $normalized = str_replace('ة', 'ه', $normalized);
        $normalized = str_replace('ى', 'ي', $normalized);

        $patterns = [
            '/حصلت.*ارخص/u',
            '/لقيت.*ارخص/u',
            '/بمكان ثاني/u',
            '/محل ثاني/u',
            '/موقع ثاني/u',
            '/عند غيركم/u',
            '/غيركم ارخص/u',
            '/مكان اخر/u',
            '/هسه مو محتاج/u',
            '/ما احتاج/u',
            '/مو محتاج/u',
            '/ما ابي/u',
            '/بعدين اشتري/u',
            '/افكر بيها/u',
            '/اشوف وارد/u',
            '/راح افكر/u',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $normalized)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Handle sales objections with persuasive salesman-like responses.
     * The bot should counter objections, not give up.
     */
    protected function handleSalesObjection(
        AiChatSession $session,
        User $store,
        string $message
    ): array {
        $normalized = mb_strtolower(trim($message));
        $normalized = str_replace(['أ', 'إ', 'آ'], 'ا', $normalized);

        $lastProduct = $this->conversationManager->getLastMentionedProduct($session);
        $productName = $lastProduct['name'] ?? null;
        $productPrice = $lastProduct['price'] ?? null;

        // ── Competitor comparison: "found cheaper elsewhere" ──
        if (preg_match('/حصلت|لقيت|بمكان|محل ثاني|موقع ثاني|عند غيركم|غيركم/u', $normalized)) {
            if ($productName) {
                $formattedPrice = number_format((int)($productPrice ?? 0));
                $replies = [
                    "يعني صحيح اكو اسعار بالسوق مختلفه 😊 بس {$productName} عدنا اصلي ومضمون ✅\nوالتوصيل سريع! تبي تجربه وتشوف الفرق؟",
                    "والله يمكن لقيت ارخص، بس انتبه للجوده! {$productName} عدنا بضمان كامل 💪\nالسعر {$formattedPrice} د.ع ويشمل كل شي. شنو رايك؟",
                    "اخي الغالي، السعر الرخيص مو دائماً الاحسن 😅\n{$productName} جودته ممتازه ومضمون عدنا.\nتبي اشوفلك عرض خاص؟ 🌟",
                    "فاهم عليك! بس احنا نضمنلك الجوده والتوصيل 🚚\n{$productName} يسوى كل فلس.\nشنو رايك نحطه بالسله ونكمل؟",
                ];
            } else {
                $replies = [
                    "يعني والله، بس منتجاتنا اصليه ومضمونه ✅ وتوصيل سريع!\nقولي شنو يهمك واشوفلك احسن سعر 😊",
                    "اسعارنا تعكس الجوده 💪 ومضمونه عدنا.\nتبي اشوفلك شي بميزانيه معينه؟",
                ];
            }
            return $this->buildResponse($replies[array_rand($replies)]);
        }

        // ── Hesitation: "افكر بيها", "بعدين", "ما احتاج" ──
        if ($productName) {
            $replies = [
                "يعني لا تضيع الفرصه! {$productName} عليه طلب كبير 🔥\nخذه هسه قبل ما يخلص!",
                "اوكي، بس خلي بالك {$productName} الكميه محدوده 😊\nلو تبي شي ثاني اقدر اساعدك!",
                "تمام، لو تحتاج اي شي انا هنا! 🌟\nبس {$productName} يوصلك بسرعه لو حبيت تطلبه.",
                "اوكي، بس لو تحب تضيفه للسله هسه وتاكد لاحقاً، الامر بسيط ✅",
            ];
        } else {
            $replies = [
                "تمام! لو تحتاج اي شي ارجع وكلمني 😊\nعدنا منتجات حلوه بأسعار ممتازه!",
                "اوكي، شنو ممكن اساعدك بيه؟ عدنا تشكيله واسعه 🌟",
            ];
        }
        return $this->buildResponse($replies[array_rand($replies)]);
    }

    // ── OFFER/PROMOTION INQUIRY ──

    /**
     * Detect offer/promotion inquiries: "العرض بعده موجود", "اكو عرض"
     */
    protected function isOfferInquiry(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));
        $normalized = str_replace(['أ', 'إ', 'آ'], 'ا', $normalized);
        $normalized = str_replace('ة', 'ه', $normalized);
        $normalized = str_replace('ى', 'ي', $normalized);

        $patterns = [
            '/العرض.*موجود/u',
            '/العرض.*بعد/u',
            '/بعد.*عرض/u',
            '/العروض.*بعد/u',
            '/العروض.*متوفر/u',
            '/هل.*عرض/u',
            '/اكو.*عرض(?!.*سل)/u',
            '/شنو العروض/u',
            '/عندكم.*عرض/u',
            '/عدكم.*عرض/u',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $normalized)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Handle offer/promotion inquiries
     */
    protected function handleOfferInquiry(
        AiChatSession $session,
        User $store,
        string $message
    ): array {
        // Try to delegate to ChatAgent for a more natural response
        try {
            $agentResult = $this->chatAgent->process($message, $store, $session);
            if ($agentResult && !empty($agentResult['reply'])) {
                return $agentResult;
            }
        } catch (\Exception $e) {
            Log::warning('GroqChatServiceV3: ChatAgent offer inquiry failed', ['error' => $e->getMessage()]);
        }

        // Fallback: direct response
        $lastProduct = $this->conversationManager->getLastMentionedProduct($session);
        $productName = $lastProduct['name'] ?? null;

        if ($productName) {
            $replies = [
                "حالياً {$productName} بالسعر الموجود بدون عرض اضافي 😊\nبس هذا أفضل سعر عدنا!\nتبي تضيفه للسله؟",
                "ما عدنا عرض خاص على {$productName} هسه، بس السعر الحالي ممتاز 💪\nتبي اطلبلك اياه؟",
            ];
        } else {
            $replies = [
                "حالياً ما عدنا عروض خاصه 😊\nبس عدنا منتجات بأسعار حلوه!\nشنو تبي تشوف؟ اقدر اساعدك تختار 🌟",
                "العروض مو متوفره هسه، بس لو تقولي شنو تبي اشوفلك احسن الخيارات! 💪",
            ];
        }
        return $this->buildResponse($replies[array_rand($replies)]);
    }

    // ── CART VIEW / CLEAR (intent-first overrides) ──

    /**
     * Detect cart view requests: "شنو محتوى سلتي", "شنو بالسله", "سلتي", etc.
     */
    /**
     * PHP fallback for conversational messages when the AI Agent is unavailable.
     * Handles the most common cases: price complaints, explanation requests, negotiation.
     */
    protected function handleConversationalFallback(
        AiChatSession $session,
        User $store,
        string $message,
        ConversationState $currentState
    ): ?array {
        $normalized = mb_strtolower(trim($message));
        $normalized = str_replace(['أ', 'إ', 'آ'], 'ا', $normalized);
        $normalized = str_replace('ة', 'ه', $normalized);
        $normalized = str_replace('ى', 'ي', $normalized);

        $lastProduct = $this->conversationManager->getLastMentionedProduct($session);
        $productName = $lastProduct['name'] ?? null;
        $productPrice = $lastProduct['price'] ?? null;

        // ── Price complaint ──
        $isPriceComplaint = preg_match('/غالي|مكلف|كثير|غير معقول|ما يسوى|خفض|قلل|تخفيض|رخص/u', $normalized);
        if ($isPriceComplaint) {
            if ($productName && $productPrice) {
                $formattedPrice = number_format((int)$productPrice);
                $replies = [
                    "والله يعني صح، بس {$productName} فيه جوده عاليه. السعر {$formattedPrice} د.ع يعكس الجوده 💪\nخذه هسه وما راح تندم!\nتبي اضيفه للسله؟",
                    "يعني فاهم قصدك 😊 سعر {$productName} هو {$formattedPrice} د.ع بس صدقني يسوى كل فلس!\nتبي أشوف لك بديل بسعر ارخص؟",
                    "صح يعني مو رخيص 😅 بس {$productName} يسوى فلوسه، جودته عالية ومضمون.\nوالتوصيل سريع! تبي نحطه بالسله؟ 🌟",
                ];
                return $this->buildResponse($replies[array_rand($replies)]);
            }
            // No specific product in context — check if we just showed a list of products
            $shownProducts = $this->conversationManager->getShownProducts($session);
            if (!empty($shownProducts)) {
                $prices = array_filter(array_column($shownProducts, 'price'));
                $minPrice = !empty($prices) ? min($prices) : null;
                $names = array_slice(array_column($shownProducts, 'name'), 0, 2);
                $nameStr = implode(' و ', $names);
                $priceStr = $minPrice ? ' تبدأ من ' . number_format((int)$minPrice) . ' د.ع' : '';
                return $this->buildResponse("اسعار منتجاتنا{$priceStr} وتعكس جودة المنتج 💪\nمثلاً {$nameStr}.\nتبي شوف شي بسعر ارخص من القائمة؟");
            }
            // Truly no context at all
            $replies = [
                "شنو المنتج اللي تقصده؟ اقدر اساعدك تلقى شي ينسجم مع ميزانيتك 😊",
                "قولي اسم المنتج واشوفلك بديل بسعر احسن 💪",
            ];
            return $this->buildResponse($replies[array_rand($replies)]);
        }

        // ── Explanation / details request ──
        $isExplanation = preg_match('/اشرح|فصل|وصف|تفاصيل|فائده|فايده|فارق|ميزه|ميزات|معلومات/u', $normalized);
        if ($isExplanation) {
            if ($productName) {
                // Try to get full product details from DB
                $product = null;
                if (!empty($lastProduct['id'])) {
                    $product = $this->productService->getById($lastProduct['id']);
                }
                if ($product && !empty($product->description)) {
                    $desc = mb_substr($product->description, 0, 200);
                    return $this->buildResponse(
                        "{$product->name} 📋\n━━━━━━━━━━━━━━━\n{$desc}\n━━━━━━━━━━━━━━━\nاي سؤال ثاني؟ 😊"
                    );
                }
                return $this->buildResponse(
                    "بخصوص {$productName}، هذا منتج متوفر بسعر " . number_format((int)($productPrice ?? 0)) . " د.ع ✅\nتبي تضيفه للسله؟"
                );
            }
            return $this->buildResponse("شنو المنتج اللي تبي تعرف عنه؟ 😊");
        }

        return null;
    }

    /**
     * Detect messages that are conversational in nature (price negotiation, explanations,
     * opinions) and should be sent directly to the AI Agent without keyword rescue attempts.
     */
    protected function isConversationalMessage(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));
        $normalized = str_replace(['أ', 'إ', 'آ'], 'ا', $normalized);
        $normalized = str_replace('ة', 'ه', $normalized);
        $normalized = str_replace('ى', 'ي', $normalized);

        $conversationalPatterns = [
            // Price complaints
            '/غالي/u',
            '/مكلف/u',
            '/كلش غالي/u',
            '/غير معقول/u',
            '/ما يسوى/u',
            '/ليش هذا السعر/u',
            '/خفض.*سعر/u',
            '/قلل.*سعر/u',
            // Discount/offer requests
            '/تخفيض/u',
            '/خصم/u',
            '/عرض/u',
            '/مابي/u',
            '/ما عندكم.*عرض/u',
            '/اكو.*تخفيض/u',
            '/اكو.*خصم/u',
            // Competitor comparison / sales objection
            '/حصلت.*ارخص/u',
            '/بمكان ثاني/u',
            '/محل ثاني/u',
            '/عند غيركم/u',
            '/لقيت.*ارخص/u',
            '/موقع ثاني/u',
            '/ما احتاج/u',
            '/ما ابي/u',
            '/مو محتاج/u',
            // Offer/promotion inquiry
            '/العرض.*موجود/u',
            '/العرض.*بعد/u',
            '/بعد.*عرض/u',
            '/العروض.*متوفر/u',
            '/هل.*عرض/u',
            '/اكو.*عرض/u',
            // Explanation / detail requests
            '/اشرح/u',
            '/فصل/u',
            '/شنو فائدت/u',
            '/شنو ميزات/u',
            '/شنو الفرق/u',
            '/فرق بين/u',
            '/وصف/u',
            '/تفاصيل/u',
            '/معلومات عن/u',
            '/اخبرني عن/u',
            // Opinions/reactions
            '/ليش/u',
            '/مو زين/u',
            '/ما عجبني/u',
        ];

        foreach ($conversationalPatterns as $pattern) {
            if (preg_match($pattern, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the message is a discount/offer request.
     */
    protected function isDiscountRequest(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));
        $normalized = str_replace(['أ', 'إ', 'آ'], 'ا', $normalized);
        $normalized = str_replace('ة', 'ه', $normalized);
        $normalized = str_replace('ى', 'ي', $normalized);

        $patterns = [
            '/تخفيض/u',
            '/خصم/u',
            '/عرض|عروض/u',
            '/مابي.*سعر/u',
            '/ارخص/u',
            '/اقل سعر/u',
            '/سعر احسن/u',
            '/اكو.*تخفيض/u',
            '/اكو.*خصم/u',
            '/عندكم.*عرض/u',
            '/عدكم.*عرض/u',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $normalized)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Handle discount/offer requests with contextual responses.
     */
    protected function handleDiscountFallback(
        AiChatSession $session,
        User $store,
        string $message
    ): ?array {
        $lastProduct = $this->conversationManager->getLastMentionedProduct($session);
        $productName = $lastProduct['name'] ?? null;

        if ($productName) {
            $replies = [
                "حالياً  {$productName} بالسعر الحالي بدون تخفيض 😊\nبس تقدر تطلبه والسعر ثابت ✅\nتبي أضيفه للسلة؟",
                "يعني {$productName} مالته عرض حالياً، بس هذا أفضل سعر عدنا 💪\nتبي تضيفه؟",
                "ما عدنا تخفيض على {$productName} حالياً 😅\nبس إذا تبي أشوفلك بديل بسعر اقل، قولي!",
            ];
        } else {
            $replies = [
                "حالياً ما عدنا عروض خاصة 😊\nبس اقدر أساعدك تلاقي أفضل الأسعار!\nشنو تبي تشوف؟",
                "ما عدنا تخفيضات حالياً 😅\nبس لو تقولي شنو تبي، أشوفلك أفضل الخيارات!",
                "العروض مو متوفرة هسة، بس عدنا منتجات بأسعار حلوة 💪\nشنو يهمك؟",
            ];
        }

        return $this->buildResponse($replies[array_rand($replies)]);
    }

    protected function isCartViewRequest(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));
        $normalized = str_replace(['أ', 'إ', 'آ'], 'ا', $normalized);
        $normalized = str_replace('ة', 'ه', $normalized);
        $normalized = str_replace('ى', 'ي', $normalized);

        $patterns = [
            '/محتوى\s*سلتي/u',
            '/محتويات\s*السل/u',
            '/شنو\s*(?:ب|في)\s*(?:ال)?سل/u',
            '/^سلتي\s*[؟?]*\s*$/u',
            '/شنو\s*سلتي/u',
            '/وريني\s*(?:ال)?سل/u',
            '/عرض\s*(?:ال)?سل/u',
            '/شوف\s*(?:ال)?سل/u',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect cart clear requests: "فرغ سلتي", "فضي السله", "نظف السله"
     */
    protected function isCartClearRequest(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));
        $normalized = str_replace(['أ', 'إ', 'آ'], 'ا', $normalized);
        $normalized = str_replace('ة', 'ه', $normalized);
        $normalized = str_replace('ى', 'ي', $normalized);

        $patterns = [
            '/فرغ\s*(?:ال)?سل/u',
            '/فضي\s*(?:ال)?سل/u',
            '/نظف\s*(?:ال)?سل/u',
            '/مسح\s*(?:ال)?سل/u',
            '/خلي\s*السله\s*فاضي/u',
            '/حذف\s*كل\s*(?:ال)?سل/u',
            '/شيل\s*كلشي\s*من\s*(?:ال)?سل/u',
            '/امسح\s*(?:كل\s*)?(?:ال)?سل/u',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect info update requests: "غير اسمي", "عدل رقمي", etc.
     */
    protected function isInfoUpdateRequest(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));
        $normalized = str_replace(['أ', 'إ', 'آ'], 'ا', $normalized);
        $normalized = str_replace('ة', 'ه', $normalized);
        $normalized = str_replace('ى', 'ي', $normalized);

        $patterns = [
            '/غير\s*(?:اسمي|رقمي|عنواني|الاسم|الرقم|العنوان)/u',
            '/عدل\s*(?:اسمي|رقمي|عنواني|الاسم|الرقم|العنوان)/u',
            '/بدل\s*(?:اسمي|رقمي|عنواني|الاسم|الرقم|العنوان)/u',
            '/حدث\s*(?:اسمي|رقمي|عنواني|الاسم|الرقم|العنوان)/u',
            '/اسمي\s*(?:هو|هوه|هي)/u',
            '/رقمي\s*(?:هو|هوه)/u',
            '/عنواني\s*(?:هو|هوه)/u',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Show cart contents
     */
    protected function handleCartView(AiChatSession $session, User $store): array
    {
        $cart = $this->cartService->getFormattedCart($session);

        if (empty($cart['items'])) {
            return $this->buildResponse("السله فاضيه حالياً 🛒\nشنو تحب تطلب؟");
        }

        $deliveryCost = $store->aiSetting->delivery_cost ?? 5000;
        $response = $this->responseGenerator->cartSummary(
            $cart['items'],
            $cart['total'],
            true,
            $deliveryCost
        );

        // Stay in or move to CART_REVIEW state
        $session->conversation_state = ConversationState::CART_REVIEW->value;
        $session->save();

        return $this->buildResponse($response);
    }

    /**
     * Clear all items from cart
     */
    protected function handleCartClear(AiChatSession $session): array
    {
        if ($this->cartService->isEmpty($session)) {
            return $this->buildResponse("السله فاضيه اصلاً 🛒\nشنو تحب تطلب؟");
        }

        $this->cartService->clearCart($session);

        $session->conversation_state = ConversationState::IDLE->value;
        $session->save();

        return $this->buildResponse("تم تفريغ السله ✅\nشنو تحب تطلب؟");
    }

    // ── CART REVIEW ──

    protected function handleCartReview(
        AiChatSession $session,
        User $store,
        Intent $intent
    ): array {
        $cart = $this->cartService->getFormattedCart($session);

        if (empty($cart['items'])) {
            return $this->buildResponse($this->responseGenerator->template('cart_empty'));
        }

        $deliveryCost = $store->aiSetting->delivery_cost ?? 5000;

        $response = $this->responseGenerator->cartSummary(
            $cart['items'],
            $cart['total'],
            $intent !== Intent::DECLINE_MORE,
            $deliveryCost
        );

        return $this->buildResponse($response);
    }

    // ── UPDATE QUANTITY ──

    protected function handleUpdateQuantity(
        AiChatSession $session,
        User $store,
        array $entities,
        string $message
    ): array {
        if ($this->cartService->isEmpty($session)) {
            return $this->buildResponse($this->responseGenerator->template('cart_empty'));
        }

        $newQuantity = $entities['quantity'] ?? null;
        if ($newQuantity === null) {
            return $this->buildResponse('كم تريد الكميه؟');
        }

        $product = $this->resolveProduct($session, $store, $entities, $message);

        if ($product) {
            $result = $this->cartService->updateQuantity($session, $product->id, $newQuantity);
        } else {
            $cart = $this->cartService->getCart($session);
            if (count($cart) === 1) {
                $result = $this->cartService->updateQuantity($session, $cart[0]['product_id'], $newQuantity);
            } else {
                return $this->buildResponse('اي منتج تريد تعدل كميته؟');
            }
        }

        if (!$result['success']) {
            return $this->buildResponse($result['message']);
        }

        $session->conversation_state = ConversationState::CART_REVIEW->value;
        $session->save();

        $cart = $this->cartService->getFormattedCart($session);
        $deliveryCost = $store->aiSetting->delivery_cost ?? 5000;
        $response = $this->responseGenerator->cartUpdated('update', $cart['items'], $cart['total'], $deliveryCost);

        return $this->buildResponse($response);
    }

    // ── REMOVE FROM CART ──

    protected function handleRemoveFromCart(
        AiChatSession $session,
        User $store,
        array $entities,
        string $message
    ): array {
        if ($this->cartService->isEmpty($session)) {
            return $this->buildResponse($this->responseGenerator->template('cart_empty'));
        }

        $product = $this->resolveProduct($session, $store, $entities, $message);

        if ($product) {
            $result = $this->cartService->removeItem($session, $product->id);
        } else {
            $cart = $this->cartService->getCart($session);
            if (count($cart) === 1) {
                $result = $this->cartService->removeItem($session, $cart[0]['product_id']);
            } else {
                return $this->buildResponse('اي منتج تريد تشيله من السله؟');
            }
        }

        if (!$result['success']) {
            return $this->buildResponse($result['message']);
        }

        if ($this->cartService->isEmpty($session)) {
            $session->conversation_state = ConversationState::IDLE->value;
            $session->save();
            return $this->buildResponse('تم حذف المنتج. السله فاضيه. شنو تريد تطلب؟');
        }

        $session->conversation_state = ConversationState::CART_REVIEW->value;
        $session->save();

        $cart = $this->cartService->getFormattedCart($session);
        $deliveryCost = $store->aiSetting->delivery_cost ?? 5000;
        $response = $this->responseGenerator->cartUpdated('remove', $cart['items'], $cart['total'], $deliveryCost);

        return $this->buildResponse($response);
    }

    // ── WAITING CUSTOMER INFO ──

    protected function handleWaitingInfo(
        AiChatSession $session,
        Lead $lead,
        array $entities,
        string $message
    ): array {
        $extractedInfo = $this->extractCustomerInfo($entities, $message);

        if (!empty($extractedInfo)) {
            $this->conversationManager->collectCustomerInfo($session, $extractedInfo, $lead);
        }

        $missing = $this->conversationManager->getMissingInfo($session, $lead);

        if (empty($missing)) {
            $session->conversation_state = ConversationState::WAITING_CONFIRMATION->value;
            $session->save();
            return $this->handleWaitingConfirmation($session, $session->user, $lead);
        }

        $response = $this->responseGenerator->requestMissingInfo($missing);
        return $this->buildResponse($response);
    }

    // ── WAITING CONFIRMATION ──

    protected function handleWaitingConfirmation(
        AiChatSession $session,
        User $store,
        Lead $lead
    ): array {
        $cart = $this->cartService->getFormattedCart($session);
        $customerData = $this->conversationManager->getCustomerData($session, $lead);
        $deliveryCost = $store->aiSetting->delivery_cost ?? 5000;

        $response = $this->responseGenerator->orderSummaryForConfirmation(
            $cart['items'],
            $cart['total'],
            $deliveryCost,
            $customerData
        );

        return $this->buildResponse($response);
    }

    // ── ORDER COMPLETED ──

    protected function handleOrderCompleted(
        AiChatSession $session,
        User $store,
        Lead $lead
    ): array {
        $existingOrder = $this->conversationManager->getCurrentOrder($session);

        if ($existingOrder) {
            $deliveryTime = $store->aiSetting->delivery_time ?? 'نفس اليوم';
            $response = $this->responseGenerator->orderConfirmed(
                $existingOrder['order_id'],
                $existingOrder['total'],
                $deliveryTime
            );
            return $this->buildResponse($response);
        }

        $result = $this->orderService->createOrder($session, $store, $lead);

        if (!$result['success']) {
            $session->conversation_state = ConversationState::WAITING_CONFIRMATION->value;
            $session->save();
            return $this->buildResponse($result['message']);
        }

        $order = $result['order'];
        $deliveryTime = $store->aiSetting->delivery_time ?? 'نفس اليوم';

        $response = $this->responseGenerator->orderConfirmed(
            $order->id,
            $order->total,
            $deliveryTime
        );

        return $this->buildResponse($response, [], ['order_created' => $order->id]);
    }

    // ── CANCELLED ──

    protected function handleCancelled(AiChatSession $session): array
    {
        $this->cartService->clearCart($session);
        $this->conversationManager->resetForNewOrder($session);

        return $this->buildResponse($this->responseGenerator->template('order_cancelled'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // CATALOG DISPLAY METHODS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Show ONLY categories (for "شنو عدكم?" general browse)
     * Rule: Don't list any products, just category names
     */
    protected function showCategoriesOnly(User $store): array
    {
        $categories = $this->productService->getCategories($store);

        $response = $this->responseGenerator->showCategories($categories->toArray());

        return $this->buildResponse($response);
    }

    /**
     * Show products from a specific category (15-20 items)
     */
    protected function showCategoryProducts(
        AiChatSession $session,
        User $store,
        string $categoryName,
        ?int $categoryId = null
    ): array {
        // Find category if ID not provided
        if (!$categoryId) {
            $category = $this->productService->findCategory($store, $categoryName);
            if (!$category) {
                // Category not found → try searching as a product name instead
                $products = $this->productService->search($store, $categoryName, 5);
                if ($products->isNotEmpty()) {
                    $this->conversationManager->setShownProducts($session, $products->toArray());
                    if ($products->count() === 1) {
                        $this->conversationManager->setLastMentionedProduct($session, $products->first());
                    }
                    $formatted = $this->productService->formatListForDisplay($products);
                    $response = $this->responseGenerator->showProducts($formatted, $categoryName);

                    $images = [];
                    $productIds = [];
                    foreach ($products as $product) {
                        $imageUrl = $this->productService->getPrimaryImageUrl($product);
                        if ($imageUrl) { $images[] = $imageUrl; $productIds[] = $product->id; }
                    }

                    $session->conversation_state = ConversationState::WAITING_PRODUCT_SELECTION->value;
                    $session->save();

                    return $this->buildResponse($response, $images, [], $productIds);
                }

                // Also try Arabic variations
                $variations = $this->productService->getArabicVariations($categoryName);
                foreach ($variations as $variation) {
                    $products = $this->productService->search($store, $variation, 5);
                    if ($products->isNotEmpty()) {
                        $this->conversationManager->setShownProducts($session, $products->toArray());
                        $formatted = $this->productService->formatListForDisplay($products);
                        $response = $this->responseGenerator->showProducts($formatted, $categoryName);

                        $images = [];
                        $productIds = [];
                        foreach ($products as $product) {
                            $imageUrl = $this->productService->getPrimaryImageUrl($product);
                            if ($imageUrl) { $images[] = $imageUrl; $productIds[] = $product->id; }
                        }

                        $session->conversation_state = ConversationState::WAITING_PRODUCT_SELECTION->value;
                        $session->save();

                        return $this->buildResponse($response, $images, [], $productIds);
                    }
                }

                // Nothing found → show categories with "not found" message
                $categories = $this->productService->getCategories($store);
                $response = $this->responseGenerator->productNotFound(
                    $categoryName,
                    $categories->toArray(),
                    []
                );
                return $this->buildResponse($response);
            }
            $categoryId = $category->id;
            $categoryName = $category->name;
        }

        $products = $this->productService->getByCategory($store, $categoryId, 20);

        if ($products->isEmpty()) {
            return $this->buildResponse("ما في منتجات بقسم {$categoryName} حالياً. جرب قسم ثاني؟");
        }

        $this->conversationManager->setShownProducts($session, $products->toArray());

        if ($products->count() === 1) {
            $this->conversationManager->setLastMentionedProduct($session, $products->first());
        }

        $formatted = $this->productService->formatListForDisplay($products);
        $response = $this->responseGenerator->showCategoryProducts($formatted, $categoryName);

        $images = [];
        $productIds = [];
        foreach ($products as $product) {
            $imageUrl = $this->productService->getPrimaryImageUrl($product);
            if ($imageUrl) { $images[] = $imageUrl; $productIds[] = $product->id; }
        }

        // Always move to WAITING_PRODUCT_SELECTION after showing category products
        $session->conversation_state = ConversationState::WAITING_PRODUCT_SELECTION->value;
        $session->save();

        return $this->buildResponse($response, $images, [], $productIds);
    }

    /**
     * Show ALL products across all categories (for "عددلي كل المنتجات" / "جميع المنتجات")
     * Shows first 15-20 products from all categories with images
     */
    protected function showAllProducts(AiChatSession $session, User $store): array
    {
        $categories = $this->productService->getCategories($store);
        $allProducts = collect();

        // Get products from each category
        foreach ($categories as $category) {
            $products = $this->productService->getByCategory($store, $category->id, 5);
            $allProducts = $allProducts->merge($products);
            if ($allProducts->count() >= 20) break;
        }

        // If no category-based products, get all available products
        if ($allProducts->isEmpty()) {
            $allProducts = $this->productService->search($store, '', 20);
        }

        // Limit to 20
        $allProducts = $allProducts->take(20);

        if ($allProducts->isEmpty()) {
            return $this->buildResponse("ما عدنا منتجات متوفره حالياً 😅\nتقدر ترجع لاحقاً!");
        }

        $this->conversationManager->setShownProducts($session, $allProducts->toArray());

        $formatted = $this->productService->formatListForDisplay($allProducts);
        $response = "هاي كل منتجاتنا المتوفره 🌟\n" . $this->responseGenerator->showProducts($formatted);

        $images = [];
        $productIds = [];
        foreach ($allProducts as $product) {
            $imageUrl = $this->productService->getPrimaryImageUrl($product);
            if ($imageUrl) { $images[] = $imageUrl; $productIds[] = $product->id; }
        }

        $session->conversation_state = ConversationState::WAITING_PRODUCT_SELECTION->value;
        $session->save();

        return $this->buildResponse($response, $images, [], $productIds);
    }

    // ═══════════════════════════════════════════════════════════════════
    // IMAGE & ORDER STATUS HANDLERS
    // ═══════════════════════════════════════════════════════════════════

    protected function handleImageRequest(
        AiChatSession $session,
        User $store,
        array $entities,
        string $message
    ): array {
        $product = $this->resolveProduct($session, $store, $entities, $message);

        if (!$product) {
            $current = $this->conversationManager->getCurrentOrder($session);
            if ($current && !empty($current['order_id'])) {
                $order = OnlineOrder::with('items')->find($current['order_id']);
                if ($order && $order->items->isNotEmpty()) {
                    $firstItem = $order->items->first();
                    if ($firstItem && $firstItem->product_id) {
                        $product = $this->productService->getById($firstItem->product_id);
                    }
                }
            }
        }

        if (!$product) {
            return $this->buildResponse("اي منتج تقصد؟");
        }

        $imageUrl = $this->productService->getPrimaryImageUrl($product);
        if (!$imageUrl) {
            return $this->buildResponse("ماكو صورة لهذا المنتج حالياً.");
        }

        return $this->buildResponse("تفضل صورة {$product->name}", [$imageUrl], [], [$product->id]);
    }

    protected function handleOrderStatusInquiry(
        AiChatSession $session,
        User $store,
        Lead $lead
    ): array {
        // Method 1: Current session order
        $currentOrder = $this->conversationManager->getCurrentOrder($session);
        if ($currentOrder) {
            $order = $this->orderService->getOrder($currentOrder['order_id']);
            if ($order) {
                return $this->buildResponse($this->formatOrderStatusResponse($order));
            }
        }

        // Method 2: By conversation_id
        $recentOrder = OnlineOrder::where('conversation_id', $session->conversation_id)
            ->orderBy('created_at', 'desc')->first();
        if ($recentOrder) {
            return $this->buildResponse($this->formatOrderStatusResponse($recentOrder));
        }

        // Method 3: By lead_id
        $recentOrder = OnlineOrder::where('lead_id', $lead->id)
            ->orderBy('created_at', 'desc')->first();
        if ($recentOrder) {
            return $this->buildResponse($this->formatOrderStatusResponse($recentOrder));
        }

        // Method 4: By phone
        $phone = $lead->phone ?? ($session->customer_data['phone'] ?? null);
        if ($phone) {
            $recentOrder = OnlineOrder::where('customer_phone', $phone)
                ->where('user_id', $store->id)
                ->orderBy('created_at', 'desc')->first();
            if ($recentOrder) {
                return $this->buildResponse($this->formatOrderStatusResponse($recentOrder));
            }
        }

        return $this->buildResponse($this->responseGenerator->template('no_order_found'));
    }

    /**
     * Format order status response using ResponseGenerator
     */
    protected function formatOrderStatusResponse(OnlineOrder $order): string
    {
        if (!$order->relationLoaded('items')) {
            $order->load('items');
        }

        $items = [];
        $calculatedTotal = 0;

        foreach ($order->items as $item) {
            $itemTotal = $item->total ?? $item->subtotal ?? 0;
            if (empty($itemTotal) && !empty($item->unit_price)) {
                $itemTotal = $item->unit_price * $item->quantity;
            }
            if (empty($itemTotal) && $item->product_id) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $itemTotal = (int)$product->price * $item->quantity;
                }
            }

            $items[] = [
                'name' => $item->product_name ?? '',
                'quantity' => $item->quantity ?? 1,
                'total' => (int)$itemTotal,
            ];

            $calculatedTotal += (int)$itemTotal;
        }

        $orderTotal = (int)$order->total;
        if ($orderTotal <= 0 && $calculatedTotal > 0) {
            $orderTotal = $calculatedTotal;
        }

        return $this->responseGenerator->orderStatus(
            $order->id,
            $order->status ?? 'pending',
            $items,
            $orderTotal
        );
    }

    /**
     * Handle FAQ questions
     */
    protected function handleFaq(
        AiChatSession $session,
        User $store,
        array $entities
    ): array {
        $faqTopic = $entities['faq_topic'] ?? null;

        // Get store settings
        $settings = $store->aiSetting ? [
            'delivery_cost' => $store->aiSetting->delivery_cost ?? 5000,
            'delivery_time' => $store->aiSetting->delivery_time ?? 'خلال 24-48 ساعة',
            'working_hours' => $store->aiSetting->working_hours ?? '',
            'store_policies' => $store->aiSetting->store_policies ?? '',
            'store_name' => $store->store_name ?? $store->name ?? 'متجرنا',
        ] : [
            'delivery_cost' => 5000,
            'delivery_time' => 'خلال 24-48 ساعة',
            'working_hours' => '',
            'store_policies' => '',
            'store_name' => 'متجرنا',
        ];

        if ($faqTopic) {
            $response = $this->responseGenerator->answerFaq($faqTopic, $settings);
        } else {
            // FAQ system can't handle it – delegate to AI agent for natural conversation
            try {
                $agentResult = $this->chatAgent->process(
                    $entities['original_message'] ?? 'سؤال عام',
                    $store,
                    $session
                );
                if ($agentResult && !empty($agentResult['reply'])) {
                    Log::info('GroqChatServiceV3: ChatAgent answered FAQ question', [
                        'session_id' => $session->id,
                        'reply_length' => mb_strlen($agentResult['reply']),
                    ]);
                    return $agentResult;
                }
            } catch (\Exception $e) {
                Log::warning('GroqChatServiceV3: ChatAgent FAQ fallback failed', ['error' => $e->getMessage()]);
            }
            $response = "عذراً، ما فهمت سؤالك. ممكن توضح اكثر؟ 🌟";
        }

        return $this->buildResponse($response);
    }

    // ═══════════════════════════════════════════════════════════════════
    // PRICE INQUIRY HANDLER
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Handle ASK_PRICE from ANY state - search for product and show price
     * Fixes bug where "شنو سعر القميص" in CART_REVIEW showed cart instead of searching
     */
    protected function handleAskPrice(
        AiChatSession $session,
        User $store,
        array $entities,
        string $message
    ): array {
        // IMPROVEMENT: Check session context for last mentioned product first
        $lastMentioned = $this->conversationManager->getLastMentionedProduct($session);
        $productName = $entities['product_name'] ?? $this->extractProductFromMessage($message);

        // If message is vague and we have a recent product context, use it
        $messageLen = mb_strlen(trim($message));
        if ($lastMentioned && $messageLen <= 15 && !$productName) {
            $product = $this->productService->getById($lastMentioned['id']);
            if ($product) {
                $price = number_format((int)$product->price);
                $response = "{$product->name} سعره {$price} د.ع\nتبي تضيفه للسلة؟";

                $images = [];
                $productIds = [];
                $img = $this->productService->getPrimaryImageUrl($product);
                if ($img) { $images[] = $img; $productIds[] = $product->id; }

                return $this->buildResponse($response, $images, [], $productIds);
            }
        }

        if ($productName) {
            // Search for the product in database
            $product = $this->productService->findBestMatch($store, $productName);

            if ($product) {
                $this->conversationManager->setLastMentionedProduct($session, $product);
                $formatted = $this->productService->formatListForDisplay(collect([$product]));
                $response = $this->responseGenerator->showProductDetail($formatted[0] ?? [
                    'name' => $product->name,
                    'price' => (int)$product->price,
                    'stock' => (int)$product->quantity,
                ]);

                $images = [];
                $productIds = [];
                $img = $this->productService->getPrimaryImageUrl($product);
                if ($img) { $images[] = $img; $productIds[] = $product->id; }

                return $this->buildResponse($response, $images, [], $productIds);
            }

            // Product not found
            $categories = $this->productService->getCategories($store);
            $response = $this->responseGenerator->productNotFound(
                $productName,
                $categories->toArray(),
                []
            );
            return $this->buildResponse($response);
        }

        // No product name in question - check last mentioned product
        if ($lastMentioned && isset($lastMentioned['id'])) {
            $product = $this->productService->getById($lastMentioned['id']);
            if ($product) {
                $price = number_format((int)$product->price);
                $response = "{$product->name} سعره {$price} د.ع\nتبي تضيفه للسلة؟";

                $images = [];
                $productIds = [];
                $img = $this->productService->getPrimaryImageUrl($product);
                if ($img) { $images[] = $img; $productIds[] = $product->id; }

                return $this->buildResponse($response, $images, [], $productIds);
            }
        }

        return $this->buildResponse("اي منتج تقصد؟ اكتب اسم المنتج حتى اخبرك بسعره.");
    }

    // ═══════════════════════════════════════════════════════════════════
    // MULTI-ITEM ORDER HANDLING
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Check if message contains multiple product requests
     * Examples: "اريد قميص احمر واريد فرن بيتزا", "قميصين و فرن"
     */
    protected function isMultiItemMessage(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));
        $normalized = str_replace(['أ', 'إ', 'آ'], 'ا', $normalized);

        // Check for connectors that suggest multiple items
        // "واريد", "و اريد", "وابي", "و ابي", or simply "و" between product-looking words
        if (preg_match('/\s+و\s*(?:اريد|ابي|ابغي|بدي|كمان|هم)\s+/u', $normalized)) {
            return true;
        }

        // Multiple "اريد" occurrences
        if (substr_count($normalized, 'اريد') >= 2 || substr_count($normalized, 'ابي') >= 2) {
            return true;
        }

        // Comma-separated items
        if (mb_strpos($normalized, '،') !== false && preg_match('/\p{Arabic}{3,}/u', $normalized)) {
            return true;
        }

        return false;
    }

    /**
     * Handle multi-item add to cart
     * Split message by connectors and add each item separately
     */
    protected function handleMultiItemAdd(
        AiChatSession $session,
        User $store,
        array $entities,
        string $message
    ): array {
        // Split by "و" connector, "واريد", "،" etc.
        $parts = preg_split('/\s*(?:و\s*(?:اريد|ابي|ابغي|بدي|كمان|هم)?\s*|،\s*)/u', $message);

        $addedItems = [];
        $notFoundItems = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part) || mb_strlen($part) < 2) continue;

            // Extract entities from this part
            $partEntities = $this->intentAnalyzer->extractEntities($part, $store);
            $productName = $partEntities['product_name'] ?? $this->extractProductFromMessage($part);
            $quantity = $partEntities['quantity'] ?? 1;
            $color = $partEntities['color'] ?? $entities['color'] ?? null;
            $size = $partEntities['size'] ?? $entities['size'] ?? null;

            if (!$productName || mb_strlen($productName) < 2) continue;

            // Check if it's a category name
            $category = $this->productService->findCategory($store, $productName);
            if ($category) {
                // Skip categories in multi-item add - we're looking for products
                continue;
            }

            // Search for the product
            $product = $this->productService->findBestMatch($store, $productName);

            if ($product) {
                $attributes = [];
                if ($color) $attributes['color'] = $color;
                if ($size) $attributes['size'] = $size;

                $result = $this->cartService->addItem($session, $product, $quantity, $attributes);
                if ($result['success']) {
                    $addedItems[] = ['name' => $product->name, 'quantity' => $quantity];
                    $this->conversationManager->setLastMentionedProduct($session, $product);
                }
            } else {
                $notFoundItems[] = $productName;
            }
        }

        // Generate response
        if (empty($addedItems) && empty($notFoundItems)) {
            return $this->buildResponse("ما فهمت شنو تريد تطلب بالضبط. ممكن توضح اسماء المنتجات؟");
        }

        $session->conversation_state = ConversationState::CART_REVIEW->value;
        $session->save();

        $cart = $this->cartService->getFormattedCart($session);
        $deliveryCost = $store->aiSetting->delivery_cost ?? 5000;

        $lines = [];

        if (!empty($addedItems)) {
            $lines[] = "تم اضافة المنتجات للسلة ✅";
        }

        if (!empty($notFoundItems)) {
            $lines[] = "ما لكيت: " . implode('، ', $notFoundItems) . " 😔";
        }

        if (!empty($cart['items'])) {
            $lines[] = "━━━━━━━━━━━━━━━";
            foreach ($cart['items'] as $item) {
                $name = $item['name'] ?? '';
                $qty = $item['quantity'] ?? 1;
                $price = $item['price'] ?? 0;
                $subtotal = $price * $qty;

                if ($qty > 1) {
                    $lines[] = "{$name}: {$qty} قطعة (" . number_format($price) . " × {$qty} = " . number_format($subtotal) . " د.ع)";
                } else {
                    $lines[] = "{$name}: {$qty} قطعة (" . number_format($price) . " د.ع)";
                }
            }
            $grandTotal = $cart['total'] + $deliveryCost;
            $lines[] = "━━━━━━━━━━━━━━━";
            $lines[] = "سعر التوصيل: " . number_format($deliveryCost) . " د.ع";
            $lines[] = "المجموع الكلي: " . number_format($grandTotal) . " د.ع";
            $lines[] = "━━━━━━━━━━━━━━━";
            $lines[] = "تبي تضيف منتجات اخرى او تأكد الطلب؟";
        }

        return $this->buildResponse(implode("\n", $lines));
    }

    // ═══════════════════════════════════════════════════════════════════
    // PRODUCT RESOLUTION
    // ═══════════════════════════════════════════════════════════════════

    protected function resolveProduct(
        AiChatSession $session,
        User $store,
        array $entities,
        string $message
    ): ?Product {
        // IMPROVEMENT: Check session context for recently mentioned product first
        $lastMentioned = $this->conversationManager->getLastMentionedProduct($session);
        $messageNorm = mb_strtolower(trim($message));
        $messageNorm = str_replace(['أ', 'إ', 'آ'], 'ا', $messageNorm);
        $messageNorm = str_replace('ة', 'ه', $messageNorm);
        $messageLen = mb_strlen($messageNorm);

        // If message is vague (short) and we have a recent product context, try to match with it
        if ($lastMentioned && $messageLen <= 20 && empty($entities['product_name'])) {
            $lastProdNorm = mb_strtolower($lastMentioned['name']);
            $lastProdNorm = str_replace(['أ', 'إ', 'آ'], 'ا', $lastProdNorm);
            $lastProdNorm = str_replace('ة', 'ه', $lastProdNorm);

            $msgWords = array_filter(preg_split('/\s+/u', $messageNorm), fn($w) => mb_strlen($w) > 1);
            $prodWords = preg_split('/\s+/u', $lastProdNorm);

            $allMatch = true;
            foreach ($msgWords as $word) {
                $found = false;
                foreach ($prodWords as $pWord) {
                    if (mb_stripos($pWord, $word) !== false || mb_stripos($word, $pWord) !== false) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $allMatch = false;
                    break;
                }
            }

            if ($allMatch && !empty($msgWords)) {
                $product = $this->productService->getById($lastMentioned['id']);
                if ($product) {
                    Log::debug('GroqChatServiceV3: resolveProduct - matched via session context', [
                        'message' => $message,
                        'matched_product' => $product->name,
                    ]);
                    return $product;
                }
            }
        }

        // 1. Number selection from shown products
        $selectionNum = $entities['selection_number'] ?? null;
        if ($selectionNum !== null) {
            $shownProducts = $this->conversationManager->getShownProducts($session);
            if (!empty($shownProducts) && isset($shownProducts[$selectionNum - 1])) {
                return $this->productService->getById($shownProducts[$selectionNum - 1]['id']);
            }
        }

        // 2. Product name entity → search database for exact/close match
        $hasExplicitProductName = !empty($entities['product_name']);
        if ($hasExplicitProductName) {
            $product = $this->productService->findBestMatch($store, $entities['product_name']);
            if ($product) return $product;
        }

        // 3. If user provided an explicit product name but DB search found nothing,
        //    do NOT fall back to last mentioned or shown products.
        //    This prevents "اريد قميص احمر" from adding "طقم ادوات منزلية" (the last shown product).
        if ($hasExplicitProductName) {
            Log::info('GroqChatServiceV3: resolveProduct - explicit product name not found, no fallback', [
                'product_name' => $entities['product_name'],
            ]);
            return null;
        }

        // 4. Last mentioned product (only when no explicit product name given)
        if ($lastMentioned && isset($lastMentioned['id'])) {
            return $this->productService->getById($lastMentioned['id']);
        }

        // 5. Shown products (first if "نعم" or single product)
        $shownProducts = $this->conversationManager->getShownProducts($session);
        if (!empty($shownProducts)) {
            if (count($shownProducts) === 1 || preg_match('/^(نعم|اي|تمام)$/u', $message)) {
                return $this->productService->getById($shownProducts[0]['id']);
            }

            foreach ($shownProducts as $shown) {
                if (mb_stripos($message, $shown['name']) !== false) {
                    return $this->productService->getById($shown['id']);
                }
            }
        }

        // 6. Fallback: search by full message
        return $this->productService->findBestMatch($store, $message);
    }

    // ═══════════════════════════════════════════════════════════════════
    // CUSTOMER INFO EXTRACTION
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Extract customer info from entities and message
     * Handles multi-line messages: name / phone / address
     */
    protected function extractCustomerInfo(array $entities, string $message): array
    {
        $info = [];

        // From entities
        if (!empty($entities['customer_name'])) $info['name'] = $entities['customer_name'];
        if (!empty($entities['customer_phone'])) $info['phone'] = $entities['customer_phone'];
        if (!empty($entities['customer_address'])) $info['address'] = $entities['customer_address'];

        $cities = [
            'بغداد', 'البصره', 'البصرة', 'الموصل', 'اربيل', 'النجف', 'كربلاء',
            'السليمانيه', 'كركوك', 'الناصريه', 'ديالى', 'الزبير', 'العماره',
            'الكوت', 'واسط', 'ميسان', 'ذي قار', 'المثنى', 'السماوه', 'الديوانيه',
            'بابل', 'الحله', 'صلاح الدين', 'تكريت', 'الانبار', 'الرمادي', 'دهوك',
            'السليمانية', 'الكاظميه', 'المنصور', 'الكراده', 'زيونه', 'الاعظميه',
        ];

        $excludedWords = [
            // Yes/No/Confirm
            'لا', 'نعم', 'اي', 'تمام', 'خلاص', 'بس', 'كافي', 'اوك', 'ok', 'موافق',
            'اكيد', 'طيب', 'حسنا', 'زين', 'ماشي', 'لا شكرا', 'ما اريد', 'لا اريد',
            // Greetings
            'السلام عليكم', 'سلام', 'مرحبا', 'هلا', 'اهلا', 'هاي', 'هلو',
            'صباح الخير', 'مساء الخير', 'شلونك', 'كيفك',
            // Commands & Order confirmation
            'شكرا', 'مشكور', 'الغي', 'كانسل', 'cancel', 'اريد', 'ابي', 'اطلب',
            'اشوف', 'وريني', 'حط بالسله', 'اضيفه', 'ضيفه', 'شيل', 'احذف', 'امسح',
            'اكد', 'تأكد', 'تاكد', 'اكدلي', 'تأكدلي', 'تاكدلي', 'الطلي', 'الطلب',
            'اكدلي الطلي', 'اكدلي الطلب', 'تأكدلي الطلي', 'تاكدلي الطلب', 'اكد الطلي',
            // Product/browse words (should NOT be stored as name)
            'قميص', 'بنطلون', 'منتج', 'صوره', 'صورة', 'سعر', 'بكم', 'كم', 'شنو',
            'المنتجات', 'كتلوج', 'القائمه', 'متوفر', 'عندكم', 'عدكم', 'موجود',
            'اريد اشوف', 'وريني المنتجات', 'اريد اشتري', 'ابي اشتري',
            'تبي تضيف', 'شنو تبيعون', 'منتجاتكم', 'كهربائيات', 'ملابس',
        ];

        // Split by lines
        $lines = preg_split('/[\n\r]+/', trim($message));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Phone (strict Iraqi format: 07X-YYYYYYYY = 11 digits)
            if (empty($info['phone']) && preg_match('/\b(07[3-9]\d{8})\b/', $line, $m)) {
                $info['phone'] = $m[1];
                continue;
            }

            // Explicit name
            if (empty($info['name']) && preg_match('/(?:اسمي|الاسم)[:\s]+(.+)/u', $line, $m)) {
                $extractedName = trim($m[1]);
                // Strip Arabic copula/filler words (هوه/هو/هي/هيه = is/are)
                $extractedName = preg_replace('/^(?:هوه|هو|هي|هيه)\s+/u', '', $extractedName);
                $info['name'] = $extractedName;
                continue;
            }

            // Explicit address
            if (empty($info['address']) && preg_match('/(?:عنواني|العنوان|منطقه|منطقة|حي)[:\s]+(.+)/u', $line, $m)) {
                $info['address'] = trim($m[1]);
                continue;
            }

            // Explicit phone
            if (empty($info['phone']) && preg_match('/(?:رقمي|الرقم|هاتفي|تلفوني)[:\s]+(.+)/u', $line, $m)) {
                $phoneCandidate = trim($m[1]);
                if (preg_match('/\b(07[3-9]\d{8})\b/', $phoneCandidate, $phoneMatch)) {
                    $info['phone'] = $phoneMatch[1];
                    continue;
                }
            }

            // City names → address
            if (empty($info['address'])) {
                foreach ($cities as $city) {
                    if (mb_stripos($line, $city) !== false) {
                        $info['address'] = $line;
                        $info['city'] = $city;
                        break;
                    }
                }
                if (!empty($info['address'])) continue;
            }
        }

        // Heuristic name detection on unmatched lines
        if (empty($info['name'])) {
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                // Skip phone/address lines
                if (preg_match('/\b07[3-9]\d{8}\b/', $line)) continue;
                $isAddress = false;
                foreach ($cities as $city) {
                    if (mb_stripos($line, $city) !== false) { $isAddress = true; break; }
                }
                if ($isAddress) continue;
                if (preg_match('/(?:عنواني|العنوان|منطقه|منطقة|حي|رقمي|الرقم|هاتفي|تلفوني)/u', $line)) continue;

                $isExcluded = false;
                foreach ($excludedWords as $word) {
                    if (mb_strtolower($line) === mb_strtolower($word)) { $isExcluded = true; break; }
                }

                if (!$isExcluded && mb_strlen($line) >= 2 && mb_strlen($line) < 30 && preg_match('/^[\p{Arabic}\s]+$/u', $line)) {
                    $info['name'] = $line;
                    break;
                }
            }
        }

        // Single-line fallback → name
        if (empty($info['name']) && count($lines) === 1 && empty($info['phone']) && empty($info['address'])) {
            $cleaned = trim($message);
            $isExcluded = false;
            foreach ($excludedWords as $word) {
                if (mb_strtolower($cleaned) === mb_strtolower($word)) { $isExcluded = true; break; }
            }
            if (!$isExcluded && mb_strlen($cleaned) >= 2 && mb_strlen($cleaned) < 30 && preg_match('/^[\p{Arabic}\s]+$/u', $cleaned)) {
                $info['name'] = $cleaned;
            }
        }

        return $info;
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════

    protected function extractProductFromMessage(string $message): ?string
    {
        $msg = mb_strtolower(trim($message));
        $msg = str_replace(['أ', 'إ', 'آ'], 'ا', $msg);
        $msg = str_replace('ة', 'ه', $msg);
        $msg = str_replace('ى', 'ي', $msg);
        $msg = str_replace(['؟', '?'], '', $msg);

        $stripWords = [
            'اريد', 'ابي', 'ابغى', 'اشوف', 'شنو', 'بدي',
            'عندكم', 'عدكم', 'متوفر', 'موجود', 'في',
            'هل', 'شلون', 'وريني', 'اي', 'منتج',
            'تبيعون', 'تبيع',
            'من', 'على', 'مع', 'لو', 'بس', 'يا', 'بال', 'بقسم',
            'سمحت', 'ممكن', 'اكو', 'ماكو', 'طيب', 'حسنا', 'ماشي',
            // Price/inquiry words - CRITICAL: must strip these for product name extraction
            'شكد', 'سعر', 'بكم', 'كم', 'السعر', 'كلش',
            'صوره', 'صور', 'اضف', 'حط', 'ضيف', 'زيد',
            'ابي', 'ابغي', 'تبي',
            // Cart-action words - must strip so "اضيفه للسله" / "حط بالسله" don't produce false product names
            'اضيفه', 'ضيفه', 'للسله', 'بالسله', 'سلتي', 'السله', 'الكارت',
            'اكد', 'اكيد', 'الطلب', 'طلبي',
            // Quantity words
            'قطعتين', 'قطعات', 'قطع', 'قطعه',
            'واحد', 'وحده', 'اثنين', 'ثنين', 'زوج',
            'ثلاثه', 'ثلاث', 'اربعه', 'اربع', 'خمسه', 'خمس',
            'سته', 'ست', 'سبعه', 'سبع', 'ثمانيه', 'ثمان',
            'تسعه', 'تسع', 'عشره', 'عشر',
        ];

        foreach ($stripWords as $word) {
            $msg = preg_replace('/\b' . preg_quote($word, '/') . '\b/u', '', $msg);
        }

        $msg = preg_replace('/\s+/u', ' ', trim($msg));

        if (mb_strlen($msg) >= 2) {
            return $msg;
        }

        return null;
    }

    protected function checkRateLimit(int $leadId): bool
    {
        return RateLimiter::attempt(
            "chat_rate_limit:{$leadId}",
            self::RATE_LIMIT,
            fn() => true,
            60
        );
    }

    protected function buildResponse(
        string $reply,
        array $images = [],
        array $actions = [],
        array $productsToShow = []
    ): array {
        return [
            'reply' => $reply,
            'images' => $images,
            'actions' => $actions,
            'products_to_show' => $productsToShow,
        ];
    }

    // ── Public service accessors ──

    public function getCartService(): CartService { return $this->cartService; }
    public function getOrderService(): OrderService { return $this->orderService; }
    public function getStateMachine(): StateMachine { return $this->stateMachine; }
    public function getConversationManager(): ConversationManager { return $this->conversationManager; }
}
