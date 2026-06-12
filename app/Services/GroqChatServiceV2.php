<?php

namespace App\Services;

use App\Models\AiChatSession;
use App\Models\AiFastReply;
use App\Models\AiSetting;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderItem;
use App\Models\Product;
use App\Models\StoreType;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * GroqChatServiceV2 - Complete Rewrite with Bug Fixes
 *
 * FIXED ISSUES:
 * 1. Cart modification - "اضفلي", "اريد قميصين" now works correctly
 * 2. Quantity updates - "سويهم 3", "الابيض 3" now understands context
 * 3. Order saving - Orders are ALWAYS saved to database on confirmation
 * 4. Order status lookup - Uses phone/lead to find orders correctly
 * 5. Product variants - "اكو غير نوعيه" now searches for alternatives
 * 6. Context tracking - Maintains conversation state properly
 *
 * KEY IMPROVEMENTS:
 * - Separate intent for cart modifications (add_to_cart, update_quantity, remove_from_cart)
 * - Better contextual understanding using last_mentioned_product
 * - Guaranteed order persistence with verification
 * - Smarter product variant search
 */
class GroqChatServiceV2
{
    protected ?AiProviderService $aiProvider = null;
    protected ?string $apiKey;
    protected string $model;
    protected string $provider;
    protected int $sessionTimeoutMinutes;
    protected ?string $currentConversationId = null;
    protected ?string $currentLeadId = null;

    // Arabic digit translation
    protected const AR_DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    // Arabic number words (including compound numbers 11-99)
    protected const NUM_WORDS = [
        'صفر' => 0,
        'واحد' => 1, 'وحده' => 1, 'وحدة' => 1, 'واحده' => 1, 'واحدة' => 1,
        'اثنان' => 2, 'اثنين' => 2, 'ثنين' => 2, 'اثنتان' => 2, 'اثنتين' => 2,
        'ثلاث' => 3, 'ثلاثه' => 3, 'ثلاثة' => 3,
        'اربع' => 4, 'أربع' => 4, 'أربعة' => 4, 'اربعه' => 4,
        'خمس' => 5, 'خمسه' => 5, 'خمسة' => 5,
        'ست' => 6, 'سته' => 6, 'ستة' => 6,
        'سبع' => 7, 'سبعه' => 7, 'سبعة' => 7,
        'ثمان' => 8, 'ثماني' => 8, 'ثمانيه' => 8, 'ثمانية' => 8,
        'تسع' => 9, 'تسعه' => 9, 'تسعة' => 9,
        'عشر' => 10, 'عشره' => 10, 'عشرة' => 10,
        // Compound numbers (11-19)
        'احدعشر' => 11, 'احد عشر' => 11, 'إحدى عشر' => 11, 'حدعشر' => 11,
        'اثناعشر' => 12, 'اثنا عشر' => 12, 'اثنى عشر' => 12, 'طناعشر' => 12,
        'ثلاثعشر' => 13, 'ثلاث عشر' => 13, 'ثلاثة عشر' => 13, 'ثلطعشر' => 13,
        'اربعطعشر' => 14, 'اربع عشر' => 14, 'أربعة عشر' => 14, 'اربعة عشر' => 14,
        'خمسطعشر' => 15, 'خمس عشر' => 15, 'خمسة عشر' => 15, 'خمستعشر' => 15,
        'ستطعشر' => 16, 'ست عشر' => 16, 'ستة عشر' => 16, 'سطعشر' => 16,
        'سبعطعشر' => 17, 'سبع عشر' => 17, 'سبعة عشر' => 17,
        'ثمانطعشر' => 18, 'ثمان عشر' => 18, 'ثمانية عشر' => 18,
        'تسعطعشر' => 19, 'تسع عشر' => 19, 'تسعة عشر' => 19,
        // Tens (20-50)
        'عشرين' => 20, 'عشرون' => 20,
        'واحد وعشرين' => 21, 'واحد وعشرون' => 21,
        'اثنين وعشرين' => 22, 'اثنان وعشرون' => 22,
        'ثلاثين' => 30, 'ثلاثون' => 30,
        'اربعين' => 40, 'أربعون' => 40,
        'خمسين' => 50, 'خمسون' => 50,
    ];

    // Dual form patterns (indicates quantity = 2) - ين suffix for common items
    protected const DUAL_PATTERNS = [
        // Clothing
        '/قميصين|قميصتين/' => 2,
        '/بنطرونين|بنطلونين|بنطروناتين|بنطلونتين/' => 2,
        '/تشيرتين|تيشيرتين|تيشرتين/' => 2,
        '/هوديين|هودياتين|هوديتين/' => 2,
        '/فستانين|فستانتين/' => 2,
        '/جاكيتين|جاكيتتين/' => 2,
        '/بلوزتين|بلوزاتين/' => 2,
        '/شورتين|شورتاتين/' => 2,
        '/عباءتين|عبايتين/' => 2,
        // Accessories
        '/حذائين|جزمتين|حذاءين/' => 2,
        '/نظارتين|نضارتين/' => 2,
        '/ساعتين/' => 2,
        '/شنطتين|حقيبتين/' => 2,
        '/محفظتين/' => 2,
        '/خاتمين|خواتم/' => 2,
        '/سوارين/' => 2,
        // General unit words (CRITICAL: اريد قطعتين)
        '/قطعتين/' => 2,
        '/حبتين/' => 2,
        '/علبتين/' => 2,
        '/زوجين/' => 2,
        '/طقمين/' => 2,
        '/كرتونين/' => 2,
    ];

    // Unit words that follow numbers (ثلاث قطع، خمس حبات)
    protected const UNIT_WORDS = [
        'قطع', 'قطعه', 'قطعة', 'قطعات',
        'حبه', 'حبة', 'حبات',
        'علبه', 'علبة', 'علب',
        'زوج', 'ازواج', 'أزواج',
        'طقم', 'اطقم', 'أطقم',
        'كرتون', 'كراتين',
        'دزينه', 'دزينة', 'دزن',
        'صندوق', 'صناديق',
        'عدد', 'من',
    ];

    // Plural patterns that indicate quantity (بنطرونات، قمصان)
    protected const PLURAL_PATTERNS = [
        // Iraqi/Gulf plurals
        'بنطرونات' => 'بنطرون',
        'قمصان' => 'قميص',
        'فساتين' => 'فستان',
        'تيشيرتات' => 'تيشيرت',
        'هوديات' => 'هودي',
        'جاكيتات' => 'جاكيت',
        'بلوزات' => 'بلوزه',
        'شورتات' => 'شورت',
        // Accessories
        'احذيه' => 'حذاء',
        'أحذية' => 'حذاء',
        'نظارات' => 'نظاره',
        'ساعات' => 'ساعه',
        'شنط' => 'شنطه',
        'حقائب' => 'حقيبه',
    ];

    // Intent keywords
    protected const GREETING_KEYWORDS = [
        'السلام عليكم', 'سلام عليكم', 'سلام', 'مرحبا', 'مرحبه', 'اهلا', 'هلا', 'هلو', 'هاي', 'hi', 'hello',
        'كيف الحال', 'كيف حالك', 'كيفك', 'شلونك', 'شخبارك', 'صباح الخير', 'مساء الخير',
    ];

    protected const THANKS_KEYWORDS = ['شكرا', 'شكراً', 'مشكور', 'مشكورين', 'تسلم', 'ممنون', 'thanks', 'thx'];

    // CRITICAL: Cart modification keywords - these indicate user wants to ADD or CHANGE items
    protected const ADD_TO_CART_KEYWORDS = [
        'اضف', 'اضفلي', 'اضيف', 'ضيف', 'ضيفلي', 'اضافه', 'زيد', 'زيدلي', 'خلي', 'حط', 'حطلي',
        'اريد هم', 'اريد ايضا', 'ابي هم', 'وهم', 'و هم', 'ايضا', 'كمان', 'بعد',
        'اريد اطلب', 'ابي اطلب', 'اضيفلي للطلب',
    ];

    // CRITICAL: Quantity update keywords - user wants to change quantity of existing item
    protected const QUANTITY_UPDATE_KEYWORDS = [
        'سويهم', 'سوهم', 'خليهم', 'خليهن', 'غيرهم', 'غيرها', 'عدلهم', 'عدلها', 'بدالهم', 'بدلهم',
        'اريده', 'اريدهم', 'ابيه', 'ابيهم', 'اريدها', 'ابيها',
        'قطعه', 'قطعة', 'قطع', 'حبه', 'حبة',
    ];

    // Order intent keywords - NEW order (not modification)
    protected const ORDER_KEYWORDS = [
        'اريد', 'أريد', 'ابي', 'أبي', 'اطلب', 'أطلب', 'طلب',
        'اشتري', 'أشتري', 'خذلي', 'جيبلي', 'بدي', 'اخذ', 'آخذ',
        'ابغي', 'ابغى', 'اوصلي', 'محتاج', 'نريد', 'نطلب',
    ];

    // Inquiry keywords
    protected const INQUIRY_KEYWORDS = [
        'كم', 'شكد', 'شگد', 'بشكد', 'سعر', 'اسعار', 'شنو', 'شنهي', 'ماهي',
        'عدكم', 'عندكم', 'موجود', 'متوفر', 'يوجد', 'فيه', 'هل', 'بكم', 'قيمة',
    ];

    // Product variant keywords - user asking for alternatives
    protected const VARIANT_KEYWORDS = [
        'غير', 'نوعيه', 'نوعية', 'انواع', 'اخر', 'آخر', 'ثاني', 'ثانيه', 'بديل', 'مشابه',
        'اكو غير', 'عندكم غير', 'في غير', 'نوع ثاني', 'شي ثاني', 'شغله ثانيه', 'لون ثاني',
    ];

    // Confirmation keywords
    protected const CONFIRM_KEYWORDS = [
        'نعم', 'اي', 'صح', 'تمام', 'اكيد', 'موافق', 'اوكي', 'ok', 'yes',
        'استمر', 'كمل', 'اكمل', 'خلاص', 'مضبوط', 'ماشي', 'حلو', 'زين', 'طيب',
        'اكد', 'اكد الطلب', 'ارسل الطلب', 'ثبت الطلب',
    ];

    // Cancel keywords - FIXED: Only explicit cancel phrases
    protected const CANCEL_KEYWORDS = ['الغي الطلب', 'كانسل', 'الغي كلشي', 'لا اريد اشتري', 'ما اريد اشتري', 'cancel order'];

    // Decline MORE items keywords (NOT cancel - user has cart and says no more)
    protected const DECLINE_MORE_KEYWORDS = ['ما اريد اضيف', 'لا اريد اضيف', 'ما اريد شي', 'لا اريد شي', 'بس هيج', 'بس هذا', 'خلاص', 'يكفي', 'لا شكرا', 'شكرا بس', 'مو محتاج', 'ما اريد بعد', 'لا بعد'];

    // Remove keywords - for removing specific items from cart
    protected const REMOVE_KEYWORDS = ['حذف', 'شيل', 'امسح', 'احذف', 'شيله', 'شيلها', 'شيل من السلة'];

    // Category inquiry patterns - user asking about a category not a specific product
    protected const CATEGORY_INQUIRY_PATTERNS = [
        'شنو عدكم', 'شنو عندكم', 'عدكم', 'عندكم', 'شوفني', 'وريني',
        'اريد اشوف', 'ابي اشوف', 'متوفر عندكم', 'عندكم اشياء',
        'ماذا لديكم', 'ماعندكم', 'شنو موجود', 'شنو متوفر',
    ];

    // Category keywords - common category names to match
    protected const CATEGORY_KEYWORDS = [
        'ملابس', 'قمصان', 'بناطيل', 'فساتين', 'احذية', 'جزم', 'شنط', 'حقائب',
        'اكسسوارات', 'ساعات', 'نظارات', 'عطور', 'مكياج', 'تجميل', 'عناية',
        'اجهزة', 'الكترونيات', 'موبايلات', 'جوالات', 'تلفونات', 'لابتوب',
        'اثاث', 'مفروشات', 'ادوات منزلية', 'مطبخ', 'حمام',
        'رجالي', 'نسائي', 'اطفال', 'بنات', 'اولاد',
        'طعام', 'اكل', 'مشروبات', 'حلويات',
    ];

    // Human agent escalation keywords
    protected const HUMAN_AGENT_KEYWORDS = [
        'شكوى', 'شكاوى', 'مشكله', 'مشكلة', 'شاكي', 'complaint',
        'اتكلم مع', 'اريد موظف', 'ابي موظف', 'اكلم حد', 'بشري',
        'استرجاع', 'ارجاع', 'استبدال', 'تالف', 'مكسور', 'غلط',
    ];

    // New order after completed keywords
    protected const NEW_ORDER_KEYWORDS = [
        'طلب جديد', 'من جديد', 'اطلب مرة', 'طلب ثاني', 'طلب اخر', 'طلب آخر',
        'اريد اطلب', 'ابي اطلب', 'طلب تاني',
    ];

    // Order status keywords
    protected const ORDER_STATUS_KEYWORDS = [
        'حالة طلبي', 'حالة الطلب', 'وين صار الطلب', 'وين وصل', 'متى يوصل', 'يمته يوصل',
        'طلبي', 'طلبي الحالي', 'سلة طلبي', 'ملخص طلبي', 'تتبع الطلب', 'order status',
    ];

    // Delivery keywords
    protected const DELIVERY_KEYWORDS = ['توصيل', 'توصلون', 'يوصل', 'التوصيل', 'سعر التوصيل', 'اجرة التوصيل', 'اجور التوصيل'];

    // Image request keywords
    protected const IMAGE_REQUEST_KEYWORDS = [
        'صور', 'صورة', 'صوره', 'شوفني', 'ارني', 'وريني', 'ابي اشوف', 'اريد اشوف',
        'صورته', 'شكله', 'ارسل صوره', 'دزلي صوره', 'picture', 'image', 'photo',
    ];

    protected User $user;
    protected AiSetting $settings;
    protected StoreAgentService $agent;
    protected ?MissingDataDetector $missingDataDetector = null;
    protected ?ProductAttributeService $attributeService = null;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->settings = $user->aiSetting ?? new AiSetting();
        $this->agent = new StoreAgentService($user);
        $this->missingDataDetector = new MissingDataDetector($user);
        $this->attributeService = new ProductAttributeService();
        $this->aiProvider = new AiProviderService($user);
        $defaultProvider = config('services.ai.default_provider', 'openai');
        $this->provider = $defaultProvider === 'openai'
            ? 'openai'
            : ($this->settings->ai_provider ?? $defaultProvider);

        if ($this->provider === 'openai') {
            $this->apiKey = $this->settings->openai_api_key ?? config('services.openai.api_key');
            $this->model = $this->settings->openai_model ?? config('services.openai.model', 'gpt-4.1-mini');
        } else {
            $this->apiKey = $this->settings->groq_api_key ?? config('services.groq.api_key');
            $this->model = $this->settings->groq_model ?? config('services.groq.model', 'llama-3.3-70b-versatile');
        }

        $this->sessionTimeoutMinutes = $this->settings->session_timeout_minutes ?? 1440; // 24 hours
    }

    /**
     * =====================================================
     * AI-FIRST ARCHITECTURE - Main Entry Point
     * =====================================================
     * The AI is the BRAIN. It understands intent, context, and generates responses.
     * We only use keyword matching for FAST PATH (simple greetings, confirmations).
     */
    public function processMessage(Conversation $conversation, Lead $lead, string $message): array
    {
        $this->currentConversationId = (string) $conversation->id;
        $this->currentLeadId = (string) $lead->id;

        $session = $this->getOrCreateSession($lead, $conversation);

        // FIX #8: Rate limiting - max 15 AI calls per minute per lead
        $rateLimitKey = "ai_rate:{$lead->id}";
        $currentCalls = (int) cache()->get($rateLimitKey, 0);
        if ($currentCalls >= 15) {
            Log::warning('GroqChatV2: Rate limit exceeded', ['lead_id' => $lead->id]);
            $reply = 'رجاءً انتظر قليلاً قبل إرسال رسائل أخرى.';
            return $this->buildResponse($reply, 'rate_limited', $session, false);
        }

        // FIX #15: Sanitize message for prompt injection
        $sanitizedMessage = $this->sanitizeForPromptInjection($message);
        if ($sanitizedMessage !== $message) {
            Log::warning('GroqChatV2: Potential prompt injection detected', [
                'lead_id' => $lead->id,
                'original_length' => mb_strlen($message),
            ]);
        }

        $session->addMessage('user', $sanitizedMessage);

        Log::info('GroqChatV2: AI-First Processing', [
            'message' => mb_substr($sanitizedMessage, 0, 100),
            'cart_count' => count($session->cart ?? []),
        ]);

        // FAST PATH: Handle common cases without AI (saves cost)
        $fastResult = $this->tryFastPath($session, $lead, $sanitizedMessage);
        if ($fastResult !== null) {
            $fastResult['reply'] = $this->removeEmojis($fastResult['reply'] ?? '');
            return $fastResult;
        }

        // FIX #13: Check cache for common questions (user-specific)
        $cachedResult = $this->getCachedResponse($sanitizedMessage, $session, $lead);
        if ($cachedResult !== null) {
            Log::info('GroqChatV2: Cache hit for common question');
            $session->addMessage('assistant', $cachedResult['reply']);
            return $cachedResult;
        }

        // Increment rate limit counter before AI call
        cache()->put($rateLimitKey, $currentCalls + 1, 60); // 60 seconds

        // AI-FIRST: Let AI understand and respond
        try {
            $result = $this->processWithAI($session, $lead, $sanitizedMessage);
            $result['reply'] = $this->removeEmojis($result['reply'] ?? '');

            // FIX #13: Cache common question responses
            $this->cacheCommonResponse($sanitizedMessage, $result);

            return $result;
        } catch (\Exception $e) {
            // FIX #6: Complete keyword fallback when AI fails
            Log::error('GroqChatV2: AI failed, using keyword fallback', ['error' => $e->getMessage()]);
            return $this->processWithKeywordFallback($session, $lead, $sanitizedMessage);
        }
    }

    /**
     * FIX #6: Complete keyword-based fallback system when AI is unavailable
     */
    protected function processWithKeywordFallback(AiChatSession $session, Lead $lead, string $message): array
    {
        $normalized = $this->normalize($message);

        // Try to detect intent using keywords (legacy method)
        $intent = $this->detectIntent($message, $session);

        Log::info('GroqChatV2: Keyword fallback intent', ['intent' => $intent]);

        // Route to legacy handlers
        $result = match($intent) {
            'greeting' => $this->handleGreeting($session, $message),
            'thanks' => $this->handleThanks($session, $message),
            'add_to_cart' => $this->handleAddToCart($session, $lead, $message),
            'update_quantity' => $this->handleUpdateQuantity($session, $lead, $message),
            'order' => $this->handleOrder($session, $lead, $message),
            'category_inquiry' => $this->handleCategoryInquiry($session, $message),
            'inquiry' => $this->handleInquiry($session, $message),
            'product_variant' => $this->handleProductVariant($session, $message),
            'confirm' => $this->handleConfirmation($session, $lead, $message),
            'cancel' => $this->handleCancel($session, $message),
            'decline_suggestion' => $this->handleDeclineSuggestion($session, $message),
            'remove' => $this->handleRemoval($session, $message),
            'order_status' => $this->handleOrderStatus($session, $lead, $message),
            'cart_summary' => $this->handleCartSummary($session, $lead, $message),
            'delivery_info' => $this->handleDeliveryInfo($session, $message),
            'image_request' => $this->handleImageRequest($session, $message),
            'customer_info' => $this->handleCustomerInfo($session, $lead, $message),
            default => $this->handleUnknownFallback($session),
        };

        $result['reply'] = $this->removeEmojis($result['reply'] ?? '');
        return $result;
    }

    /**
     * Handle unknown intent when AI is unavailable
     */
    protected function handleUnknownFallback(AiChatSession $session): array
    {
        $categories = $this->getStoreCategories();
        $catNames = array_column($categories, 'name');

        $reply = 'عذراً، ما فهمت طلبك. ';
        if (!empty($catNames)) {
            $reply .= 'الأقسام المتوفرة: ' . implode('، ', array_slice($catNames, 0, 5)) . '. شنو تحتاج؟';
        } else {
            $reply .= 'شنو تحتاج؟';
        }

        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'unknown', $session, false);
    }

    /**
     * FIX #13: Get cached response for common questions
     * Cache is user-specific and expires in 1 hour
     */
    protected function getCachedResponse(string $message, AiChatSession $session, Lead $lead): ?array
    {
        // Only cache simple info questions (no cart actions)
        if (!$this->isCacheableQuestion($message)) {
            return null;
        }

        $cacheKey = $this->buildCacheKey($message);
        $cached = cache()->get($cacheKey);

        if ($cached && isset($cached['reply'])) {
            return $this->buildResponse($cached['reply'], $cached['intent'] ?? 'cached', $session, false);
        }

        return null;
    }

    /**
     * FIX #13: Cache response for common questions
     */
    protected function cacheCommonResponse(string $message, array $result): void
    {
        // Only cache if it's a cacheable question and response has no actions
        if (!$this->isCacheableQuestion($message)) {
            return;
        }

        // Don't cache if there were cart actions
        if (!empty($result['actions_taken'])) {
            return;
        }

        $cacheKey = $this->buildCacheKey($message);
        $cacheData = [
            'reply' => $result['reply'] ?? '',
            'intent' => $result['intent'] ?? 'info',
        ];

        // Cache for 1 hour
        cache()->put($cacheKey, $cacheData, 3600);

        Log::debug('GroqChatV2: Cached response', ['key' => $cacheKey]);
    }

    /**
     * FIX #13: Check if question is cacheable (common info questions)
     */
    protected function isCacheableQuestion(string $message): bool
    {
        $normalized = $this->normalize($message);

        // Common info questions that don't need personalization
        $cacheablePatterns = [
            'شنو التوصيل', 'كم التوصيل', 'سعر التوصيل', 'توصيل',
            'شنو الدفع', 'طريقة الدفع', 'كيف ادفع',
            'وين موقعكم', 'العنوان', 'موقع المحل',
            'شنو ساعات الدوام', 'متى تفتحون', 'وقت العمل',
            'رقم الهاتف', 'رقم التواصل',
            'سياسة الاستبدال', 'استرجاع', 'ارجاع',
        ];

        foreach ($cacheablePatterns as $pattern) {
            if (mb_strpos($normalized, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * FIX #13: Build cache key for user-specific caching
     */
    protected function buildCacheKey(string $message): string
    {
        $normalized = $this->normalize($message);
        // Hash to create consistent key, user-specific
        return "ai_cache:{$this->user->id}:" . md5($normalized);
    }

    /**
     * FIX #15: Sanitize message for potential prompt injection attacks
     * Removes or neutralizes patterns that could manipulate AI behavior
     */
    protected function sanitizeForPromptInjection(string $message): string
    {
        // Suspicious patterns in Arabic and English
        $suspiciousPatterns = [
            // Arabic injection attempts
            '/انسى\s*(كل\s*)?(التعليمات|الأوامر|القواعد)/ui',
            '/تجاهل\s*(كل\s*)?(التعليمات|الأوامر|القواعد)/ui',
            '/ابدأ\s*من\s*جديد/ui',
            '/أنت\s*الآن\s*(مساعد|روبوت)/ui',
            '/تصرف\s*كـ?أنك/ui',
            '/غير\s*(سلوكك|شخصيتك)/ui',

            // English injection attempts
            '/ignore\s+(all\s+)?(previous|above)\s+(instructions|rules|prompts)/i',
            '/forget\s+(everything|all|previous)/i',
            '/you\s+are\s+(now|a)\s+(new|different)/i',
            '/act\s+as\s+(if|a)/i',
            '/disregard\s+(all|previous)/i',
            '/new\s+instructions?:/i',
            '/system\s*:\s*/i',
            '/\[INST\]/i',
            '/\[\/INST\]/i',
            '/<<SYS>>/i',
            '/<<\/SYS>>/i',

            // Code/markup injection
            '/```\s*(system|prompt|instruction)/i',
            '/<\s*system\s*>/i',
            '/<\s*prompt\s*>/i',
        ];

        $sanitized = $message;
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $sanitized)) {
                // Replace suspicious content with neutral message
                $sanitized = preg_replace($pattern, '[...]', $sanitized);
            }
        }

        // Limit message length to prevent token abuse
        if (mb_strlen($sanitized) > 500) {
            $sanitized = mb_substr($sanitized, 0, 500);
            Log::info('GroqChatV2: Message truncated', ['original_length' => mb_strlen($message)]);
        }

        return $sanitized;
    }

    /**
     * FAST PATH - Handle common cases without AI to save cost
     * FIX #7: Expanded to handle ~60% of messages
     * Returns null if AI should handle it
     */
    protected function tryFastPath(AiChatSession $session, Lead $lead, string $message): ?array
    {
        $normalized = $this->normalize($message);
        $msgLen = mb_strlen($normalized);
        $storeContext = $session->store_context ?? [];
        $hasCart = !empty($session->cart);

        // === VERY SHORT MESSAGES (< 30 chars) ===
        if ($msgLen < 30) {
            // Greetings - no cart context needed
            if ($this->isSimpleGreeting($normalized)) {
                return $this->handleSimpleGreeting($session);
            }

            // Thanks
            if ($this->containsKeyword($normalized, self::THANKS_KEYWORDS)) {
                return $this->handleSimpleThanks($session);
            }

            // FIX #10: Customer info confirmation
            if (!empty($storeContext['awaiting_info_confirmation']) && $this->isSimpleYes($normalized)) {
                return $this->handleInfoConfirmation($session, $lead);
            }

            // User said yes after AI offered to show products — browse, NOT confirm order
            if (!empty($storeContext['awaiting_browse_offer']) && $this->isSimpleYes($normalized)) {
                $storeContext['awaiting_browse_offer'] = false;
                $session->store_context = $storeContext;
                $session->save();
                $categories = $this->getStoreCategories();
                $catNames   = array_map(fn($c) => $c['name'], $categories);
                $reply = !empty($catNames)
                    ? 'بكل سرور! عندنا هاي الأقسام:' . "\n• " . implode("\n• ", $catNames) . "\n\nشنو القسم يعجبك؟"
                    : 'تفضل! وريني شنو تحتاج وراح أساعدك تلكي المناسب';
                $session->addMessage('assistant', $reply);
                return $this->buildResponse($reply, 'product_categories', $session, false);
            }

            // Yes confirmation when awaiting order confirmation
            if (!empty($storeContext['awaiting_confirmation']) && $this->isSimpleYes($normalized)) {
                // Set explicit confirmation flag for order creation
                $storeContext['explicit_order_confirmation'] = true;
                $session->store_context = $storeContext;
                $session->save();
                return $this->handleConfirmation($session, $lead, $message);
            }

            // FIX: Handle explicit order confirmation phrases like "نعم اكد الطلب", "اكد الطلب"
            if ($this->containsConfirmationPhrase($normalized)) {
                $storeContext['explicit_order_confirmation'] = true;
                $session->store_context = $storeContext;
                $session->save();
                return $this->handleConfirmation($session, $lead, $message);
            }

            // No/Cancel when awaiting any confirmation
            if ((!empty($storeContext['awaiting_confirmation']) || !empty($storeContext['awaiting_info_confirmation']))
                && $this->isSimpleNo($normalized)) {
                return $this->handleSimpleCancel($session);
            }

            // FIX #24: "المزيد" for pagination
            if ($this->containsKeyword($normalized, ['المزيد', 'اكثر', 'أكثر', 'more', 'زيادة', 'باقي'])) {
                if (!empty($storeContext['last_search_results']) && !empty($storeContext['search_page'])) {
                    return $this->handleMoreResults($session);
                }
            }

            // Pure number after quantity question
            if (!empty($storeContext['asking_quantity']) && preg_match('/^\d+$/', $normalized)) {
                return $this->handleQuantityResponse($session, $lead, (int)$normalized);
            }
        }

        // === MEDIUM MESSAGES (< 50 chars) ===
        if ($msgLen < 50) {
            // "Add it" / "اضفه" / "حطه بالسلة" with last mentioned product
            $lastProduct = $storeContext['last_mentioned_product'] ?? null;
            if ($lastProduct && $this->isSimpleAddRequest($normalized)) {
                return $this->handleSimpleAddToCart($session, $lastProduct);
            }

            // FIX: Handle quantity requests like "اريد قطعتين" or "اريد 3" with last mentioned product
            if ($lastProduct && $this->isQuantityRequest($normalized)) {
                $quantity = $this->extractQuantityFromText($normalized);
                if ($quantity > 0) {
                    Log::info('GroqChatV2: Fast path - Quantity request detected', [
                        'normalized' => $normalized,
                        'quantity' => $quantity,
                        'product' => $lastProduct['name'],
                    ]);
                    return $this->handleAddWithQuantity($session, $lastProduct, $quantity);
                }
            }

            // FIX: Handle price-based selection like "ابو ال ٥٠" or "اريد ابو 50"
            $lastShown = $storeContext['last_shown_products'] ?? [];
            Log::info('GroqChatV2: Checking price selection fast path', [
                'normalized' => $normalized,
                'last_shown_count' => count($lastShown),
                'last_shown_products' => array_map(fn($p) => $p['name'] ?? 'unnamed', $lastShown),
            ]);
            if (!empty($lastShown) && $this->isPriceSelectionRequest($normalized)) {
                $selectedProduct = $this->findProductByPriceSelection($normalized, $lastShown);
                if ($selectedProduct) {
                    Log::info('GroqChatV2: Fast path found product by price', ['product' => $selectedProduct['name']]);
                    return $this->handleSimpleAddToCart($session, $selectedProduct);
                }
            }

            // Cart summary request
            if ($this->containsKeyword($normalized, ['سلتي', 'سلة طلبي', 'شنو طلبي', 'ملخص طلبي'])) {
                return $this->handleCartSummary($session, $lead, $message);
            }

            // Price question for last mentioned product
            if ($lastProduct && $this->isPriceQuestion($normalized)) {
                return $this->handlePriceQuestion($session, $lastProduct);
            }

            // Delivery info question
            if ($this->containsKeyword($normalized, self::DELIVERY_KEYWORDS)) {
                return $this->handleDeliveryInfo($session, $message);
            }

            // Cancel keywords
            if ($this->containsKeyword($normalized, self::CANCEL_KEYWORDS)) {
                return $this->handleCancel($session, $message);
            }

            // Remove keywords with cart
            if ($hasCart && $this->containsKeyword($normalized, self::REMOVE_KEYWORDS)) {
                return $this->handleRemoval($session, $message);
            }
        }

        // === CUSTOMER INFO DETECTION ===
        if ($this->looksLikeCustomerInfo($message)) {
            return $this->handleCustomerInfo($session, $lead, $message);
        }

        return null; // Let AI handle complex cases
    }

    /**
     * Check if message is a simple add request
     */
    protected function isSimpleAddRequest(string $normalized): bool
    {
        $addPhrases = ['اضفه', 'اضيفه', 'حطه', 'حطه بالسلة', 'اريده', 'ابيه', 'اخذه', 'اوكي اضفه', 'نعم اضفه', 'اي اضفه', 'add', 'add it'];
        return $this->containsKeyword($normalized, $addPhrases);
    }

    /**
     * Check if message is a quantity-based add request (e.g., "اريد قطعتين", "اريد 3")
     */
    protected function isQuantityRequest(string $normalized): bool
    {
        // Check for "اريد" + quantity pattern
        if (preg_match('/(?:اريد|ابي|ابغى|اعطني)\s*(?:قطعتين|ثنتين|وحدتين|\d+|قطع)/u', $normalized)) {
            return true;
        }
        // Check for just quantity words that imply adding
        if (preg_match('/^(?:قطعتين|ثنتين|وحدتين|\d+\s*(?:قطع|حبات?|قطعه?))$/u', $normalized)) {
            return true;
        }
        return false;
    }

    /**
     * Handle add to cart with specific quantity
     */
    protected function handleAddWithQuantity(AiChatSession $session, array $product, int $quantity): array
    {
        // Verify product still exists and has stock
        $dbProduct = Product::where('user_id', $this->user->id)
            ->where('id', $product['id'])
            ->where('is_active', true)
            ->first();

        if (!$dbProduct) {
            $reply = 'عذراً، هذا المنتج غير متوفر حالياً. شنو تحتاج ثاني؟';
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'product_unavailable', $session, false);
        }

        // Check stock
        $availableStock = (int) $dbProduct->quantity;
        if ($availableStock <= 0) {
            $reply = "عذراً، {$dbProduct->name} نفذ من المخزن حالياً.";
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'out_of_stock', $session, false);
        }

        // Adjust quantity if needed
        $finalQty = min($quantity, $availableStock);

        $session->addToCart($dbProduct->name, (int)$dbProduct->price, $finalQty);

        // Update last mentioned
        $storeContext = $session->store_context ?? [];
        $storeContext['last_mentioned_product'] = [
            'id' => $dbProduct->id,
            'name' => $dbProduct->name,
            'price' => (int)$dbProduct->price,
        ];
        $session->store_context = $storeContext;
        $session->save();

        // Build reply with cart summary and confirmation prompt
        $total = $session->getCartTotal();
        $cartItems = $session->cart ?? [];
        $itemCount = count($cartItems);

        $reply = "تم إضافة {$dbProduct->name} ({$finalQty} قطع) للسلة.\n";
        $reply .= "المجموع: {$total} دينار.\n";
        $reply .= "تريد تأكد الطلب؟ رد بـ نعم أو لا";

        // Set awaiting confirmation
        $storeContext['awaiting_confirmation'] = true;
        $session->store_context = $storeContext;
        $session->save();

        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'added_to_cart', $session, false, null, null, [$dbProduct->id]);
    }

    /**
     * Check if message is asking about price
     */
    protected function isPriceQuestion(string $normalized): bool
    {
        $priceWords = ['كم سعره', 'شكد سعره', 'بكم', 'شكد', 'كم قيمته', 'السعر'];
        return $this->containsKeyword($normalized, $priceWords);
    }

    /**
     * Handle simple add to cart from last mentioned product
     */
    protected function handleSimpleAddToCart(AiChatSession $session, array $product): array
    {
        // Verify product still exists and has stock
        $dbProduct = Product::where('user_id', $this->user->id)
            ->where('id', $product['id'])
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->first();

        if (!$dbProduct) {
            $reply = 'عذراً، هذا المنتج غير متوفر حالياً. شنو تحتاج ثاني؟';
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'product_unavailable', $session, false);
        }

        $session->addToCart($dbProduct->name, (int)$dbProduct->price, 1);

        // Update last mentioned product
        $storeContext = $session->store_context ?? [];
        $storeContext['last_mentioned_product'] = [
            'id' => $dbProduct->id,
            'name' => $dbProduct->name,
            'price' => (int)$dbProduct->price,
        ];
        // Set awaiting confirmation
        $storeContext['awaiting_confirmation'] = true;
        $session->store_context = $storeContext;
        $session->save();

        $total = $session->getCartTotal();
        $reply = "تمت إضافة {$dbProduct->name} للسلة.\n";
        $reply .= "المجموع: {$total} دينار.\n";
        $reply .= "تريد تأكد الطلب؟ رد بـ نعم أو لا";
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'added_to_cart', $session, false, null, null, [$dbProduct->id]);
    }

    /**
     * Check if message is a price-based selection (e.g., "ابو ال 50" or "اريد ابو 50000")
     */
    protected function isPriceSelectionRequest(string $normalized): bool
    {
        // NOTE: $normalized already has Arabic digits converted to English by normalize()
        // Also "ال" prefix is stripped, so "اريد ابو ال 50" becomes "اريد ابو 50"

        // Check for messages containing "ابو" or "ابي" + number (with anything in between)
        // e.g., "اريد ابو 50", "ابو 50", "اريد ابو ال 50000", "ابي 50"
        if (preg_match('/(?:ابو|ابي)[^0-9]*(\d+)/u', $normalized)) {
            return true;
        }
        // Check for "اريد" followed by number
        if (preg_match('/اريد[^0-9]*(\d+)/u', $normalized)) {
            return true;
        }
        // Check for "الاول" "الثاني" etc. (ordinal selection)
        if (preg_match('/(?:الاول|الثاني|الثالث|الرابع|الخامس|اول|ثاني|ثالث|رابع|خامس)/u', $normalized)) {
            return true;
        }
        return false;
    }

    /**
     * Find product from shown products by price selection
     */
    protected function findProductByPriceSelection(string $normalized, array $shownProducts): ?array
    {
        // NOTE: $normalized already has Arabic digits converted to English by normalize()

        // Extract price from message
        if (preg_match('/(\d+)/u', $normalized, $match)) {
            $price = (int)$match[1];

            // Normalize price (50 -> 50000, 55 -> 55000)
            if ($price < 1000) {
                $price *= 1000;
            }

            Log::info('GroqChatV2: Price selection detected', [
                'normalized' => $normalized,
                'extracted_price' => $price,
                'shown_products' => array_map(fn($p) => ['name' => $p['name'], 'price' => $p['price']], $shownProducts),
            ]);

            // Find product with matching price
            foreach ($shownProducts as $product) {
                if ((int)$product['price'] === $price) {
                    Log::info('GroqChatV2: Found product by price', ['product' => $product['name']]);
                    return $product;
                }
            }
        }

        // Check for ordinal selection (الاول، الثاني)
        // Note: normalize() strips "ال" prefix, so "الاول" becomes "اول"
        $ordinals = [
            'الاول' => 0, 'الأول' => 0, 'اول' => 0, 'أول' => 0,
            'الثاني' => 1, 'الثانى' => 1, 'ثاني' => 1, 'ثانى' => 1,
            'الثالث' => 2, 'ثالث' => 2,
            'الرابع' => 3, 'رابع' => 3,
            'الخامس' => 4, 'خامس' => 4,
        ];

        foreach ($ordinals as $word => $index) {
            if (mb_stripos($normalized, $word) !== false && isset($shownProducts[$index])) {
                return $shownProducts[$index];
            }
        }

        return null;
    }

    /**
     * Handle price question for a product
     */
    protected function handlePriceQuestion(AiChatSession $session, array $product): array
    {
        $reply = "سعر {$product['name']} هو {$product['price']} دينار. تريد أضيفه للسلة؟";
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'price_info', $session, false, null, null, [$product['id']]);
    }

    /**
     * Handle quantity response after asking "how many?"
     */
    protected function handleQuantityResponse(AiChatSession $session, Lead $lead, int $quantity): array
    {
        $storeContext = $session->store_context ?? [];
        $lastProduct = $storeContext['last_mentioned_product'] ?? null;

        if (!$lastProduct) {
            $reply = 'شنو المنتج الي تريده؟';
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'ask_product', $session, false);
        }

        // Clear the asking_quantity flag
        $storeContext['asking_quantity'] = false;
        $session->store_context = $storeContext;
        $session->save();

        // Add with specified quantity (executeAddToCart will validate stock)
        $this->executeAddToCart($session, [
            'product_id' => $lastProduct['id'],
            'product_name' => $lastProduct['name'],
            'quantity' => $quantity,
        ]);

        $total = $session->getCartTotal();
        $reply = "تمت إضافة {$quantity} من {$lastProduct['name']}. المجموع: {$total} دينار. تريد شي ثاني؟";
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'quantity_set', $session, false);
    }

    /**
     * Handle simple thanks message
     */
    protected function handleSimpleThanks(AiChatSession $session): array
    {
        $replies = ['عفواً! شنو تحتاج ثاني؟', 'أهلاً بيك! خدمة ثانية؟', 'تسلم! شنو تريد بعد؟'];
        $reply = $replies[array_rand($replies)];
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'thanks', $session, false);
    }

    /**
     * Handle customer info confirmation
     */
    protected function handleInfoConfirmation(AiChatSession $session, Lead $lead): array
    {
        // Confirm the pending customer info
        $this->confirmCustomerInfo($session, $lead);

        // Clear the confirmation flag
        $storeContext = $session->store_context ?? [];
        $storeContext['awaiting_info_confirmation'] = false;
        $session->store_context = $storeContext;
        $session->save();

        $reply = 'تم حفظ معلوماتك بنجاح! شنو تحتاج ثاني؟';
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'info_confirmed', $session, false);
    }

    /**
     * FIX #24: Handle "المزيد" for search pagination
     */
    protected function handleMoreResults(AiChatSession $session): array
    {
        $storeContext = $session->store_context ?? [];
        $results = $storeContext['last_search_results'] ?? [];
        $currentPage = $storeContext['search_page'] ?? 1;
        $total = $storeContext['search_total'] ?? count($results);
        $perPage = 5;

        // Move to next page
        $nextPage = $currentPage + 1;
        $maxPage = ceil(count($results) / $perPage);

        if ($nextPage > $maxPage) {
            $reply = 'هذي كل النتائج. شنو تحتاج ثاني؟';
            // Clear search state
            unset($storeContext['last_search_results']);
            unset($storeContext['search_page']);
            unset($storeContext['search_total']);
            $session->store_context = $storeContext;
            $session->save();
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'no_more_results', $session, false);
        }

        // Get next page results
        $reply = $this->formatSearchResultsWithPagination($results, $total, $nextPage, $perPage);

        // Update page number
        $storeContext['search_page'] = $nextPage;
        $session->store_context = $storeContext;
        $session->save();

        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'more_results', $session, false);
    }

    /**
     * Store search results for pagination
     */
    protected function storeSearchResultsForPagination(AiChatSession $session, array $results, int $total): void
    {
        $storeContext = $session->store_context ?? [];
        $storeContext['last_search_results'] = $results;
        $storeContext['search_page'] = 1;
        $storeContext['search_total'] = $total;
        $session->store_context = $storeContext;
        $session->save();
    }

    /**
     * AI-FIRST: Main AI processing with full context
     */
    protected function processWithAI(AiChatSession $session, Lead $lead, string $message): array
    {
        // Build comprehensive context for AI
        $systemPrompt = $this->buildComprehensiveSystemPrompt($session, $lead);
        $conversationHistory = $this->buildConversationHistory($session);

        // Call AI with full context
        $aiResponse = $this->callAIWithFullContext($systemPrompt, $conversationHistory, $message);

        Log::info('GroqChatV2: AI Response', [
            'intent' => $aiResponse['intent'] ?? 'unknown',
            'has_actions' => !empty($aiResponse['actions']),
            'actions' => $aiResponse['actions'] ?? [],
            'product_ids' => $aiResponse['product_ids'] ?? [],
            'reply_preview' => mb_substr($aiResponse['reply'] ?? '', 0, 100),
        ]);

        // Execute any actions AI requested
        if (!empty($aiResponse['actions'])) {
            $this->executeAIActions($session, $lead, $aiResponse['actions']);
        }

        // FIX: If AI reply mentions adding to cart but no add_to_cart action, try to add from context
        $reply = $aiResponse['reply'] ?? 'عذرا، حصل خطأ. شنو تحتاج؟';
        if ($this->replyMentionsAdding($reply) && empty($aiResponse['actions'])) {
            Log::warning('GroqChatV2: AI said it will add but no action provided, attempting fallback');
            $this->tryAddFromContext($session, $aiResponse);
        }

        // Save assistant message
        $session->addMessage('assistant', $reply);

        // Detect if AI offered to show products — override awaiting_confirmation so
        // the next bare "yes" from the customer means "show me products", not "confirm order"
        if (!empty(($session->store_context ?? [])['awaiting_confirmation']) && $this->replyOffersToShowProducts($reply)) {
            $ctx = $session->store_context ?? [];
            $ctx['awaiting_browse_offer'] = true;
            $session->store_context = $ctx;
            $session->save();
            Log::info('GroqChatV2: Detected product-browse offer in AI reply — set awaiting_browse_offer');
        }

        // Build response with product IDs if any
        $productIds = $aiResponse['product_ids'] ?? [];

        // FIX: Store products from product_ids as last_shown_products for future reference
        if (!empty($productIds)) {
            $products = Product::where('user_id', $this->user->id)
                ->whereIn('id', $productIds)
                ->select('id', 'name', 'price')
                ->get()
                ->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'price' => (int)$p->price])
                ->toArray();

            if (!empty($products)) {
                $this->storeShownProducts($session, $products);
                Log::info('GroqChatV2: Stored products from AI response', [
                    'product_ids' => $productIds,
                    'products_count' => count($products),
                ]);
            }
        }

        // FIX: If AI returned product_ids but no send_image action, populate images
        // This ensures images are sent when AI says "ستصل الصورة" with product_ids
        if (!empty($productIds)) {
            $storeContext = $session->store_context ?? [];
            $hasPendingImages = !empty($storeContext['pending_images']) || !empty($storeContext['pending_image_url']);

            // Only add if no images were already queued by send_image actions
            if (!$hasPendingImages) {
                foreach ($productIds as $productId) {
                    $this->executeSendImage($session, ['product_id' => $productId]);
                }
            }
        }

        return $this->buildResponse(
            $reply,
            $aiResponse['intent'] ?? 'ai_response',
            $session,
            !empty($aiResponse['needs_response']),
            null,
            null,
            $productIds
        );
    }

    /**
     * Build comprehensive system prompt with ALL store context
     * SMART AGENT: Includes customer history, best sellers, and intelligent instructions
     */
    protected function buildComprehensiveSystemPrompt(AiChatSession $session, Lead $lead): string
    {
        $storeName = $this->user->name ?? 'المتجر';
        $deliveryCost = $this->settings->delivery_cost ?? 5000;
        $deliveryTime = $this->settings->delivery_time ?? 'نفس اليوم او اليوم التالي';

        // Get ALL categories with products
        $categoriesData = $this->getAllCategoriesWithProducts();

        // Build cart context
        $cartContext = $this->buildCartContextForAI($session);

        // Build customer context
        $customerContext = $this->buildCustomerContextForAI($session, $lead);

        // SMART AGENT: Get customer intelligence
        $customerIntelligence = $this->buildCustomerIntelligence($lead);

        // SMART AGENT: Get best sellers
        $bestSellersContext = $this->buildBestSellersContext();

        // DISABLED: Cross-sell/upsell removed - user doesn't want suggestions
        $crossSellContext = '';
        $upsellContext = '';

        // Get last shown/mentioned products
        $storeContext = $session->store_context ?? [];
        $lastMentioned = $storeContext['last_mentioned_product'] ?? null;
        $lastShown = $storeContext['last_shown_products'] ?? [];

        // Build last shown products context for price-based selection
        $lastShownContext = '';
        if (!empty($lastShown)) {
            $lastShownContext = "\n## المنتجات المعروضة حالياً (للاختيار بالسعر)\n";
            foreach ($lastShown as $idx => $product) {
                $num = $idx + 1;
                $lastShownContext .= "- [{$product['id']}] {$product['name']}: {$product['price']} دينار (الخيار رقم {$num})\n";
            }
        }

        $systemPrompt = <<<PROMPT
أنت وكيل مبيعات ذكي لمتجر "{$storeName}" في العراق. تتحدث باللهجة العراقية وتتصرف كموظف مبيعات محترف.

## معلومات المتجر
- اسم المتجر: {$storeName}
- تكلفة التوصيل: {$deliveryCost} دينار
- وقت التوصيل: {$deliveryTime}
- طريقة الدفع: الدفع نقداً عند الاستلام فقط
{$bestSellersContext}
{$customerIntelligence}
{$crossSellContext}
{$upsellContext}
## الأقسام والمنتجات المتوفرة (استخدم هذه فقط - لا تخترع منتجات أبداً)
{$categoriesData}
{$lastShownContext}
## حالة السلة الحالية
{$cartContext}

## معلومات الزبون
{$customerContext}

PROMPT;

        if ($lastMentioned) {
            $systemPrompt .= "\n## آخر منتج تم ذكره\n";
            $systemPrompt .= "المنتج: [{$lastMentioned['id']}] {$lastMentioned['name']} - السعر: {$lastMentioned['price']} دينار\n";
        }

        $systemPrompt .= <<<RULES

## قواعد الوكيل الذكي
1. افهم السياق - إذا الزبون قال "اريد اسود بسعر 17" وعرضت له بنطرونات، فهو يريد البنطرون الاسود بـ 17000
2. استخدم قائمة "المنتجات المعروضة حالياً" لفهم اختيار الزبون بالسعر أو الرقم
3. إذا قال "ابو 17" أو "بسعر 17" ابحث في المنتجات المعروضة عن سعر 17000
4. إذا قال "الاول" أو "الثاني" استخدم رقم الخيار من القائمة
5. ممنوع تماماً ذكر أي منتج غير موجود في القائمة
6. ممنوع تماماً اختراع أسعار - استخدم الأسعار الحقيقية فقط
7. ممنوع استخدام إيموجي نهائياً
8. أجوبتك تكون مختصرة - 3 جمل كحد أقصى
9. لا تكتب product_ids في الرد - هذا للنظام فقط وليس للزبون

## قاعدة مهمة جداً - عرض السلة وطلب التأكيد
- عندما الزبون يرفض اقتراح إضافي (قال "لا" أو "شكرا" أو "بس هيج") والسلة فيها منتجات:
  1. اعرض ملخص السلة (المنتجات مع الأسعار والمجموع)
  2. اسأل: "تريد نأكد الطلب؟"
- مثال الرد: "تمام! سلتك: بنطرون رسمي اسود 20,000 دينار. المجموع: 20,000 + توصيل 5,000 = 25,000 دينار. تريد نأكد الطلب؟"

## سلوك السلة - مهم جداً
- بعد إضافة منتج: اعرض السلة واسأل "تريد تضيف شي ثاني؟"
- إذا قال "لا" أو "ما اريد" أو "بس" -> اعرض ملخص السلة الكامل واسأل "تريد نأكد الطلب؟"
- لا تقترح منتجات إضافية أبداً
- لا تلغي الطلب إلا إذا قال صراحة "الغي الطلب"

## قواعد الإجراءات (Actions)
- إضافة للسلة: استخدم add_to_cart مع product_id وproduct_name وprice وquantity
- تغيير الكمية: استخدم update_quantity مع product_name وquantity
- حذف من السلة: استخدم remove_from_cart مع product_name
- طلب صورة: استخدم send_image مع product_id
- تأكيد الطلب: استخدم create_order (فقط إذا المعلومات كاملة والزبون أكد)
- حفظ معلومات الزبون: استخدم set_customer_info مع name/phone/address
- عرض السلة: استخدم show_cart لعرض محتويات السلة

## صيغة الرد (JSON فقط - بدون نص إضافي)
{
    "intent": "greeting|inquiry|add_to_cart|confirm_order|decline_suggestion|...",
    "reply": "الرد للزبون باللهجة العراقية - بدون product_ids",
    "actions": [{"type": "add_to_cart", "product_id": 30, "product_name": "بنطرون قماش", "price": 17000, "quantity": 1}],
    "product_ids": [30, 29],
    "needs_response": false
}

## أمثلة مهمة
- الزبون: "اريد اسود بسعر 17" (بعد عرض بنطرونات) -> استخدم add_to_cart للبنطرون الاسود بـ 17000
- الزبون: "ابو 17 الف" -> ابحث عن منتج بسعر 17000 في المنتجات المعروضة
- الزبون: "اي نعم" (بعد عرض منتج) -> استخدم add_to_cart لآخر منتج ذكرته
- الزبون: "اريد هم بنطرون" (عنده شي بالسلة) -> ابحث عن بنطرون واعرض الخيارات
- الزبون: "نعم اكد الطلب" (المعلومات كاملة) -> استخدم create_order
- بعد إضافة للسلة: "تم! سلتك: [المنتجات]. تريد تضيف شي ثاني؟"
- الزبون: "لا" أو "ما اريد شي" -> اعرض السلة مع المجموع الكلي واسأل "تريد نأكد الطلب؟"
RULES;

        return $systemPrompt;
    }

    /**
     * SMART AGENT: Build customer intelligence context
     * Includes purchase history, preferences, VIP status
     */
    protected function buildCustomerIntelligence(Lead $lead): string
    {
        $context = "";

        // Check if returning customer
        $totalOrders = $lead->total_orders ?? 0;
        $totalSpent = $lead->total_spent ?? 0;

        if ($totalOrders > 0) {
            $context .= "\n## معلومات العميل (زبون سابق)\n";
            $context .= "- عدد الطلبات السابقة: {$totalOrders}\n";
            $context .= "- إجمالي المشتريات: " . number_format($totalSpent, 0) . " دينار\n";

            // VIP detection
            if ($totalSpent > 100000 || $totalOrders >= 3) {
                $context .= "- حالة العميل: VIP (عامله باحترام خاص)\n";
            }

            // Get last order items for recommendations
            $lastOrder = OnlineOrder::where('lead_id', $lead->id)
                ->with('items')
                ->latest()
                ->first();

            if ($lastOrder && $lastOrder->items->count() > 0) {
                $lastItems = $lastOrder->items->pluck('product_name')->take(3)->implode('، ');
                $context .= "- آخر طلب: {$lastItems}\n";
                $context .= "- يمكنك اقتراح: 'تريد نفس طلبك السابق؟'\n";
            }
        }

        // Customer interests
        $interests = $lead->interests ?? [];
        if (!empty($interests) && is_array($interests)) {
            $context .= "- اهتماماته: " . implode('، ', array_slice($interests, 0, 5)) . "\n";
        }

        // Admin notes
        if (!empty($lead->notes)) {
            $context .= "- ملاحظات: {$lead->notes}\n";
        }

        return $context;
    }

    /**
     * SMART AGENT: Build best sellers context
     */
    protected function buildBestSellersContext(): string
    {
        // Get top selling products
        $topProducts = OnlineOrderItem::select('product_name', \DB::raw('SUM(quantity) as total_sold'))
            ->whereHas('order', function($q) {
                $q->where('user_id', $this->user->id)
                  ->where('status', '!=', 'cancelled')
                  ->where('created_at', '>=', now()->subDays(30));
            })
            ->groupBy('product_name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        if ($topProducts->isEmpty()) {
            return "";
        }

        $context = "\n## الأكثر مبيعاً (يمكنك اقتراحها)\n";
        foreach ($topProducts as $product) {
            $context .= "- {$product->product_name} (بيع {$product->total_sold} قطعة)\n";
        }

        return $context;
    }

    /**
     * SMART AGENT: Build cross-sell suggestions based on cart contents
     * Suggests complementary products that go well with what's in cart
     */
    protected function buildCrossSellSuggestions(AiChatSession $session): string
    {
        $cart = $session->cart ?? [];
        if (empty($cart)) {
            return "";
        }

        // Get cart product names and categories
        $cartProductNames = array_column($cart, 'name');
        $cartProducts = Product::where('user_id', $this->user->id)
            ->whereIn('name', $cartProductNames)
            ->with('category')
            ->get();

        if ($cartProducts->isEmpty()) {
            return "";
        }

        // Get category IDs from cart
        $cartCategoryIds = $cartProducts->pluck('category_id')->unique()->toArray();
        $cartProductIds = $cartProducts->pluck('id')->toArray();

        // Find frequently bought together (products from same orders)
        $frequentlyBoughtWith = OnlineOrderItem::select('product_name', \DB::raw('COUNT(*) as frequency'))
            ->whereHas('order', function($q) use ($cartProductNames) {
                $q->where('user_id', $this->user->id)
                  ->where('status', '!=', 'cancelled')
                  ->whereHas('items', function($qi) use ($cartProductNames) {
                      $qi->whereIn('product_name', $cartProductNames);
                  });
            })
            ->whereNotIn('product_name', $cartProductNames)
            ->groupBy('product_name')
            ->orderByDesc('frequency')
            ->limit(3)
            ->get();

        $suggestions = [];

        // Add frequently bought together
        if ($frequentlyBoughtWith->isNotEmpty()) {
            foreach ($frequentlyBoughtWith as $item) {
                // Verify product still exists and in stock
                $product = Product::where('user_id', $this->user->id)
                    ->where('name', $item->product_name)
                    ->where('is_active', true)
                    ->where('quantity', '>', 0)
                    ->first();
                if ($product) {
                    $suggestions[] = [
                        'name' => $product->name,
                        'price' => $product->price,
                        'reason' => 'زبائن ثانيين اشتروه مع طلبك'
                    ];
                }
            }
        }

        // Add products from same category (different from cart)
        if (count($suggestions) < 3) {
            $sameCategory = Product::where('user_id', $this->user->id)
                ->whereIn('category_id', $cartCategoryIds)
                ->whereNotIn('id', $cartProductIds)
                ->where('is_active', true)
                ->where('quantity', '>', 0)
                ->inRandomOrder()
                ->limit(3 - count($suggestions))
                ->get();

            foreach ($sameCategory as $product) {
                $suggestions[] = [
                    'name' => $product->name,
                    'price' => $product->price,
                    'reason' => 'من نفس القسم'
                ];
            }
        }

        if (empty($suggestions)) {
            return "";
        }

        $context = "\n## اقتراحات للبيع (Cross-sell) - اقترحها بذكاء\n";
        foreach ($suggestions as $suggestion) {
            $context .= "- {$suggestion['name']}: " . number_format($suggestion['price']) . " دينار ({$suggestion['reason']})\n";
        }
        $context .= "مثال: 'شنو رأيك تضيف {$suggestions[0]['name']}؟ زبائن ثانيين حبوه'\n";

        return $context;
    }

    /**
     * SMART AGENT: Build upsell suggestions for higher-value alternatives
     */
    protected function buildUpsellSuggestions(AiChatSession $session): string
    {
        $cart = $session->cart ?? [];
        if (empty($cart)) {
            return "";
        }

        $suggestions = [];

        foreach ($cart as $cartItem) {
            $cartProduct = Product::where('user_id', $this->user->id)
                ->where('name', $cartItem['name'])
                ->with('category')
                ->first();

            if (!$cartProduct || !$cartProduct->category) {
                continue;
            }

            // Find higher-priced alternative in same category
            $higherPriced = Product::where('user_id', $this->user->id)
                ->where('category_id', $cartProduct->category_id)
                ->where('id', '!=', $cartProduct->id)
                ->where('price', '>', $cartProduct->price)
                ->where('price', '<=', $cartProduct->price * 1.5) // Max 50% more expensive
                ->where('is_active', true)
                ->where('quantity', '>', 0)
                ->orderBy('price', 'asc')
                ->first();

            if ($higherPriced) {
                $priceDiff = $higherPriced->price - $cartProduct->price;
                $suggestions[] = [
                    'current' => $cartProduct->name,
                    'upgrade' => $higherPriced->name,
                    'upgrade_price' => $higherPriced->price,
                    'difference' => $priceDiff
                ];
            }
        }

        if (empty($suggestions)) {
            return "";
        }

        $context = "\n## ترقية ذكية (Upsell) - اقترحها إذا مناسب\n";
        foreach ($suggestions as $s) {
            $context .= "- بدل '{$s['current']}' اقترح '{$s['upgrade']}' بـ " . number_format($s['upgrade_price']) . " دينار (فرق " . number_format($s['difference']) . ")\n";
        }
        $context .= "مثال: 'في عندنا {$suggestions[0]['upgrade']} بجودة أعلى، تريد تشوفه؟'\n";

        return $context;
    }

    /**
     * Get categories with products for AI context - BALANCED VERSION
     * Shows category names + products with prices (essential for accurate responses)
     */
    protected function getAllCategoriesWithProducts(): string
    {
        $categories = Category::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->withCount(['products' => function($q) {
                $q->where('is_active', true)->where('quantity', '>', 0);
            }])
            ->get();

        if ($categories->isEmpty()) {
            return "لا توجد أقسام أو منتجات متوفرة حالياً.";
        }

        $result = [];
        $totalProducts = 0;

        foreach ($categories as $category) {
            if ($category->products_count == 0) continue;
            $totalProducts += $category->products_count;

            // Get products for this category (max 8 per category)
            $products = Product::where('user_id', $this->user->id)
                ->where('category_id', $category->id)
                ->where('is_active', true)
                ->where('quantity', '>', 0)
                ->select('id', 'name', 'price')
                ->orderBy('created_at', 'desc')
                ->limit(8)
                ->get();

            $result[] = "\n### {$category->name} ({$category->products_count} منتج):";

            foreach ($products as $product) {
                $result[] = "- [{$product->id}] {$product->name}: " . number_format($product->price) . " دينار";
            }

            if ($category->products_count > 8) {
                $remaining = $category->products_count - 8;
                $result[] = "  (و {$remaining} منتج آخر)";
            }
        }

        $result[] = "\n\nإجمالي: {$totalProducts} منتج في " . $categories->count() . " قسم";

        return implode("\n", $result);
    }

    /**
     * Get products for a specific category (on-demand loading)
     * Called when customer asks about a specific category
     */
    protected function getCategoryProducts(int $categoryId, int $limit = 10): array
    {
        return Product::where('user_id', $this->user->id)
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->with(['attributes' => function($q) {
                $q->where('quantity', '>', 0)->select('product_id', 'attribute_key', 'attribute_value');
            }])
            ->select('id', 'name', 'price', 'quantity', 'image')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($product) {
                $data = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (int) $product->price,
                    'stock' => (int) $product->quantity,
                    'has_image' => !empty($product->image),
                ];

                // Include attributes
                if ($product->attributes && $product->attributes->count() > 0) {
                    $data['attributes'] = $product->attributes->groupBy('attribute_key')
                        ->map(fn($group) => $group->pluck('attribute_value')->unique()->values()->toArray())
                        ->toArray();
                }

                return $data;
            })
            ->toArray();
    }

    /**
     * Build cart context for AI
     */
    protected function buildCartContextForAI(AiChatSession $session): string
    {
        $cart = $session->cart ?? [];

        if (empty($cart)) {
            return "السلة فارغة";
        }

        $lines = ["السلة تحتوي على:"];
        $total = 0;
        foreach ($cart as $item) {
            $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
            $total += $itemTotal;
            $lines[] = "- {$item['name']} × {$item['quantity']} = {$itemTotal} دينار";
        }
        $lines[] = "المجموع: {$total} دينار";

        return implode("\n", $lines);
    }

    /**
     * Build customer context for AI
     */
    protected function buildCustomerContextForAI(AiChatSession $session, Lead $lead): string
    {
        $customerData = $session->customer_data ?? [];
        $name = $customerData['name'] ?? $lead->name ?? 'غير معروف';
        $phone = $customerData['phone'] ?? $lead->phone ?? 'غير معروف';
        $address = $customerData['address'] ?? $lead->address ?? 'غير معروف';

        $missing = [];
        if ($name === 'غير معروف' || empty($name)) $missing[] = 'الاسم';
        if ($phone === 'غير معروف' || empty($phone)) $missing[] = 'رقم الهاتف';
        if ($address === 'غير معروف' || empty($address)) $missing[] = 'العنوان';

        $result = "الاسم: {$name}\nرقم الهاتف: {$phone}\nالعنوان: {$address}";

        if (!empty($missing)) {
            $result .= "\nالمعلومات الناقصة: " . implode('، ', $missing);
        } else {
            $result .= "\nالمعلومات كاملة - يمكن تأكيد الطلب";
        }

        return $result;
    }

    /**
     * Call AI with full context and get structured response
     * FIX #14: Added accuracy tracking
     */
    protected function callAIWithFullContext(string $systemPrompt, array $conversationHistory, string $message): array
    {
        $startTime = microtime(true);
        $metrics = [
            'user_id' => $this->user->id,
            'message_length' => mb_strlen($message),
            'history_size' => count($conversationHistory),
        ];

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Add conversation history (last 10 messages)
        foreach (array_slice($conversationHistory, -10) as $msg) {
            $messages[] = $msg;
        }

        // Add current user message
        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $result = $this->aiProvider->chat(
                $messages,
                0.2, // Low temperature for consistency
                800, // Max tokens
                'chat',
                $this->currentConversationId,
                $this->currentLeadId
            );

            $metrics['response_time_ms'] = round((microtime(true) - $startTime) * 1000);

            if (!$result['success']) {
                Log::error('GroqChatV2: AI call failed', ['error' => $result['error'] ?? 'Unknown']);
                $this->trackAIMetrics($metrics, false, 'api_error');
                return $this->getFallbackResponse($message);
            }

            $content = trim($result['content'] ?? '');

            // Parse JSON response
            $parsed = $this->parseAIResponse($content);

            // FIX #14: Track parsing success
            $parseSuccess = !empty($parsed['reply']) && $parsed['reply'] !== 'شنو تحتاج؟';
            $metrics['intent'] = $parsed['intent'] ?? 'unknown';
            $metrics['actions_count'] = count($parsed['actions'] ?? []);
            $metrics['product_ids_count'] = count($parsed['product_ids'] ?? []);

            $this->trackAIMetrics($metrics, $parseSuccess, $parseSuccess ? 'success' : 'parse_fallback');

            return $parsed;

        } catch (\Exception $e) {
            $metrics['response_time_ms'] = round((microtime(true) - $startTime) * 1000);
            Log::error('GroqChatV2: AI Exception', ['error' => $e->getMessage()]);
            $this->trackAIMetrics($metrics, false, 'exception');
            return $this->getFallbackResponse($message);
        }
    }

    /**
     * FIX #14: Track AI accuracy and performance metrics
     */
    protected function trackAIMetrics(array $metrics, bool $success, string $status): void
    {
        $metrics['success'] = $success;
        $metrics['status'] = $status;
        $metrics['timestamp'] = now()->toISOString();

        // Log for analysis
        Log::channel('daily')->info('GroqChatV2:AIMetrics', $metrics);

        // Update daily stats in cache
        $statsKey = "ai_stats:{$this->user->id}:" . now()->format('Y-m-d');
        $stats = cache()->get($statsKey, [
            'total_calls' => 0,
            'successful_parses' => 0,
            'failures' => 0,
            'total_response_time_ms' => 0,
        ]);

        $stats['total_calls']++;
        if ($success) {
            $stats['successful_parses']++;
        } else {
            $stats['failures']++;
        }
        $stats['total_response_time_ms'] += ($metrics['response_time_ms'] ?? 0);

        // Cache for 48 hours
        cache()->put($statsKey, $stats, 172800);
    }

    /**
     * Parse AI JSON response
     * FIX #5: Improved JSON extraction with multiple strategies
     */
    protected function parseAIResponse(string $content): array
    {
        // Strategy 1: Try to find JSON block between ```json and ```
        if (preg_match('/```json\s*([\s\S]*?)```/u', $content, $codeBlock)) {
            $decoded = json_decode(trim($codeBlock[1]), true);
            if ($decoded && isset($decoded['reply'])) {
                return $this->validateAndBuildAIResponse($decoded);
            }
        }

        // Strategy 2: Find the LAST complete JSON object (AI sometimes adds text before)
        if (preg_match_all('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/u', $content, $allMatches)) {
            // Try each match starting from the last (most likely the actual response)
            foreach (array_reverse($allMatches[0]) as $jsonCandidate) {
                $decoded = json_decode($jsonCandidate, true);
                if ($decoded && isset($decoded['reply'])) {
                    return $this->validateAndBuildAIResponse($decoded);
                }
            }
        }

        // Strategy 3: Try to fix common JSON errors
        $fixedContent = $this->tryFixBrokenJSON($content);
        if ($fixedContent) {
            $decoded = json_decode($fixedContent, true);
            if ($decoded && isset($decoded['reply'])) {
                return $this->validateAndBuildAIResponse($decoded);
            }
        }

        // Strategy 4: Extract reply from malformed response
        if (preg_match('/"reply"\s*:\s*"([^"]+)"/u', $content, $replyMatch)) {
            Log::warning('GroqChatV2: Extracted reply from malformed JSON');
            return [
                'intent' => 'ai_response',
                'reply' => $replyMatch[1],
                'actions' => [],
                'product_ids' => [],
                'needs_response' => false,
            ];
        }

        // Final fallback: treat entire content as reply (cleaned)
        Log::warning('GroqChatV2: Could not parse JSON, using raw response', [
            'content_preview' => mb_substr($content, 0, 200),
        ]);
        return [
            'intent' => 'ai_response',
            'reply' => $this->cleanAIReply($content),
            'actions' => [],
            'product_ids' => [],
            'needs_response' => false,
        ];
    }

    /**
     * Validate and build AI response from decoded JSON
     */
    protected function validateAndBuildAIResponse(array $decoded): array
    {
        // Validate actions - remove invalid ones
        $validActions = [];
        foreach (($decoded['actions'] ?? []) as $action) {
            if ($this->isValidAction($action)) {
                $validActions[] = $action;
            }
        }

        return [
            'intent' => $decoded['intent'] ?? 'ai_response',
            'reply' => $decoded['reply'] ?? 'شنو تحتاج؟',
            'actions' => $validActions,
            'product_ids' => array_filter($decoded['product_ids'] ?? [], 'is_numeric'),
            'needs_response' => (bool) ($decoded['needs_response'] ?? false),
        ];
    }

    /**
     * Check if an action is valid
     */
    protected function isValidAction(array $action): bool
    {
        $type = $action['type'] ?? '';
        $validTypes = ['add_to_cart', 'update_quantity', 'remove_from_cart', 'set_customer_info', 'create_order', 'send_image', 'ask_info'];

        if (!in_array($type, $validTypes)) {
            return false;
        }

        // Specific validations
        if ($type === 'add_to_cart') {
            return !empty($action['product_id']) || !empty($action['product_name']);
        }

        return true;
    }

    /**
     * Try to fix common JSON syntax errors
     */
    protected function tryFixBrokenJSON(string $content): ?string
    {
        // Extract what looks like JSON
        if (!preg_match('/\{[\s\S]*\}/u', $content, $match)) {
            return null;
        }

        $json = $match[0];

        // Fix: trailing commas before }
        $json = preg_replace('/,\s*}/u', '}', $json);
        // Fix: trailing commas before ]
        $json = preg_replace('/,\s*]/u', ']', $json);
        // Fix: single quotes to double quotes
        $json = preg_replace("/(?<!\\\\)'/u", '"', $json);

        // Validate it's now valid JSON
        json_decode($json);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }

        return null;
    }

    /**
     * Clean AI reply from any JSON artifacts and internal data
     * FIX: Remove product_ids patterns that shouldn't appear in customer messages
     */
    protected function cleanAIReply(string $content): string
    {
        // Remove markdown code blocks completely
        $content = preg_replace('/```(?:json)?[\s\S]*?```/u', '', $content);

        // FIX: Remove product_ids patterns that AI incorrectly includes in text
        // Patterns like: "product_ids: [30, 29]" or "product_ids": [30, 29]
        $content = preg_replace('/\s*["\']?product_ids["\']?\s*[:=]\s*\[[^\]]*\]/ui', '', $content);
        // Also remove standalone patterns like [30, 29] at end of message
        $content = preg_replace('/\s*\[\d+(?:,\s*\d+)*\]\s*$/u', '', $content);

        // FIX #23: Only remove COMPLETE JSON objects (starts with { ends with })
        // Don't remove Arabic text like "السعر يختلف حسب {اللون والمقاس}"
        // A valid JSON must have "key": pattern
        if (preg_match('/^\s*\{[\s\S]*"[^"]+"\s*:[\s\S]*\}\s*$/u', $content)) {
            // This is pure JSON, try to extract reply field
            $decoded = json_decode($content, true);
            if ($decoded && isset($decoded['reply'])) {
                return trim($decoded['reply']);
            }
        }

        // Remove JSON embedded in text (but keep surrounding text)
        // Only match if it looks like a complete JSON object with quoted keys
        $content = preg_replace('/\{\s*"(?:intent|reply|actions)"[\s\S]*?\}/u', '', $content);

        // Remove common AI prefixes
        $prefixes = ['حسناً،', 'بالتأكيد،', 'طبعاً،', 'هذا هو الرد:', 'الرد:'];
        foreach ($prefixes as $prefix) {
            if (mb_strpos($content, $prefix) === 0) {
                $content = mb_substr($content, mb_strlen($prefix));
            }
        }

        $content = trim($content);

        if (empty($content)) {
            return 'شنو تحتاج؟';
        }

        return $content;
    }

    /**
     * FIX #22: Validate product attributes exist and are available
     */
    protected function validateProductAttributes(Product $product, array $requestedAttrs): ?array
    {
        if (empty($requestedAttrs)) {
            return [];
        }

        $validAttrs = [];

        foreach ($requestedAttrs as $key => $value) {
            // Check if this attribute combination exists and has stock
            $exists = $product->attributes()
                ->where('attribute_key', $key)
                ->where('attribute_value', $value)
                ->where('quantity', '>', 0)
                ->exists();

            if ($exists) {
                $validAttrs[$key] = $value;
            } else {
                // Log but don't fail - customer might have mistyped
                Log::info('GroqChatV2: Requested attribute not available', [
                    'product' => $product->name,
                    'key' => $key,
                    'value' => $value,
                ]);
            }
        }

        // Return null only if ALL attributes were invalid
        return empty($validAttrs) && !empty($requestedAttrs) ? null : $validAttrs;
    }

    /**
     * Execute actions requested by AI
     * FIX #11: Actions are executed in priority order
     */
    protected function executeAIActions(AiChatSession $session, Lead $lead, array $actions): void
    {
        // Define priority order (lower = execute first)
        $priorityOrder = [
            'set_customer_info' => 1,  // Set info first
            'add_to_cart' => 2,         // Then add products
            'update_quantity' => 3,     // Then update quantities
            'remove_from_cart' => 4,    // Then remove
            'send_image' => 5,          // Then send images
            'ask_info' => 6,            // Then ask for info
            'create_order' => 99,       // ALWAYS LAST
        ];

        // Sort actions by priority
        usort($actions, function($a, $b) use ($priorityOrder) {
            $prioA = $priorityOrder[$a['type'] ?? ''] ?? 50;
            $prioB = $priorityOrder[$b['type'] ?? ''] ?? 50;
            return $prioA - $prioB;
        });

        foreach ($actions as $action) {
            $type = $action['type'] ?? '';

            Log::info('GroqChatV2: Executing action', ['type' => $type, 'action' => $action]);

            switch ($type) {
                case 'add_to_cart':
                    $this->executeAddToCart($session, $action);
                    break;

                case 'update_quantity':
                    $this->executeUpdateQuantity($session, $action);
                    break;

                case 'remove_from_cart':
                    $this->executeRemoveFromCart($session, $action);
                    break;

                case 'set_customer_info':
                    $this->executeSetCustomerInfo($session, $lead, $action);
                    break;

                case 'create_order':
                    // FIX #2: Only create if explicitly confirmed by customer
                    $ctx = $session->store_context ?? [];
                    if (!empty($ctx['explicit_order_confirmation'])) {
                        $this->executeCreateOrder($session, $lead);
                    } else {
                        Log::warning('GroqChatV2: AI tried to create order without explicit confirmation');
                        // Set awaiting confirmation flag
                        $ctx['awaiting_confirmation'] = true;
                        $session->store_context = $ctx;
                        $session->save();
                    }
                    break;

                case 'send_image':
                    $this->executeSendImage($session, $action);
                    break;

                case 'ask_info':
                    // Just tracking - AI already asked in reply
                    $storeContext = $session->store_context ?? [];
                    $storeContext['asking_for'] = $action['field'] ?? 'info';
                    $session->store_context = $storeContext;
                    $session->save();
                    break;
            }
        }
    }

    /**
     * Execute add to cart action
     * FIX #1, #3, #9: Validates product exists for THIS store with stock check
     */
    protected function executeAddToCart(AiChatSession $session, array $action): void
    {
        $productId = $action['product_id'] ?? null;
        $productName = $action['product_name'] ?? '';
        $requestedQty = $action['quantity'] ?? 1;

        Log::info('GroqChatV2: executeAddToCart called', [
            'action' => $action,
            'product_id' => $productId,
            'product_name' => $productName,
            'quantity' => $requestedQty,
            'session_id' => $session->id,
        ]);

        // CRITICAL: Always verify product exists in THIS store's database
        $product = null;

        if ($productId) {
            // Search by ID - MUST belong to this user
            $product = Product::where('user_id', $this->user->id)
                ->where('id', $productId)
                ->where('is_active', true)
                ->first();

            Log::info('GroqChatV2: Product search by ID', [
                'product_id' => $productId,
                'found' => $product ? true : false,
            ]);
        }

        if (!$product && !empty($productName)) {
            // Fallback: search by name - MUST belong to this user
            $product = Product::where('user_id', $this->user->id)
                ->where('is_active', true)
                ->where('name', 'LIKE', '%' . $productName . '%')
                ->first();

            Log::info('GroqChatV2: Product search by name', [
                'product_name' => $productName,
                'found' => $product ? true : false,
            ]);
        }

        // VALIDATION: Product must exist
        if (!$product) {
            Log::warning('GroqChatV2: AI suggested non-existent product', [
                'product_id' => $productId,
                'product_name' => $productName,
                'user_id' => $this->user->id,
            ]);
            return; // Don't add fake products!
        }

        // VALIDATION: Check stock availability
        $availableStock = (int) $product->quantity;
        if ($availableStock <= 0) {
            Log::warning('GroqChatV2: Product out of stock', [
                'product' => $product->name,
                'stock' => $availableStock,
            ]);
            return; // Out of stock!
        }

        // Adjust quantity if requested more than available
        $quantity = min($requestedQty, $availableStock);
        if ($quantity < $requestedQty) {
            Log::info('GroqChatV2: Adjusted quantity due to stock limit', [
                'requested' => $requestedQty,
                'available' => $availableStock,
                'adjusted' => $quantity,
            ]);
        }

        // FIX #22: Validate product attributes if requested
        $selectedAttributes = $action['attributes'] ?? [];
        if (!empty($selectedAttributes)) {
            $validAttrs = $this->validateProductAttributes($product, $selectedAttributes);
            if ($validAttrs === null) {
                Log::warning('GroqChatV2: Invalid product attributes', [
                    'product' => $product->name,
                    'requested_attrs' => $selectedAttributes,
                ]);
                // Don't block - just log and continue without attributes
            } else {
                $selectedAttributes = $validAttrs;
            }
        }

        // Use REAL data from database (not AI hallucinated data)
        $session->addToCart($product->name, (int) $product->price, $quantity, $selectedAttributes);

        // Store as last mentioned with REAL data
        $storeContext = $session->store_context ?? [];
        $storeContext['last_mentioned_product'] = [
            'id' => $product->id,
            'name' => $product->name,
            'price' => (int) $product->price,
            'stock' => $availableStock,
        ];
        $session->store_context = $storeContext;
        $session->save();

        Log::info('GroqChatV2: Added to cart (verified)', [
            'product' => $product->name,
            'quantity' => $quantity,
            'price' => $product->price,
            'cart_total' => $session->getCartTotal(),
        ]);
    }

    /**
     * Execute update quantity action
     */
    protected function executeUpdateQuantity(AiChatSession $session, array $action): void
    {
        $productName = $action['product_name'] ?? '';
        $quantity = $action['quantity'] ?? 1;

        if (empty($productName)) {
            // Try last mentioned product
            $storeContext = $session->store_context ?? [];
            $lastMentioned = $storeContext['last_mentioned_product'] ?? null;
            if ($lastMentioned) {
                $productName = $lastMentioned['name'];
            }
        }

        if (!empty($productName) && $quantity > 0) {
            $session->updateCartQuantity($productName, $quantity);
            Log::info('GroqChatV2: Updated quantity', [
                'product' => $productName,
                'new_quantity' => $quantity,
            ]);
        }
    }

    /**
     * Execute remove from cart action
     */
    protected function executeRemoveFromCart(AiChatSession $session, array $action): void
    {
        $productName = $action['product_name'] ?? '';

        if (!empty($productName)) {
            $session->removeFromCart($productName);
            Log::info('GroqChatV2: Removed from cart', ['product' => $productName]);
        }
    }

    /**
     * Execute set customer info action
     * FIX #10: Stores info as pending and asks for confirmation
     */
    protected function executeSetCustomerInfo(AiChatSession $session, Lead $lead, array $action): void
    {
        $updates = [];

        if (!empty($action['name'])) {
            $updates['name'] = $action['name'];
        }
        if (!empty($action['phone'])) {
            $updates['phone'] = $action['phone'];
        }
        if (!empty($action['address'])) {
            $updates['address'] = $action['address'];
        }

        if (!empty($updates)) {
            // Store as pending - will be confirmed later
            $storeContext = $session->store_context ?? [];
            $storeContext['pending_customer_info'] = $updates;
            $storeContext['awaiting_info_confirmation'] = true;
            $session->store_context = $storeContext;
            $session->save();

            Log::info('GroqChatV2: Customer info pending confirmation', $updates);
        }
    }

    /**
     * Confirm and save pending customer info
     */
    protected function confirmCustomerInfo(AiChatSession $session, Lead $lead): void
    {
        $storeContext = $session->store_context ?? [];
        $pending = $storeContext['pending_customer_info'] ?? [];

        if (!empty($pending)) {
            // Now actually save it
            if (!empty($pending['name'])) {
                $lead->name = $pending['name'];
            }
            if (!empty($pending['phone'])) {
                $lead->phone = $pending['phone'];
            }
            if (!empty($pending['address'])) {
                $lead->address = $pending['address'];
            }
            $lead->save();

            $session->updateCustomerData($pending);

            // Clear pending
            unset($storeContext['pending_customer_info']);
            $storeContext['awaiting_info_confirmation'] = false;
            $storeContext['customer_info_confirmed'] = true;
            $session->store_context = $storeContext;
            $session->save();

            Log::info('GroqChatV2: Customer info confirmed and saved', $pending);
        }
    }

    /**
     * Execute create order action
     */
    protected function executeCreateOrder(AiChatSession $session, Lead $lead): void
    {
        // Verify we have complete info
        if (!$session->hasCompleteCustomerData()) {
            Log::warning('GroqChatV2: Cannot create order - missing customer data');
            return;
        }

        if (empty($session->cart)) {
            Log::warning('GroqChatV2: Cannot create order - cart empty');
            return;
        }

        $order = $this->createOrderInDatabase($session, $lead);

        if ($order) {
            // Mark order completed
            $storeContext = $session->store_context ?? [];
            $storeContext['order_completed'] = true;
            $storeContext['last_order_id'] = $order->id;
            $storeContext['last_order_number'] = $order->order_number;
            $session->store_context = $storeContext;
            $session->save();

            Log::info('GroqChatV2: Order created', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
        }
    }

    /**
     * Execute send image action
     * FIX #19 & #27: Include actual image URLs for products (supports multiple)
     */
    protected function executeSendImage(AiChatSession $session, array $action): void
    {
        $productId = $action['product_id'] ?? null;
        $storeContext = $session->store_context ?? [];

        // Initialize pending_images array if not exists
        if (!isset($storeContext['pending_images'])) {
            $storeContext['pending_images'] = [];
        }

        if ($productId) {
            // Get actual product with image URL
            $product = Product::where('user_id', $this->user->id)
                ->where('id', $productId)
                ->first();

            // Add to pending images if product has image
            if ($product && !empty($product->image)) {
                $storeContext['pending_images'][] = [
                    'url' => $product->image,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                ];
            }

            // Also set single image for backward compatibility
            if ($product && !empty($product->image)) {
                $storeContext['pending_image_url'] = $product->image;
                $storeContext['pending_image_product_id'] = $productId;
                $storeContext['pending_image_product_name'] = $product->name;
            }

            $session->store_context = $storeContext;
            $session->save();
        }
    }

    /**
     * Get fallback response when AI fails
     */
    protected function getFallbackResponse(string $message): array
    {
        return [
            'intent' => 'error',
            'reply' => 'عذراً، حصل خطأ. ممكن تعيد السؤال؟',
            'actions' => [],
            'product_ids' => [],
            'needs_response' => false,
        ];
    }

    /**
     * Check if message is simple greeting
     */
    protected function isSimpleGreeting(string $normalized): bool
    {
        $greetings = ['سلام', 'مرحبا', 'هلا', 'اهلا', 'السلام عليكم', 'مساء الخير', 'صباح الخير'];
        foreach ($greetings as $g) {
            if ($normalized === $g || mb_strpos($normalized, $g) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if message is simple yes
     */
    protected function isSimpleYes(string $normalized): bool
    {
        $yesWords = ['نعم', 'اي', 'اوكي', 'ok', 'yes', 'تمام', 'اكيد', 'موافق', 'صح'];
        return in_array($normalized, $yesWords);
    }

    /**
     * Detect when the AI reply ends with an offer to show products.
     * Used to set awaiting_browse_offer so the next "yes" is treated as "show me products"
     * rather than "confirm order".
     */
    protected function replyOffersToShowProducts(string $reply): bool
    {
        $patterns = [
            'هل تود.*أريك.*منتج',
            'هل تود.*نريك',
            'هل تود.*وريك',
            'تود.*اطلاع.*منتج',
            'وريك.*منتج.*مميز',
            'أريك.*منتجات.*مميزة',
            'نريك.*منتجات',
            'تحب.*أريك.*منتج',
            'ابيك.*تشوف.*منتج',
            'تود.*تشوف.*منتج',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match('/' . $pattern . '/ui', $reply)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if message contains explicit order confirmation phrase
     * FIX: Handle "نعم اكد الطلب", "اكد الطلب", "ثبت الطلب", etc.
     */
    protected function containsConfirmationPhrase(string $normalized): bool
    {
        $confirmPhrases = [
            'اكد الطلب', 'اكد طلبي', 'ثبت الطلب', 'ارسل الطلب',
            'نعم اكد', 'اي اكد', 'تمام اكد', 'موافق اكد',
            'اكده', 'اكدي', 'كمل الطلب', 'اكمل الطلب',
            'اكدلي', 'اكدلي الطلب'
        ];

        foreach ($confirmPhrases as $phrase) {
            if (mb_stripos($normalized, $phrase) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if AI reply mentions adding to cart
     */
    protected function replyMentionsAdding(string $reply): bool
    {
        $addPhrases = [
            'راح أضيف', 'راح اضيف', 'سأضيف', 'ساضيف',
            'تمت إضافة', 'تمت اضافة', 'تم إضافة', 'تم اضافة',
            'أضفت', 'اضفت', 'أضفته', 'اضفته',
            'تمت الإضافة', 'تمت الاضافة',
            'للسلة', 'بالسلة'
        ];
        foreach ($addPhrases as $phrase) {
            if (mb_stripos($reply, $phrase) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Try to add product from context when AI mentions adding but no action provided
     */
    protected function tryAddFromContext(AiChatSession $session, array $aiResponse): void
    {
        $storeContext = $session->store_context ?? [];
        $lastMentioned = $storeContext['last_mentioned_product'] ?? null;
        $lastShown = $storeContext['last_shown_products'] ?? [];

        // Try to extract quantity from AI reply (e.g., "قطعتين" = 2)
        $reply = $aiResponse['reply'] ?? '';
        $quantity = $this->extractQuantityFromText($reply);
        if ($quantity <= 0) {
            $quantity = 1;
        }

        Log::info('GroqChatV2: Fallback - Attempting to add from context', [
            'quantity' => $quantity,
            'has_last_mentioned' => !empty($lastMentioned),
            'last_shown_count' => count($lastShown),
        ]);

        // Try last mentioned product first
        if ($lastMentioned && !empty($lastMentioned['id'])) {
            $product = Product::where('user_id', $this->user->id)
                ->where('id', $lastMentioned['id'])
                ->where('is_active', true)
                ->where('quantity', '>', 0)
                ->first();

            if ($product) {
                $session->addToCart($product->name, (int)$product->price, $quantity);
                Log::info('GroqChatV2: Fallback - Added last mentioned product to cart', [
                    'product' => $product->name,
                    'price' => $product->price,
                    'quantity' => $quantity,
                ]);
                return;
            }
        }

        // Try to find product from product_ids in response
        $productIds = $aiResponse['product_ids'] ?? [];
        if (!empty($productIds)) {
            $product = Product::where('user_id', $this->user->id)
                ->whereIn('id', $productIds)
                ->where('is_active', true)
                ->where('quantity', '>', 0)
                ->first();

            if ($product) {
                $session->addToCart($product->name, (int)$product->price, $quantity);
                Log::info('GroqChatV2: Fallback - Added product from product_ids to cart', [
                    'product' => $product->name,
                    'price' => $product->price,
                    'quantity' => $quantity,
                ]);
                return;
            }
        }

        // Try first product from last shown products
        if (!empty($lastShown) && !empty($lastShown[0]['id'])) {
            $product = Product::where('user_id', $this->user->id)
                ->where('id', $lastShown[0]['id'])
                ->where('is_active', true)
                ->where('quantity', '>', 0)
                ->first();

            if ($product) {
                $session->addToCart($product->name, (int)$product->price, $quantity);
                Log::info('GroqChatV2: Fallback - Added first shown product to cart', [
                    'product' => $product->name,
                    'price' => $product->price,
                    'quantity' => $quantity,
                ]);
                return;

            }
        }

        Log::warning('GroqChatV2: Fallback failed - No product found to add');
    }

    /**
     * Extract quantity from text (e.g., "قطعتين" -> 2, "3 قطع" -> 3)
     */
    protected function extractQuantityFromText(string $text): int
    {
        // Check for dual form (قطعتين = 2)
        if (preg_match('/قطعتين|ثنتين|وحدتين|اثنين|2/u', $text)) {
            return 2;
        }
        // Check for number + unit (3 قطع)
        if (preg_match('/(\d+)\s*(?:قطع|قطعه|قطعة|حبات?|pieces?)/u', $text, $match)) {
            return (int)$match[1];
        }
        // Check for just a number
        if (preg_match('/(\d+)/u', $text, $match)) {
            $num = (int)$match[1];
            // Only use if reasonable quantity (1-99)
            if ($num > 0 && $num < 100) {
                return $num;
            }
        }
        return 0;
    }

    /**
     * Check if message is simple no
     */
    protected function isSimpleNo(string $normalized): bool
    {
        $noWords = ['لا', 'كلا', 'لاء', 'no'];
        return in_array($normalized, $noWords);
    }

    /**
     * Handle simple greeting (fast path)
     */
    protected function handleSimpleGreeting(AiChatSession $session): array
    {
        $storeName = $this->user->name ?? 'المتجر';
        $categories = $this->getStoreCategories();
        $categoryNames = array_column($categories, 'name');

        $reply = "وعليكم السلام، حياك الله! شنو تحتاج اليوم؟";
        if (!empty($categoryNames)) {
            $reply .= " عندنا أقسام: " . implode('، ', array_slice($categoryNames, 0, 5));
            if (count($categoryNames) > 5) {
                $reply .= " وأكثر...";
            }
        }

        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'greeting', $session, false);
    }

    /**
     * Handle simple cancel (fast path)
     */
    protected function handleSimpleCancel(AiChatSession $session): array
    {
        $storeContext = $session->store_context ?? [];
        $storeContext['awaiting_confirmation'] = false;
        $session->store_context = $storeContext;
        $session->save();

        $reply = 'تمام، شنو تحتاج ثاني؟';
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'cancel', $session, false);
    }

    /**
     * Handle decline suggestion - when user says "لا شكرا" after a cross-sell offer
     * IMPORTANT: This should show the cart and ask for confirmation, not just say "شنو تريد؟"
     */
    protected function handleDeclineSuggestion(AiChatSession $session, string $message): array
    {
        $session->addMessage('user', $message);

        $cart = $session->cart ?? [];
        if (!empty($cart)) {
            // User has items - show cart and ask for confirmation
            $deliveryCost = $this->settings->delivery_cost ?? 5000;
            $cartTotal = $session->getCartTotal();
            $grandTotal = $cartTotal + $deliveryCost;

            // Format cart items
            $cartItems = [];
            foreach ($cart as $item) {
                $qty = $item['quantity'] ?? 1;
                $price = $item['price'] ?? 0;
                $cartItems[] = "- {$item['name']} x{$qty}: " . number_format($price * $qty) . " دينار";
            }

            $reply = "تمام! سلتك:\n" . implode("\n", $cartItems);
            $reply .= "\n\nالمجموع: " . number_format($cartTotal) . " + توصيل " . number_format($deliveryCost) . " = " . number_format($grandTotal) . " دينار";
            $reply .= "\nتريد نأكد الطلب؟";

            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'asking_confirmation', $session, false);
        }

        // No cart - just ask what they want
        $categories = $this->getStoreCategories();
        $categoryNames = array_column($categories, 'name');

        $reply = 'تمام، شنو ممكن أساعدك؟ عندنا: ' . implode('، ', array_slice($categoryNames, 0, 5));
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'greeting', $session, false);
    }

    // ==================== LEGACY METHODS (kept for compatibility) ====================

    /**
     * IMPROVED Intent Detection - Context aware
     * NOTE: This is now only used as fallback, AI handles most intents
     */
    protected function detectIntent(string $message, AiChatSession $session): string
    {
        $normalized = $this->normalize($message);
        $msgLen = mb_strlen($normalized);
        $hasCart = !empty($session->cart);

        // SPECIAL: Decline suggestion when cart has items and user says "لا شكرا"
        $declineSuggestionKeywords = ['لا شكرا', 'شكرا لا', 'شكرا بس', 'بس شكرا'];
        if ($hasCart && $msgLen < 20 && $this->containsKeyword($normalized, $declineSuggestionKeywords)) {
            return 'decline_suggestion';
        }

        // Short greetings/thanks first
        if ($msgLen < 30) {
            if ($this->containsKeyword($normalized, self::GREETING_KEYWORDS)) {
                return 'greeting';
            }
            if ($this->containsKeyword($normalized, self::THANKS_KEYWORDS)) {
                return 'thanks';
            }
        }

        // CRITICAL: Check for cart summary request FIRST
        // "ما هيه سله طلبي" / "شنو سلتي" / "شنو طلبي"
        $cartSummaryPatterns = ['سله طلبي', 'سلتي', 'سلة طلبي', 'ما هيه سلة', 'شنو طلبي', 'طلبي شنو'];
        if ($this->containsKeyword($normalized, $cartSummaryPatterns)) {
            return 'cart_summary';
        }

        // CRITICAL: Check for ADD TO CART intent (user wants to add MORE items)
        // This is different from new order - user already has cart and wants to add
        if ($hasCart && $this->containsKeyword($normalized, self::ADD_TO_CART_KEYWORDS)) {
            return 'add_to_cart';
        }

        // CRITICAL: Check for QUANTITY UPDATE intent
        // "سويهم 3" / "الابيض 3" / "اريده 5 قطع"
        if ($hasCart && $this->isQuantityUpdateIntent($normalized, $session)) {
            return 'update_quantity';
        }

        // Confirmation (short messages)
        if ($msgLen < 20 && $this->containsKeyword($normalized, self::CONFIRM_KEYWORDS)) {
            return 'confirm';
        }

        // IMPORTANT: Check decline_more BEFORE cancel (user says no more items, not cancel)
        if ($hasCart && $this->containsKeyword($normalized, self::DECLINE_MORE_KEYWORDS)) {
            return 'decline_suggestion';
        }

        // Cancel - only explicit cancel phrases
        if ($this->containsKeyword($normalized, self::CANCEL_KEYWORDS)) {
            return 'cancel';
        }

        // Image request
        if ($this->containsKeyword($normalized, self::IMAGE_REQUEST_KEYWORDS)) {
            return 'image_request';
        }

        // Product variant request
        if ($this->containsKeyword($normalized, self::VARIANT_KEYWORDS)) {
            return 'product_variant';
        }

        // Order status
        if ($this->containsKeyword($normalized, self::ORDER_STATUS_KEYWORDS)) {
            return 'order_status';
        }

        // Delivery info
        if ($this->containsKeyword($normalized, self::DELIVERY_KEYWORDS)) {
            return 'delivery_info';
        }

        // Removal
        if ($this->containsKeyword($normalized, self::REMOVE_KEYWORDS)) {
            return 'remove';
        }

        // Customer info (phone/name/address)
        if ($this->looksLikeCustomerInfo($message)) {
            return 'customer_info';
        }

        // SMART: Check for CATEGORY inquiry first ("شنو عدكم ملابس")
        // This is different from product inquiry - user asks about a category
        if ($this->isCategoryInquiry($normalized)) {
            return 'category_inquiry';
        }

        // Check for inquiry vs order
        $hasInquiry = $this->containsKeyword($normalized, self::INQUIRY_KEYWORDS);
        $hasOrder = $this->containsKeyword($normalized, self::ORDER_KEYWORDS);

        if ($hasInquiry && !$hasOrder) {
            return 'inquiry';
        }

        if ($hasOrder) {
            return 'order';
        }

        return 'unknown';
    }

    /**
     * Check if this is a quantity update intent
     * "سويهم 3", "الابيض 3", "القميص الاحمر 5 قطع"
     */
    protected function isQuantityUpdateIntent(string $normalized, AiChatSession $session): bool
    {
        // Check for quantity update keywords
        if ($this->containsKeyword($normalized, self::QUANTITY_UPDATE_KEYWORDS)) {
            return true;
        }

        // Check if message contains a product color/type from cart + number
        $cart = $session->cart ?? [];
        if (empty($cart)) {
            return false;
        }

        // Extract any quantity from message
        $quantity = $this->extractQuantity($normalized);
        if ($quantity === null || $quantity < 1) {
            return false;
        }

        // Check if message references a cart item by color or name
        $colors = ['احمر', 'ابيض', 'اسود', 'ازرق', 'اخضر', 'اصفر', 'بني', 'رمادي', 'وردي', 'بنفسجي', 'ماروني'];
        foreach ($colors as $color) {
            if (mb_stripos($normalized, $color) !== false) {
                // Check if any cart item contains this color
                foreach ($cart as $item) {
                    if (mb_stripos($this->normalize($item['name']), $color) !== false) {
                        return true;
                    }
                }
            }
        }

        // Check if message references a cart item by product type
        $productTypes = ['قميص', 'بنطلون', 'فستان', 'هودي', 'تشيرت', 'جاكيت', 'حذاء', 'نظاره'];
        foreach ($productTypes as $type) {
            if (mb_stripos($normalized, $type) !== false) {
                foreach ($cart as $item) {
                    if (mb_stripos($this->normalize($item['name']), $type) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * SMART: Check if this is a category inquiry (not a specific product)
     * "شنو عدكم ملابس" = asking about clothing category
     * "شنو عدكم قمصان" = asking about shirts category
     */
    protected function isCategoryInquiry(string $normalized): bool
    {
        // Must have an inquiry pattern
        $hasInquiryPattern = $this->containsKeyword($normalized, self::CATEGORY_INQUIRY_PATTERNS);
        if (!$hasInquiryPattern) {
            return false;
        }

        // Check if contains a category keyword
        if ($this->containsKeyword($normalized, self::CATEGORY_KEYWORDS)) {
            return true;
        }

        // Check against actual store categories
        $categories = $this->getStoreCategories();
        foreach ($categories as $category) {
            $catNorm = $this->normalize($category['name']);
            // Check if category name or part of it is in the message
            $catWords = preg_split('/\s+/u', $catNorm);
            foreach ($catWords as $word) {
                if (mb_strlen($word) > 2 && mb_stripos($normalized, $word) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * SMART: Handle category inquiry - show products from matching category
     * "شنو عدكم ملابس" -> show clothing categories/products
     */
    protected function handleCategoryInquiry(AiChatSession $session, string $message): array
    {
        $session->addMessage('user', $message);
        $normalized = $this->normalize($message);

        // Get all categories
        $categories = $this->getStoreCategories();

        // Find matching category/categories
        $matchingCategories = [];
        foreach ($categories as $category) {
            $catNorm = $this->normalize($category['name']);
            $catWords = preg_split('/\s+/u', $catNorm);

            // Check category name match
            foreach ($catWords as $word) {
                if (mb_strlen($word) > 2 && mb_stripos($normalized, $word) !== false) {
                    $matchingCategories[] = $category;
                    break;
                }
            }

            // Also check category keywords match
            foreach (self::CATEGORY_KEYWORDS as $keyword) {
                if (mb_stripos($normalized, $keyword) !== false && mb_stripos($catNorm, $keyword) !== false) {
                    if (!in_array($category, $matchingCategories)) {
                        $matchingCategories[] = $category;
                    }
                    break;
                }
            }
        }

        // If multiple matching categories, show them
        if (count($matchingCategories) > 1) {
            $catList = array_map(fn($c) => "• {$c['name']} ({$c['products_count']} منتج)", $matchingCategories);
            $reply = "عندنا هاي الأقسام المشابهة:\n" . implode("\n", $catList) . "\n\nشنو القسم يعجبك؟";
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'category_list', $session, false);
        }

        // If single category, show products from it
        if (count($matchingCategories) === 1) {
            $category = $matchingCategories[0];
            $products = $this->getProductsInCategory($category['id']);

            if (!empty($products)) {
                $this->storeShownProducts($session, $products);
                $productList = array_map(fn($p) => "• {$p['name']}: {$p['price']} دينار", array_slice($products, 0, 5));

                $reply = "عندنا بقسم {$category['name']}:\n" . implode("\n", $productList);
                if (count($products) > 5) {
                    $reply .= "\n\nوأكثر... شنو يعجبك؟";
                } else {
                    $reply .= "\n\nشنو يعجبك؟";
                }
                $session->addMessage('assistant', $reply);
                return $this->buildResponse($reply, 'product_list', $session, false);
            }
        }

        // No matching category found - try to search products as fallback
        $searchResults = $this->searchProducts($message, null, 5);
        if (!empty($searchResults)) {
            $this->storeShownProducts($session, $searchResults);
            $productList = array_map(fn($p) => "• {$p['name']}: {$p['price']} دينار", array_slice($searchResults, 0, 5));
            $reply = "لكيتلك هاي المنتجات:\n" . implode("\n", $productList) . "\n\nشنو يعجبك؟";
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'product_list', $session, false);
        }

        // Still nothing - show all categories
        $allCatNames = array_map(fn($c) => $c['name'], $categories);
        $reply = "الأقسام المتوفرة عندنا:\n• " . implode("\n• ", $allCatNames) . "\n\nشنو القسم يعجبك؟";
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'category_list', $session, false);
    }

    /**
     * Handle ADD TO CART - User wants to add MORE items to existing cart
     * CRITICAL FIX: This is different from new order
     */
    protected function handleAddToCart(AiChatSession $session, Lead $lead, string $message): array
    {
        $session->addMessage('user', $message);
        $normalized = $this->normalize($message);

        // Search for the product mentioned
        $searchResults = $this->searchProducts($message, null, 5);

        if (!empty($searchResults)) {
            // Find best match
            $bestMatch = $this->findBestMatch($normalized, $searchResults);

            if ($bestMatch) {
                // Extract quantity
                $quantity = $this->extractQuantity($normalized) ?? 1;

                // Add to cart
                $session->addToCart($bestMatch['name'], $bestMatch['price'], $quantity);

                // Store as last mentioned product
                $this->setLastMentionedProduct($session, $bestMatch);

                Log::info('GroqChatV2: Added to cart', [
                    'product' => $bestMatch['name'],
                    'quantity' => $quantity,
                    'cart_total' => $session->getCartTotal(),
                ]);

                // Build response
                return $this->buildCartAndAskInfo($session, $lead);
            }
        }

        // Product not found
        $categories = $this->getStoreCategories();
        $categoryNames = array_column(array_slice($categories, 0, 4), 'name');

        $prompt = "الزبون يريد يضيف منتج للسلة لكن ما لكيته.
رسالته: {$message}
الأقسام المتوفرة: " . implode('، ', $categoryNames) . "
اسأله شنو بالضبط يريد يضيف. لا ايموجي.";

        $reply = $this->callAI($session, $message, $prompt);
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'add_to_cart_not_found', $session, false);
    }

    /**
     * Handle QUANTITY UPDATE - User wants to change quantity of cart item
     * CRITICAL FIX: "سويهم 3", "الابيض 3", "القميص الاحمر اريده 5"
     */
    protected function handleUpdateQuantity(AiChatSession $session, Lead $lead, string $message): array
    {
        $session->addMessage('user', $message);
        $normalized = $this->normalize($message);
        $cart = $session->cart ?? [];

        if (empty($cart)) {
            $reply = 'السلة فارغة. شنو تريد تطلب؟';
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'empty_cart', $session, false);
        }

        // Extract quantity from message
        $newQuantity = $this->extractQuantity($normalized);
        if ($newQuantity === null || $newQuantity < 1) {
            $reply = 'كم قطعة تريد؟';
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'asking_quantity', $session, false);
        }

        // Find which cart item to update
        $targetItem = null;

        // Method 1: Check for color reference
        $colors = [
            'احمر' => 'احمر', 'الاحمر' => 'احمر',
            'ابيض' => 'ابيض', 'الابيض' => 'ابيض',
            'اسود' => 'اسود', 'الاسود' => 'اسود',
            'ازرق' => 'ازرق', 'الازرق' => 'ازرق',
            'اخضر' => 'اخضر', 'الاخضر' => 'اخضر',
            'اصفر' => 'اصفر', 'الاصفر' => 'اصفر',
            'ماروني' => 'ماروني', 'الماروني' => 'ماروني',
        ];

        foreach ($colors as $variant => $baseColor) {
            if (mb_stripos($normalized, $variant) !== false) {
                foreach ($cart as $item) {
                    if (mb_stripos($this->normalize($item['name']), $baseColor) !== false) {
                        $targetItem = $item;
                        break 2;
                    }
                }
            }
        }

        // Method 2: Check for product type reference
        if (!$targetItem) {
            $productTypes = ['قميص', 'بنطلون', 'فستان', 'هودي', 'تشيرت'];
            foreach ($productTypes as $type) {
                if (mb_stripos($normalized, $type) !== false) {
                    foreach ($cart as $item) {
                        if (mb_stripos($this->normalize($item['name']), $type) !== false) {
                            $targetItem = $item;
                            break 2;
                        }
                    }
                }
            }
        }

        // Method 3: If only one item in cart, assume it's that one
        if (!$targetItem && count($cart) === 1) {
            $targetItem = $cart[0];
        }

        // Method 4: Use last mentioned product
        if (!$targetItem) {
            $lastProduct = $this->getLastMentionedProduct($session);
            if ($lastProduct) {
                foreach ($cart as $item) {
                    if ($item['name'] === $lastProduct['name']) {
                        $targetItem = $item;
                        break;
                    }
                }
            }
        }

        if ($targetItem) {
            // Update quantity
            $session->updateCartQuantity($targetItem['name'], $newQuantity);

            Log::info('GroqChatV2: Updated quantity', [
                'product' => $targetItem['name'],
                'old_quantity' => $targetItem['quantity'],
                'new_quantity' => $newQuantity,
            ]);

            return $this->buildCartAndAskInfo($session, $lead);
        }

        // Couldn't determine which item - ask
        $cartItems = array_map(fn($item) => $item['name'], $cart);
        $reply = 'أي منتج تريد تغير كميته؟ ' . implode(' أو ', $cartItems);
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'asking_which_item', $session, false);
    }

    /**
     * Handle regular ORDER - New product order
     * IMPROVED: Uses extractLineItems for multi-product support (قميصين وبنطرونين)
     */
    protected function handleOrder(AiChatSession $session, Lead $lead, string $message): array
    {
        $session->addMessage('user', $message);
        $normalized = $this->normalize($message);

        // Get stored products for context
        $storeContext = $session->store_context ?? [];
        $contextProducts = $storeContext['products'] ?? [];

        // IMPROVED: Try to extract multiple line items first
        $lineItems = $this->extractLineItems($message, $contextProducts);

        if (!empty($lineItems)) {
            $validItems = [];
            $stockIssues = [];

            foreach ($lineItems as $item) {
                // Validate stock
                if ($item['stock'] < $item['quantity']) {
                    $stockIssues[] = "{$item['name']}: طلبت {$item['quantity']}، المتوفر {$item['stock']}";
                    // Add max available quantity
                    if ($item['stock'] > 0) {
                        $validItems[] = [
                            'name' => $item['name'],
                            'price' => $item['price'],
                            'quantity' => $item['stock'],
                        ];
                    }
                } else {
                    $validItems[] = [
                        'name' => $item['name'],
                        'price' => $item['price'],
                        'quantity' => $item['quantity'],
                    ];
                }
            }

            // Add valid items to cart
            foreach ($validItems as $item) {
                $session->addToCart($item['name'], $item['price'], $item['quantity']);
            }

            // Store last mentioned product
            if (!empty($lineItems)) {
                $this->setLastMentionedProduct($session, $lineItems[count($lineItems) - 1]);
            }

            Log::info('GroqChatV2: Multi-product order', [
                'items_requested' => count($lineItems),
                'items_added' => count($validItems),
                'stock_issues' => count($stockIssues),
            ]);

            // Report stock issues if any
            if (!empty($stockIssues)) {
                $reply = "تم إضافة المنتجات للسلة، لكن في ملاحظة:\n" . implode("\n", $stockIssues) . "\n\nهل تريد تكمل بالكمية المتوفرة؟";
                $session->addMessage('assistant', $reply);
                return $this->buildResponse($reply, 'stock_warning', $session, false);
            }

            return $this->buildCartAndAskInfo($session, $lead);
        }

        // Fallback: Single product search
        $searchResults = $this->searchProducts($message, null, 10);

        if (!empty($searchResults)) {
            $bestMatch = $this->findBestMatch($normalized, $searchResults);

            if ($bestMatch) {
                // Extract quantity
                $quantity = $this->extractQuantity($normalized) ?? 1;

                // Validate stock
                if ($bestMatch['stock'] < $quantity) {
                    $reply = "{$bestMatch['name']} متوفر {$bestMatch['stock']} قطعة فقط. كم تريد؟";
                    $session->addMessage('assistant', $reply);
                    $this->setLastMentionedProduct($session, $bestMatch);
                    return $this->buildResponse($reply, 'stock_limited', $session, false);
                }

                // Add to cart
                $session->addToCart($bestMatch['name'], $bestMatch['price'], $quantity);
                $this->setLastMentionedProduct($session, $bestMatch);

                Log::info('GroqChatV2: Product added to cart', [
                    'product' => $bestMatch['name'],
                    'price' => $bestMatch['price'],
                    'quantity' => $quantity,
                ]);

                return $this->buildCartAndAskInfo($session, $lead);
            }

            // Multiple results - show options
            $this->storeShownProducts($session, $searchResults);
            $productNames = array_map(fn($p) => $p['name'], array_slice($searchResults, 0, 4));

            $prompt = "لكيت هاي المنتجات: " . implode('، ', $productNames) . ".
اعرضها للزبون واسأله شنو يفضل منها. لا ايموجي.";

            $reply = $this->callAI($session, $message, $prompt);
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'show_options', $session, false);
        }

        // Nothing found - ask for clarification
        $categories = $this->getStoreCategories();
        $categoryNames = array_column($categories, 'name');

        $prompt = "الزبون يريد يطلب شي لكن ما لكيته.
رسالته: {$message}
الأقسام المتوفرة: " . implode('، ', $categoryNames) . "
اسأله شنو يريد بالضبط. لا ايموجي.";

        $reply = $this->callAI($session, $message, $prompt);
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'order_not_found', $session, false);
    }

    /**
     * Handle product variant request - "اكو غير نوعيه"
     */
    protected function handleProductVariant(AiChatSession $session, string $message): array
    {
        $session->addMessage('user', $message);

        // Get last shown or mentioned products
        $lastProduct = $this->getLastMentionedProduct($session);
        $storeContext = $session->store_context ?? [];
        $lastShown = $storeContext['last_shown_products'] ?? [];

        // Determine what category/type user was looking at
        $searchCategory = null;
        if ($lastProduct && !empty($lastProduct['category_id'])) {
            $searchCategory = $lastProduct['category_id'];
        } elseif (!empty($lastShown) && !empty($lastShown[0]['category_id'])) {
            $searchCategory = $lastShown[0]['category_id'];
        }

        // Search for alternatives
        $alternatives = [];
        if ($searchCategory) {
            $alternatives = $this->getProductsInCategory($searchCategory);
            // Exclude already shown products
            $shownIds = array_column($lastShown, 'id');
            if ($lastProduct) {
                $shownIds[] = $lastProduct['id'];
            }
            $alternatives = array_filter($alternatives, fn($p) => !in_array($p['id'], $shownIds));
        }

        if (empty($alternatives)) {
            // Get products from same type/category
            $searchQuery = $lastProduct['name'] ?? '';
            $alternatives = $this->searchProducts($searchQuery, null, 10);
            // Exclude current
            if ($lastProduct) {
                $alternatives = array_filter($alternatives, fn($p) => $p['id'] !== $lastProduct['id']);
            }
        }

        $alternatives = array_values($alternatives);

        if (!empty($alternatives)) {
            $this->storeShownProducts($session, $alternatives);
            $productNames = array_map(fn($p) => "{$p['name']} ({$p['price']} دينار)", array_slice($alternatives, 0, 4));

            $reply = "نعم، عدنا:\n" . implode("\n", array_map(fn($n) => "• {$n}", $productNames)) . "\n\nشنو يعجبك؟";
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'show_variants', $session, false);
        }

        // No alternatives found
        $categories = $this->getStoreCategories();
        $categoryNames = array_column(array_slice($categories, 0, 4), 'name');

        $reply = "للأسف ما عندي نوع ثاني من هذا المنتج. بس عندي أقسام أخرى: " . implode('، ', $categoryNames) . ". شنو يهمك؟";
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'no_variants', $session, false);
    }

    /**
     * Handle cart summary request - "ما هيه سله طلبي"
     */
    protected function handleCartSummary(AiChatSession $session, Lead $lead, string $message): array
    {
        $session->addMessage('user', $message);
        return $this->buildCartAndAskInfo($session, $lead);
    }

    /**
     * Handle CONFIRMATION - CRITICAL: Must save to database
     * SMART AGENT: Uses detectMissingCustomerInfo and updates Lead
     */
    protected function handleConfirmation(AiChatSession $session, Lead $lead, string $message): array
    {
        $session->addMessage('user', $message);

        $cart = $session->cart ?? [];
        $customerData = $session->customer_data ?? [];

        Log::info('GroqChatV2: Confirmation received', [
            'cart_count' => count($cart),
            'customer_data' => $customerData,
            'lead_name' => $lead->name,
            'lead_phone' => $lead->phone,
        ]);

        // Check if cart is empty
        if (empty($cart)) {
            $categories = $this->getStoreCategories();
            $categoryNames = array_column($categories, 'name');
            $reply = 'السلة فارغة! شنو تريد تطلب؟ الأقسام المتوفرة: ' . implode('، ', $categoryNames);
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'empty_cart', $session, false);
        }

        // SMART AGENT: Use detectMissingCustomerInfo to check BOTH Lead and session
        $missingFields = $this->detectMissingCustomerInfo($session, $lead);

        if (!empty($missingFields)) {
            Log::info('GroqChatV2: Confirmation blocked - missing info', ['missing' => $missingFields]);
            return $this->buildCartAndAskInfo($session, $lead);
        }

        // SMART AGENT: Ensure session has all data merged from Lead
        $this->ensureSessionHasAllData($session, $lead);
        $customerData = $session->customer_data ?? [];

        // CRITICAL: Create order in database
        $order = $this->createOrderInDatabase($session, $lead);

        if (!$order) {
            Log::error('GroqChatV2: Failed to create order', [
                'cart' => $cart,
                'customer' => $customerData,
            ]);
            $reply = 'حصل خطأ بتأكيد الطلب. يرجى المحاولة مرة أخرى.';
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'order_error', $session, false);
        }

        Log::info('GroqChatV2: Order created successfully', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total' => $order->total,
        ]);

        // SMART AGENT: Update Lead with customer data for future orders
        $this->updateLeadFromSession($lead, $customerData);

        // Update Lead status to converted
        $lead->status = 'converted';
        $lead->total_orders = ($lead->total_orders ?? 0) + 1;
        $lead->total_spent = ($lead->total_spent ?? 0) + $order->total;
        $lead->save();

        // Mark customer info as confirmed in session
        $storeContext = $session->store_context ?? [];
        $storeContext['customer_info_confirmed'] = true;
        $storeContext['awaiting_confirmation'] = false;
        $storeContext['order_completed'] = true;
        $storeContext['last_order_id'] = $order->id;
        $session->store_context = $storeContext;

        // Store order in session
        $session->current_order = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => 'pending',
            'items' => $cart,
            'total' => $order->total,
            'customer' => $customerData,
        ];
        $session->save();

        // Build beautiful confirmation message
        $total = $order->total;
        $formattedTotal = number_format($total, 0, '', ',');

        $itemLines = [];
        foreach ($cart as $item) {
            $qty = $item['quantity'] ?? 1;
            $itemLines[] = "• {$item['name']}" . ($qty > 1 ? " × {$qty}" : "");
        }

        $reply = "تم تأكيد طلبك بنجاح!\n";
        $reply .= "━━━━━━━━━━━━━━━━\n";
        $reply .= "رقم الطلب: {$order->order_number}\n";
        $reply .= "━━━━━━━━━━━━━━━━\n";
        $reply .= implode("\n", $itemLines) . "\n";
        $reply .= "━━━━━━━━━━━━━━━━\n";
        $reply .= "المجموع: {$formattedTotal} د.ع\n\n";
        $reply .= "سيتم التواصل معك قريباً للتوصيل.\n";
        $reply .= "شكراً لك يا {$customerData['name']}!";

        $session->addMessage('assistant', $reply);

        // Clear cart AFTER order confirmed
        $session->clearCart();

        return $this->buildResponse($reply, 'order_confirmed', $session, false, null, [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);
    }

    /**
     * CRITICAL: Create order in database - Guaranteed persistence
     */
    protected function createOrderInDatabase(AiChatSession $session, Lead $lead): ?OnlineOrder
    {
        $cart = $session->cart ?? [];
        $customerData = $session->customer_data ?? [];
        $storeContext = $session->store_context ?? [];

        try {
            // Check for duplicate order (same cart hash in last 5 minutes)
            $orderPayload = json_encode(['items' => $cart, 'customer' => $customerData]);
            $orderHash = hash('sha256', $orderPayload);

            // Check if we already created this exact order
            if (!empty($storeContext['last_order_hash']) && $storeContext['last_order_hash'] === $orderHash) {
                $existingOrder = OnlineOrder::find($storeContext['last_order_id'] ?? 0);
                if ($existingOrder) {
                    return $existingOrder;
                }
            }

            // Calculate totals
            $subtotal = 0;
            foreach ($cart as $item) {
                $subtotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
            }

            // Update lead with customer data
            $lead->name = $customerData['name'] ?? $lead->name;
            $lead->phone = $customerData['phone'] ?? $lead->phone;
            $lead->address = $customerData['address'] ?? $lead->address;
            $lead->save();

            // Create order
            $order = OnlineOrder::create([
                'user_id' => $this->user->id,
                'lead_id' => $lead->id,
                'conversation_id' => $session->conversation_id,
                'customer_name' => $customerData['name'] ?? $lead->name ?? 'زبون',
                'customer_phone' => $customerData['phone'] ?? $lead->phone ?? '',
                'customer_address' => $customerData['address'] ?? $lead->address ?? '',
                'subtotal' => $subtotal,
                'shipping_cost' => 0,
                'discount' => 0,
                'total' => $subtotal,
                'status' => 'pending',
                'source' => 'ai_chat',
                'payment_method' => 'cash_on_delivery',
                'payment_status' => 'pending',
                'customer_notes' => 'طلب من الذكاء الاصطناعي - ' . count($cart) . ' منتج',
                'meta_data' => [
                    'created_by' => 'groq_ai_v2',
                    'items_count' => count($cart),
                    'session_id' => $session->id,
                ],
            ]);

            // Create order items
            foreach ($cart as $item) {
                $productName = $item['name'] ?? '';
                $quantity = (int) ($item['quantity'] ?? 1);
                $price = (int) ($item['price'] ?? 0);

                // Find product ID
                $product = Product::where('user_id', $this->user->id)
                    ->where(function($q) use ($productName) {
                        $q->where('name', $productName)
                          ->orWhere('name', 'LIKE', '%' . $productName . '%');
                    })
                    ->first();

                OnlineOrderItem::create([
                    'online_order_id' => $order->id,
                    'product_id' => $product->id ?? null,
                    'product_name' => $productName,
                    'unit_price' => $price,
                    'quantity' => $quantity,
                    'total' => $price * $quantity,
                ]);
            }

            // Recalculate totals
            $order->calculateTotals();

            // Store hash to prevent duplicates
            $storeContext['last_order_hash'] = $orderHash;
            $storeContext['last_order_id'] = $order->id;
            $session->store_context = $storeContext;
            $session->save();

            return $order;

        } catch (\Exception $e) {
            Log::error('GroqChatV2: Order creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Handle order status - IMPROVED: Searches by phone/lead
     */
    protected function handleOrderStatus(AiChatSession $session, Lead $lead, string $message): array
    {
        $session->addMessage('user', $message);

        // Try to find order by multiple methods
        $order = null;

        // Method 1: Check session for recent order
        $sessionOrder = $session->current_order ?? null;
        if (!empty($sessionOrder['order_id'])) {
            $order = OnlineOrder::with('items')->find($sessionOrder['order_id']);
        }

        // Method 2: Check if phone number in message
        if (!$order && preg_match('/(?:\+?964|0)?7[3-9]\d{8}/', $message, $phoneMatch)) {
            $order = OnlineOrder::where('user_id', $this->user->id)
                ->where('customer_phone', 'LIKE', '%' . $phoneMatch[0] . '%')
                ->latest()
                ->first();
        }

        // Method 3: Find by lead
        if (!$order) {
            $order = OnlineOrder::where('user_id', $this->user->id)
                ->where('lead_id', $lead->id)
                ->latest()
                ->first();
        }

        // Method 4: Find by conversation
        if (!$order && $session->conversation_id) {
            $order = OnlineOrder::where('user_id', $this->user->id)
                ->where('conversation_id', $session->conversation_id)
                ->latest()
                ->first();
        }

        // Method 5: Find by phone from lead
        if (!$order && $lead->phone) {
            $order = OnlineOrder::where('user_id', $this->user->id)
                ->where('customer_phone', 'LIKE', '%' . $lead->phone . '%')
                ->latest()
                ->first();
        }

        if ($order) {
            $statusLabels = [
                'pending' => 'قيد الانتظار',
                'confirmed' => 'مؤكد',
                'processing' => 'قيد التجهيز',
                'shipped' => 'تم الشحن',
                'delivered' => 'تم التوصيل',
                'cancelled' => 'ملغي',
            ];

            $status = $statusLabels[$order->status] ?? 'قيد التجهيز';
            $items = $order->items->map(fn($i) => "• {$i->product_name} × {$i->quantity}")->implode("\n");

            $reply = "طلبك رقم {$order->order_number}:\n{$items}\n\nالمجموع: {$order->total} دينار\nالحالة: {$status}\n\nسيتم التواصل معك قريبا للتوصيل.";
            $session->addMessage('assistant', $reply);

            // Sync session with database order
            $session->current_order = [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => $order->total,
            ];
            $session->save();

            return $this->buildResponse($reply, 'order_status', $session, false);
        }

        // No order found
        $reply = 'ما لكيت طلب مسجل. إذا طلبت سابقا، ممكن تعطيني رقم الهاتف المستخدم بالطلب؟ أو تريد تطلب شي جديد؟';
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'no_order_found', $session, false);
    }

    /**
     * Handle inquiry - Product/price questions
     */
    protected function handleInquiry(AiChatSession $session, string $message): array
    {
        $session->addMessage('user', $message);
        $normalized = $this->normalize($message);

        // Search for products
        $searchResults = $this->searchProducts($message, null, 5);

        if (!empty($searchResults)) {
            $bestMatch = $this->findBestMatch($normalized, $searchResults);

            if ($bestMatch) {
                $this->setLastMentionedProduct($session, $bestMatch);
                $this->storeShownProducts($session, [$bestMatch]);

                $reply = "نعم، لدينا {$bestMatch['name']} بسعر {$bestMatch['price']} دينار. هل ترغب في طلبه؟";
                $session->addMessage('assistant', $reply);

                return $this->buildResponse($reply, 'product_info', $session, false, null, null, [$bestMatch['id']]);
            }

            // Multiple results
            $this->storeShownProducts($session, $searchResults);
            $productList = array_map(fn($p) => "• {$p['name']}: {$p['price']} دينار", array_slice($searchResults, 0, 4));

            $reply = "عندنا:\n" . implode("\n", $productList) . "\n\nشنو يعجبك؟";
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'product_list', $session, false);
        }

        // Nothing found in product search - try category match before giving up
        $categories = $this->getStoreCategories();

        // Check if message matches any category
        foreach ($categories as $category) {
            $catNorm = $this->normalize($category['name']);
            $catWords = preg_split('/\s+/u', $catNorm);
            foreach ($catWords as $word) {
                if (mb_strlen($word) > 2 && mb_stripos($normalized, $word) !== false) {
                    // Found matching category - show its products
                    $products = $this->getProductsInCategory($category['id']);
                    if (!empty($products)) {
                        $this->storeShownProducts($session, $products);
                        $productList = array_map(fn($p) => "• {$p['name']}: {$p['price']} دينار", array_slice($products, 0, 5));
                        $reply = "عندنا بقسم {$category['name']}:\n" . implode("\n", $productList) . "\n\nشنو يعجبك؟";
                        $session->addMessage('assistant', $reply);
                        return $this->buildResponse($reply, 'product_list', $session, false);
                    }
                }
            }
        }

        // Still nothing - use AI for smart response
        $categoryNames = array_column($categories, 'name');
        $reply = "ما لكيت هذا المنتج عندنا. الأقسام المتوفرة: " . implode('، ', $categoryNames) . ". شنو تفضل؟";
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'not_found', $session, false);
    }

    /**
     * Handle customer info
     * SMART AGENT: Detects complete info in single message and handles accordingly
     */
    protected function handleCustomerInfo(AiChatSession $session, Lead $lead, string $message): array
    {
        $customerInfo = $this->extractCustomerInfo($message);

        Log::info('GroqChatV2: Customer info extracted', [
            'extracted' => $customerInfo,
            'cart_count' => count($session->cart ?? []),
            'session_id' => $session->id,
        ]);

        if (!empty($customerInfo)) {
            // SMART AGENT: Update session with new info
            $session->updateCustomerData($customerInfo);

            // SMART AGENT: Update Lead using new method
            $this->updateLeadFromSession($lead, $customerInfo);

            Log::info('GroqChatV2: Customer data saved', [
                'customer_data' => $session->customer_data,
                'lead_id' => $lead->id,
            ]);
        }

        // Reload session to get fresh cart data
        $session->refresh();

        // If cart has items, continue order flow
        if (!empty($session->cart)) {
            Log::info('GroqChatV2: Cart has items, continuing order flow');
            $session->addMessage('user', $message);

            // SMART AGENT: Check if all info is now complete
            $missingFields = $this->detectMissingCustomerInfo($session, $lead);

            if (empty($missingFields)) {
                // All info complete! Set awaiting confirmation and show summary
                $storeContext = $session->store_context ?? [];
                $storeContext['awaiting_confirmation'] = true;
                $session->store_context = $storeContext;
                $session->save();

                // Ensure all data is in session
                $this->ensureSessionHasAllData($session, $lead);
                $customerData = $session->customer_data ?? [];

                $cartPreview = $this->formatCartForDisplay($session->cart, $session->getCartTotal());

                $reply = "{$cartPreview}\n\n";
                $reply .= "بيانات التوصيل:\n";
                $reply .= "الاسم: {$customerData['name']}\n";
                $reply .= "الرقم: {$customerData['phone']}\n";
                $reply .= "العنوان: {$customerData['address']}\n\n";
                $reply .= "تأكد الطلب؟ (نعم/لا)";

                $session->addMessage('assistant', $reply);
                return $this->buildResponse($reply, 'asking_confirmation', $session, true);
            }

            return $this->buildCartAndAskInfo($session, $lead);
        }

        // No cart - thank them and ask what they want
        $session->addMessage('user', $message);
        $reply = 'شكرا! شنو تريد تطلب؟';
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'info_received', $session, false);
    }

    /**
     * Handle delivery info request
     */
    protected function handleDeliveryInfo(AiChatSession $session, string $message): array
    {
        $session->addMessage('user', $message);

        $deliveryCost = $this->settings->delivery_cost ?? 5000;
        $deliveryTime = $this->settings->delivery_time ?? 'نفس اليوم';
        $deliveryAreas = $this->settings->delivery_areas ?? 'جميع مناطق العراق';

        $reply = "أجور التوصيل {$deliveryCost} دينار، والتوصيل يكون {$deliveryTime} لـ{$deliveryAreas}. هل تحتاج أي مساعدة إضافية؟";
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'delivery_info', $session, false);
    }

    /**
     * Handle image request
     * FIX: First try to find product in message, then fall back to last mentioned
     */
    protected function handleImageRequest(AiChatSession $session, string $message): array
    {
        $session->addMessage('user', $message);

        // FIX: First check if message contains a specific product name
        $productNameResult = $this->handleImageRequestWithProduct($session, $message);
        if ($productNameResult !== null) {
            return $productNameResult;
        }

        // Get last mentioned product
        $lastProduct = $this->getLastMentionedProduct($session);

        if ($lastProduct && !empty($lastProduct['id'])) {
            // FIX: Execute send_image action to populate pending_images
            $this->executeSendImage($session, ['product_id' => $lastProduct['id']]);

            $reply = 'حاضر، هاي صورة ' . $lastProduct['name'];
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'image_request', $session, false, null, null, [$lastProduct['id']]);
        }

        // Check last shown products
        $storeContext = $session->store_context ?? [];
        $lastShown = $storeContext['last_shown_products'] ?? [];

        if (!empty($lastShown) && !empty($lastShown[0]['id'])) {
            $product = $lastShown[0];
            // FIX: Execute send_image action to populate pending_images
            $this->executeSendImage($session, ['product_id' => $product['id']]);

            $reply = 'حاضر، هاي صورة ' . $product['name'];
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'image_request', $session, false, null, null, [$product['id']]);
        }

        $reply = 'شنو المنتج الي تريد تشوف صورته؟';
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'asking_product', $session, false);
    }

    /**
     * Handle greeting
     */
    protected function handleGreeting(AiChatSession $session, string $message): array
    {
        $session->addMessage('user', $message);

        $categories = $this->getStoreCategories();
        $categoryNames = array_column(array_slice($categories, 0, 4), 'name');

        $prompt = "الزبون حياك: {$message}
رد عليه بعراقي ودود واسأله شنو يحتاج.
الأقسام المتوفرة: " . implode('، ', $categoryNames) . "
لا ايموجي. كن مختصر وودود.";

        $reply = $this->callAI($session, $message, $prompt);
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'greeting', $session, false);
    }

    /**
     * Handle thanks
     */
    protected function handleThanks(AiChatSession $session, string $message): array
    {
        $session->addMessage('user', $message);
        $reply = 'عفوا! هل تحتاج شي ثاني؟';
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'thanks', $session, false);
    }

    /**
     * Handle removal
     */
    protected function handleRemoval(AiChatSession $session, string $message): array
    {
        $session->addMessage('user', $message);
        $normalized = $this->normalize($message);
        $cart = $session->cart ?? [];

        if (empty($cart)) {
            $reply = 'السلة فارغة أصلا.';
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'empty_cart', $session, false);
        }

        // Try to find which item to remove
        $itemToRemove = null;
        foreach ($cart as $item) {
            $itemNorm = $this->normalize($item['name']);
            // Check if item name or parts appear in message
            $words = preg_split('/\s+/u', $itemNorm);
            foreach ($words as $word) {
                if (mb_strlen($word) > 2 && mb_stripos($normalized, $word) !== false) {
                    $itemToRemove = $item['name'];
                    break 2;
                }
            }
        }

        if ($itemToRemove) {
            $session->removeFromCart($itemToRemove);
            $reply = "تم حذف {$itemToRemove} من السلة.";
        } else {
            // Clear entire cart
            $session->clearCart();
            $reply = 'تم تفريغ السلة.';
        }

        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'item_removed', $session, false);
    }

    /**
     * Handle cancel - IMPROVED: Smart cancel that checks context
     * If user has items in cart but says "لا اريد شئ آخر", show cart instead of cancel
     */
    protected function handleCancel(AiChatSession $session, string $message): array
    {
        $session->addMessage('user', $message);
        $normalized = $this->normalize($message);

        // Check if there's an actual order in the database
        $currentOrder = $session->current_order ?? null;
        $orderId = $currentOrder['order_id'] ?? null;

        if ($orderId) {
            // Order exists in database - check its status
            $order = OnlineOrder::find($orderId);

            if ($order) {
                // Statuses that cannot be cancelled via chat
                $nonCancellableStatuses = ['shipped', 'delivered', 'returned'];

                if (in_array($order->status, $nonCancellableStatuses, true)) {
                    $statusLabels = [
                        'shipped' => 'مشحون',
                        'delivered' => 'موصل',
                        'returned' => 'مرتجع',
                    ];
                    $statusLabel = $statusLabels[$order->status] ?? $order->status;

                    $reply = "للأسف طلبك رقم {$order->order_number} حالته ({$statusLabel}) ولا يمكن إلغاؤه من هنا. يرجى التواصل مع خدمة العملاء للمساعدة.";
                    $session->addMessage('assistant', $reply);

                    return $this->buildResponse($reply, 'order_cancel_denied', $session, false);
                }

                // Order can be cancelled (pending, confirmed, processing)
                $order->update(['status' => 'cancelled']);

                Log::info('GroqChatV2: Order cancelled via chat', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'previous_status' => $order->getOriginal('status'),
                ]);

                $reply = "تم إلغاء طلبك رقم {$order->order_number} بنجاح. هل تحتاج شي ثاني؟";
                $session->addMessage('assistant', $reply);

                // Clear session order
                $session->current_order = null;
                $session->clearCart();
                $session->save();

                return $this->buildResponse($reply, 'order_cancelled', $session, false);
            }
        }

        // SMART FIX: If cart has items and user says "لا اريد شئ" or "بس هيج"
        // This usually means they don't want MORE items, not that they want to cancel
        $cart = $session->cart ?? [];
        if (!empty($cart)) {
            // Check if this is a "I don't want anything else" vs "cancel everything"
            $softDeclineKeywords = ['لا شكرا', 'شكرا لا', 'لا اريد شئ', 'لا اريد شي', 'ما اريد شي', 'بس هيج', 'بس هذا', 'هذا بس', 'يكفي', 'خلاص', 'لا مو محتاج', 'لا شكرا'];
            $hardCancelKeywords = ['الغي', 'الغاء', 'كانسل', 'cancel', 'الغي الطلب', 'لا اريد اشتري', 'ما اريد اشتري'];

            // Check for hard cancel
            $isHardCancel = false;
            foreach ($hardCancelKeywords as $kw) {
                if (mb_strpos($normalized, $kw) !== false) {
                    $isHardCancel = true;
                    break;
                }
            }

            // If not a hard cancel, show cart and ask for confirmation
            if (!$isHardCancel) {
                $deliveryCost = $this->settings->delivery_cost ?? 5000;
                $cartTotal = $session->getCartTotal();
                $grandTotal = $cartTotal + $deliveryCost;

                // Format cart items
                $cartItems = [];
                foreach ($cart as $item) {
                    $qty = $item['quantity'] ?? 1;
                    $price = $item['price'] ?? 0;
                    $cartItems[] = "- {$item['name']} x{$qty}: " . number_format($price * $qty) . " دينار";
                }

                $reply = "تمام! سلتك:\n" . implode("\n", $cartItems);
                $reply .= "\n\nالمجموع: " . number_format($cartTotal) . " + توصيل " . number_format($deliveryCost) . " = " . number_format($grandTotal) . " دينار";
                $reply .= "\nتريد نأكد الطلب؟";

                $session->addMessage('assistant', $reply);
                return $this->buildResponse($reply, 'asking_confirmation', $session, false);
            }
        }

        // Hard cancel - clear everything
        $session->clearCart();
        $session->current_order = null;
        $session->save();

        $categories = $this->getStoreCategories();
        $categoryNames = array_column($categories, 'name');

        $reply = 'تم إلغاء الطلب. اذا تريد شي ثاني، عندنا: ' . implode('، ', $categoryNames);
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'cancelled', $session, false);
    }

    /**
     * Handle pending attribute response (size, color, etc.)
     */
    protected function handlePendingAttributeResponse(
        AiChatSession $session,
        Lead $lead,
        string $message,
        array $pendingQuestion
    ): ?array {
        $attrKey = $pendingQuestion['attribute_key'] ?? null;
        $cartIndex = $pendingQuestion['cart_index'] ?? null;
        $productName = $pendingQuestion['product_name'] ?? '';

        if (!$attrKey || $cartIndex === null) {
            return null;
        }

        // Try to extract the attribute value
        $extractedValue = $this->attributeService?->extractAttributeFromMessage($message, $attrKey);

        if (!$extractedValue) {
            // User didn't provide valid attribute - ask again
            $session->addMessage('user', $message);
            $attrName = StoreType::getAttributeName($attrKey);
            $reply = "ما فهمت شنو {$attrName} تريده لـ {$productName}. ممكن توضح أكثر؟";
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'asking_attribute_again', $session, false);
        }

        // Update cart with the attribute
        $this->missingDataDetector?->updateCartItemAttribute($session, $cartIndex, $attrKey, $extractedValue);

        // Clear pending question
        $storeContext = $session->store_context ?? [];
        unset($storeContext['pending_attribute_question']);
        $session->store_context = $storeContext;
        $session->save();

        Log::info('GroqChatV2: Attribute added to cart', [
            'cart_index' => $cartIndex,
            'attribute_key' => $attrKey,
            'attribute_value' => $extractedValue,
        ]);

        $session->addMessage('user', $message);
        return $this->buildCartAndAskInfo($session, $lead);
    }

    /**
     * Handle customer info confirmation (reuse existing info)
     */
    protected function handleCustomerInfoConfirmation(AiChatSession $session, Lead $lead, string $message): ?array
    {
        $normalized = $this->normalize($message);
        $storeContext = $session->store_context ?? [];

        $confirmKeywords = array_merge(self::CONFIRM_KEYWORDS, ['نفس', 'نفسه', 'نفس العنوان', 'مثل السابق']);
        $changeKeywords = ['غير', 'تغيير', 'بدل', 'مو نفس', 'مو نفسها', 'عنوان جديد', 'رقم جديد', 'اسم جديد'];

        if ($this->containsKeyword($normalized, $confirmKeywords)) {
            $storeContext['confirming_customer_info'] = false;
            $storeContext['customer_info_confirmed'] = true;
            $session->store_context = $storeContext;
            $session->save();
            $session->addMessage('user', $message);
            return $this->buildCartAndAskInfo($session, $lead);
        }

        if ($this->containsKeyword($normalized, $changeKeywords)) {
            $storeContext['confirming_customer_info'] = false;
            $storeContext['customer_info_confirmed'] = false;
            $session->store_context = $storeContext;
            $session->save();

            $reply = 'تمام، شنو تريد تغيّر؟ الاسم لو الرقم لو العنوان؟';
            $session->addMessage('user', $message);
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'confirm_info_change', $session, false);
        }

        // Check if they're providing new info
        if ($this->looksLikeCustomerInfo($message)) {
            $storeContext['confirming_customer_info'] = false;
            $session->store_context = $storeContext;
            $session->save();
            return $this->handleCustomerInfo($session, $lead, $message);
        }

        return null; // Continue to regular intent detection
    }

    /**
     * Handle pending quantity response ("كم قطعة؟" -> "3")
     */
    protected function handlePendingQuantity(AiChatSession $session, Lead $lead, string $message, array $pendingProduct): array
    {
        $quantity = $this->extractQuantity($message) ?? 1;
        $productName = $pendingProduct['name'];
        $price = $pendingProduct['price'];
        $stock = $pendingProduct['stock'] ?? 99;

        // Validate stock
        if ($quantity > $stock) {
            $reply = "للأسف {$productName} متوفر {$stock} قطع فقط. كم تريد؟";
            $session->addMessage('user', $message);
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'stock_limited', $session, false);
        }

        // Add to cart
        $session->addToCart($productName, $price, $quantity);

        // Clear pending
        $storeContext = $session->store_context ?? [];
        unset($storeContext['pending_product']);
        $session->store_context = $storeContext;
        $session->save();

        Log::info('GroqChatV2: Pending quantity added', [
            'product' => $productName,
            'quantity' => $quantity,
        ]);

        $session->addMessage('user', $message);
        return $this->buildCartAndAskInfo($session, $lead);
    }

    /**
     * Handle image request with specific product name
     * FIX: Call executeSendImage to properly populate pending_images for actual image sending
     * This is called from handleImageRequest when message contains a product name
     */
    protected function handleImageRequestWithProduct(AiChatSession $session, string $message): ?array
    {
        // Clean message to extract product name (remove image keywords)
        $cleanedMessage = preg_replace('/اريد|ابي|ابغى|عايز|صوره|صورة|صور|شوفني|وريني|ارني/u', '', $message);
        $cleanedMessage = trim($cleanedMessage);

        if (empty($cleanedMessage)) {
            return null; // No product name found, let caller handle it
        }

        $searchResults = $this->searchProducts($cleanedMessage, null, 5);

        if (!empty($searchResults)) {
            $bestMatch = $this->findBestMatch($this->normalize($cleanedMessage), $searchResults);
            if ($bestMatch && !empty($bestMatch['id'])) {
                $this->setLastMentionedProduct($session, $bestMatch);
                // FIX: Execute send_image action to populate pending_images
                $this->executeSendImage($session, ['product_id' => $bestMatch['id']]);

                $reply = 'حاضر، هاي صورة ' . $bestMatch['name'];
                // Note: session message already added in handleImageRequest
                $session->addMessage('assistant', $reply);
                return $this->buildResponse($reply, 'image_request', $session, false, null, null, [$bestMatch['id']]);
            }
        }

        // Product not found in message
        return null;
    }

    /**
     * Check if message requires human agent
     */
    protected function requiresHumanAgent(string $message): bool
    {
        $normalized = $this->normalize($message);

        // Direct human agent keywords
        if ($this->containsKeyword($normalized, self::HUMAN_AGENT_KEYWORDS)) {
            return true;
        }

        // Payment/installment questions
        $paymentKeywords = ['تقسيط', 'دفعات', 'كي نت', 'بطاقة', 'فيزا', 'ماستر'];
        if ($this->containsKeyword($normalized, $paymentKeywords)) {
            return true;
        }

        // Wholesale/bulk (large quantities)
        if (preg_match('/\d{2,}\s*(قطعة|حبة|عدد)/u', $message)) {
            return true;
        }

        return false;
    }

    /**
     * Handle human agent escalation
     */
    protected function handleHumanAgentRequest(AiChatSession $session, string $message): array
    {
        $session->addMessage('user', $message);

        $reply = 'سيتواصل معك أحد موظفينا قريباً للمساعدة. شكراً لصبرك.';
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'human_agent_needed', $session, false, 'human_agent_needed');
    }

    /**
     * Check if message looks like just a quantity ("3", "ثلاثة", "5 قطع")
     */
    protected function looksLikeQuantityOnly(string $message): bool
    {
        $normalized = $this->normalize($message);
        $len = mb_strlen($normalized);

        // Very short message
        if ($len > 30) return false;

        // Just a number
        if (preg_match('/^[0-9٠-٩]+$/', $normalized)) return true;

        // Number word alone
        foreach (self::NUM_WORDS as $word => $value) {
            if ($value > 0 && $normalized === $word) return true;
        }

        // Number + unit (3 قطع)
        $unitPattern = implode('|', self::UNIT_WORDS);
        if (preg_match('/^[0-9٠-٩]+\s*(' . $unitPattern . ')$/u', $normalized)) return true;

        // Arabic word + unit (ثلاث قطع)
        foreach (self::NUM_WORDS as $word => $value) {
            if ($value > 0 && preg_match('/^' . preg_quote($word, '/') . '\s*(' . $unitPattern . ')$/u', $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle with AI - fallback
     */
    protected function handleWithAI(AiChatSession $session, Lead $lead, string $message): array
    {
        $session->addMessage('user', $message);
        $normalized = $this->normalize($message);

        // Try product search first
        $searchResults = $this->searchProducts($message, null, 5);

        if (!empty($searchResults)) {
            $this->storeShownProducts($session, $searchResults);

            if (count($searchResults) === 1) {
                $product = $searchResults[0];
                $this->setLastMentionedProduct($session, $product);

                $reply = "نعم، لدينا {$product['name']} بسعر {$product['price']} دينار. هل ترغب في طلبه؟";
                $session->addMessage('assistant', $reply);
                return $this->buildResponse($reply, 'product_found', $session, false, null, null, [$product['id']]);
            }

            $productNames = array_map(fn($p) => $p['name'], array_slice($searchResults, 0, 4));
            $reply = "لكيت: " . implode('، ', $productNames) . ". شنو يهمك منها؟";
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'search_results', $session, false);
        }

        // General AI response
        $categories = $this->getStoreCategories();
        $categoryNames = array_column($categories, 'name');

        $prompt = "الزبون يسأل: {$message}
أنت مساعد مبيعات لمتجر عراقي.
الأقسام المتوفرة: " . implode('، ', $categoryNames) . "
ساعده وأجب على سؤاله. لا ايموجي. كن مختصر.";

        $reply = $this->callAI($session, $message, $prompt);
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'ai_response', $session, false);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Build cart preview and ask for missing info ONE at a time
     * SMART AGENT: Uses detectMissingCustomerInfo to check Lead + session
     * Only asks for truly missing info, not what's already saved
     */
    protected function buildCartAndAskInfo(AiChatSession $session, Lead $lead): array
    {
        // FIRST: Check for missing product attributes (size, color, etc.)
        if ($this->missingDataDetector) {
            $attrResponse = $this->askForMissingAttributes($session, $lead);
            if ($attrResponse !== null) {
                return $attrResponse;
            }
        }

        $cart = $session->cart ?? [];
        $total = $session->getCartTotal();
        $customerData = $session->customer_data ?? [];
        $storeContext = $session->store_context ?? [];

        // SMART AGENT: Build beautiful cart preview
        $cartPreview = $this->formatCartForDisplay($cart, $total);

        // SMART AGENT: Use detectMissingCustomerInfo to check BOTH Lead and session
        $missingFields = $this->detectMissingCustomerInfo($session, $lead);

        Log::info('GroqChatV2: buildCartAndAskInfo - checking missing info', [
            'missing_fields' => $missingFields,
            'session_data' => $customerData,
            'lead_name' => $lead->name,
            'lead_phone' => $lead->phone,
            'lead_address' => $lead->address,
        ]);

        // If no missing fields, we have complete data
        if (empty($missingFields)) {
            // SMART AGENT: Merge Lead data into session if not already there
            $this->ensureSessionHasAllData($session, $lead);
            $customerData = $session->customer_data ?? [];

            // Check if this is a returning customer (has preloaded info)
            $isReturning = !empty($storeContext['customer_info_preloaded']) || $lead->total_orders > 0;

            if ($isReturning && empty($storeContext['customer_info_confirmed'])) {
                // Show saved info and ask to confirm/change
                $storeContext['confirming_customer_info'] = true;
                $storeContext['awaiting_confirmation'] = true;
                $session->store_context = $storeContext;
                $session->save();

                $reply = "{$cartPreview}\n\n";
                $reply .= "معلوماتك المحفوظة:\n";
                $reply .= "الاسم: {$customerData['name']}\n";
                $reply .= "الرقم: {$customerData['phone']}\n";
                $reply .= "العنوان: {$customerData['address']}\n\n";
                $reply .= "تأكد الطلب بهذه المعلومات؟\n";
                $reply .= "(قل 'نعم' للتأكيد، أو اكتب البيانات الجديدة)";

                $session->addMessage('assistant', $reply);
                return $this->buildResponse($reply, 'confirm_customer_info', $session, true);
            }

            // All info complete and confirmed - ASK for final confirmation
            $storeContext['awaiting_confirmation'] = true;
            $session->store_context = $storeContext;
            $session->save();

            $reply = "{$cartPreview}\n\n";
            $reply .= "بيانات التوصيل:\n";
            $reply .= "الاسم: {$customerData['name']}\n";
            $reply .= "الرقم: {$customerData['phone']}\n";
            $reply .= "العنوان: {$customerData['address']}\n\n";
            $reply .= "تأكد الطلب؟ (نعم/لا)";

            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'asking_confirmation', $session, true);
        }

        // SMART AGENT: Ask for only the FIRST missing field
        $firstMissing = $missingFields[0];
        $name = $customerData['name'] ?? $lead->name ?? '';

        if ($firstMissing === 'name') {
            $ask = AiFastReply::getRandomReply($this->user->id, 'ask_name') ?? 'شنو اسمك الكريم؟';
            $reply = "{$cartPreview}\n\n{$ask}";
            $intent = 'asking_name';
        } elseif ($firstMissing === 'phone') {
            $ask = AiFastReply::getRandomReply($this->user->id, 'ask_phone') ?? 'شنو رقم تلفونك؟';
            if (!empty($name)) {
                $reply = "{$cartPreview}\n\nزين يا {$name}، {$ask}";
            } else {
                $reply = "{$cartPreview}\n\n{$ask}";
            }
            $intent = 'asking_phone';
        } else { // address
            $ask = AiFastReply::getRandomReply($this->user->id, 'ask_address') ?? 'وين نوصل إلك؟ (المحافظة والمنطقة)';
            $reply = "{$cartPreview}\n\nممتاز! {$ask}";
            $intent = 'asking_address';
        }

        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, $intent, $session, true);
    }

    /**
     * SMART AGENT: Ensure session has all available data from Lead
     */
    protected function ensureSessionHasAllData(AiChatSession $session, Lead $lead): void
    {
        $customerData = $session->customer_data ?? [];
        $updated = false;

        if (empty($customerData['name']) && !empty($lead->name)) {
            $customerData['name'] = $lead->name;
            $updated = true;
        }
        if (empty($customerData['phone'])) {
            $phone = $lead->phone ?? $lead->whatsapp ?? null;
            if (!empty($phone)) {
                $customerData['phone'] = $phone;
                $updated = true;
            }
        }
        if (empty($customerData['address'])) {
            $address = $this->buildAddressFromLead($lead);
            if (!empty($address)) {
                $customerData['address'] = $address;
                $updated = true;
            }
        }

        if ($updated) {
            $session->customer_data = $customerData;
            $session->save();
        }
    }

    /**
     * Ask for missing product attributes before continuing order
     */
    protected function askForMissingAttributes(AiChatSession $session, Lead $lead): ?array
    {
        $nextQuestion = $this->missingDataDetector->getNextQuestion($session);

        if (!$nextQuestion || $nextQuestion['type'] !== 'attribute') {
            return null;
        }

        // Store pending question in session
        $storeContext = $session->store_context ?? [];
        $storeContext['pending_attribute_question'] = [
            'cart_index' => $nextQuestion['cart_index'],
            'attribute_key' => $nextQuestion['attribute_key'],
            'product_name' => $nextQuestion['product_name'],
        ];
        $session->store_context = $storeContext;
        $session->save();

        $cartPreview = $this->missingDataDetector->formatCartWithAttributes($session->cart ?? []);
        $reply = "{$cartPreview}\n\n{$nextQuestion['question']}";

        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'asking_attribute', $session, true);
    }

    /**
     * Build simple cart preview
     */
    protected function buildCartPreview(array $cart, int $total): string
    {
        return $this->formatCartForDisplay($cart, $total);
    }

    /**
     * SMART AGENT: Format cart for beautiful display
     * Shows items clearly with editing instructions
     */
    protected function formatCartForDisplay(array $cart, ?int $total = null): string
    {
        if (empty($cart)) {
            return "سلة التسوق فارغة";
        }

        $total = $total ?? array_reduce($cart, fn($sum, $item) => $sum + (($item['price'] ?? 0) * ($item['quantity'] ?? 1)), 0);

        $lines = [];
        $lines[] = "سلة التسوق:";
        $lines[] = "━━━━━━━━━━━━━━━━";

        $itemNum = 1;
        foreach ($cart as $item) {
            $name = $item['name'] ?? 'منتج';
            $price = (int) ($item['price'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 1);
            $itemTotal = $price * $qty;

            // Format price with thousands separator
            $formattedPrice = number_format($price, 0, '', ',');
            $formattedTotal = number_format($itemTotal, 0, '', ',');

            $line = "{$itemNum}. {$name}";
            if ($qty > 1) {
                $line .= " × {$qty}";
            }
            $line .= " = {$formattedTotal} د.ع";

            // Add attributes if any
            if (!empty($item['attributes'])) {
                $attrs = [];
                foreach ($item['attributes'] as $key => $value) {
                    if (!empty($value)) {
                        $attrs[] = $value;
                    }
                }
                if (!empty($attrs)) {
                    $line .= " (" . implode('، ', $attrs) . ")";
                }
            }

            $lines[] = $line;
            $itemNum++;
        }

        $lines[] = "━━━━━━━━━━━━━━━━";
        $lines[] = "المجموع: " . number_format($total, 0, '', ',') . " د.ع";
        $lines[] = "";
        $lines[] = "للتعديل: 'احذف [اسم المنتج]' أو 'غير الكمية'";
        $lines[] = "للتأكيد: 'أكد الطلب'";

        return implode("\n", $lines);
    }

    /**
     * SMART AGENT: Detect what info is truly missing (checking Lead + session)
     * Returns array of missing fields or empty if all info available
     */
    protected function detectMissingCustomerInfo(AiChatSession $session, Lead $lead): array
    {
        $missing = [];
        $customerData = $session->customer_data ?? [];

        // Check name - from session or lead
        $name = $customerData['name'] ?? $lead->name ?? null;
        if (empty($name) || mb_strlen(trim($name)) < 2) {
            $missing[] = 'name';
        }

        // Check phone - from session or lead
        $phone = $customerData['phone'] ?? $lead->phone ?? $lead->whatsapp ?? null;
        if (empty($phone) || !preg_match('/7[3-9]\d{8}/', $phone)) {
            $missing[] = 'phone';
        }

        // Check address - from session or lead
        $address = $customerData['address'] ?? $this->buildAddressFromLead($lead) ?? null;
        if (empty($address) || mb_strlen(trim($address)) < 3) {
            $missing[] = 'address';
        }

        return $missing;
    }

    /**
     * SMART AGENT: Update Lead with new customer info from session
     */
    protected function updateLeadFromSession(Lead $lead, array $customerData): void
    {
        $updated = false;

        if (!empty($customerData['name']) && $customerData['name'] !== $lead->name) {
            $lead->name = $customerData['name'];
            $updated = true;
        }
        if (!empty($customerData['phone'])) {
            $phone = $customerData['phone'];
            if ($phone !== $lead->phone && $phone !== $lead->whatsapp) {
                $lead->phone = $phone;
                $updated = true;
            }
        }
        if (!empty($customerData['address']) && $customerData['address'] !== $lead->address) {
            $lead->address = $customerData['address'];
            $updated = true;
        }

        if ($updated) {
            $lead->save();
            Log::info('GroqChatV2: Updated Lead from session', [
                'lead_id' => $lead->id,
                'name' => $lead->name,
                'phone' => $lead->phone,
                'address' => $lead->address,
            ]);
        }
    }

    /**
     * Search products in database
     * FIX #24: Enhanced with pagination support
     */
    protected function searchProducts(string $query, ?int $categoryId = null, int $limit = 10): array
    {
        $normalized = $this->normalize($query);

        // Remove common words
        $ignoreWords = ['غير', 'متوفر', 'عندكم', 'عدكم', 'موجود', 'هل', 'في', 'فيه', 'كم', 'سعر', 'شكد', 'شنو', 'اريد', 'ابي', 'ممكن'];
        $words = preg_split('/\s+/u', $normalized);
        $words = array_filter($words, fn($w) => mb_strlen($w) > 1 && !in_array($w, $ignoreWords));

        if (empty($words)) {
            $words = preg_split('/\s+/u', $normalized);
            $words = array_filter($words, fn($w) => mb_strlen($w) > 1);
        }

        $queryBuilder = Product::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0);

        if ($categoryId) {
            $queryBuilder->where('category_id', $categoryId);
        }

        $queryBuilder->where(function($q) use ($normalized, $query, $words) {
            $q->where('name', 'LIKE', "%{$query}%")
              ->orWhere('name', 'LIKE', "%{$normalized}%");

            foreach ($words as $word) {
                if (mb_strlen($word) > 2) {
                    $q->orWhere('name', 'LIKE', "%{$word}%");
                }
            }
        });

        return $queryBuilder
            ->orderBy('quantity', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (int) $p->price,
                'stock' => (int) $p->quantity,
                'category_id' => $p->category_id,
            ])
            ->toArray();
    }

    /**
     * FIX #24: Search with pagination - returns total count
     */
    protected function searchProductsWithCount(string $query, ?int $categoryId = null): array
    {
        $normalized = $this->normalize($query);

        $ignoreWords = ['غير', 'متوفر', 'عندكم', 'عدكم', 'موجود', 'هل', 'في', 'فيه', 'كم', 'سعر', 'شكد', 'شنو', 'اريد', 'ابي', 'ممكن'];
        $words = preg_split('/\s+/u', $normalized);
        $words = array_filter($words, fn($w) => mb_strlen($w) > 1 && !in_array($w, $ignoreWords));

        if (empty($words)) {
            $words = preg_split('/\s+/u', $normalized);
            $words = array_filter($words, fn($w) => mb_strlen($w) > 1);
        }

        $queryBuilder = Product::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0);

        if ($categoryId) {
            $queryBuilder->where('category_id', $categoryId);
        }

        $queryBuilder->where(function($q) use ($normalized, $query, $words) {
            $q->where('name', 'LIKE', "%{$query}%")
              ->orWhere('name', 'LIKE', "%{$normalized}%");

            foreach ($words as $word) {
                if (mb_strlen($word) > 2) {
                    $q->orWhere('name', 'LIKE', "%{$word}%");
                }
            }
        });

        $totalCount = $queryBuilder->count();

        $results = $queryBuilder
            ->orderBy('quantity', 'desc')
            ->limit(20) // Get more for pagination
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (int) $p->price,
                'stock' => (int) $p->quantity,
                'category_id' => $p->category_id,
            ])
            ->toArray();

        return [
            'results' => $results,
            'total' => $totalCount,
        ];
    }

    /**
     * FIX #24: Format search results with pagination info
     */
    protected function formatSearchResultsWithPagination(array $results, int $total, int $page = 1, int $perPage = 5): string
    {
        $start = ($page - 1) * $perPage;
        $pageResults = array_slice($results, $start, $perPage);

        if (empty($pageResults)) {
            return 'ما لكيت نتائج أخرى.';
        }

        $lines = [];
        foreach ($pageResults as $product) {
            $lines[] = "• {$product['name']}: {$product['price']} دينار";
        }

        $reply = implode("\n", $lines);

        // Add pagination info if there are more
        $totalPages = ceil($total / $perPage);
        if ($total > $perPage) {
            $remaining = $total - ($page * $perPage);
            if ($remaining > 0) {
                $reply .= "\n\n📦 لكيت {$total} منتج. قل 'المزيد' لعرض {$remaining} منتج آخر.";
            }
        }

        return $reply;
    }

    /**
     * Find best match from search results
     */
    protected function findBestMatch(string $normalized, array $results): ?array
    {
        if (empty($results)) return null;
        if (count($results) === 1) return $results[0];

        $words = array_filter(preg_split('/\s+/u', $normalized), fn($w) => mb_strlen($w) > 2);
        if (empty($words)) return $results[0];

        $bestMatch = null;
        $bestScore = 0;

        foreach ($results as $product) {
            $productNorm = $this->normalize($product['name']);
            $score = 0;

            foreach ($words as $word) {
                if (mb_stripos($productNorm, $word) !== false) {
                    $score += 10;
                    if (mb_strpos($productNorm, $word) === 0) {
                        $score += 15;
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $product;
            }
        }

        return $bestScore >= 10 ? $bestMatch : $results[0];
    }

    /**
     * Get store categories
     */
    protected function getStoreCategories(): array
    {
        return Category::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->withCount(['products' => fn($q) => $q->where('is_active', true)->where('quantity', '>', 0)])
            ->get()
            ->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'products_count' => $cat->products_count,
            ])
            ->toArray();
    }

    /**
     * Get products in category
     */
    protected function getProductsInCategory(int $categoryId): array
    {
        return Product::where('user_id', $this->user->id)
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->limit(20)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (int) $p->price,
                'stock' => (int) $p->quantity,
                'category_id' => $p->category_id,
            ])
            ->toArray();
    }

    /**
     * Store shown products
     */
    protected function storeShownProducts(AiChatSession $session, array $products): void
    {
        $storeContext = $session->store_context ?? [];
        $storeContext['last_shown_products'] = $products;
        $session->store_context = $storeContext;
        $session->save();
    }

    /**
     * Set last mentioned product (for context)
     */
    protected function setLastMentionedProduct(AiChatSession $session, array $product): void
    {
        $storeContext = $session->store_context ?? [];
        $storeContext['last_mentioned_product'] = $product;
        $session->store_context = $storeContext;
        $session->save();
    }

    /**
     * Get last mentioned product
     */
    protected function getLastMentionedProduct(AiChatSession $session): ?array
    {
        $storeContext = $session->store_context ?? [];
        return $storeContext['last_mentioned_product'] ?? null;
    }

    /**
     * Extract quantity from text - COMPREHENSIVE Arabic number handling
     * Handles: digits, Arabic numerals, number words, dual forms, unit patterns
     * Examples: "2", "٣", "ثلاث", "قطعتين", "ثلاث قطع", "قميصين"
     */
    protected function extractQuantity(string $text): ?int
    {
        // STEP 1: Check dual patterns first (قميصين = 2, قطعتين = 2)
        foreach (self::DUAL_PATTERNS as $pattern => $qty) {
            if (preg_match($pattern, $text)) {
                return $qty;
            }
        }

        // STEP 2: Check for "NUMBER + UNIT" pattern (ثلاث قطع، خمس حبات)
        // Build regex for unit words
        $unitPattern = implode('|', array_map('preg_quote', self::UNIT_WORDS));

        // Check for Arabic word + unit (ثلاث قطع)
        $sortedWords = self::NUM_WORDS;
        uksort($sortedWords, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        foreach ($sortedWords as $word => $value) {
            if ($value > 0) {
                // Pattern: number word followed by unit word
                $pattern = '/' . preg_quote($word, '/') . '\s*(' . $unitPattern . ')/u';
                if (preg_match($pattern, $text)) {
                    return (int) $value;
                }
            }
        }

        // STEP 3: Check for digit/Arabic numeral + unit (3 قطع، ٥ حبات)
        if (preg_match('/([0-9٠-٩]+)\s*(' . $unitPattern . ')/u', $text, $match)) {
            $numStr = $match[1];
            // Convert Arabic numerals if present
            $numStr = strtr($numStr, self::AR_DIGITS);
            $qty = (int) $numStr;
            if ($qty > 0) return min(100, $qty);
        }

        // STEP 4: Arabic numerals (٠-٩)
        if (preg_match('/[٠-٩]+/', $text, $match)) {
            $arabicNum = $match[0];
            $englishNum = strtr($arabicNum, self::AR_DIGITS);
            $qty = (int) $englishNum;
            if ($qty > 0) return min(100, $qty);
        }

        // STEP 5: English numbers
        if (preg_match('/(\d+)/', $text, $match)) {
            $qty = (int) $match[1];
            if ($qty > 0) return min(100, $qty);
        }

        // STEP 6: Arabic number words alone (ثلاث، خمسة)
        foreach ($sortedWords as $word => $value) {
            if ($value > 0 && mb_stripos($text, $word) !== false) {
                return (int) $value;
            }
        }

        // STEP 7: Generic dual suffix (ين ending = 2, but not number words)
        $numberEndings = ['اثنين', 'ثنين', 'عشرين', 'ثلاثين', 'اربعين', 'خمسين'];
        if (preg_match('/\p{Arabic}{2,}ين$/u', $text)) {
            $isNumberWord = false;
            foreach ($numberEndings as $ending) {
                if (mb_stripos($text, $ending) !== false) {
                    $isNumberWord = true;
                    break;
                }
            }
            if (!$isNumberWord) {
                return 2;
            }
        }

        // STEP 8: Check for plural patterns with number (3 بنطرونات)
        foreach (self::PLURAL_PATTERNS as $plural => $singular) {
            if (mb_stripos($text, $plural) !== false) {
                // Look for accompanying number
                if (preg_match('/([0-9٠-٩]+)\s*' . preg_quote($plural, '/') . '/u', $text, $match)) {
                    $numStr = strtr($match[1], self::AR_DIGITS);
                    return min(100, (int) $numStr);
                }
                // Check for Arabic word number before plural
                foreach ($sortedWords as $word => $value) {
                    if ($value > 0 && preg_match('/' . preg_quote($word, '/') . '\s*' . preg_quote($plural, '/') . '/u', $text)) {
                        return (int) $value;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract line items (multiple products with quantities) from message
     * CRITICAL: Handles "قميصين وبنطرونين" (two shirts and two pants)
     */
    protected function extractLineItems(string $message, array $products): array
    {
        $text = $this->normalize($message);
        $items = [];

        // Split message by connectors (و، ,) to handle multiple products
        $parts = preg_split('/\s*[،,]\s*|\s+و\s+/u', $text);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part) || mb_strlen($part) < 2) continue;

            // Extract quantity for this part
            $quantity = $this->extractQuantityFromPart($part);

            // Remove quantity-related words from part to find product name
            $quantityWords = ['قطع', 'قطعه', 'قطعة', 'حبه', 'حبة', 'pieces', 'عدد', 'كمية', 'اريد', 'ابي'];
            $cleanPart = $part;
            foreach ($quantityWords as $qWord) {
                $cleanPart = str_ireplace($qWord, '', $cleanPart);
            }
            $cleanPart = preg_replace('/[0-9٠-٩]+/u', '', $cleanPart);
            $cleanPart = trim(preg_replace('/\s+/u', ' ', $cleanPart));

            if (empty($cleanPart) || mb_strlen($cleanPart) < 2) continue;

            // Search for matching product
            $searchResults = $this->searchProducts($cleanPart, null, 5);
            $matchedProduct = !empty($searchResults) ? $this->findBestMatch($cleanPart, $searchResults) : null;

            if ($matchedProduct) {
                $productKey = $matchedProduct['name'];
                if (isset($items[$productKey])) {
                    $items[$productKey]['quantity'] += $quantity;
                } else {
                    $items[$productKey] = [
                        'name' => $matchedProduct['name'],
                        'price' => (int) $matchedProduct['price'],
                        'quantity' => $quantity,
                        'stock' => (int) ($matchedProduct['stock'] ?? 99),
                        'id' => $matchedProduct['id'] ?? null,
                    ];
                }

                Log::info('GroqChatV2: Multi-item extracted', [
                    'part' => $part,
                    'product' => $matchedProduct['name'],
                    'quantity' => $quantity,
                ]);
            }
        }

        // If no matches from parts, try whole message
        if (empty($items)) {
            $quantity = $this->extractQuantity($text) ?? 1;
            $searchResults = $this->searchProducts($message, null, 5);
            $matchedProduct = !empty($searchResults) ? $this->findBestMatch($text, $searchResults) : null;

            if ($matchedProduct) {
                $items[$matchedProduct['name']] = [
                    'name' => $matchedProduct['name'],
                    'price' => (int) $matchedProduct['price'],
                    'quantity' => $quantity,
                    'stock' => (int) ($matchedProduct['stock'] ?? 99),
                    'id' => $matchedProduct['id'] ?? null,
                ];
            }
        }

        Log::info('GroqChatV2: Extracted line items', [
            'message' => $message,
            'items_count' => count($items),
        ]);

        return array_values($items);
    }

    /**
     * Extract quantity from a message part - COMPREHENSIVE
     * Handles dual forms, number words + unit, digits, etc.
     */
    protected function extractQuantityFromPart(string $part): int
    {
        // STEP 1: Check dual patterns first (قميصين = 2, قطعتين = 2)
        foreach (self::DUAL_PATTERNS as $pattern => $qty) {
            if (preg_match($pattern, $part)) {
                return $qty;
            }
        }

        // STEP 2: Check for "NUMBER + UNIT" pattern (ثلاث قطع)
        $unitPattern = implode('|', array_map('preg_quote', self::UNIT_WORDS));
        $sortedWords = self::NUM_WORDS;
        uksort($sortedWords, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        foreach ($sortedWords as $word => $value) {
            if ($value > 0) {
                $pattern = '/' . preg_quote($word, '/') . '\s*(' . $unitPattern . ')/u';
                if (preg_match($pattern, $part)) {
                    return (int) $value;
                }
            }
        }

        // STEP 3: Digit + unit (3 قطع)
        if (preg_match('/([0-9٠-٩]+)\s*(' . $unitPattern . ')/u', $part, $match)) {
            $numStr = strtr($match[1], self::AR_DIGITS);
            $qty = (int) $numStr;
            if ($qty > 0) return min(100, $qty);
        }

        // STEP 4: Arabic number words alone
        foreach ($sortedWords as $word => $num) {
            if ($num > 0 && mb_stripos($part, $word) !== false) {
                return $num;
            }
        }

        // STEP 5: Digits
        if (preg_match('/(\d+)/', $part, $match)) {
            return max(1, min(100, (int)$match[1]));
        }

        // STEP 6: Arabic numerals
        if (preg_match('/([٠-٩]+)/', $part, $match)) {
            $englishNum = strtr($match[1], self::AR_DIGITS);
            return max(1, min(100, (int)$englishNum));
        }

        // STEP 7: Plural patterns with preceding number
        foreach (self::PLURAL_PATTERNS as $plural => $singular) {
            if (mb_stripos($part, $plural) !== false) {
                if (preg_match('/([0-9٠-٩]+)\s*' . preg_quote($plural, '/') . '/u', $part, $match)) {
                    $numStr = strtr($match[1], self::AR_DIGITS);
                    return min(100, (int) $numStr);
                }
                foreach ($sortedWords as $word => $value) {
                    if ($value > 0 && preg_match('/' . preg_quote($word, '/') . '\s*' . preg_quote($plural, '/') . '/u', $part)) {
                        return (int) $value;
                    }
                }
            }
        }

        // STEP 8: Generic dual suffix (ين ending = 2)
        $numberEndings = ['اثنين', 'ثنين', 'عشرين', 'ثلاثين', 'اربعين', 'خمسين'];
        if (preg_match('/\p{Arabic}{2,}ين$/u', $part)) {
            $isNumberWord = false;
            foreach ($numberEndings as $ending) {
                if (mb_stripos($part, $ending) !== false) {
                    $isNumberWord = true;
                    break;
                }
            }
            if (!$isNumberWord) {
                return 2;
            }
        }

        return 1;
    }

    /**
     * Extract customer info
     * SMART AGENT: Better multi-line detection for name, address, phone in one message
     */
    protected function extractCustomerInfo(string $message): array
    {
        // FIX: Normalize Arabic digits in message for phone extraction
        $normalizedMessage = strtr($message, self::AR_DIGITS);
        $text = $this->normalize($message);
        $info = [];

        // Phone - use normalized message to support Arabic digits
        if (preg_match('/(?:\+?964|0)?7[3-9]\d{8}/', $normalizedMessage, $match)) {
            $info['phone'] = $match[0];
        }

        // Name patterns (explicit: "اسمي زيد")
        if (preg_match('/(?:اسمي|انا|اني)\s+([\p{Arabic}]{2,}(?:\s+[\p{Arabic}]{2,})?)/u', $text, $match)) {
            $info['name'] = trim($match[1]);
        }

        // Address patterns (explicit: "عنواني البصرة")
        if (preg_match('/(?:عنواني|ساكن|بمنطقه)\s+(.+)/iu', $message, $match)) {
            $info['address'] = trim($match[1]);
        }

        // SMART AGENT: Multi-line detection for "زيد\nالبصره\n073256229" format
        $lines = preg_split('/[\r\n]+/', $message);
        $normalizedLines = preg_split('/[\r\n]+/', $normalizedMessage);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, fn($l) => mb_strlen($l) > 0);
        $lines = array_values($lines);

        if (count($lines) >= 2) {
            // Find phone line index
            $phoneLineIdx = -1;
            foreach ($lines as $i => $line) {
                $normalizedLine = strtr($line, self::AR_DIGITS);
                if (preg_match('/(?:\+?964|0)?7[3-9]\d{8}/', $normalizedLine)) {
                    $phoneLineIdx = $i;
                    break;
                }
            }

            // Iraqi cities for address detection
            $cities = ['بغداد', 'بصره', 'البصره', 'البصرة', 'موصل', 'نجف', 'كربلاء', 'اربيل', 'ديالى', 'انبار', 'بابل', 'زبير', 'كوت', 'ناصريه', 'سماوه', 'ديوانيه', 'حله', 'عماره', 'كركوك', 'دهوك', 'سليمانيه'];

            if ($phoneLineIdx >= 0) {
                // Line before phone could be name OR address
                if (empty($info['name']) && $phoneLineIdx > 0) {
                    $potentialName = $lines[$phoneLineIdx - 1];
                    // Check if it's a city (address) or name
                    $isCity = false;
                    foreach ($cities as $city) {
                        if (mb_stripos($this->normalize($potentialName), $this->normalize($city)) !== false) {
                            $isCity = true;
                            break;
                        }
                    }

                    if ($isCity) {
                        // It's address, look for name before it
                        if (empty($info['address'])) {
                            $info['address'] = $potentialName;
                        }
                        if ($phoneLineIdx > 1) {
                            $info['name'] = $lines[$phoneLineIdx - 2];
                        }
                    } else {
                        // It's name
                        if (preg_match('/^[\p{Arabic}\s]+$/u', $potentialName) && mb_strlen($potentialName) < 50) {
                            $info['name'] = $potentialName;
                        }
                    }
                }

                // Line after phone = address (if not already set)
                if (empty($info['address']) && $phoneLineIdx < count($lines) - 1) {
                    $info['address'] = $lines[$phoneLineIdx + 1];
                }
            }

            // SMART AGENT: If 3 lines and no phone found at specific position, try pattern matching
            if (count($lines) === 3 && empty($info['phone'])) {
                // Assume: name, city, phone OR name, phone, city
                foreach ($lines as $i => $line) {
                    $normalizedLine = strtr($line, self::AR_DIGITS);

                    // Check for phone
                    if (empty($info['phone']) && preg_match('/(?:\+?964|0)?7[3-9]\d{8}/', $normalizedLine, $match)) {
                        $info['phone'] = $match[0];
                        continue;
                    }

                    // Check for city
                    $isCity = false;
                    foreach ($cities as $city) {
                        if (mb_stripos($this->normalize($line), $this->normalize($city)) !== false) {
                            $isCity = true;
                            break;
                        }
                    }
                    if ($isCity && empty($info['address'])) {
                        $info['address'] = $line;
                        continue;
                    }

                    // Assume it's name
                    if (empty($info['name']) && preg_match('/^[\p{Arabic}\s]+$/u', $line) && mb_strlen($line) < 50) {
                        $info['name'] = $line;
                    }
                }
            }
        }

        // Single-line address (city detection) - only if nothing else extracted
        if (empty($info['address']) && empty($info['name']) && empty($info['phone'])) {
            $cities = ['بغداد', 'بصره', 'موصل', 'نجف', 'كربلاء', 'اربيل', 'ديالى', 'انبار', 'بابل', 'زبير', 'كوت', 'ناصريه'];
            foreach ($cities as $city) {
                if (mb_stripos($this->normalize($message), $this->normalize($city)) !== false && mb_strlen($message) < 100) {
                    $info['address'] = trim($message);
                    break;
                }
            }
        }

        Log::info('GroqChatV2: extractCustomerInfo result', [
            'original_message' => mb_substr($message, 0, 100),
            'extracted' => $info,
        ]);

        return $info;
    }

    /**
     * Check if message looks like customer info
     * FIX: Normalize Arabic digits before checking phone pattern
     */
    protected function looksLikeCustomerInfo(string $message): bool
    {
        $normalized = $this->normalize($message);
        // FIX: Normalize Arabic digits for phone detection
        $normalizedMessage = strtr($message, self::AR_DIGITS);

        // Phone number - use normalized message to support Arabic digits
        if (preg_match('/(?:\+?964|0)?7[3-9]\d{8}/', $normalizedMessage)) {
            return true;
        }

        // Name patterns
        if (preg_match('/(?:اسمي|انا|اني)\s+/u', $normalized)) {
            return true;
        }

        // Address patterns
        if (preg_match('/(?:عنواني|ساكن|بمنطقه)/u', $normalized)) {
            return true;
        }

        // City names in short messages
        $cities = ['بغداد', 'بصره', 'موصل', 'نجف', 'كربلاء', 'زبير', 'كوت'];
        if (mb_strlen($normalized) < 80) {
            foreach ($cities as $city) {
                if (mb_stripos($normalized, $this->normalize($city)) !== false) {
                    if (!$this->containsKeyword($normalized, ['توصلون', 'توصيل', 'هل', 'شنو', 'كم', 'سعر'])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Normalize Arabic text
     */
    protected function normalize(string $text): string
    {
        $text = trim($text);
        $text = strtr($text, self::AR_DIGITS);
        $text = str_replace(['أ', 'إ', 'آ', 'ء', 'ؤ'], 'ا', $text);
        $text = str_replace('ة', 'ه', $text);
        $text = str_replace('ى', 'ي', $text);
        $text = str_replace('ئ', 'ي', $text);
        $text = str_replace(['گ', 'ڭ'], 'ك', $text);
        $text = str_replace('چ', 'ج', $text);
        $text = str_replace('پ', 'ب', $text);
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
        $text = preg_replace('/(?:^|\s)ال/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', trim($text));
        return $text;
    }

    /**
     * Check if text contains any keyword
     */
    protected function containsKeyword(string $text, array $keywords): bool
    {
        $normalizedText = $this->normalize($text);
        foreach ($keywords as $keyword) {
            $normalizedKeyword = $this->normalize($keyword);
            if (mb_stripos($normalizedText, $normalizedKeyword) !== false) {
                return true;
            }
            if (mb_stripos($text, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Remove emojis
     */
    protected function removeEmojis(string $text): string
    {
        $text = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $text);
        $text = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', '', $text);
        $text = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $text);
        $text = preg_replace('/[\x{1F1E0}-\x{1F1FF}]/u', '', $text);
        $text = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $text);
        $text = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $text);
        $text = preg_replace('/[\x{FE00}-\x{FE0F}]/u', '', $text);
        $text = preg_replace('/[\x{1F900}-\x{1F9FF}]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * Call AI for response
     */
    protected function callAI(AiChatSession $session, string $message, string $prompt): string
    {
        try {
            $messages = $this->buildConversationHistory($session);
            $messages[] = ['role' => 'user', 'content' => $prompt];

            $result = $this->aiProvider->chat($messages, 0.3, 500, 'chat', $this->currentConversationId, $this->currentLeadId);

            if ($result['success'] && !empty($result['content'])) {
                return $this->removeEmojis(trim($result['content']));
            }

            return 'شلون اقدر اساعدك؟';
        } catch (\Exception $e) {
            Log::error('GroqChatV2: AI call failed', ['error' => $e->getMessage()]);
            return 'شلون اقدر اساعدك؟';
        }
    }

    /**
     * Build conversation history
     * FIX #16: Limit conversation history to prevent memory issues
     */
    protected function buildConversationHistory(AiChatSession $session): array
    {
        $messages = $session->messages ?? [];

        // FIX #16: Prune old messages if too many (keep last 50 total, send last 12)
        $maxStoredMessages = 50;
        $maxSentMessages = 12;

        if (count($messages) > $maxStoredMessages) {
            // Keep only last 50 messages in session
            $session->messages = array_slice($messages, -$maxStoredMessages);
            $session->save();
            Log::info('GroqChatV2: Pruned old messages from session', [
                'session_id' => $session->id,
                'pruned_count' => count($messages) - $maxStoredMessages,
            ]);
            $messages = $session->messages;
        }

        $history = [];
        $recentMessages = array_slice($messages, -$maxSentMessages);

        foreach ($recentMessages as $msg) {
            $history[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        return $history;
    }

    /**
     * Get or create session
     * FIX #20: Refresh context for sessions older than 1 hour
     * SMART AGENT FIX: Pre-load ALL customer data from Lead to avoid re-asking
     */
    protected function getOrCreateSession(Lead $lead, Conversation $conversation): AiChatSession
    {
        $session = AiChatSession::where('user_id', $this->user->id)
            ->where('lead_id', $lead->id)
            ->where('expires_at', '>', now())
            ->first();

        if ($session) {
            // FIX #20: Check if session context is stale (> 1 hour old)
            $lastActivity = $session->updated_at ?? $session->created_at;
            $hourAgo = now()->subHour();

            if ($lastActivity && $lastActivity->lt($hourAgo)) {
                Log::info('GroqChatV2: Refreshing stale session context', [
                    'session_id' => $session->id,
                    'last_activity' => $lastActivity->toISOString(),
                ]);

                // Refresh product context (prices/stock may have changed)
                $this->refreshSessionProductContext($session);
            }

            // SMART AGENT FIX: Always sync customer_data from Lead (in case updated externally)
            $this->syncCustomerDataFromLead($session, $lead);

            $session->touchActivity();
            return $session;
        }

        // Load products
        $products = Product::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->limit(200)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (int) $p->price,
                'stock' => (int) $p->quantity,
                'category_id' => $p->category_id,
            ])
            ->toArray();

        // SMART AGENT: Pre-load ALL available customer data from Lead
        $customerData = $this->extractCustomerDataFromLead($lead);

        Log::info('GroqChatV2: Creating new session with pre-loaded customer data', [
            'lead_id' => $lead->id,
            'has_name' => !empty($customerData['name']),
            'has_phone' => !empty($customerData['phone']),
            'has_address' => !empty($customerData['address']),
        ]);

        return AiChatSession::create([
            'user_id' => $this->user->id,
            'lead_id' => $lead->id,
            'conversation_id' => $conversation->id,
            'store_context' => [
                'name' => $this->user->name,
                'products' => $products,
                'delivery_cost' => $this->settings->delivery_cost ?? 5000,
                'delivery_time' => $this->settings->delivery_time ?? 'نفس اليوم',
                'customer_info_preloaded' => !empty($customerData['name']) || !empty($customerData['phone']) || !empty($customerData['address']),
            ],
            'messages' => [],
            'cart' => [],
            'customer_data' => $customerData,
            'expires_at' => now()->addMinutes($this->sessionTimeoutMinutes),
        ]);
    }

    /**
     * SMART AGENT: Extract all available customer data from Lead
     */
    protected function extractCustomerDataFromLead(Lead $lead): array
    {
        return [
            'name' => $lead->name ?? null,
            'phone' => $lead->phone ?? $lead->whatsapp ?? null,
            'address' => $this->buildAddressFromLead($lead),
        ];
    }

    /**
     * SMART AGENT: Build complete address from Lead fields
     */
    protected function buildAddressFromLead(Lead $lead): ?string
    {
        $parts = [];
        if (!empty($lead->address)) {
            $parts[] = $lead->address;
        }
        if (!empty($lead->area) && mb_stripos($lead->address ?? '', $lead->area) === false) {
            $parts[] = $lead->area;
        }
        if (!empty($lead->city) && mb_stripos($lead->address ?? '', $lead->city) === false) {
            $parts[] = $lead->city;
        }
        return !empty($parts) ? implode('، ', $parts) : null;
    }

    /**
     * SMART AGENT: Sync customer_data in session from Lead (in case Lead was updated)
     */
    protected function syncCustomerDataFromLead(AiChatSession $session, Lead $lead): void
    {
        $currentData = $session->customer_data ?? [];
        $leadData = $this->extractCustomerDataFromLead($lead);
        $updated = false;

        // Only update empty fields from Lead (don't overwrite user-provided data)
        if (empty($currentData['name']) && !empty($leadData['name'])) {
            $currentData['name'] = $leadData['name'];
            $updated = true;
        }
        if (empty($currentData['phone']) && !empty($leadData['phone'])) {
            $currentData['phone'] = $leadData['phone'];
            $updated = true;
        }
        if (empty($currentData['address']) && !empty($leadData['address'])) {
            $currentData['address'] = $leadData['address'];
            $updated = true;
        }

        if ($updated) {
            $session->customer_data = $currentData;
            $session->save();
            Log::info('GroqChatV2: Synced customer data from Lead', [
                'lead_id' => $lead->id,
                'updated_data' => $currentData,
            ]);
        }
    }

    /**
     * FIX #20: Refresh product context for stale session
     */
    protected function refreshSessionProductContext(AiChatSession $session): void
    {
        // Reload products from database
        $products = Product::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->limit(200)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (int) $p->price,
                'stock' => (int) $p->quantity,
                'category_id' => $p->category_id,
            ])
            ->toArray();

        $storeContext = $session->store_context ?? [];
        $storeContext['products'] = $products;
        $storeContext['context_refreshed_at'] = now()->toISOString();

        $session->store_context = $storeContext;
        $session->save();
    }

    /**
     * Build response array
     * FIX #27: Include pending images in response (supports multiple images)
     */
    protected function buildResponse(
        string $reply,
        string $intent,
        AiChatSession $session,
        bool $fastReply,
        ?string $actionRequired = null,
        ?array $orderData = null,
        ?array $productIds = null
    ): array {
        $response = [
            'reply' => $reply,
            'intent' => $intent,
            'session_id' => $session->id,
            'cart' => $session->cart ?? [],
            'cart_total' => $session->getCartTotal(),
            'customer_data' => $session->customer_data ?? [],
            'has_complete_customer_data' => $session->hasCompleteCustomerData(),
            'fast_reply' => $fastReply,
            'action_required' => $actionRequired,
            'order_data' => $orderData,
            'product_ids' => $productIds,
            'products_to_show' => $productIds, // FIX: AiChatService expects products_to_show not product_ids
        ];

        // FIX #27: Include pending images if any (supports multiple)
        $storeContext = $session->store_context ?? [];

        // Check for multiple images first
        if (!empty($storeContext['pending_images'])) {
            $response['images'] = $storeContext['pending_images'];

            // Clear pending images after including in response
            unset($storeContext['pending_images']);
            unset($storeContext['pending_image_url']);
            unset($storeContext['pending_image_product_id']);
            unset($storeContext['pending_image_product_name']);
            $session->store_context = $storeContext;
            $session->save();
        }
        // Fallback to single image (backward compatibility)
        elseif (!empty($storeContext['pending_image_url'])) {
            $response['images'] = [[
                'url' => $storeContext['pending_image_url'],
                'product_id' => $storeContext['pending_image_product_id'] ?? null,
                'product_name' => $storeContext['pending_image_product_name'] ?? null,
            ]];

            // Clear pending image after including in response
            unset($storeContext['pending_image_url']);
            unset($storeContext['pending_image_product_id']);
            unset($storeContext['pending_image_product_name']);
            $session->store_context = $storeContext;
            $session->save();
        }

        return $response;
    }
}
