<?php

namespace App\Services\AI;

use App\Enums\ConversationState;
use App\Enums\Intent;
use App\Models\User;
use App\Services\AiProviderService;
use App\Services\Orders\ProductService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Intent Analyzer V2 - Smarter Iraqi Arabic Intent Detection
 *
 * REWRITTEN:
 * ✅ Better separation: general browse ("شنو عدكم") vs category browse ("شنو عدكم بالكهربائيات")
 * ✅ State-aware: "نعم" means different things in different states
 * ✅ "لا" = decline more, NEVER cancel. Only "الغي الطلب" = cancel.
 * ✅ Number selection from product list (#1, #2, etc.)
 * ✅ Multi-line customer info extraction
 * ✅ Improved product name extraction (doesn't catch category keywords)
 * ✅ Better Arabic number words → digits
 */
class IntentAnalyzer
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    // ─── KEYWORD PATTERNS (ordered by priority) ────────────────────

    /**
     * HIGH-PRIORITY patterns matched first.
     * Order matters: cancel before decline, confirm before browse, etc.
     */
    protected const KEYWORD_PATTERNS = [
        // ── GREETINGS ──
        '/^(السلام|سلام|مرحبا|هلا|اهلا|هاي|هلو|هالو|الو)/u' => Intent::GREETING,
        '/^(كيفك|شلونك|شخبارك|كيف الحال|صباح الخير|مساء الخير|مساء النور)/u' => Intent::GREETING,
        '/^منو\s*انا/u' => Intent::GREETING,

        // ── EXPLICIT CANCEL (only these!) ──
        '/الغي الطلب/u' => Intent::CANCEL_ORDER,
        '/الغي كلشي/u' => Intent::CANCEL_ORDER,
        '/كانسل/u' => Intent::CANCEL_ORDER,
        '/cancel/i' => Intent::CANCEL_ORDER,

        // ── ORDER STATUS ──
        '/وين طلبي/u' => Intent::ORDER_STATUS,
        '/حالة الطلب/u' => Intent::ORDER_STATUS,
        '/حالة طلبي/u' => Intent::ORDER_STATUS,
        '/طلبيتي/u' => Intent::ORDER_STATUS,
        '/متى يوصل/u' => Intent::ORDER_STATUS,
        '/وصل الطلب/u' => Intent::ORDER_STATUS,
        '/شنو صار بطلبي/u' => Intent::ORDER_STATUS,
        '/تتبع الطلب/u' => Intent::ORDER_STATUS,

        // ── DECLINE MORE (NOT cancel!) ──
        '/^لا$/u' => Intent::DECLINE_MORE,
        '/^لا شكرا/u' => Intent::DECLINE_MORE,
        '/^ما اريد/u' => Intent::DECLINE_MORE,
        '/لا اريد شي/u' => Intent::DECLINE_MORE,
        '/^بس هذا/u' => Intent::DECLINE_MORE,
        '/^خلاص$/u' => Intent::DECLINE_MORE,
        '/^كافي/u' => Intent::DECLINE_MORE,
        '/^بس$/u' => Intent::DECLINE_MORE,
        '/^لا بس$/u' => Intent::DECLINE_MORE,

        // ── CONFIRM ──
        '/^(نعم|اي|تمام|اكيد|اوك|ok|موافق|زين|ماشي)$/ui' => Intent::CONFIRM_ORDER,

        // ── WANT MORE / ADD ──
        '/اريد هم/u' => Intent::WANT_MORE,
        '/اريد اضيف/u' => Intent::WANT_MORE,
        '/^زيد/u' => Intent::WANT_MORE,
        '/^اضف/u' => Intent::ADD_TO_CART,
        '/حط بالسله/u' => Intent::ADD_TO_CART,
        '/اضيفه/u' => Intent::ADD_TO_CART,
        '/ضيفه/u' => Intent::ADD_TO_CART,
        '/ارضيف/u' => Intent::ADD_TO_CART,
        '/ارضف/u' => Intent::ADD_TO_CART,
        '/نضيف/u' => Intent::ADD_TO_CART,
        '/^اريد\s+(?!اشوف|هم|اضيف|صوره|صور|بديل|بدائل|اعرف|ان اعرف|ان)/u' => Intent::ADD_TO_CART,
        '/^ابي\s+(?!اشوف|صوره|صور|بديل|بدائل|اعرف)/u' => Intent::ADD_TO_CART,
        '/^ابغي\s+/u' => Intent::ADD_TO_CART,
        '/^بدي\s+/u' => Intent::ADD_TO_CART,

        // ── UPDATE QUANTITY ──
        '/سويهم/u' => Intent::UPDATE_QUANTITY,
        '/سويها/u' => Intent::UPDATE_QUANTITY,
        '/خليها/u' => Intent::UPDATE_QUANTITY,
        '/خليهم/u' => Intent::UPDATE_QUANTITY,
        '/غير الكميه/u' => Intent::UPDATE_QUANTITY,
        '/عدل الكميه/u' => Intent::UPDATE_QUANTITY,
        '/بدل الكميه/u' => Intent::UPDATE_QUANTITY,

        // ── REMOVE FROM CART ──
        '/شيل/u' => Intent::REMOVE_FROM_CART,
        '/احذف/u' => Intent::REMOVE_FROM_CART,
        '/امسح/u' => Intent::REMOVE_FROM_CART,
        '/فرغ/u' => Intent::REMOVE_FROM_CART,
        '/فضي.*سل/u' => Intent::REMOVE_FROM_CART,
        '/نظف.*سل/u' => Intent::REMOVE_FROM_CART,
        '/حذف من السله/u' => Intent::REMOVE_FROM_CART,
        '/شيل من السله/u' => Intent::REMOVE_FROM_CART,
        '/ما اريده/u' => Intent::REMOVE_FROM_CART,

        // ── PRICE INQUIRY ──
        '/بكم/u' => Intent::ASK_PRICE,
        '/شكد السعر/u' => Intent::ASK_PRICE,
        '/شكد سعر/u' => Intent::ASK_PRICE,
        '/كم سعر/u' => Intent::ASK_PRICE,
        '/شنو سعر/u' => Intent::ASK_PRICE,
        '/ما سعر/u' => Intent::ASK_PRICE,
        '/ما هو سعر/u' => Intent::ASK_PRICE,
        '/ماهو سعر/u' => Intent::ASK_PRICE,
        '/اشلون سعر/u' => Intent::ASK_PRICE,
        '/شلون سعر/u' => Intent::ASK_PRICE,
        '/كمو سعر/u' => Intent::ASK_PRICE,
        '/بقديش سعر/u' => Intent::ASK_PRICE,
        '/سعره؟/u' => Intent::ASK_PRICE,

        // ── IMAGE REQUEST ──
        '/صوره/u' => Intent::REQUEST_IMAGE,
        '/وريني/u' => Intent::REQUEST_IMAGE,
        '/شوفني/u' => Intent::REQUEST_IMAGE,

        // ── FAQ / STORE QUESTIONS ──
        '/شكد سعر التوصيل/u' => Intent::ASK_QUESTION,
        '/شكد التوصيل/u' => Intent::ASK_QUESTION,
        '/كم التوصيل/u' => Intent::ASK_QUESTION,
        '/سعر التوصيل/u' => Intent::ASK_QUESTION,
        '/اجور التوصيل/u' => Intent::ASK_QUESTION,
        '/اجور الشحن/u' => Intent::ASK_QUESTION,
        '/توصلون/u' => Intent::ASK_QUESTION,
        '/سياسه الاسترجاع/u' => Intent::ASK_QUESTION,
        '/سياسة الاسترجاع/u' => Intent::ASK_QUESTION,
        '/الاسترجاع/u' => Intent::ASK_QUESTION,
        '/الاستبدال/u' => Intent::ASK_QUESTION,
        '/سياسه الاستبدال/u' => Intent::ASK_QUESTION,
        '/طرق الدفع/u' => Intent::ASK_QUESTION,
        '/طريقه الدفع/u' => Intent::ASK_QUESTION,
        '/كيف ادفع/u' => Intent::ASK_QUESTION,
        '/ساعات العمل/u' => Intent::ASK_QUESTION,
        '/متى مفتوحين/u' => Intent::ASK_QUESTION,
        '/اوقات العمل/u' => Intent::ASK_QUESTION,
        '/متى يوصل/u' => Intent::ASK_QUESTION,
        '/وقت التوصيل/u' => Intent::ASK_QUESTION,
        '/متى يصلني/u' => Intent::ASK_QUESTION,
        '/وين المحل/u' => Intent::ASK_QUESTION,
        '/العنوان/u' => Intent::ASK_QUESTION,
        '/الموقع/u' => Intent::ASK_QUESTION,
        '/شنو سياسه/u' => Intent::ASK_QUESTION,

        // ── BROWSE PRODUCTS ──
        '/شنو عندكم/u' => Intent::BROWSE_PRODUCTS,
        '/شنو عدكم/u' => Intent::BROWSE_PRODUCTS,
        '/شنو موجود/u' => Intent::BROWSE_PRODUCTS,
        '/شنو متوفر/u' => Intent::BROWSE_PRODUCTS,
        '/عدكم/u' => Intent::BROWSE_PRODUCTS,
        '/عددلي/u' => Intent::BROWSE_PRODUCTS,
        '/اعرضلي/u' => Intent::BROWSE_PRODUCTS,
        '/شوفلي/u' => Intent::BROWSE_PRODUCTS,
        '/وريني/u' => Intent::BROWSE_PRODUCTS,
        '/شوفني/u' => Intent::BROWSE_PRODUCTS,
        '/المنتجات/u' => Intent::BROWSE_PRODUCTS,
        '/اريد اشوف/u' => Intent::BROWSE_PRODUCTS,
        '/ايش عندكم/u' => Intent::BROWSE_PRODUCTS,
        '/شنو الاقسام/u' => Intent::BROWSE_PRODUCTS,
        '/كتلوج/u' => Intent::BROWSE_PRODUCTS,
        '/القائمه/u' => Intent::BROWSE_PRODUCTS,
        '/منتجاتكم/u' => Intent::BROWSE_PRODUCTS,
        '/شنو تبيعون/u' => Intent::BROWSE_PRODUCTS,
        '/متوفر عندكم/u' => Intent::BROWSE_PRODUCTS,
        '/متوفر عدكم/u' => Intent::BROWSE_PRODUCTS,
        '/كل المنتجات/u' => Intent::BROWSE_PRODUCTS,
        '/جميع المنتجات/u' => Intent::BROWSE_PRODUCTS,
        '/اريد ان اعرف.*المنتجات/u' => Intent::BROWSE_PRODUCTS,
        '/ماهي المنتجات/u' => Intent::BROWSE_PRODUCTS,
        '/شنو المنتجات.*توفر/u' => Intent::BROWSE_PRODUCTS,
        '/المنتجات.*توفر/u' => Intent::BROWSE_PRODUCTS,
        '/المنتجات.*متاح/u' => Intent::BROWSE_PRODUCTS,
        '/شنو القسم/u' => Intent::BROWSE_PRODUCTS,
        // ── CATEGORY-SPECIFIC browse patterns (must be before bare availability) ──
        '/(?:اريد|ابي|ابغي|بدي)\s+(?:منتج(?:ات)?\s+)?(?:خاص|خاصه|تخص|تتعلق|يتعلق|من)\s*(?:ب|بال|في|من)/u' => Intent::BROWSE_PRODUCTS,
        '/^(?:قسم|اقسام|فئه|صنف)\s+/u' => Intent::BROWSE_PRODUCTS,
        // Bare availability + product: "متوفر قميص؟", "موجود حزام؟", "اكو جاكيت؟"
        '/^متوفر\s+\S+/u' => Intent::BROWSE_PRODUCTS,
        '/^موجود\s+\S+/u' => Intent::BROWSE_PRODUCTS,
        '/^اكو\s+\S+/u'   => Intent::BROWSE_PRODUCTS,
        '/^في\s+\S+.{1}/u' => Intent::BROWSE_PRODUCTS,
        // "قميص متوفر؟", "حزام موجود؟"
        '/\S+\s+متوفر\s*[؟?]?\s*$/u' => Intent::BROWSE_PRODUCTS,
        '/\S+\s+موجود\s*[؟?]?\s*$/u'  => Intent::BROWSE_PRODUCTS,
        // "هل متوفر قميص", "هل عندكم قمصان"
        '/^هل\s+(?:متوفر|موجود|عندكم|عدكم|اكو)/u' => Intent::BROWSE_PRODUCTS,
        // "تبيعون قمصان", "عندكم ملابس رجالية"
        '/^(?:تبيعون|تبيع|عندكم|عدكم)\s+\S+/u' => Intent::BROWSE_PRODUCTS,
    ];

    /**
     * Arabic number words to digits
     */
    protected const ARABIC_NUMBERS = [
        'واحد' => 1, 'وحده' => 1, 'وحدة' => 1, 'قطعه' => 1,
        'اثنين' => 2, 'ثنين' => 2, 'قطعتين' => 2, 'زوج' => 2,
        'ثلاثه' => 3, 'ثلاث' => 3,
        'اربعه' => 4, 'اربع' => 4,
        'خمسه' => 5, 'خمس' => 5,
        'سته' => 6, 'ست' => 6,
        'سبعه' => 7, 'سبع' => 7,
        'ثمانيه' => 8, 'ثمان' => 8,
        'تسعه' => 9, 'تسع' => 9,
        'عشره' => 10, 'عشر' => 10,
    ];

    /**
     * Iraqi city names for address detection
     */
    protected const IRAQI_CITIES = [
        'بغداد', 'البصره', 'البصرة', 'الموصل', 'اربيل', 'النجف', 'كربلاء',
        'السليمانيه', 'السليمانية', 'كركوك', 'الناصريه', 'ديالى', 'الزبير',
        'العماره', 'الكوت', 'واسط', 'ميسان', 'ذي قار', 'المثنى', 'السماوه',
        'الديوانيه', 'بابل', 'الحله', 'صلاح الدين', 'تكريت', 'الانبار',
        'الرمادي', 'دهوك', 'الكاظميه', 'المنصور', 'الكراده', 'زيونه',
        'الاعظميه', 'الشعله', 'الحريه', 'الجاهريه', 'الدوره', 'المعقل',
    ];

    // ─── MAIN ANALYSIS ────────────────────────────────────────

    /**
     * Analyze customer message and return structured intent
     */
    public function analyze(
        string $message,
        ConversationState $state,
        array $context = [],
        ?User $user = null
    ): array {
        $normalized = $this->normalizeMessage($message);

        // STEP 1: State-priority override (WAITING_CUSTOMER_INFO always checks info first)
        if ($state === ConversationState::WAITING_CUSTOMER_INFO) {
            if ($this->containsCustomerInfo($normalized)) {
                return [
                    'intent' => Intent::PROVIDE_INFO,
                    'entities' => $this->extractEntities($normalized, $user),
                    'confidence' => 0.95,
                ];
            }
            // Allow explicit greetings to pass through (handled by GroqChatServiceV3)
            if (preg_match('/^(السلام|سلام|مرحبا|هلا|اهلا|هاي|هلو|هالو|الو|كيفك|شلونك|شخبارك|صباح الخير|مساء الخير)/u', $normalized)) {
                return [
                    'intent' => Intent::GREETING,
                    'entities' => $this->extractEntities($normalized, $user),
                    'confidence' => 0.90,
                ];
            }

            // Even non-matching text in info state is likely a name
            if (!$this->isExplicitCancel($normalized) && !$this->isOrderStatusQuery($normalized)) {
                return [
                    'intent' => Intent::PROVIDE_INFO,
                    'entities' => $this->extractEntities($normalized, $user),
                    'confidence' => 0.80,
                ];
            }
        }

        // STEP 2: Number selection from product list (e.g., "1", "2", "#3")
        if ($this->isNumberSelection($normalized, $state)) {
            $num = $this->extractNumberSelection($normalized);
            return [
                'intent' => Intent::SELECT_PRODUCT,
                'entities' => array_merge($this->extractEntities($normalized, $user), ['selection_number' => $num]),
                'confidence' => 0.95,
            ];
        }

        // STEP 3: Keyword matching (fast, no AI needed)
        $keywordResult = $this->matchKeywords($normalized, $state, $user);
        if ($keywordResult !== null) {
            Log::debug('IntentAnalyzer: Keyword match', [
                'message' => $normalized,
                'intent' => $keywordResult['intent']->value,
            ]);
            return $keywordResult;
        }

        // STEP 4: Cache check
        $cacheKey = $this->getCacheKey($normalized, $state);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::debug('IntentAnalyzer: Cache hit', ['message' => $normalized]);
            return $cached;
        }

        // STEP 5: AI fallback for complex messages
        if ($user) {
            $result = $this->analyzeWithAI($normalized, $state, $context, $user);
            Cache::put($cacheKey, $result, 3600);
            return $result;
        }

        // STEP 6: No AI available - best-effort
        return [
            'intent' => Intent::UNKNOWN,
            'entities' => $this->extractEntities($normalized, $user),
            'confidence' => 0.3,
        ];
    }

    // ─── NORMALIZATION ────────────────────────────────────────

    protected function normalizeMessage(string $message): string
    {
        $message = trim($message);

        // Normalize Arabic characters
        $message = str_replace(['أ', 'إ', 'آ'], 'ا', $message);
        $message = str_replace('ة', 'ه', $message);
        $message = str_replace('ى', 'ي', $message);

        // Remove diacritics (tashkeel)
        $message = preg_replace('/[\x{064B}-\x{065F}]/u', '', $message);

        return $message;
    }

    // ─── NUMBER SELECTION ─────────────────────────────────────

    /**
     * Check if message is a number selection from a product list
     * Works in WAITING_PRODUCT_SELECTION and BROWSING states
     */
    protected function isNumberSelection(string $message, ConversationState $state): bool
    {
        if (!in_array($state, [
            ConversationState::WAITING_PRODUCT_SELECTION,
            ConversationState::BROWSING_PRODUCTS,
        ])) {
            return false;
        }

        return preg_match('/^#?\d{1,2}$/u', trim($message)) === 1;
    }

    protected function extractNumberSelection(string $message): int
    {
        preg_match('/(\d+)/', $message, $m);
        return (int)($m[1] ?? 1);
    }

    // ─── KEYWORD MATCHING ─────────────────────────────────────

    /**
     * Conversational messages that should bypass keyword matching and go to the AI Agent.
     * These are phrases that signal negotiation, explanation requests, opinions, or discussion.
     */
    protected const CONVERSATIONAL_PATTERNS = [
        // Price complaints / negotiation
        '/غالي/u',
        '/كلش غالي/u',
        '/مكلف/u',
        '/غير معقول/u',
        '/هذا كثير/u',
        '/ما يسوى/u',
        '/ارخص/u',
        '/خفض.*سعر/u',
        '/قلل.*سعر/u',
        // Explanation / detail requests
        '/اشرح/u',
        '/فصلي/u',
        '/شنو فائدت/u',
        '/شنو فايدت/u',
        '/شنو ميزات/u',
        '/شنو الفرق/u',
        '/فرق بين/u',
        '/وصف/u',
        '/تفاصيل/u',
        '/معلومات عن/u',
        '/اخبرني عن/u',
        // Competitor comparison / leaving objection
        '/حصلت.*ارخص/u',
        '/بمكان ثاني/u',
        '/محل ثاني/u',
        '/عند غيركم/u',
        '/لقيت.*ارخص/u',
        '/موقع ثاني/u',
        '/ما احتاج/u',
        '/ما ابي/u',
        '/مو محتاج/u',
        // Offer / promotion inquiry
        '/العرض.*موجود/u',
        '/العرض.*بعد/u',
        '/بعد.*عرض/u',
        '/العروض.*متوفر/u',
        '/هل.*عرض/u',
        '/اكو.*عرض/u',
        // Conversational follow-ups
        '/ليش/u',
        '/ليش هذا السعر/u',
        '/مو زين/u',
        '/ما عجبني/u',
    ];

    protected function matchKeywords(string $message, ConversationState $state, ?User $store = null): ?array
    {
        // ── CONVERSATIONAL INTERCEPT: Route to AI Agent for natural discussion ──
        // Check these BEFORE keyword patterns so they don't get misrouted
        foreach (self::CONVERSATIONAL_PATTERNS as $pattern) {
            if (preg_match($pattern, $message)) {
                // Return UNKNOWN so ChatAgentService handles it naturally
                return null;
            }
        }

        foreach (self::KEYWORD_PATTERNS as $pattern => $intent) {
            if (preg_match($pattern, $message)) {
                $adjusted = $this->adjustIntentForState($intent, $state, $message);

                return [
                    'intent' => $adjusted,
                    'entities' => $this->extractEntities($message, $store),
                    'confidence' => 0.95,
                ];
            }
        }

        // ──────────────────────────────────────────────
        // STATE-AWARE FALLBACK: Patterns that require state context
        // ──────────────────────────────────────────────

        $productStates = [
            ConversationState::WAITING_PRODUCT_SELECTION,
            ConversationState::BROWSING_PRODUCTS,
        ];

        if (in_array($state, $productStates)) {
            // Bare product name + quantity word (no verb prefix)
            // E.g., "طقم ضوء قطعتين", "فرن بيتزا 3"
            $entities = $this->extractEntities($message);
            $hasQuantity = !empty($entities['quantity']);
            $hasProductName = !empty($entities['product_name']) && mb_strlen($entities['product_name']) >= 2;

            if ($hasProductName && $hasQuantity) {
                return [
                    'intent' => Intent::ADD_TO_CART,
                    'entities' => $entities,
                    'confidence' => 0.85,
                ];
            }

            // Bare product name (no quantity) in WAITING → SELECT_PRODUCT
            if ($hasProductName && !$hasQuantity && $state === ConversationState::WAITING_PRODUCT_SELECTION) {
                return [
                    'intent' => Intent::SELECT_PRODUCT,
                    'entities' => $entities,
                    'confidence' => 0.80,
                ];
            }
        }

        return null;
    }

    // ─── STATE-AWARE ADJUSTMENTS ──────────────────────────────────

    /**
     * CRITICAL: Same word means different things in different states
     *
     * "نعم" in WAITING_PRODUCT_SELECTION = add to cart
     * "نعم" in CART_REVIEW = proceed to checkout
     * "نعم" in WAITING_CONFIRMATION = confirm order
     * "لا" in CART_REVIEW = done adding (NOT cancel!)
     * "لا" in WAITING_PRODUCT_SELECTION = show more
     */
    protected function adjustIntentForState(Intent $intent, ConversationState $state, string $message): Intent
    {
        $productStates = [
            ConversationState::WAITING_PRODUCT_SELECTION,
            ConversationState::BROWSING_PRODUCTS,
        ];

        // ADD_TO_CART in product states → keep it
        if (in_array($state, $productStates) && $intent === Intent::ADD_TO_CART) {
            return Intent::ADD_TO_CART;
        }

        // "نعم"/"تمام" in WAITING_PRODUCT_SELECTION → ADD_TO_CART
        if ($intent === Intent::CONFIRM_ORDER && $state === ConversationState::WAITING_PRODUCT_SELECTION) {
            return Intent::ADD_TO_CART;
        }

        // "نعم"/"تمام" in CART_REVIEW → proceed to checkout (DECLINE_MORE triggers info collection)
        if ($intent === Intent::CONFIRM_ORDER && $state === ConversationState::CART_REVIEW) {
            return Intent::DECLINE_MORE;
        }

        // "لا" in WAITING_PRODUCT_SELECTION → browse more
        if ($intent === Intent::DECLINE_MORE && $state === ConversationState::WAITING_PRODUCT_SELECTION) {
            return Intent::BROWSE_PRODUCTS;
        }

        // ASK_PRICE is now handled as an intent-first override in GroqChatServiceV3
        // No longer convert to BROWSE_PRODUCTS here - handleAskPrice will search and show price

        // In WAITING_CUSTOMER_INFO: override most intents to PROVIDE_INFO
        if ($state === ConversationState::WAITING_CUSTOMER_INFO) {
            if ($this->containsCustomerInfo($message)) {
                return Intent::PROVIDE_INFO;
            }
            if ($intent === Intent::UNKNOWN) {
                return Intent::PROVIDE_INFO;
            }
            // Allow GREETING to pass through (handled by GroqChatServiceV3)
            if ($intent === Intent::GREETING) {
                return Intent::GREETING;
            }
        }

        return $intent;
    }

    // ─── ENTITY EXTRACTION ────────────────────────────────────

    /**
     * Extract entities from message
     */
    public function extractEntities(string $message, ?User $store = null): array
    {
        $entities = [
            'product_name' => null,
            'category_name' => null,
            'color' => null,
            'size' => null,
            'quantity' => null,
            'customer_name' => null,
            'customer_phone' => null,
            'customer_address' => null,
            'selection_number' => null,
            'faq_topic' => null,
        ];

        // FAQ topic
        $entities['faq_topic'] = $this->extractFaqTopic($message);

        // Phone (strict Iraqi format: 11 digits)
        if (preg_match('/\b(07[3-9]\d{8})\b/', $message, $m)) {
            $entities['customer_phone'] = $m[1];
        }

        // Quantity
        $entities['quantity'] = $this->extractQuantity($message);

        // Number selection (#1, #2)
        if (preg_match('/^#?(\d{1,2})$/', trim($message), $m)) {
            $entities['selection_number'] = (int)$m[1];
        }

        // Colors
        $colors = [
            'احمر', 'اسود', 'ابيض', 'ازرق', 'اخضر', 'اصفر',
            'رمادي', 'بني', 'زهري', 'بنفسجي', 'برتقالي', 'ذهبي', 'فضي',
        ];
        foreach ($colors as $color) {
            if (mb_stripos($message, $color) !== false) {
                $entities['color'] = $color;
                break;
            }
        }

        // Sizes
        $sizes = ['صغير', 'وسط', 'كبير', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
        foreach ($sizes as $size) {
            if (mb_stripos($message, $size) !== false) {
                $entities['size'] = $size;
                break;
            }
        }

        // Name
        if (preg_match('/(?:اسمي|الاسم)[:\s]+(.+)/u', $message, $m)) {
            $entities['customer_name'] = trim($m[1]);
        }

        // Address (city check) - extract only the LINE containing a city, not the entire message
        $msgLines = preg_split('/[\n\r]+/', trim($message));
        foreach (self::IRAQI_CITIES as $city) {
            if (mb_stripos($message, $city) !== false) {
                // Find the specific line containing this city
                foreach ($msgLines as $line) {
                    if (mb_stripos(trim($line), $city) !== false) {
                        $entities['customer_address'] = trim($line);
                        break 2;
                    }
                }
                $entities['customer_address'] = $message; // fallback
                break;
            }
        }

        // Category hint: "شنو عدكم بالكهربائيات" / "قسم الملابس"
        $categoryName = $this->extractCategoryHint($message);

        // Product name (only if not phone/address/category-only)
        $productName = null;
        if (empty($entities['customer_phone']) && empty($entities['customer_address'])) {
            $productName = $this->extractProductName($message);
            if ($productName !== null && $productName === $categoryName) {
                $productName = null; // same as category hint, don't duplicate
            }
        }

        // ── DB-AWARE VALIDATION: Check store's actual categories & products ──
        // Different stores have different items - we must check the real DB
        if ($store && $categoryName) {
            $realCategory = $this->productService->findCategory($store, $categoryName);
            if ($realCategory) {
                // It IS a real category in this store
                $entities['category_name'] = $categoryName;
            } else {
                // Not a real category → check if it's a product instead
                $productHit = $this->productService->search($store, $categoryName, 1);
                if ($productHit->isNotEmpty()) {
                    // It's a product, not a category
                    $entities['product_name'] = $categoryName;
                    // Don't set category_name - it's not a real category
                    Log::debug('IntentAnalyzer: DB check reclassified category_name to product_name', [
                        'entity' => $categoryName,
                        'store_id' => $store->id,
                    ]);
                } else {
                    // Not found as category or product - still pass it as category_name
                    // so downstream handlers can try broader searches
                    $entities['category_name'] = $categoryName;
                }
            }
        } elseif ($categoryName) {
            // No store context available - pass through as-is (text-only mode)
            $entities['category_name'] = $categoryName;
        }

        // Set product_name if we found one (and DB didn't already set it above)
        if ($productName && empty($entities['product_name'])) {
            $entities['product_name'] = $productName;
        }

        return $entities;
    }

    /**
     * Extract category hint from browse queries
     * "شنو عدكم بالكهربائيات" → "كهربائيات"
     * "قسم الملابس" → "ملابس"
     * "شنو عدكم من اكسسوارات" → "اكسسوارات"
     */
    protected function extractCategoryHint(string $message): ?string
    {
        // "شنو عدكم بال..." or "شنو عدكم من ..."
        $patterns = [
            // NEW: "اريد منتج خاص بال...", "اريد شي يخص ال..."
            '/(?:خاص|تخص|يخص|يتعلق|تتعلق|متعلق)\s*(?:ب|بال|في|من)\s*(.+?)\s*[؟?]*\s*$/u',
            // NEW: bare "متوفر/موجود/اكو <product>" → extract product directly
            '/^(?:متوفر|موجود|اكو|في)\s+(.+?)\s*[؟?]*\s*$/u',
            '/^هل\s+(?:متوفر|موجود|عندكم|عدكم)\s+(.+?)\s*[؟?]*\s*$/u',
            '/(?:عدكم|عندكم|متوفر|موجود)\s+(?:بال|بقسم|من|في)\s*(.+?)\s*[؟?]*\s*$/u',
            '/(?:قسم|فئه|فئة|صنف)\s+(?:ال)?(.+?)\s*[؟?]*\s*$/u',
            '/(?:عدكم|عندكم)\s+(.+?)\s*[؟?]*\s*$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $m)) {
                $candidate = trim($m[1], '؟? ');
                // Must be 2+ chars and not a filler word
                $fillers = ['شنو', 'شلون', 'هل', 'اي', 'بعد', 'هم', 'كمان', 'غير'];
                if (mb_strlen($candidate) >= 2 && !in_array($candidate, $fillers)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Extract FAQ topic from message
     * Returns: delivery_cost, return_policy, payment_methods, working_hours, delivery_time, store_location, or null
     */
    protected function extractFaqTopic(string $message): ?string
    {
        $normalized = mb_strtolower(trim($message));

        // Delivery cost
        if (preg_match('/(التوصيل|الشحن|اجور|سعر التوصيل|شكد التوصيل|كم التوصيل|توصلون)/u', $normalized)) {
            return 'delivery_cost';
        }

        // Return/exchange policy
        if (preg_match('/(الاسترجاع|الاستبدال|سياسه|سياسة)/u', $normalized)) {
            return 'return_policy';
        }

        // Payment methods
        if (preg_match('/(طرق الدفع|طريقه الدفع|كيف ادفع|الدفع)/u', $normalized)) {
            return 'payment_methods';
        }

        // Working hours
        if (preg_match('/(ساعات العمل|متى مفتوحين|اوقات العمل)/u', $normalized)) {
            return 'working_hours';
        }

        // Delivery time
        if (preg_match('/(متى يوصل|وقت التوصيل|متى يصلني)/u', $normalized)) {
            return 'delivery_time';
        }

        // Store location
        if (preg_match('/(وين المحل|العنوان|الموقع)/u', $normalized)) {
            return 'store_location';
        }

        return null;
    }

    /**
     * Extract product name from message by stripping browse/filler keywords
     */
    protected function extractProductName(string $message): ?string
    {
        $msg = mb_strtolower(trim($message));
        $msg = str_replace(['أ', 'إ', 'آ'], 'ا', $msg);
        $msg = str_replace('ة', 'ه', $msg);
        $msg = str_replace('ى', 'ي', $msg);
        $msg = str_replace(['؟', '?'], '', $msg);

        // Words that are NOT product names
        $stripWords = [
            'اريد', 'ابي', 'ابغي', 'بدي', 'اشوف', 'شوفني', 'وريني',
            'شنو', 'شلون', 'هل', 'في', 'اي', 'يا',
            'عندكم', 'عدكم', 'متوفر', 'موجود', 'تبيعون', 'تبيع',
            'السلام', 'سلام', 'مرحبا', 'هلا', 'اهلا',
            'صوره', 'صور', 'سعر', 'بكم', 'شكد',
            'اضف', 'زيد', 'حط', 'ضيف',
            // Cart-action words
            'اضيفه', 'ضيفه', 'للسله', 'بالسله', 'سلتي', 'السله', 'الكارت',
            'اكد', 'اكيد', 'الطلب',
            'من', 'على', 'الي', 'مع', 'لو', 'بس', 'بال', 'بقسم',
            'نعم', 'لا', 'تمام', 'اوك', 'اكيد',
            'سمحت', 'ممكن', 'اكو', 'ماكو', 'طيب', 'حسنا', 'ماشي',
            'قسم', 'فئه', 'صنف', 'نوع',
            // Browse-filler words (not product names)
            'عددلي', 'اعرضلي', 'كل', 'جميع', 'ان', 'اعرف', 'ماهي',
            'التي', 'يتم', 'توفيرها', 'المتاحه', 'المنتجات', 'منتجات',
            // Quantity words
            'قطعتين', 'قطعات', 'قطع', 'قطعه', 'قطعة',
            'واحد', 'وحده', 'وحدة', 'اثنين', 'ثنين', 'زوج',
            'ثلاثه', 'ثلاث', 'اربعه', 'اربع', 'خمسه', 'خمس',
            'سته', 'ست', 'سبعه', 'سبع', 'ثمانيه', 'ثمان',
            'تسعه', 'تسع', 'عشره', 'عشر',
        ];

        $cleaned = $msg;
        foreach ($stripWords as $word) {
            $cleaned = preg_replace('/(?:^|\s)' . preg_quote($word, '/') . '(?:\s|$)/u', ' ', $cleaned);
        }

        $cleaned = preg_replace('/\d+/u', '', $cleaned);
        $cleaned = preg_replace('/\s+/u', ' ', trim($cleaned));

        if (mb_strlen($cleaned) >= 2) {
            return $cleaned;
        }

        return null;
    }

    /**
     * Extract quantity from message
     */
    protected function extractQuantity(string $message): ?int
    {
        // Arabic number words
        foreach (self::ARABIC_NUMBERS as $word => $number) {
            if (mb_stripos($message, $word) !== false) {
                return $number;
            }
        }

        // Digits (but NOT phone numbers!)
        $cleaned = preg_replace('/\b07[3-9]\d{8}\b/', '', $message);
        if (preg_match('/(\d+)/', $cleaned, $m)) {
            $num = (int)$m[1];
            if ($num >= 1 && $num <= 100) {
                return $num;
            }
        }

        return null;
    }

    // ─── AI FALLBACK ──────────────────────────────────────────

    protected function analyzeWithAI(string $message, ConversationState $state, array $context, User $user): array
    {
        $prompt = $this->buildAnalysisPrompt($message, $state, $context);

        try {
            $aiProvider = new AiProviderService($user);

            $response = $aiProvider->chat(
                messages: [
                    ['role' => 'system', 'content' => $prompt],
                    ['role' => 'user', 'content' => $message],
                ],
                temperature: 0.1,
                maxTokens: 200,
            );

            $content = $response['content'] ?? $response;
            if (is_array($content)) {
                $content = json_encode($content);
            }

            $parsed = $this->parseAIResponse($content);

            // Apply state adjustments even on AI results
            $parsed['intent'] = $this->adjustIntentForState($parsed['intent'], $state, $message);

            Log::debug('IntentAnalyzer: AI analysis', [
                'message' => $message,
                'intent' => $parsed['intent']->value,
                'confidence' => $parsed['confidence'],
            ]);

            return $parsed;

        } catch (\Exception $e) {
            Log::error('IntentAnalyzer: AI call failed', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);

            return [
                'intent' => Intent::UNKNOWN,
                'entities' => $this->extractEntities($message),
                'confidence' => 0.0,
            ];
        }
    }

    protected function buildAnalysisPrompt(string $message, ConversationState $state, array $context): string
    {
        $cartSummary = $context['cart_summary'] ?? 'فارغة';
        $lastProduct = $context['last_product'] ?? 'لا يوجد';

        return <<<PROMPT
You are an intent analyzer for an Iraqi e-commerce store chatbot.

Current conversation state: {$state->value}
Cart: {$cartSummary}
Last product discussed: {$lastProduct}

Return ONLY valid JSON:
{
  "intent": "<intent_name>",
  "entities": {
    "product_name": null,
    "category_name": null,
    "color": null,
    "size": null,
    "quantity": null,
    "customer_name": null,
    "customer_phone": null,
    "customer_address": null
  },
  "confidence": 0.0-1.0
}

Allowed intents:
- greeting: سلام عليكم, هلا, مرحبا
- browse_products: شنو عدكم, اريد اشوف, وريني المنتجات
- select_product: customer picks a specific product by name or number
- add_to_cart: اضف, اريده, حطه بالسله
- update_quantity: سويهم 3, خليها 5, غير الكميه
- remove_from_cart: شيل, احذف, ما اريده
- request_image: صوره, وريني الصوره
- provide_info: customer gives name, phone, or address
- confirm_order: نعم, اكيد, تمام (in confirmation state)
- cancel_order: الغي الطلب, كانسل (EXPLICIT cancel ONLY)
- decline_more: لا, لا شكرا, بس هذا, خلاص (NOT cancel!)
- ask_price: بكم, شكد السعر
- order_status: وين طلبي, حالة الطلب
- want_more: اريد هم, زيد شي ثاني
- unknown: cannot determine

CRITICAL RULES:
1. "لا" or "لا شكرا" = decline_more, NEVER cancel_order
2. "الغي الطلب" = ONLY way to cancel
3. If message contains a phone number (07XXXXXXXX) → provide_info
4. If message contains a city name (بغداد, البصره, etc.) → provide_info
5. "شنو عدكم" alone = browse_products (general categories)
6. "شنو عدكم بالكهربائيات" = browse_products with category_name: "كهربائيات"
7. Numbers like "1" or "2" in product selection state = select_product
8. Extract Arabic number words: قطعتين=2, ثلاث=3, خمسه=5
9. "غالي", "مكلف", "كلش غالي", "السعر غالي", complaints about price = unknown (let agent handle)
10. "اشرحلي", "وصف", "شنو فائدته" = unknown (let agent explain naturally)
11. Price complaints and product discussion = ALWAYS unknown
PROMPT;
    }

    protected function parseAIResponse(string $response): array
    {
        $json = $response;
        if (preg_match('/\{.*\}/s', $response, $m)) {
            $json = $m[0];
        }

        $decoded = json_decode($json, true);

        if (!$decoded || !isset($decoded['intent'])) {
            return [
                'intent' => Intent::UNKNOWN,
                'entities' => [],
                'confidence' => 0.0,
            ];
        }

        $intent = Intent::tryFrom($decoded['intent']) ?? Intent::UNKNOWN;

        return [
            'intent' => $intent,
            'entities' => $decoded['entities'] ?? [],
            'confidence' => (float)($decoded['confidence'] ?? 0.5),
        ];
    }

    // ─── HELPER METHODS ───────────────────────────────────────

    protected function getCacheKey(string $message, ConversationState $state): string
    {
        return 'intent_' . md5($message . '_' . $state->value);
    }

    /**
     * Check if message is an explicit cancel
     */
    public function isExplicitCancel(string $message): bool
    {
        $patterns = ['/الغي الطلب/u', '/الغي كلشي/u', '/كانسل/u', '/cancel/i'];
        foreach ($patterns as $p) {
            if (preg_match($p, $message)) return true;
        }
        return false;
    }

    /**
     * Check if message is declining more items (NOT cancel!)
     */
    public function isDecliningMore(string $message): bool
    {
        $patterns = [
            '/^لا$/u', '/^لا شكرا/u', '/^ما اريد/u', '/لا اريد شي/u',
            '/^بس هذا/u', '/^خلاص$/u', '/^كافي/u', '/^بس$/u',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, trim($message))) return true;
        }
        return false;
    }

    /**
     * Check if message is asking about order status
     */
    public function isOrderStatusQuery(string $message): bool
    {
        $patterns = ['/وين طلبي/u', '/حالة الطلب/u', '/حالة طلبي/u', '/طلبيتي/u', '/متى يوصل/u'];
        foreach ($patterns as $p) {
            if (preg_match($p, $message)) return true;
        }
        return false;
    }

    /**
     * Check if message contains customer info (phone, address, name)
     */
    public function containsCustomerInfo(string $message): bool
    {
        // Phone number (strict Iraqi format: 11 digits)
        if (preg_match('/\b07[3-9]\d{8}\b/', $message)) {
            return true;
        }

        // City name
        foreach (self::IRAQI_CITIES as $city) {
            if (mb_stripos($message, $city) !== false) {
                return true;
            }
        }

        // Name indicator
        if (preg_match('/(?:اسمي|الاسم)/u', $message)) {
            return true;
        }

        // Address indicator
        if (preg_match('/(?:عنواني|العنوان|منطقه|حي)/u', $message)) {
            return true;
        }

        return false;
    }

    /**
     * Check if this is a general browse query ("شنو عدكم") vs category-specific browse vs product search
     *
     * DB-AWARE: When store is provided, checks the actual store's categories & products
     * to determine the correct classification. Different stores have different items.
     *
     * Returns: 'general' | 'category' | 'product' | 'all' | null (not a browse query)
     */
    public function classifyBrowseQuery(string $message, ?User $store = null): ?string
    {
        $msg = $this->normalizeMessage($message);

        // "All products" requests → show all products not just categories
        $allProductsPatterns = [
            '/كل المنتجات/u',
            '/جميع المنتجات/u',
            '/عددلي كل/u',
            '/وريني كل/u',
            '/اعرضلي كل/u',
            '/كل شي عندكم/u',
            '/كل شي عدكم/u',
            '/كلشي عندكم/u',
            '/كلشي عدكم/u',
            '/اريد ان اعرف.*المنتجات/u',
            '/ماهي المنتجات/u',
            '/المنتجات.*توفر/u',
            '/المنتجات.*متاح/u',
        ];

        foreach ($allProductsPatterns as $p) {
            if (preg_match($p, $msg)) {
                return 'all';
            }
        }

        // General browse patterns (check first - no DB needed)
        $generalPatterns = [
            '/^شنو عندكم\s*[؟?]?\s*$/u',
            '/^شنو عدكم\s*[؟?]?\s*$/u',
            '/^شنو متوفر\s*[؟?]?\s*$/u',
            '/^شنو موجود\s*[؟?]?\s*$/u',
            '/^شنو الاقسام/u',
            '/^شنو تبيعون/u',
            '/^كتلوج/u',
            '/^القائمه/u',
            '/^منتجاتكم/u',
            '/^المنتجات\s*$/u',
            '/^اريد اشوف\s*$/u',
        ];

        foreach ($generalPatterns as $p) {
            if (preg_match($p, $msg)) {
                return 'general';
            }
        }

        // Check if message is only browse-filler words (no product/category substance)
        $words = preg_split('/\s+/u', $msg);
        $fillers = ['شنو', 'شلون', 'اريد', 'اشوف', 'هل', 'في', 'اي', 'ال', 'عدكم', 'عندكم', 'متوفر', 'موجود', 'المنتجات', 'منتجات', 'كتلوج', 'القائمه', 'منتجاتكم', 'وريني', 'تبيعون', 'كل', 'جميع', 'عددلي', 'اعرضلي', 'ان', 'اعرف', 'ماهي', 'التي', 'يتم', 'توفيرها', 'المتاحه', 'متاح'];
        $allFiller = true;
        foreach ($words as $w) {
            $w = trim($w, '؟? ');
            if (mb_strlen($w) < 1) continue;
            $isFiller = in_array($w, $fillers);
            if (!$isFiller) {
                $allFiller = false;
                break;
            }
        }

        if ($allFiller && count($words) > 0) {
            return 'general';
        }

        // Extract the entity name from the message
        $categoryHint = $this->extractCategoryHint($msg);
        if (!$categoryHint) {
            return null;
        }

        // ── DB-AWARE CLASSIFICATION ──
        // When store context is available, check REAL data instead of guessing
        if ($store) {
            // 1. Check if it's an actual category in this store's DB
            $realCategory = $this->productService->findCategory($store, $categoryHint);
            if ($realCategory) {
                Log::debug('IntentAnalyzer::classifyBrowseQuery: DB confirmed category', [
                    'hint' => $categoryHint,
                    'category_id' => $realCategory->id,
                    'store_id' => $store->id,
                ]);
                return 'category';
            }

            // 2. Not a category → check if it matches actual products
            $productHits = $this->productService->search($store, $categoryHint, 1);
            if ($productHits->isNotEmpty()) {
                Log::debug('IntentAnalyzer::classifyBrowseQuery: DB found products, not category', [
                    'hint' => $categoryHint,
                    'store_id' => $store->id,
                ]);
                return 'product';
            }

            // 3. Not found as category or product → still classify as 'category'
            // so downstream handlers can try broader searches / Arabic variations
            Log::debug('IntentAnalyzer::classifyBrowseQuery: DB found nothing, defaulting to category', [
                'hint' => $categoryHint,
                'store_id' => $store->id,
            ]);
            return 'category';
        }

        // No store context → text-only fallback (legacy behavior)
        return 'category';
    }
}
