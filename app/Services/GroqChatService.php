<?php

namespace App\Services;

use App\Models\AiChatSession;
use App\Models\AiFastReply;
use App\Models\AiSetting;
use App\Models\AiUsageLog;
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
 * Smart AI Chat Service for Store Management
 *
 * KEY FEATURES:
 * - Multi-provider support (OpenAI/ChatGPT and Groq)
 * - Smart category-based product discovery (for stores with 500+ items)
 * - Full store control access (delivery info, policies, inventory)
 * - Intent detection and contextual responses
 * - Token usage tracking and cost monitoring
 * - No cached replies - always fresh AI responses
 * - No emojis in responses
 * - Multi-item cart management
 * - Customer info collection
 */
class GroqChatService
{
    protected ?AiProviderService $aiProvider = null;
    protected ?string $apiKey;
    protected string $model;
    protected string $provider;
    protected string $apiUrl;
    protected int $sessionTimeoutMinutes;
    protected int $maxHistoryTurns;
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
        // Compound numbers (11-99)
        'احدعشر' => 11, 'احد عشر' => 11, 'إحدى عشر' => 11, 'حدعشر' => 11,
        'اثناعشر' => 12, 'اثنا عشر' => 12, 'اثنى عشر' => 12, 'طناعشر' => 12,
        'ثلاثعشر' => 13, 'ثلاث عشر' => 13, 'ثلاثة عشر' => 13, 'ثلطعشر' => 13,
        'اربعطعشر' => 14, 'اربع عشر' => 14, 'أربعة عشر' => 14, 'اربعة عشر' => 14,
        'خمسطعشر' => 15, 'خمس عشر' => 15, 'خمسة عشر' => 15, 'خمستعشر' => 15,
        'ستطعشر' => 16, 'ست عشر' => 16, 'ستة عشر' => 16, 'سطعشر' => 16,
        'سبعطعشر' => 17, 'سبع عشر' => 17, 'سبعة عشر' => 17,
        'ثمانطعشر' => 18, 'ثمان عشر' => 18, 'ثمانية عشر' => 18,
        'تسعطعشر' => 19, 'تسع عشر' => 19, 'تسعة عشر' => 19,
        'عشرين' => 20, 'عشرون' => 20,
        // Compound with tens (21-99)
        'واحد وعشرين' => 21, 'واحد وعشرون' => 21,
        'اثنين وعشرين' => 22, 'اثنان وعشرون' => 22,
        'ثلاثين' => 30, 'ثلاثون' => 30,
        'اربعين' => 40, 'أربعون' => 40,
        'خمسين' => 50, 'خمسون' => 50,
    ];

    // Dual form suffixes (indicates quantity = 2)
    protected const DUAL_SUFFIXES = ['ين', 'تين'];

    // Greeting keywords (includes smalltalk)
    // NOTE: Include both original and normalized versions (without ال)
    protected const GREETING_KEYWORDS = [
        'السلام عليكم', 'سلام عليكم', 'سلام', 'السلام', 'مرحبا', 'مرحبه',
        'اهلا', 'اهلاً', 'هلا', 'هلو', 'هاي', 'hi', 'hello',
        // Smalltalk / casual greetings (with and without ال)
        'كيف الحال', 'كيف حال', 'كيف حالك', 'كيفك', 'شلونك', 'شخبارك', 'شلون',
        'اخبارك', 'شنو اخبارك', 'كيف الأحوال', 'كيف الاحوال', 'كيف احوال',
        'صباح الخير', 'صباح خير', 'مساء الخير', 'مساء خير', 'صباحو', 'مسائو',
        // More casual greetings
        'شلونكم', 'شخباركم', 'كيفكم', 'اهلين', 'هلا والله', 'يا هلا',
    ];

    // Thanks keywords
    protected const THANKS_KEYWORDS = [
        'شكرا', 'شكراً', 'مشكور', 'مشكورين', 'مشكوره', 'تسلم', 'تسلمين',
        'تسلمون', 'ممنون', 'ممنونه', 'ممنونين', 'thx', 'thanks',
    ];

    // ORDER INTENT keywords - customer wants to BUY
    // IMPROVED: Added Iraqi dialect variations
    protected const ORDER_KEYWORDS = [
        'اريد', 'أريد', 'ابي', 'أبي', 'اطلب', 'أطلب', 'طلب',
        'اشتري', 'أشتري', 'خذلي', 'جيبلي', 'بدي', 'اخذ', 'آخذ',
        'ابغي', 'ابغى', 'اوصلي', 'احجز', 'نريد',
        'محتاج', 'اخذلي', 'جيب لي', 'وصلي', 'دزلي', 'دز لي',
        'ياخذ', 'يطلب', 'نطلب', 'اوردر', 'order',
    ];

    // INQUIRY keywords - customer is ASKING, NOT ordering
    // IMPROVED: Added Iraqi dialect variations for price questions
    protected const INQUIRY_KEYWORDS = [
        'كم', 'شكد', 'شگد', 'بشكد', 'چم', 'جم', 'سعر', 'اسعار', 'أسعار',
        'شنو', 'شنهي', 'ماهي', 'ما هي', 'عدكم', 'عندكم', 'موجود',
        'متوفر', 'متوفره', 'يوجد', 'فيه', 'في', 'هل',
        'قطعه', 'قطعة', 'كميه', 'كمية', 'عدد',
        'ابحث', 'بحث', 'دور', 'بقسم', 'قسم',
        'بيش', 'بكم', 'بچم', 'قيمة', 'قيمته',
    ];

    // Product list request keywords - trigger category discovery
    // Note: Include versions without "ال" since normalize() removes it
    // IMPORTANT: Don't include 'متوفر' here - it conflicts with inquiry intent
    protected const PRODUCT_LIST_KEYWORDS = [
        'المنتجات', 'منتجات', 'شنو عدكم', 'شنو عندكم', 'شنهي المنتجات',
        'ماذا عندكم', 'ماذا لديكم', 'شنو تبيعون', 'شتبيعون', 'البضاعه', 'بضاعه',
        'اعرض', 'اعرضلي', 'شنو موجود', 'شنو الي عندكم', 'المتوفر',
        'شنو اكو', 'شاكو', 'عدكم شنو', 'شنو عدكم اشياء', 'شغلات ثانيه',
        'شي ثاني', 'شي ثانيه', 'اشياء ثانيه', 'غير هذا', 'غيره',
    ];

    // Product attributes / store-related keywords
    protected const PRODUCT_ATTR_KEYWORDS = [
        'مقاس', 'مقاسات', 'قياس', 'قياسات',
        'لون', 'الوان', 'ألوان',
        'خامة', 'نوعيه', 'نوعية',
        'تفاصيل', 'مواصفات', 'وصف',
        'عرض', 'طول', 'قصه', 'قصة', 'موديل',
        'تبديل', 'استبدال', 'ضمان',
    ];

    // Cart removal keywords
    protected const REMOVE_KEYWORDS = ['الغاء', 'حذف', 'شيل', 'امسح', 'الغي', 'احذف', 'لا اريد', 'ما اريد'];

    // Confirmation keywords
    protected const CONFIRM_KEYWORDS = [
        'نعم', 'اي', 'صح', 'تمام', 'اكيد', 'موافق', 'اوكي', 'ok', 'yes',
        'استمر', 'كمل', 'اكمل', 'امشي', 'خلاص', 'مضبوط', 'ماشي', 'حلو', 'زين', 'طيب',
        'اكد', 'اكد الطلب', 'ارسل', 'ارسل الطلب', 'ثبت', 'ثبت الطلب', 'كمل الطلب',
    ];

    // Cancel/No keywords - Removed standalone 'لا' to prevent false positives with 'لا مشكلة'
    protected const CANCEL_KEYWORDS = ['كلا', 'لاء', 'ما اريد', 'ماريد', 'الغي', 'لا اريد', 'لا ابي', 'مو اريد', 'no', 'cancel'];

    // Image request keywords - customer wants to see product image
    // IMPROVED: Added more Iraqi dialect variations
    protected const IMAGE_REQUEST_KEYWORDS = [
        'صور', 'صورة', 'صوره', 'الصور', 'الصوره', 'الصورة',
        'شوفني', 'ارني', 'أرني', 'وريني', 'عرضلي صور',
        'ابي اشوف', 'اريد اشوف', 'أريد أشوف', 'ممكن اشوف',
        'بصور', 'صور المنتج', 'صورته', 'شكله', 'شوفه',
        'ممكن صوره', 'ممكن صورة', 'ممكن صور',
        'ارسل', 'ارسلي', 'ارسل لي', 'ابعث', 'ابعثلي', 'ابعث لي',
        'نعم ارسل', 'نعم ارسلي', 'اي ارسل', 'اي ارسلي',
        'اكو صور', 'فيه صور', 'عندكم صور',
        'دزلي', 'دز لي', 'دزني', 'دز', // Iraqi dialect for "send me"
        'اعطني صوره', 'اعطني صورة', 'اعطيني صوره', 'اعطيني صورة', // give me picture
        'خلي اشوف', 'ابي صوره', 'ابي صورة', 'اريد صوره', 'اريد صورة', // want to see/want picture
        'picture', 'image', 'photo', 'show me', 'send me',
    ];

    // Existing order status / summary keywords
    protected const ORDER_STATUS_KEYWORDS = [
        'تفاصيل طلبي', 'تفاصيل الطلب', 'ملخص الطلب', 'ملخص طلبي',
        'حالة الطلب', 'حالة طلبي', 'وين صار الطلب', 'وين وصل الطلب', 'وين وصل طلبي',
        'متى يوصل الطلب', 'يمته يوصل الطلب', 'يمتى يوصل طلبي', 'موعد التوصيل', 'وقت التوصيل',
        'طلبي الحالي', 'الطلب الحالي', 'طلبي الان', 'طلبي هسه', 'طلبي هسة', 'طلبي الحال', 'طلب الحالي',
        'شنو تفاصيل طلبي', 'شنو تفاصيل الطلب', 'تتبع الطلب', 'tracking', 'track order', 'order status',
    ];

    // Order reference keywords
    protected const ORDER_REFERENCE_KEYWORDS = ['طلبت', 'طلبي', 'الطلب', 'سابقا', 'سابقآ', 'قبل'];

    // Store info keywords
    protected const DELIVERY_KEYWORDS = ['توصيل', 'توصلون', 'توصيله', 'يوصل', 'التوصيل', 'سعر التوصيل', 'اجرة التوصيل'];
    protected const RETURN_KEYWORDS = ['استرجاع', 'استرداد', 'ارجاع', 'الاسترجاع', 'الاسترداد'];
    protected const HOURS_KEYWORDS = ['ساعات العمل', 'ساعات', 'مفتوح', 'يفتح', 'تفتحون', 'الدوام'];
    protected const PAYMENT_KEYWORDS = ['دفع', 'طريقة الدفع', 'كيف ادفع', 'نقد', 'كاش'];

    protected User $user;
    protected AiSetting $settings;
    protected StoreAgentService $agent;
    protected ?MissingDataDetector $missingDataDetector = null;
    protected ?ProductAttributeService $attributeService = null;
    protected ?AiPromptBuilder $promptBuilder = null;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->settings = $user->aiSetting ?? new AiSetting();
        $this->agent = new StoreAgentService($user);

        // Initialize new services
        $this->missingDataDetector = new MissingDataDetector($user);
        $this->attributeService = new ProductAttributeService();
        $this->promptBuilder = new AiPromptBuilder($user);

        // Initialize AI Provider Service (supports OpenAI and Groq)
        $this->aiProvider = new AiProviderService($user);
        $defaultProvider = config('services.ai.default_provider', 'openai');
        $this->provider = $defaultProvider === 'openai'
            ? 'openai'
            : ($this->settings->ai_provider ?? $defaultProvider);

        // Set API details based on provider
        if ($this->provider === 'openai') {
            $this->apiKey = $this->settings->openai_api_key ?? config('services.openai.api_key');
            $this->model = $this->settings->openai_model ?? config('services.openai.model', 'gpt-4.1-mini');
            $this->apiUrl = config('services.openai.api_url', 'https://api.openai.com/v1/chat/completions');
        } else {
            $this->apiKey = $this->settings->groq_api_key ?? config('services.groq.api_key');
            $this->model = $this->settings->groq_model ?? config('services.groq.model', 'llama-3.3-70b-versatile');
            $this->apiUrl = config('services.groq.api_url', 'https://api.groq.com/openai/v1/chat/completions');
        }

        // Extended to 24 hours to prevent cart loss during long conversations
        $this->sessionTimeoutMinutes = $this->settings->session_timeout_minutes ?? 1440;
        $this->maxHistoryTurns = $this->settings->max_history_turns ?? 10;
    }

    /**
     * Process a message and return AI response
     *
     * SMART AGENT FLOW:
     * 1. Try to handle simple queries locally (saves tokens)
     * 2. Get/create session with full store context
     * 3. Check for pending attribute questions
     * 4. Detect intent from message
     * 5. Use AI only when necessary
     * 6. Handle cart operations and customer info collection
     * 7. Remove emojis from response
     */
    public function processMessage(Conversation $conversation, Lead $lead, string $message): array
    {
        // Store conversation/lead IDs for usage tracking
        $this->currentConversationId = (string) $conversation->id;
        $this->currentLeadId = (string) $lead->id;

        // Get or create session with FULL store context
        $session = $this->getOrCreateSession($lead, $conversation);

        // Clear stale current_order if order is in a terminal status (delivered/cancelled/returned)
        // This prevents the AI from blocking product browsing due to old completed orders
        $sessionOrder = $session->current_order ?? null;
        if (!empty($sessionOrder)) {
            $terminalStatuses = ['delivered', 'cancelled', 'returned'];
            $orderStatus = $sessionOrder['status'] ?? null;
            if ($orderStatus && in_array($orderStatus, $terminalStatuses, true)) {
                $session->current_order = null;
                $session->save();
            } elseif (!empty($sessionOrder['order_id'])) {
                // Sync status from DB to catch orders updated by admin since session started
                $dbOrder = OnlineOrder::find($sessionOrder['order_id']);
                if ($dbOrder && in_array($dbOrder->status, $terminalStatuses, true)) {
                    $session->current_order = null;
                    $session->save();
                }
            }
        }

        // Check if we're waiting for an attribute response (size, color)
        $pendingAttrQuestion = $session->store_context['pending_attribute_question'] ?? null;
        if ($pendingAttrQuestion) {
            $result = $this->handlePendingAttributeResponse($session, $lead, $message, $pendingAttrQuestion);
            if ($result !== null) {
                $result['reply'] = $this->removeEmojis($result['reply'] ?? '');
                return $result;
            }
        }

        // Confirm previously saved customer info before asking again
        $storeContext = $session->store_context ?? [];
        if (!empty($storeContext['confirming_customer_info'])) {
            $normalized = $this->normalize($message);
            $confirmKeywords = array_merge(self::CONFIRM_KEYWORDS, ['نفس', 'نفسه', 'نفس العنوان', 'مثل السابق']);
            $changeKeywords = ['غير', 'تغيير', 'بدل', 'مو نفس', 'مو نفسها', 'عنوان جديد', 'رقم جديد', 'اسم جديد'];

            if ($this->containsKeyword($normalized, $confirmKeywords)) {
                $storeContext['confirming_customer_info'] = false;
                $storeContext['customer_info_confirmed'] = true;
                $session->store_context = $storeContext;
                $session->save();
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

            if ($this->looksLikeCustomerInfo($message)) {
                return $this->handleCustomerInfo($session, $lead, $message);
            }
        }

        // Check if we're waiting for a product name for image request
        $pendingImageRequest = $storeContext['pending_image_request'] ?? false;
        if ($pendingImageRequest) {
            // Clear the flag
            $storeContext['pending_image_request'] = false;
            $session->store_context = $storeContext;
            $session->save();

            // Try to find the product they mentioned
            $result = $this->handleImageRequestWithProductName($session, $message);
            if ($result !== null) {
                $result['reply'] = $this->removeEmojis($result['reply'] ?? '');
                return $result;
            }
        }

        // TRY TO HANDLE LOCALLY FIRST (Saves tokens!)
        $localResponse = $this->agent->tryHandleLocally($message, $session);
        if ($localResponse !== null) {
            $session->addMessage('user', $message);
            $session->addMessage('assistant', $localResponse);
            return $this->buildResponse($localResponse, 'local_response', $session, false);
        }

        // If we just asked "كم قطعة تريد؟" and user replied with a quantity, handle it
        $pendingProduct = $session->store_context['pending_product'] ?? null;
        if (!empty($pendingProduct) && $this->looksLikeQuantityOnly($message)) {
            $result = $this->handlePendingQuantity($session, $lead, $message);
            $result['reply'] = $this->removeEmojis($result['reply'] ?? '');
            return $result;
        }

        // Detect message intent
        $intent = $this->detectIntent($message);

        // SMART INTENT OVERRIDES based on session state:
        // 1. "لا شكر" / "شكرا" while cart is full + data complete → treat as order confirmation
        if ($intent === 'thanks' && !empty($session->cart) && $session->hasCompleteCustomerData()) {
            $intent = 'confirm';
        }
        // 2. "شكرا" after order already confirmed and cart cleared → farewell
        if ($intent === 'thanks' && !empty($session->current_order) && empty($session->cart)) {
            $intent = 'farewell';
        }

        // CRITICAL: Log product count in session for debugging
        $sessionProducts = $session->store_context['products'] ?? [];
        Log::info('GroqChat: Intent detected', [
            'message' => mb_substr($message, 0, 50),
            'intent' => $intent,
            'cart_count' => count($session->cart ?? []),
            'session_products_count' => count($sessionProducts),
            'user_id' => $this->user->id,
        ]);

        // Route based on intent - but ALL handlers use AI for response generation
        $result = match($intent) {
            'greeting' => $this->handleGreeting($session, $message),
            'farewell' => $this->handleFarewell($session, $message),
            'thanks' => $this->handleThanks($session, $message),
            'product_list' => $this->handleProductList($session, $message),
            'category_selection' => $this->handleCategorySelection($session, $message),
            'inquiry' => $this->handleSmartInquiry($session, $message),
            'order' => $this->handleOrder($session, $lead, $message),
            'customer_info' => $this->handleCustomerInfo($session, $lead, $message),
            'remove' => $this->handleRemoval($session, $message),
            'confirm' => $this->handleConfirmation($session, $lead, $message),
            'cancel' => $this->handleCancel($session, $message),
            'order_status' => $this->handleOrderStatus($session, $lead, $message),
            'order_reference' => $this->handleOrderReference($session, $lead, $message),
            'store_info_delivery' => $this->handleStoreInfoDelivery($session, $message),
            'store_info_return' => $this->handleStoreInfoReturn($session, $message),
            'store_info_hours' => $this->handleStoreInfoHours($session, $message),
            'store_info_payment' => $this->handleStoreInfoPayment($session, $message),
            'image_request' => $this->handleImageRequest($session, $message),
            default => $this->handleWithAI($session, $lead, $message),
        };

        // Remove emojis from all responses
        $result['reply'] = $this->removeEmojis($result['reply'] ?? '');

        return $result;
    }

    /**
     * Detect the intent of the message
     * IMPROVED: Better context awareness
     */
    protected function detectIntent(string $message): string
    {
        $normalized = $this->normalize($message);
        $msgLen = mb_strlen($normalized);

        // Short messages - check greetings/thanks first
        if ($msgLen < 30) {
            if ($this->containsKeyword($normalized, self::GREETING_KEYWORDS)) {
                return 'greeting';
            }
            if ($this->containsKeyword($normalized, self::THANKS_KEYWORDS)) {
                return 'thanks';
            }
        }

        // CRITICAL: Check image request BEFORE confirmation
        // User might say "اي نعم اعطني صوره" (yes, give me a picture) - image takes priority
        if ($this->containsKeyword($normalized, self::IMAGE_REQUEST_KEYWORDS)) {
            return 'image_request';
        }

        // Confirmation - check for SHORT confirmation words (max 20 chars) WITHOUT image request
        if ($msgLen < 20 && $this->containsKeyword($normalized, self::CONFIRM_KEYWORDS)) {
            return 'confirm';
        }

        // IMPORTANT: Check if user says "لا" but wants OTHER products
        // "لا اريد شغلات ثانيه" = "No, I want other things" -> product_list intent
        $wantsOtherKeywords = ['شغلات ثانيه', 'شي ثاني', 'اشياء ثانيه', 'غيره', 'منتجات ثانيه', 'شغله ثانيه'];
        if ($this->containsKeyword($normalized, $wantsOtherKeywords)) {
            return 'product_list';
        }

        // Cancel/No - only if it's a short rejection without asking for alternatives
        if ($msgLen < 20 && $this->containsKeyword($normalized, self::CANCEL_KEYWORDS)) {
            return 'cancel';
        }

        // User asking to see/show products (اعرضلي، عرض، شوفني)
        $showProductsKeywords = ['اعرض', 'عرضلي', 'اعرضلي', 'عرض لي'];
        if ($this->containsKeyword($normalized, $showProductsKeywords)) {
            return 'category_selection'; // Will use current category context
        }

        // User agreeing/searching (نعم ابحث، اي ابحث)
        $searchAgreeKeywords = ['نعم ابحث', 'اي ابحث', 'ابحث', 'ابحثلي', 'دور', 'دورلي'];
        if ($this->containsKeyword($normalized, $searchAgreeKeywords)) {
            return 'inquiry'; // Will trigger smart search
        }

        // Existing order status / details
        if ($this->containsKeyword($normalized, self::ORDER_STATUS_KEYWORDS)) {
            return 'order_status';
        }

        // Order reference ("طلبت سابقاً")
        if ($this->containsKeyword($normalized, self::ORDER_REFERENCE_KEYWORDS)) {
            return 'order_reference';
        }

        // Store info (delivery, return, hours, payment)
        if ($this->containsKeyword($normalized, self::DELIVERY_KEYWORDS)) {
            return 'store_info_delivery';
        }
        if ($this->containsKeyword($normalized, self::RETURN_KEYWORDS)) {
            return 'store_info_return';
        }
        if ($this->containsKeyword($normalized, self::HOURS_KEYWORDS)) {
            return 'store_info_hours';
        }
        if ($this->containsKeyword($normalized, self::PAYMENT_KEYWORDS)) {
            return 'store_info_payment';
        }

        // CRITICAL: Check category FIRST before product_list
        // This ensures "شنو متوفر ضمن الادوات المنزليه" shows products in that category
        $detectedCategory = $this->detectCategoryFromMessage($normalized);
        if ($detectedCategory !== null) {
            return 'category_selection';
        }

        // Product list request - trigger category discovery (ONLY if no specific category mentioned)
        // Check if message has product-specific words that indicate they're asking about something specific
        $hasSpecificProductWords = preg_match('/(?:فرن|بيتزا|قميص|بنطلون|هودي|فستان|جهاز|طقم|نظار|سماع|حقيب|كيبورد|شامبو|زيت)/u', $normalized);
        $isGeneralQuestion = !$hasSpecificProductWords && $this->containsKeyword($normalized, self::PRODUCT_LIST_KEYWORDS);

        if ($isGeneralQuestion) {
            return 'product_list';
        }

        // Removal intent
        if ($this->containsKeyword($normalized, self::REMOVE_KEYWORDS)) {
            return 'remove';
        }

        // Check if message looks like customer info
        if ($this->looksLikeCustomerInfo($message)) {
            return 'customer_info';
        }

        // CRITICAL: Check inquiry vs order
        $hasInquiry = $this->containsKeyword($normalized, self::INQUIRY_KEYWORDS);
        $hasOrder = $this->containsKeyword($normalized, self::ORDER_KEYWORDS);

        // If user says "اريد ابحث" or similar - inquiry takes priority
        $searchKeywords = ['ابحث', 'بحث', 'دور', 'بقسم'];
        $hasSearchIntent = $this->containsKeyword($normalized, $searchKeywords);

        // Inquiry takes priority if:
        // 1. Has inquiry keyword and no order keyword, OR
        // 2. Has explicit search intent
        if (($hasInquiry && !$hasOrder) || $hasSearchIntent) {
            return 'inquiry';
        }

        // Order intent
        if ($hasOrder) {
            return 'order';
        }

        return 'unknown';
    }

    /**
     * Detect category from message using actual store categories
     * IMPROVED: Better normalization and fuzzy matching
     */
    protected function detectCategoryFromMessage(string $normalizedMessage): ?array
    {
        $categories = $this->getStoreCategories();

        // Further clean the message for better matching
        $cleanMessage = $normalizedMessage;
        // Remove common words that shouldn't affect matching
        // NOTE: Using (?:^|\s)...(?=\s|$) instead of \b because \b doesn't work with Arabic
        $cleanMessage = preg_replace('/(?:^|\s)(قسم|فئه|انواع|نوع|شنو|عدكم|عندكم|موجود|متوفر|ابحث|اريد|ابي|من|ضمن)(?=\s|$)/u', ' ', $cleanMessage);
        $cleanMessage = preg_replace('/\s+/u', ' ', trim($cleanMessage));

        foreach ($categories as $category) {
            $categoryName = $this->normalize($category['name']);

            // Direct match (already normalized)
            if (mb_stripos($cleanMessage, $categoryName) !== false) {
                return $category;
            }

            // Word-by-word match (handles "ملابس رجالية" matching "ملابس")
            $categoryWords = preg_split('/\s+/u', $categoryName);
            $matchedWords = 0;
            foreach ($categoryWords as $word) {
                if (mb_strlen($word) < 2) continue;
                if (mb_stripos($cleanMessage, $word) !== false) {
                    $matchedWords++;
                }
            }
            // If most words match (at least half + 1)
            $requiredMatches = max(1, ceil(count($categoryWords) / 2));
            if ($matchedWords >= $requiredMatches) {
                return $category;
            }

            // Check aliases
            foreach ($category['aliases'] ?? [] as $alias) {
                $normalizedAlias = $this->normalize($alias);
                if (mb_stripos($cleanMessage, $normalizedAlias) !== false) {
                    return $category;
                }
            }

            // Fuzzy matching for common variations
            $fuzzyMatches = $this->generateFuzzyVariations($categoryName);
            foreach ($fuzzyMatches as $fuzzy) {
                if (mb_stripos($cleanMessage, $fuzzy) !== false) {
                    return $category;
                }
            }
        }

        return null;
    }

    /**
     * Generate fuzzy variations for category names
     * IMPROVED: More comprehensive Arabic variations
     */
    protected function generateFuzzyVariations(string $name): array
    {
        $variations = [];

        // Suffix variations (ية/يه/ي)
        $variations[] = str_replace('يه', 'ية', $name);
        $variations[] = str_replace('ية', 'يه', $name);
        $variations[] = str_replace('ائيه', 'ائية', $name);
        $variations[] = str_replace('ائية', 'ائيه', $name);
        $variations[] = str_replace('ائي', 'ي', $name);

        // Common word variations
        $variations[] = str_replace('كهربائي', 'كهربي', $name);
        $variations[] = str_replace('كهربي', 'كهربائي', $name);
        $variations[] = str_replace('منزلي', 'بيتي', $name);
        $variations[] = str_replace('بيتي', 'منزلي', $name);
        $variations[] = str_replace('اجهزه', 'جهاز', $name);
        $variations[] = str_replace('الكتروني', 'اليكتروني', $name);
        $variations[] = str_replace('اليكتروني', 'الكتروني', $name);

        // Without ات suffix
        if (mb_substr($name, -2) === 'ات') {
            $variations[] = mb_substr($name, 0, -2);
        }

        return array_filter(array_unique($variations));
    }

    /**
     * Get store categories with product counts
     */
    protected function getStoreCategories(): array
    {
        $categories = Category::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->withCount(['products' => function($q) {
                $q->where('is_active', true)->where('quantity', '>', 0);
            }])
            ->get()
            ->map(function($cat) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'products_count' => $cat->products_count,
                    'aliases' => $this->generateCategoryAliases($cat->name),
                ];
            })
            ->toArray();

        return $categories;
    }

    /**
     * Generate aliases for category names
     */
    protected function generateCategoryAliases(string $name): array
    {
        $base = $this->normalize($name);
        $aliases = [$base];

        // Add dual and plural forms
        foreach (self::DUAL_SUFFIXES as $suffix) {
            $aliases[] = $base . $suffix;
        }
        $aliases[] = $base . 'ات';

        return array_unique($aliases);
    }

    /**
     * Check if message looks like customer providing their info
     */
    protected function looksLikeCustomerInfo(string $message): bool
    {
        $normalized = $this->normalize($message);

        // Phone number (Iraqi format)
        if (preg_match('/\b(?:\+?964|0)?7[3-9]\d{8}\b/', $normalized)) {
            return true;
        }

        // Explicit name patterns
        if (preg_match('/(?:اسمي|انا|اني)\s+/u', $normalized)) {
            return true;
        }

        // Address patterns
        if (preg_match('/(?:عنواني|عنوان|ساكن|بمنطقه)/u', $normalized)) {
            return true;
        }

        // City/area mention in short message (likely address)
        // Already normalized (without ال)
        $areas = ['بغداد', 'بصره', 'موصل', 'نجف', 'كربلاء', 'اربيل', 'سماوه', 'ناصريه', 'ديالى', 'انبار', 'كوت', 'حله', 'كركوك', 'واسط'];
        $subareas = ['ابي الخصيب', 'زبير', 'قرنه', 'كرخ', 'رصافه', 'كاضميه', 'اعضميه', 'شعله', 'عارضات', 'حي', 'منطقه', 'شارع', 'محله', 'قضاء', 'ناحيه', 'مدينه'];

        if (mb_strlen($normalized) < 80) {
            foreach (array_merge($areas, $subareas) as $area) {
                $normalizedArea = $this->normalize($area);
                if (mb_stripos($normalized, $normalizedArea) !== false) {
                    // But not if asking about delivery or product
                    if (!$this->containsKeyword($normalized, ['توصلون', 'توصيل', 'هل', 'شنو', 'كم', 'سعر', 'عدكم'])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    // ==================== INTENT HANDLERS ====================

    /**
     * Handle greeting - Use AI to generate natural response
     */
    protected function handleGreeting(AiChatSession $session, string $message): array
    {
        $storeContext = $session->store_context;
        $storeName = $storeContext['name'] ?? 'المتجر';
        $categories = $this->getStoreCategories();
        $categoryNames = array_column(array_slice($categories, 0, 5), 'name');

        $contextInfo = $this->buildStoreContextForAI($session);

        // Friendly greeting prompt
        $prompt = "الزبون حياك بـ: '{$message}'
رد عليه بعراقي ودود (مثل: هلا والله، الحمدلله بخير، شلونك انت؟).
بعدين اسأله شنو يحتاج من {$storeName}.
{$contextInfo}
الأقسام المتوفرة: " . implode('، ', $categoryNames) . "
ملاحظة: لا تستخدم ايموجي. كن ودود ومختصر.";

        $reply = $this->callAIForResponse($session, $message, $prompt);

        $session->addMessage('user', $message);
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'greeting', $session, false);
    }

    /**
     * Handle thanks - Use AI to generate natural response
     */
    protected function handleThanks(AiChatSession $session, string $message): array
    {
        $prompt = "الزبون شكرك. رد بعراقي واسأل اذا يحتاج شي ثاني. لا ايموجي.";

        $reply = $this->callAIForResponse($session, $message, $prompt);

        $session->addMessage('user', $message);
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'thanks', $session, false);
    }

    /**
     * Handle farewell - customer is saying goodbye after completed order
     */
    protected function handleFarewell(AiChatSession $session, string $message): array
    {
        $currentOrder = $session->current_order ?? null;
        $orderNumber = $currentOrder['order_number'] ?? '';
        $orderLine = $orderNumber ? "رقم طلبه {$orderNumber}" : 'طلبه';

        $prompt = "الزبون قال شكرا وانهى المحادثة بعد تأكيد {$orderLine}.
ودعه بود. اخبره ان سنتواصل معه قريبا للتوصيل.
لا تسأله شي آخر - هذا نهاية المحادثة.
لا ايموجي. جملة واحدة أو جملتين فقط.";

        $reply = $this->callAIForResponse($session, $message, $prompt);

        $session->addMessage('user', $message);
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'farewell', $session, false);
    }

    /**
     * Handle image request - Customer wants to see product image
     * IMPROVED: Uses current_product_id for accurate product tracking
     */
    protected function handleImageRequest(AiChatSession $session, string $message): array
    {
        $storeContext = $session->store_context ?? [];
        $lastShownProducts = $storeContext['last_shown_products'] ?? [];
        $productsHistory = $storeContext['products_history'] ?? [];
        $allProducts = $storeContext['products'] ?? [];
        $normalized = $this->normalize($message);

        // SMART PRODUCT DETECTION - Try multiple methods to find what user wants
        $mentionedProduct = null;

        // CRITICAL FIX: Check if this is a PRONOUN reference to current/last discussed product
        $pronounPatterns = [
            'صورته', 'صوره', 'صورتو', // its picture
            'هذا', 'هاذا', 'هذي', 'هاي', 'هاذي', // this
            'ياه', 'اياه', 'منه', // it/of it
            'نفسه', 'نفسها', // the same one
            'هو', 'هي', // he/she/it
            'اللي', 'الي', // the one that
            'المنتج', 'للمنتج', // the product
        ];

        $isPronounReference = false;
        foreach ($pronounPatterns as $pronoun) {
            if (mb_stripos($normalized, $pronoun) !== false) {
                $isPronounReference = true;
                break;
            }
        }

        // IMPROVED: For pronoun references, use current_product_id first (most reliable)
        if ($isPronounReference) {
            // Priority 1: Use explicitly tracked current product
            $currentProduct = $this->getCurrentProduct($session);
            if ($currentProduct) {
                $mentionedProduct = $currentProduct;
                Log::info('ImageRequest: Using current_product_id for pronoun reference', [
                    'message' => $message,
                    'product_id' => $currentProduct['id'],
                    'product_name' => $currentProduct['name'] ?? 'unknown',
                ]);
            }
            // Priority 2: Fallback to last shown products only if no current product
            elseif (!empty($lastShownProducts)) {
                $mentionedProduct = $lastShownProducts[0];
                Log::info('ImageRequest: Fallback to last_shown_products for pronoun reference', [
                    'message' => $message,
                    'product' => $mentionedProduct['name'] ?? 'unknown',
                ]);
            }
        }

        // If NOT a pronoun reference, search for specific product by name
        if (!$mentionedProduct) {
            // Method 1: Search in message for product name from all products
            $mentionedProduct = $this->findProductInMessage($normalized, $allProducts);

            // Method 2: Search database with smart keywords
            if (!$mentionedProduct) {
                $mentionedProduct = $this->smartProductSearch($message);
            }

            // Method 3: Check products history (recently discussed products)
            if (!$mentionedProduct && !empty($productsHistory)) {
                $mentionedProduct = $this->findProductInMessage($normalized, $productsHistory);
            }

            // Method 4: Check last shown products
            if (!$mentionedProduct && !empty($lastShownProducts)) {
                $mentionedProduct = $this->findProductInMessage($normalized, $lastShownProducts);
            }
        }

        // CRITICAL: If we found a product, verify it's still in stock
        if ($mentionedProduct && !empty($mentionedProduct['id'])) {
            $verifiedProduct = $this->verifyProductStock($mentionedProduct['id']);
            if ($verifiedProduct) {
                $mentionedProduct = $verifiedProduct;
            } else {
                // Product out of stock - inform user
                $productName = $mentionedProduct['name'] ?? 'المنتج';
                $reply = "للأسف {$productName} غير متوفر حاليا. هل تريد تشوف منتجات ثانية؟";
                $session->addMessage('user', $message);
                $session->addMessage('assistant', $reply);
                return $this->buildResponse($reply, 'product_unavailable', $session, false);
            }
        }

        if ($mentionedProduct) {
            // User asked for specific product image - set as current product
            $this->storeShownProducts($session, [$mentionedProduct], true);

            $productName = $mentionedProduct['name'] ?? 'المنتج';
            $reply = 'حاضر، هاي صورة ' . $productName;

            $session->addMessage('user', $message);
            $session->addMessage('assistant', $reply);

            $productId = $mentionedProduct['id'] ?? null;
            return $this->buildResponse($reply, 'image_request', $session, false, null, null, $productId ? [$productId] : null);
        }

        // No specific product found - check if we have context
        if (empty($lastShownProducts) && empty($productsHistory)) {
            // No products in context - ask which one
            $storeContext['pending_image_request'] = true;
            $session->store_context = $storeContext;
            $session->save();

            $reply = 'شنو المنتج الي تريد تشوف صورته؟';
            $session->addMessage('user', $message);
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'image_request_no_product', $session, false);
        }

        // Multiple products in context - use current_product if set, otherwise ask
        if (count($lastShownProducts) > 1) {
            // Check if we have a current product set
            $currentProduct = $this->getCurrentProduct($session);
            if ($currentProduct) {
                // Use the current product
                $productId = $currentProduct['id'];
                $productName = $currentProduct['name'] ?? 'المنتج';

                $reply = 'حاضر، هاي صورة ' . $productName;
                $session->addMessage('user', $message);
                $session->addMessage('assistant', $reply);

                return $this->buildResponse($reply, 'image_request', $session, false, null, null, [$productId]);
            }

            // No current product - ask which one they want
            $productNames = array_map(fn($p) => $p['name'] ?? '', array_slice($lastShownProducts, 0, 4));
            $productNames = array_filter($productNames);

            $reply = 'عندي عدة منتجات. تريد صورة ' . implode(' أو ', $productNames) . '؟';
            $session->addMessage('user', $message);
            $session->addMessage('assistant', $reply);

            return $this->buildResponse($reply, 'image_request_clarify', $session, false);
        }

        // Single product in context
        $product = $lastShownProducts[0];
        $productId = $product['id'] ?? null;
        $productName = $product['name'] ?? 'المنتج';

        // Verify stock
        if ($productId) {
            $verifiedProduct = $this->verifyProductStock($productId);
            if (!$verifiedProduct) {
                $reply = "للأسف {$productName} غير متوفر حاليا. هل تريد تشوف منتجات ثانية؟";
                $session->addMessage('user', $message);
                $session->addMessage('assistant', $reply);
                return $this->buildResponse($reply, 'product_unavailable', $session, false);
            }
        }

        $reply = 'حاضر، هاي صورة ' . $productName;
        $session->addMessage('user', $message);
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'image_request', $session, false, null, null, $productId ? [$productId] : null);
    }

    /**
     * Verify product is still in stock (real-time database check)
     */
    protected function verifyProductStock(int $productId): ?array
    {
        $product = Product::where('id', $productId)
            ->where('user_id', $this->user->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->first();

        if (!$product) {
            return null;
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => (int) $product->price,
            'stock' => (int) $product->quantity,
            'category_id' => $product->category_id,
        ];
    }

    /**
     * Smart product search - handles Arabic variations and common terms
     */
    protected function smartProductSearch(string $message): ?array
    {
        $normalized = $this->normalize($message);

        // Common Arabic product keywords and their variations
        $productKeywords = [
            // Clothing
            'قميص' => ['قميص', 'قمصان', 'شيرت', 'تيشيرت', 'تشيرت', 'تيشرت', 'shirt'],
            'فستان' => ['فستان', 'فساتين', 'dress'],
            'بنطلون' => ['بنطلون', 'بنطرون', 'بنطال', 'pants', 'trouser'],
            'هودي' => ['هودي', 'هوديز', 'hoodie', 'سويتر', 'سويتشرت'],
            'جاكيت' => ['جاكيت', 'جاكت', 'jacket', 'كوت'],

            // Electronics
            'موبايل' => ['موبايل', 'تلفون', 'جوال', 'هاتف', 'phone', 'mobile'],
            'لابتوب' => ['لابتوب', 'لاب توب', 'laptop', 'حاسوب', 'كمبيوتر'],
            'سماعة' => ['سماعة', 'سماعات', 'headphone', 'earphone'],
            'ساعة' => ['ساعة', 'ساعات', 'watch'],
            'ضوء' => ['ضوء', 'ضو', 'ضوي', 'اضاءة', 'اضاءه', 'ليت', 'لايت', 'light'],
            'تحكم' => ['تحكم', 'ريموت', 'remote', 'كونترول'],

            // Kitchen/Home
            'فرن' => ['فرن', 'افران', 'oven', 'طباخ'],
            'بيتزا' => ['بيتزا', 'بيتسا', 'pizza'],
            'طاولة' => ['طاولة', 'طاوله', 'table', 'منضدة'],
            'كرسي' => ['كرسي', 'كراسي', 'مقعد', 'chair', 'seat'],
            'سرير' => ['سرير', 'تخت', 'bed'],

            // Medical
            'جهاز' => ['جهاز', 'اجهزه', 'اجهزة', 'device'],
            'ضغط' => ['ضغط', 'pressure', 'blood pressure'],
        ];

        // Find which keyword category matches
        $searchTerms = [];
        foreach ($productKeywords as $main => $variations) {
            foreach ($variations as $variation) {
                if (mb_stripos($normalized, $variation) !== false) {
                    $searchTerms[] = $main;
                    $searchTerms = array_merge($searchTerms, $variations);
                    break 2;
                }
            }
        }

        // Also extract words from message that might be product names
        $words = preg_split('/\s+/u', $normalized);
        foreach ($words as $word) {
            if (mb_strlen($word) > 2) {
                $searchTerms[] = $word;
            }
        }

        // Search database for matching products
        if (!empty($searchTerms)) {
            $query = Product::where('user_id', $this->user->id)
                ->where('is_active', true)
                ->where('quantity', '>', 0);

            $query->where(function($q) use ($searchTerms) {
                foreach (array_unique($searchTerms) as $term) {
                    $q->orWhere('name', 'LIKE', "%{$term}%");
                }
            });

            $product = $query->first();

            if ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (int) $product->price,
                    'stock' => (int) $product->quantity,
                ];
            }
        }

        return null;
    }

    /**
     * Handle image request when user specifies product name
     * Called when we asked "شنو المنتج الي تريد تشوف صورته؟"
     */
    protected function handleImageRequestWithProductName(AiChatSession $session, string $message): ?array
    {
        $normalized = $this->normalize($message);
        $storeContext = $session->store_context ?? [];
        $products = $storeContext['products'] ?? [];

        // Try to find the product they mentioned
        $matchedProduct = $this->findProductInMessage($normalized, $products);

        if (!$matchedProduct) {
            // Try searching
            $searchResults = $this->searchProducts($message, null, 5);
            if (!empty($searchResults)) {
                $matchedProduct = $searchResults[0];
            }
        }

        if ($matchedProduct && !empty($matchedProduct['id'])) {
            // Store it for context
            $this->storeShownProducts($session, [$matchedProduct]);

            $productName = $matchedProduct['name'] ?? 'المنتج';
            $reply = 'حاضر، هاي صورة ' . $productName;

            $session->addMessage('user', $message);
            $session->addMessage('assistant', $reply);

            return $this->buildResponse($reply, 'image_request', $session, false, null, null, [$matchedProduct['id']]);
        }

        // Product not found
        $reply = 'ما لكيت هذا المنتج. شنو بالضبط تريد تشوف؟';
        $session->addMessage('user', $message);
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'product_not_found', $session, false);
    }

    /**
     * Handle product list request - SMART CATEGORY DISCOVERY
     * Instead of showing all products, guide customer through categories
     */
    protected function handleProductList(AiChatSession $session, string $message): array
    {
        $categories = $this->getStoreCategories();
        $storeContext = $session->store_context;
        $storeName = $storeContext['name'] ?? 'المتجر';
        $totalProducts = $this->getTotalProductCount();

        // Get ALL products to show them (limit 10)
        $allProducts = $this->getTopProducts(10);

        // If store has very few products (1-3), just list them all
        if ($totalProducts <= 3) {
            $productList = array_map(fn($p) => "- {$p['name']}: {$p['price']} دينار", $allProducts);

            if (empty($productList)) {
                $prompt = "اعتذر للزبون - لا توجد منتجات متاحة حاليا. اطلب منه يتواصل لاحقا. لا ايموجي.";
            } else {
                $prompt = "الزبون يسأل شنو المتوفر. هذي كل المنتجات المتوفرة:\n" . implode("\n", $productList) . "\n\nاعرضها للزبون واسأله شنو يريد. لا ايموجي.";
            }

            $this->storeShownProducts($session, $allProducts);

            $reply = $this->callAIForResponse($session, $message, $prompt);
            $session->addMessage('user', $message);
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'product_list', $session, false);
        }

        // SMART APPROACH: Ask customer about preferences instead of listing everything
        if (!empty($categories) && count($categories) > 1) {
            // Multiple categories - list ALL products grouped by category
            $productsByCategory = [];
            foreach ($categories as $cat) {
                $productsInCat = $this->getProductsInCategory($cat['id']);
                if (!empty($productsInCat)) {
                    $productsByCategory[$cat['name']] = array_slice($productsInCat, 0, 5);
                }
            }

            $productLines = [];
            foreach ($productsByCategory as $catName => $products) {
                $productLines[] = "\n📦 {$catName}:";
                foreach ($products as $p) {
                    $productLines[] = "- {$p['name']}: {$p['price']} دينار";
                }
            }

            $prompt = "الزبون يسأل عن المنتجات المتوفرة.\nعندنا {$totalProducts} منتج:" . implode("\n", $productLines) . "\n\nاعرضها بشكل منظم واسأله شنو يحتاج. لا ايموجي.";

            // Store all shown products
            $allShown = [];
            foreach ($productsByCategory as $products) {
                $allShown = array_merge($allShown, $products);
            }
            $this->storeShownProducts($session, $allShown);
        } elseif (!empty($categories) && count($categories) == 1) {
            // Single category - show all products in it
            $products = $this->getProductsInCategory($categories[0]['id']);
            $products = array_slice($products, 0, 10);
            $productList = array_map(fn($p) => "- {$p['name']}: {$p['price']} دينار", $products);

            $prompt = "الزبون يسأل عن المنتجات. عندنا في قسم {$categories[0]['name']}:\n" . implode("\n", $productList) . "\n\nاعرضها واسأل شنو يحتاج. لا ايموجي.";

            $this->storeShownProducts($session, $products);
        } else {
            // No categories - show all products
            $productList = array_map(fn($p) => "- {$p['name']}: {$p['price']} دينار", $allProducts);

            $prompt = "الزبون يسأل شنو المتوفر. عندنا:\n" . implode("\n", $productList) . "\n\nاعرضها واسأل شنو يريد. لا ايموجي.";

            $this->storeShownProducts($session, $allProducts);
        }

        $reply = $this->callAIForResponse($session, $message, $prompt);

        // Store that we're in category discovery mode
        $storeContext['discovery_mode'] = 'category';
        $session->store_context = $storeContext;
        $session->save();

        $session->addMessage('user', $message);
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'category_discovery', $session, false);
    }

    /**
     * Handle category selection - show products in selected category
     * OPTIMIZED: Immediate product listing, shorter prompts
     */
    protected function handleCategorySelection(AiChatSession $session, string $message): array
    {
        $normalized = $this->normalize($message);
        $selectedCategory = $this->detectCategoryFromMessage($normalized);

        $session->addMessage('user', $message);

        // If user mentions a category OR was already browsing one
        $currentCategory = $selectedCategory ?? ($session->store_context['current_category'] ?? null);

        if ($currentCategory) {
            // Get products in this category
            $productsInCategory = $this->getProductsInCategory($currentCategory['id']);

            if (empty($productsInCategory)) {
                $categories = $this->getStoreCategories();
                $otherCategories = array_filter($categories, fn($c) => $c['id'] !== $currentCategory['id']);
                $otherNames = array_column(array_slice($otherCategories, 0, 3), 'name');

                $prompt = "{$currentCategory['name']} فاضي. اقترح: " . implode('، ', $otherNames) . ". لا ايموجي.";

                $reply = $this->callAIForResponse($session, $message, $prompt);
                $session->addMessage('assistant', $reply);
                return $this->buildResponse($reply, 'category_empty', $session, false);
            }

            // OPTIMIZATION: Show only 4 products max when browsing category
            // NO images when browsing - only when asking about specific product
            $limitedProducts = array_slice($productsInCategory, 0, 4);
            $productNames = array_map(fn($p) => $p['name'], $limitedProducts);

            // Store ALL products for context (so user can ask about any)
            $this->storeShownProducts($session, $productsInCategory);

            // Store current category
            $storeContext = $session->store_context ?? [];
            $storeContext['current_category'] = $currentCategory;
            $storeContext['category_products'] = $productsInCategory;
            $session->store_context = $storeContext;
            $session->save();

            // Short prompt - just list names, ask what interests them
            $moreCount = count($productsInCategory) - 4;
            $moreText = $moreCount > 0 ? " وغيرها" : "";

            $prompt = "قسم {$currentCategory['name']} فيه: " . implode('، ', $productNames) . $moreText . ".
اذكر المنتجات بشكل مختصر واسأل الزبون شنو يهمه منها. لا تذكر الاسعار الا اذا سأل. لا ايموجي. جملتين فقط.";

            $reply = $this->callAIForResponse($session, $message, $prompt);
            $session->addMessage('assistant', $reply);

            // NO images when browsing category - only text
            return $this->buildResponse($reply, 'show_products', $session, false);
        }

        // No category found - try to search for product by name
        $searchResults = $this->searchProducts($message, null, 5);

        if (!empty($searchResults)) {
            // Store for context
            $this->storeShownProducts($session, $searchResults);
            $this->storeSearchQuery($session, $message);

            // Show max 4 products, no images
            $limitedResults = array_slice($searchResults, 0, 4);
            $productNames = array_map(fn($p) => $p['name'], $limitedResults);

            $prompt = "لكيت: " . implode('، ', $productNames) . ".
اذكرها واسأل شنو يهمه. لا اسعار الا اذا سأل. لا ايموجي. جملتين فقط.";

            $reply = $this->callAIForResponse($session, $message, $prompt);
            $session->addMessage('assistant', $reply);

            // NO images when searching - only when asking about specific product
            return $this->buildResponse($reply, 'search_results', $session, false);
        }

        // Nothing found - suggest categories
        $categories = $this->getStoreCategories();
        $categoryNames = array_map(fn($c) => $c['name'], array_slice($categories, 0, 4));

        $prompt = "ما لكيت '{$message}'. اقترح عليه يختار من: " . implode('، ', $categoryNames) . ". لا ايموجي. جملة وحدة.";

        $reply = $this->callAIForResponse($session, $message, $prompt);
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'not_found', $session, false);
    }

    /**
     * Get products in a specific category
     */
    protected function getProductsInCategory(int $categoryId): array
    {
        return Product::where('user_id', $this->user->id)
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->orderBy('quantity', 'desc')
            ->limit(20)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (int) $p->price,
                'stock' => (int) $p->quantity,
                'description' => $p->description,
            ])
            ->toArray();
    }

    /**
     * Show a specific product and ask for quantity
     */
    protected function showProductAndAskQuantity(AiChatSession $session, array $product): array
    {
        $prompt = "الزبون يسأل عن منتج: {$product['name']}
السعر: {$product['price']} دينار
المتوفر: {$product['stock']} قطعة

اخبره بالتفاصيل واسأله كم قطعة يريد.
ملاحظة: لا تستخدم ايموجي.";

        $reply = $this->callAIForResponse($session, '', $prompt);

        // Store pending product
        $storeContext = $session->store_context ?? [];
        $storeContext['pending_product'] = [
            'name' => $product['name'],
            'price' => (int) $product['price'],
            'stock' => (int) ($product['stock'] ?? 0),
        ];
        $session->store_context = $storeContext;
        $session->save();

        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'asking_quantity', $session, false);
    }

    /**
     * Handle smart inquiry - answer questions AND search for products
     * IMPROVED: Real-time stock validation, proper current product tracking
     */
    protected function handleSmartInquiry(AiChatSession $session, string $message): array
    {
        $normalized = $this->normalize($message);
        $storeContext = $session->store_context ?? [];
        $products = $storeContext['products'] ?? [];
        $currentCategory = $storeContext['current_category'] ?? null;
        $lastShownProducts = $storeContext['last_shown_products'] ?? [];

        // CRITICAL FIX: Check if this is a SHORT follow-up question about current/last product
        // Examples: "كم سعره", "بكم", "شكد", "سعره", "كم"
        $priceFollowUpPatterns = ['كم سعره', 'سعره', 'بكم', 'شكد', 'كم', 'شكد سعره', 'كم السعر', 'السعر'];
        $isShortPriceQuestion = mb_strlen($normalized) < 20 && $this->containsKeyword($normalized, $priceFollowUpPatterns);

        if ($isShortPriceQuestion) {
            // IMPROVED: Use current_product first, then fallback to last_shown
            $currentProduct = $this->getCurrentProduct($session);
            $targetProduct = $currentProduct ?? ($lastShownProducts[0] ?? null);

            if ($targetProduct) {
                // Verify stock in real-time
                if (!empty($targetProduct['id'])) {
                    $verifiedProduct = $this->verifyProductStock($targetProduct['id']);
                    if (!$verifiedProduct) {
                        $reply = "للأسف {$targetProduct['name']} غير متوفر حاليا. هل ترغب بمنتج آخر؟";
                        $session->addMessage('user', $message);
                        $session->addMessage('assistant', $reply);
                        return $this->buildResponse($reply, 'product_unavailable', $session, false);
                    }
                    $targetProduct = $verifiedProduct;
                }

                Log::info('SmartInquiry: Follow-up price question about product', [
                    'message' => $message,
                    'product' => $targetProduct['name'] ?? 'unknown',
                    'source' => $currentProduct ? 'current_product' : 'last_shown',
                ]);

                $prompt = "{$targetProduct['name']}: {$targetProduct['price']} دينار.
اخبره بالسعر واسأل اذا يريد يطلبه. جملتين فقط. لا ايموجي.";

                $reply = $this->callAIForResponse($session, $message, $prompt);
                $session->addMessage('user', $message);
                $session->addMessage('assistant', $reply);

                $productId = $targetProduct['id'] ?? null;
                return $this->buildResponse($reply, 'product_price', $session, false, null, null, $productId ? [$productId] : null);
            }
        }

        // Check if similar question was asked recently
        $cachedResponse = $this->checkCachedInquiry($session, $normalized);
        if ($cachedResponse !== null) {
            $session->addMessage('user', $message);
            $session->addMessage('assistant', $cachedResponse);
            return $this->buildResponse($cachedResponse, 'inquiry_cached', $session, false);
        }

        // CRITICAL FIX: ALWAYS search database FIRST before checking session products
        // This ensures we find products even if session doesn't have them loaded
        $searchResults = $this->searchProducts($message, null, 10); // Search without category limit first

        Log::info('SmartInquiry: Database search', [
            'query' => $message,
            'results_count' => count($searchResults),
            'results' => array_column(array_slice($searchResults, 0, 3), 'name'),
        ]);

        // If database search found results, use them
        if (!empty($searchResults)) {
            // Check if one result is very close match (has most words from query)
            $bestMatch = $this->findBestMatchFromResults($normalized, $searchResults);

            if ($bestMatch) {
                // User asking about specific product - SET AS CURRENT PRODUCT
                $this->storeShownProducts($session, [$bestMatch], true);
                $this->setCurrentProduct($session, $bestMatch); // Explicitly set as current

                $prompt = "المنتج موجود: {$bestMatch['name']}\nالسعر: {$bestMatch['price']} دينار\nالكمية المتوفرة: {$bestMatch['stock']} قطعة.
اخبره بالتفاصيل واسأل اذا يريد يطلبه. جملتين فقط. لا ايموجي.";

                $reply = $this->callAIForResponse($session, $message, $prompt);
                $session->addMessage('user', $message);
                $session->addMessage('assistant', $reply);

                $productId = $bestMatch['id'] ?? null;
                return $this->buildResponse($reply, 'product_info', $session, false, null, null, $productId ? [$productId] : null);
            }

            // Multiple results - show them (don't set current product since user hasn't chosen)
            $this->storeShownProducts($session, $searchResults, false);
            $this->trackSuggestedProducts($session, $searchResults); // Track for variety
            $limitedResults = array_slice($searchResults, 0, 4);
            $productNames = array_map(fn($p) => $p['name'], $limitedResults);

            $prompt = "لكيت: " . implode('، ', $productNames) . ".
اذكرها واسأل شنو يهمه. لا اسعار الا اذا سأل. لا ايموجي. جملتين.";

            $reply = $this->callAIForResponse($session, $message, $prompt);
            $session->addMessage('user', $message);
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'search_results', $session, false);
        }

        // Fallback: try to find in session products
        $matchedProduct = $this->findProductInMessage($normalized, $products);

        if ($matchedProduct) {
            // Verify stock before responding
            if (!empty($matchedProduct['id'])) {
                $verifiedProduct = $this->verifyProductStock($matchedProduct['id']);
                if (!$verifiedProduct) {
                    $reply = "هذا المنتج غير متوفر عندنا حاليا. هل ترغب في الاطلاع على منتجات أخرى؟";
                    $session->addMessage('user', $message);
                    $session->addMessage('assistant', $reply);
                    return $this->buildResponse($reply, 'product_unavailable', $session, false);
                }
                $matchedProduct = $verifiedProduct;
            }

            $this->storeShownProducts($session, [$matchedProduct], true);
            $this->setCurrentProduct($session, $matchedProduct); // Explicitly set as current

            $prompt = "{$matchedProduct['name']}: {$matchedProduct['price']} دينار.
اخبره بالسعر واسأل اذا يريد يطلبه او يشوف صورته. جملتين فقط. لا ايموجي.";

            $reply = $this->callAIForResponse($session, $message, $prompt);
            $session->addMessage('user', $message);
            $session->addMessage('assistant', $reply);

            $productId = $matchedProduct['id'] ?? null;
            return $this->buildResponse($reply, 'product_info', $session, false, null, null, $productId ? [$productId] : null);
        }

        // Check if asking about a category
        $category = $this->detectCategoryFromMessage($normalized);

        if ($category) {
            $productsInCat = $this->getProductsInCategory($category['id']);

            if (!empty($productsInCat)) {
                $this->storeShownProducts($session, $productsInCat);

                $storeContext['current_category'] = $category;
                $session->store_context = $storeContext;
                $session->save();

                // Max 4 products
                $limitedProducts = array_slice($productsInCat, 0, 4);
                $productNames = array_map(fn($p) => $p['name'], $limitedProducts);

                $prompt = "قسم {$category['name']} فيه: " . implode('، ', $productNames) . ".
اذكرها واسأل شنو يهمه. لا ايموجي. جملتين.";
            } else {
                $prompt = "قسم {$category['name']} فاضي. اقترح قسم ثاني. لا ايموجي.";
            }

            $reply = $this->callAIForResponse($session, $message, $prompt);
            $session->addMessage('user', $message);
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'category_products', $session, false);
        }

        // General inquiry - suggest categories
        $categories = $this->getStoreCategories();
        $categoryNames = array_map(fn($c) => $c['name'], array_slice($categories, 0, 4));

        $prompt = "سؤال: {$message}
الأقسام: " . implode('، ', $categoryNames) . "
ساعده واقترح قسم يناسبه. لا ايموجي. جملتين.";

        $reply = $this->callAIForResponse($session, $message, $prompt);

        $session->addMessage('user', $message);
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'inquiry', $session, false);
    }

    /**
     * Get price range for products
     */
    protected function getPriceRange(array $products): array
    {
        if (empty($products)) {
            return ['min' => 0, 'max' => 0];
        }

        $prices = array_column($products, 'price');
        return [
            'min' => min($prices),
            'max' => max($prices),
        ];
    }

    /**
     * Handle order intent - ADD to cart (REMOVED handleCategoryOrder - merged here)
     * CRITICAL: Only add products that EXIST in database with REAL prices
     */
    protected function handleOrder(AiChatSession $session, Lead $lead, string $message): array
    {
        $normalized = $this->normalize($message);

        // If we're already collecting customer info, don't let addresses be treated as order
        if (!empty($session->cart) && $this->looksLikeCustomerInfo($message)) {
            $missingFields = $session->getMissingFields();
            if (!empty($missingFields)) {
                return $this->handleCustomerInfo($session, $lead, $message);
            }
        }

        $products = $session->store_context['products'] ?? [];
        $lineItems = $this->extractLineItems($message, $products);

        // SPECIAL CASE: User says "اريد X" or "اريد X قطع" when cart already has items
        // This means they want to UPDATE the quantity of the last item
        if (empty($lineItems) && !empty($session->cart)) {
            $quantity = $this->extractQuantityFromMessage($message);
            // Check if message is ONLY about quantity (no product name)
            $hasOrderKeyword = $this->containsKeyword($normalized, self::ORDER_KEYWORDS);
            $cleanMsg = preg_replace('/[0-9٠-٩]+|قطع|قطعه|قطعة|حبه|حبة/u', '', $normalized);
            $cleanMsg = trim(preg_replace('/\s+/u', ' ', $cleanMsg));

            // If short message with order keyword and quantity, update last item
            if ($hasOrderKeyword && $quantity >= 1 && mb_strlen($cleanMsg) < 10) {
                $lastItem = end($session->cart);
                if ($lastItem) {
                    // Update the quantity of the last item in cart
                    $session->addToCart($lastItem['name'], $lastItem['price'], $quantity);
                    $session->addMessage('user', $message);
                    return $this->buildCartAndAskInfo($session, $lead);
                }
            }
        }

        if (empty($lineItems)) {
            // Customer said "اريد" but didn't specify product
            $categories = $this->getStoreCategories();
            $categoryNames = array_column($categories, 'name');

            $prompt = "الزبون يريد يطلب شي لكن ما حدد شنو.
الأقسام المتوفرة: " . implode('، ', $categoryNames) . "

اسأله شنو يريد بالضبط حتى تساعده.
ملاحظة: لا تستخدم ايموجي.";

            $reply = $this->callAIForResponse($session, $message, $prompt);

            $session->addMessage('user', $message);
            $session->addMessage('assistant', $reply);

            return $this->buildResponse($reply, 'order_clarify', $session, false);
        }

        // CRITICAL: Validate each item exists in database with REAL price
        $validatedItems = [];
        $invalidItems = [];

        foreach ($lineItems as $item) {
            $dbProduct = $this->validateProductInDatabase($item['name']);
            if ($dbProduct) {
                // Use REAL price from database, not from context
                $validatedItems[] = [
                    'name' => $dbProduct['name'],
                    'price' => $dbProduct['price'],
                    'quantity' => min($item['quantity'], $dbProduct['stock']), // Cap at available stock
                    'product_id' => $dbProduct['id'],
                ];
            } else {
                $invalidItems[] = $item['name'];
            }
        }

        // Report invalid items
        if (!empty($invalidItems) && empty($validatedItems)) {
            $prompt = "الزبون طلب منتجات غير موجودة: " . implode('، ', $invalidItems) . "
اخبره ان هذي المنتجات غير متوفرة عندنا واسأله شنو يريد من المنتجات المتوفرة.
ملاحظة: لا تستخدم ايموجي.";

            $reply = $this->callAIForResponse($session, $message, $prompt);
            $session->addMessage('user', $message);
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'product_not_found', $session, false);
        }

        // Add validated items to cart
        foreach ($validatedItems as $item) {
            $session->addToCart($item['name'], $item['price'], $item['quantity']);
        }

        Log::info('GroqChat: Validated items added to cart', [
            'validated_items' => $validatedItems,
            'invalid_items' => $invalidItems,
            'cart' => $session->cart,
        ]);

        // Check stock
        $outOfStock = $this->checkStock($session);
        if (!empty($outOfStock)) {
            $stockIssues = [];
            foreach ($outOfStock as $item) {
                $stockIssues[] = "{$item['name']}: طلبت {$item['requested']}، المتوفر {$item['available']}";
            }

            $prompt = "الزبون طلب منتجات لكن في مشكلة بالكمية:
" . implode("\n", $stockIssues) . "

اخبره بالمشكلة واسأله اذا يريد تعديل الكمية او حذف المنتج.
ملاحظة: لا تستخدم ايموجي.";

            $reply = $this->callAIForResponse($session, $message, $prompt);

            $session->addMessage('user', $message);
            $session->addMessage('assistant', $reply);

            return $this->buildResponse($reply, 'stock_issue', $session, false);
        }

        // Cart is valid - ask for customer info
        $session->addMessage('user', $message);
        return $this->buildCartAndAskInfo($session, $lead);
    }

    /**
     * Handle customer info (name/phone/address) - Use AI for responses
     */
    protected function handleCustomerInfo(AiChatSession $session, Lead $lead, string $message): array
    {
        $customerInfo = $this->extractCustomerInfo($message);

        Log::info('GroqChat: Customer info extracted', $customerInfo);

        if (!empty($customerInfo)) {
            $session->updateCustomerData($customerInfo);

            // Reset confirmation flags when data changes
            $storeContext = $session->store_context ?? [];
            $storeContext['customer_info_confirmed'] = false;
            $storeContext['confirming_customer_info'] = false;
            $session->store_context = $storeContext;
            $session->save();

            // Update lead
            if (!empty($customerInfo['name'])) {
                $lead->name = $customerInfo['name'];
            }
            if (!empty($customerInfo['phone'])) {
                $lead->phone = $customerInfo['phone'];
            }
            if (!empty($customerInfo['address'])) {
                $lead->address = $customerInfo['address'];
            }
            $lead->save();
        }

        // If cart has items, continue order flow
        if (!empty($session->cart)) {
            $session->addMessage('user', $message);
            return $this->buildCartAndAskInfo($session, $lead);
        }

        // No cart - acknowledge and ask what they want
        $categories = $this->getStoreCategories();
        $categoryNames = array_column($categories, 'name');

        $prompt = "الزبون اعطاك معلوماته. اشكره واسأله شنو يحتاج.
الأقسام المتوفرة: " . implode('، ', $categoryNames) . "
ملاحظة: لا تستخدم ايموجي. كن مختصر.";

        $reply = $this->callAIForResponse($session, $message, $prompt);

        $session->addMessage('user', $message);
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'info_received', $session, false);
    }

    /**
     * Handle removal request - Use AI for responses
     */
    protected function handleRemoval(AiChatSession $session, string $message): array
    {
        $products = $session->store_context['products'] ?? [];
        $lineItems = $this->extractLineItems($message, $products);
        $removedItems = [];

        if (!empty($lineItems)) {
            foreach ($lineItems as $item) {
                $name = (string) ($item['name'] ?? '');
                $removeQty = (int) ($item['quantity'] ?? 0);

                if ($name === '') {
                    continue;
                }

                if ($removeQty > 0) {
                    $cart = $session->cart ?? [];
                    $currentQty = null;
                    foreach ($cart as $cartItem) {
                        if (($cartItem['name'] ?? null) === $name) {
                            $currentQty = (int) ($cartItem['quantity'] ?? 1);
                            break;
                        }
                    }

                    if ($currentQty === null) {
                        continue;
                    }

                    $newQty = $currentQty - $removeQty;
                    if ($newQty <= 0) {
                        $session->removeFromCart($name);
                        $removedItems[] = $name;
                    } else {
                        $session->updateCartQuantity($name, $newQty);
                        $removedItems[] = "{$name} (خفضت الكمية)";
                    }
                } else {
                    $session->removeFromCart($name);
                    $removedItems[] = $name;
                }
            }
        } else {
            // Clear entire cart
            $session->clearCart();
        }

        $cartPreview = $this->buildCartPreview($session->cart ?? [], $session->getCartTotal());

        $prompt = "الزبون طلب حذف/تعديل السلة.
" . (!empty($removedItems) ? "تم حذف/تعديل: " . implode('، ', $removedItems) : "تم تفريغ السلة") . "
السلة الحالية: {$cartPreview}

أخبره بالتعديل واسأله اذا يريد شي ثاني.
ملاحظة: لا تستخدم ايموجي.";

        $reply = $this->callAIForResponse($session, $message, $prompt);

        $session->addMessage('user', $message);
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'cart_modified', $session, false);
    }

    /**
     * Handle pending attribute response (size, color, etc.)
     * Called when we asked about an attribute and waiting for response
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

        // Try to extract the attribute value from the message
        $extractedAttributes = $this->attributeService->extractAttributesFromMessage($message);
        $extractedValue = $extractedAttributes[$attrKey] ?? null;

        if (!$extractedValue) {
            // User didn't provide valid attribute - ask again
            $session->addMessage('user', $message);

            $attrName = StoreType::getAttributeName($attrKey);
            $prompt = "الزبون رد: {$message}
لكن ما فهمت شنو {$attrName} يريده لـ {$productName}.
اسأله مرة ثانية بشكل واضح.
ملاحظة: لا تستخدم ايموجي.";

            $reply = $this->callAIForResponse($session, $message, $prompt);
            $session->addMessage('assistant', $reply);

            return $this->buildResponse($reply, 'asking_attribute_again', $session, false);
        }

        // Validate the attribute value exists for the product
        $cart = $session->cart ?? [];
        $productId = $cart[$cartIndex]['product_id'] ?? null;

        if ($productId) {
            $product = Product::find($productId);
            if ($product && !$product->hasAttributeValue($attrKey, $extractedValue)) {
                // Value not available - show alternatives
                $availableValues = $product->attributes()
                    ->where('attribute_key', $attrKey)
                    ->where('is_available', true)
                    ->pluck('attribute_value')
                    ->toArray();

                $session->addMessage('user', $message);

                $attrName = StoreType::getAttributeName($attrKey);
                $displayValue = $attrKey === 'color' ? StoreType::getColorName($extractedValue) : $extractedValue;
                $availableDisplay = $attrKey === 'color'
                    ? array_map(fn($v) => StoreType::getColorName($v), $availableValues)
                    : $availableValues;

                $prompt = "الزبون اختار {$attrName}: {$displayValue} لكن غير متوفر.
المتوفر: " . implode('، ', $availableDisplay) . "
اخبره ان اختياره غير متوفر واعرض عليه البدائل.
ملاحظة: لا تستخدم ايموجي.";

                $reply = $this->callAIForResponse($session, $message, $prompt);
                $session->addMessage('assistant', $reply);

                return $this->buildResponse($reply, 'attribute_not_available', $session, false);
            }
        }

        // Update cart with the attribute
        $this->missingDataDetector->updateCartItemAttribute($session, $cartIndex, $attrKey, $extractedValue);

        // Clear pending question
        $storeContext = $session->store_context ?? [];
        unset($storeContext['pending_attribute_question']);
        $session->store_context = $storeContext;
        $session->save();

        Log::info('GroqChat: Attribute added to cart', [
            'cart_index' => $cartIndex,
            'attribute_key' => $attrKey,
            'attribute_value' => $extractedValue,
        ]);

        $session->addMessage('user', $message);

        // Check if there are more missing attributes or customer info
        return $this->buildCartAndAskInfo($session, $lead);
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
     * Handle confirmation - CREATE order
     */
    protected function handleConfirmation(AiChatSession $session, Lead $lead, string $message): array
    {
        $session->addMessage('user', $message);

        Log::info('GroqChat: handleConfirmation called', [
            'cart_count' => count($session->cart ?? []),
            'cart' => $session->cart,
            'customer_data' => $session->customer_data,
            'has_complete_data' => $session->hasCompleteCustomerData(),
            'missing_fields' => $session->getMissingFields(),
        ]);

        // Check if cart has items and all customer info is complete
        if (!empty($session->cart) && $session->hasCompleteCustomerData()) {
            $cart = $session->cart;
            $total = $session->getCartTotal();
            $customerData = $session->customer_data;

            // Create order in dashboard (prevent duplicates)
            $storeContext = $session->store_context ?? [];
            $orderPayload = [
                'items' => $cart,
                'customer' => $customerData,
                'total' => $total,
            ];
            $currentOrderHash = hash('sha256', json_encode($orderPayload));

            $order = null;
            if (!empty($storeContext['last_order_hash']) && $storeContext['last_order_hash'] === $currentOrderHash
                && !empty($storeContext['last_order_id'])) {
                $order = OnlineOrder::whereKey($storeContext['last_order_id'])->first();
            }

            if (!$order) {
                $allowedSources = ['facebook', 'instagram', 'whatsapp', 'ai_chat', 'manual'];
                $source = $storeContext['source'] ?? 'ai_chat';
                if (!in_array($source, $allowedSources, true)) {
                    $source = 'ai_chat';
                }

                // Update lead with customer data
                if (!empty($customerData['name'])) {
                    $lead->name = $customerData['name'];
                }
                if (!empty($customerData['phone'])) {
                    $lead->phone = $customerData['phone'];
                }
                if (!empty($customerData['address'])) {
                    $lead->address = $customerData['address'];
                }
                $lead->save();

                $order = OnlineOrder::create([
                    'user_id' => $this->user->id,
                    'lead_id' => $lead->id,
                    'conversation_id' => $session->conversation_id,
                    'customer_name' => $customerData['name'] ?? $lead->name ?? 'زبون',
                    'customer_phone' => $customerData['phone'] ?? $lead->phone ?? '',
                    'customer_address' => $customerData['address'] ?? $lead->address ?? '',
                    'customer_city' => $lead->city,
                    'subtotal' => $total,
                    'shipping_cost' => 0,
                    'discount' => 0,
                    'total' => $total,
                    'status' => 'pending',
                    'source' => $source,
                    'payment_method' => 'cash_on_delivery',
                    'payment_status' => 'pending',
                    'customer_notes' => 'طلب من الذكاء الاصطناعي - ' . count($cart) . ' منتج',
                    'meta_data' => [
                        'created_by' => 'groq_ai',
                        'items_count' => count($cart),
                        'inventory_strategy' => 'on_confirm',
                    ],
                ]);

                foreach ($cart as $item) {
                    $productName = $item['name'] ?? '';
                    $quantity = (int) ($item['quantity'] ?? 1);
                    $price = (int) ($item['price'] ?? 0);

                    $product = Product::where('user_id', $this->user->id)
                        ->where(function ($q) use ($productName) {
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

                $order->calculateTotals();

                $storeContext['last_order_hash'] = $currentOrderHash;
                $storeContext['last_order_id'] = $order->id;
                $session->store_context = $storeContext;
                $session->save();
            }

            // Create order data
            $currentOrder = [
                'items' => $cart,
                'total_price' => $total,
                'customer' => $customerData,
                'created_at' => now()->toIso8601String(),
                'order_id' => $order?->id,
                'order_number' => $order?->order_number,
                'status' => $order?->status ?? 'pending',
            ];
            $session->current_order = $currentOrder;
            $session->save();

            Log::info('GroqChat: Order data created for confirmation', [
                'order' => $currentOrder,
            ]);

            // Build items list for AI
            $itemsList = [];
            foreach ($cart as $item) {
                $itemsList[] = "{$item['name']} x {$item['quantity']} = " . ($item['price'] * $item['quantity']) . " دينار";
            }

            $storeContext = $session->store_context;
            $deliveryCost = $storeContext['delivery_cost'] ?? 5000;

            $orderNumber = $order?->order_number ?? '';
            $orderNumberLine = $orderNumber ? "رقم الطلب: {$orderNumber}\n" : '';
            $prompt = "الزبون أكد طلبه. أكد له الطلب بشكل ودي.
{$orderNumberLine}
المنتجات: " . implode('، ', $itemsList) . "
المجموع: {$total} دينار
اسم الزبون: {$customerData['name']}
الهاتف: {$customerData['phone']}
العنوان: {$customerData['address']}
التوصيل: {$deliveryCost} دينار

أخبره ان طلبه مؤكد وسنتواصل معه قريبا.
ملاحظة: لا تستخدم ايموجي.";

            $reply = $this->callAIForResponse($session, $message, $prompt);

            $session->addMessage('assistant', $reply);

            // Clear cart AFTER order confirmed
            $session->clearCart();

            return $this->buildResponse($reply, 'order_confirmed', $session, false, null, $currentOrder);
        }

        // If cart has items but missing info, ask for it
        if (!empty($session->cart)) {
            return $this->buildCartAndAskInfo($session, $lead);
        }

        // Nothing to confirm
        $categories = $this->getStoreCategories();
        $categoryNames = array_column($categories, 'name');

        $prompt = "الزبون قال نعم/تمام لكن ما عنده شي بالسلة.
اسأله شنو يريد يطلب.
الأقسام المتوفرة: " . implode('، ', $categoryNames) . "
ملاحظة: لا تستخدم ايموجي.";

        $reply = $this->callAIForResponse($session, $message, $prompt);
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'nothing_to_confirm', $session, false);
    }

    /**
     * Handle cancel/no - Use AI for response
     * IMPROVED: Checks if order exists in database and validates status before cancelling
     */
    protected function handleCancel(AiChatSession $session, string $message): array
    {
        $session->addMessage('user', $message);

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
                    // Order already shipped/delivered - cannot cancel via chat
                    $statusLabels = [
                        'shipped' => 'مشحون',
                        'delivered' => 'موصل',
                        'returned' => 'مرتجع',
                    ];
                    $statusLabel = $statusLabels[$order->status] ?? $order->status;

                    $prompt = "الزبون يريد يلغي طلبه لكن الطلب رقم {$order->order_number} حالته ({$statusLabel}).
هذا الطلب لا يمكن إلغاءه من المحادثة لأنه {$statusLabel}.
اخبره انه يتواصل مع خدمة العملاء للمساعدة.
ملاحظة: لا تستخدم ايموجي.";

                    $reply = $this->callAIForResponse($session, $message, $prompt);
                    $session->addMessage('assistant', $reply);

                    return $this->buildResponse($reply, 'order_cancel_denied', $session, false);
                }

                // Order can be cancelled (pending, confirmed, processing)
                $order->update(['status' => 'cancelled']);

                Log::info('GroqChat: Order cancelled via chat', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'previous_status' => $order->getOriginal('status'),
                ]);

                $prompt = "الزبون قرر يلغي طلبه رقم {$order->order_number}.
أكد له ان الطلب تم إلغاؤه بنجاح.
اسأله اذا يحتاج شي ثاني.
ملاحظة: لا تستخدم ايموجي.";
            } else {
                // Order ID exists but order not found (deleted?)
                $prompt = "الزبون قرر يلغي الطلب. اخبره تم الإلغاء واسأله اذا يحتاج شي ثاني.
ملاحظة: لا تستخدم ايموجي.";
            }
        } else {
            // No order in database - just clearing cart
            $prompt = "الزبون قرر يلغي الطلب. اخبره تم الإلغاء واسأله اذا يحتاج شي ثاني.
ملاحظة: لا تستخدم ايموجي.";
        }

        // Clear cart and session order
        $session->clearCart();
        $session->current_order = null;
        $session->save();

        $reply = $this->callAIForResponse($session, $message, $prompt);
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'order_cancelled', $session, false);
    }

    /**
     * Handle order status / details questions - SMART LOOKUP
     * Uses multiple methods to find user's order
     * IMPROVED: Always syncs session with database for latest status
     */
    protected function handleOrderStatus(AiChatSession $session, Lead $lead, string $message): array
    {
        if (!empty($session->cart)) {
            $session->addMessage('user', $message);
            return $this->buildCartAndAskInfo($session, $lead);
        }

        $session->addMessage('user', $message);

        // Try multiple ways to find the order
        $orderData = null;
        $orderFromDb = null;

        // 1. If session has order_id, fetch FRESH data from database first
        $sessionOrder = $session->current_order ?? null;
        if (!empty($sessionOrder['order_id'])) {
            $orderFromDb = OnlineOrder::with(['items', 'items.product'])->find($sessionOrder['order_id']);
            if ($orderFromDb) {
                $orderData = $this->formatOrderFromModel($orderFromDb);
                // Sync session with database
                $session->current_order = $orderData;
                $session->save();
            }
        }

        // 2. Check if there's a phone number in the message
        if (!$orderData && preg_match('/\b(?:\+?964|0)?7[3-9]\d{8}\b/', $message, $phoneMatch)) {
            $orderData = $this->agent->findOrderByPhone($phoneMatch[0]);
        }

        // 3. Check if there's an order number in the message
        if (!$orderData && preg_match('/(?:طلب|رقم)\s*#?\s*(\d+)/u', $message, $orderMatch)) {
            $orderData = $this->agent->findOrderByNumber($orderMatch[1]);
        }

        // 4. Try by lead ID (gets fresh data from database)
        if (!$orderData) {
            $orderData = $this->agent->getLatestOrder($lead->id);
        }

        // 5. Fallback to session current order (only if no database data found)
        if (!$orderData && !empty($sessionOrder) && empty($sessionOrder['order_id'])) {
            // Session has order data but no order_id (not yet saved to database)
            $orderData = $sessionOrder;
        }

        if (!empty($orderData)) {
            $items = $orderData['items'] ?? [];
            $itemsList = array_map(fn($i) => "{$i['name']} x {$i['quantity']}", $items);

            $status = $orderData['status'] ?? 'قيد التجهيز';
            $orderNumber = $orderData['order_number'] ?? '';
            $total = $orderData['total'] ?? $orderData['total_price'] ?? 0;

            $prompt = "انت مساعد مبيعات عراقي ودود.
الزبون يسأل عن حالة طلبه.

تفاصيل الطلب:
- رقم الطلب: {$orderNumber}
- المنتجات: " . implode('، ', $itemsList) . "
- المجموع: {$total} دينار
- الحالة: {$status}

اخبره بتفاصيل طلبه بشكل واضح وودي.
اذا كان قيد التجهيز، طمنه ان سنتواصل معه قريبا.
لا تستخدم ايموجي.";

            $reply = $this->callAIForResponse($session, $message, $prompt);
            $session->addMessage('assistant', $reply);

            // Always sync session with the latest order data (ensure fresh status)
            $session->current_order = $orderData;
            $session->save();

            return $this->buildResponse($reply, 'order_status', $session, false, null, $orderData);
        }

        // No order found - ask for help
        $categories = $this->agent->getCategories();
        $categoryNames = array_column($categories, 'name');
        $delivery = $this->agent->getDeliveryInfo();

        $prompt = "انت مساعد مبيعات عراقي ودود.
الزبون يسأل عن طلبه لكن ما لكيت طلب مسجل باسمه.

اخبره بلطف ما لكيت طلب.
اسأله يعطيك رقم الطلب او رقم التلفون المستخدم بالطلب.
او اذا يريد يطلب شي جديد، اعرض عليه الأقسام: " . implode('، ', $categoryNames) . "

التوصيل: {$delivery['time']} بسعر {$delivery['cost']} دينار
لا تستخدم ايموجي.";

        $reply = $this->callAIForResponse($session, $message, $prompt);
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'order_status_missing', $session, false);
    }

    /**
     * Handle order reference ("طلبت سابقاً") - Use AI
     */
    protected function handleOrderReference(AiChatSession $session, Lead $lead, string $message): array
    {
        $session->addMessage('user', $message);

        // Check if they have a pending cart
        if (!empty($session->cart)) {
            return $this->buildCartAndAskInfo($session, $lead);
        }

        [$orderData, $statusLabel, $orderNumber] = $this->resolveLatestOrderData($session, $lead);
        if (!empty($orderData)) {
            $items = $orderData['items'] ?? [];
            $itemsList = [];
            foreach ($items as $item) {
                $itemsList[] = "{$item['name']} x {$item['quantity']}";
            }

            $prompt = "الزبون يسأل عن طلبه السابق.
رقم الطلب: {$orderNumber}
المنتجات: " . implode('، ', $itemsList) . "
المجموع: {$orderData['total_price']} دينار
الحالة: {$statusLabel}

اخبره بتفاصيل طلبه.
ملاحظة: لا تستخدم ايموجي.";

            $reply = $this->callAIForResponse($session, $message, $prompt);
            $session->addMessage('assistant', $reply);

            if (empty($session->current_order)) {
                $session->current_order = $orderData;
                $session->save();
            }

            return $this->buildResponse($reply, 'order_status', $session, false, null, $orderData);
        }

        // No previous order - ask what they want
        $categories = $this->getStoreCategories();
        $categoryNames = array_column($categories, 'name');

        $prompt = "الزبون يذكر طلب سابق لكن ما عنده طلبات مسجلة.
اسأله شنو يريد يطلب هالمرة.
الأقسام المتوفرة: " . implode('، ', $categoryNames) . "
ملاحظة: لا تستخدم ايموجي.";

        $reply = $this->callAIForResponse($session, $message, $prompt);
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'order_reference', $session, false);
    }

    /**
     * Handle delivery info questions - FULL INFO from Agent
     */
    protected function handleStoreInfoDelivery(AiChatSession $session, string $message): array
    {
        $session->addMessage('user', $message);

        $delivery = $this->agent->getDeliveryInfo();
        $categories = $this->agent->getCategories();
        $categoryNames = array_column($categories, 'name');

        $prompt = "انت مساعد مبيعات عراقي ودود.
الزبون يسأل عن التوصيل.

معلومات التوصيل الكاملة:
- السعر: {$delivery['cost']} دينار
- الوقت: {$delivery['time']}
- المناطق: {$delivery['areas']}
- التفاصيل: {$delivery['description']}" .
($delivery['free_above'] ? "\n- توصيل مجاني للطلبات فوق {$delivery['free_above']} دينار" : "") . "

اجب على سؤاله بشكل واضح ومفصل.
بعدها اسأله شنو يحتاج من المنتجات.
لا تستخدم ايموجي.";

        $reply = $this->callAIForResponse($session, $message, $prompt);
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'store_info_delivery', $session, false);
    }

    /**
     * Handle return policy questions - FULL INFO from Agent
     */
    protected function handleStoreInfoReturn(AiChatSession $session, string $message): array
    {
        $session->addMessage('user', $message);

        $returnPolicy = $this->agent->getReturnPolicy();

        $prompt = "انت مساعد مبيعات عراقي ودود.
الزبون يسأل عن سياسة الاسترجاع والاستبدال.

سياسة الاسترجاع الكاملة:
- مسموح: " . ($returnPolicy['allowed'] ? 'نعم' : 'لا') . "
- المدة: {$returnPolicy['days']} يوم
- الشروط: {$returnPolicy['conditions']}
- التفاصيل: {$returnPolicy['description']}

اجب على سؤاله بشكل واضح وطمنه.
لا تستخدم ايموجي.";

        $reply = $this->callAIForResponse($session, $message, $prompt);
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'store_info_return', $session, false);
    }

    /**
     * Handle working hours questions - FULL INFO from Agent
     */
    protected function handleStoreInfoHours(AiChatSession $session, string $message): array
    {
        $session->addMessage('user', $message);

        $hours = $this->agent->getWorkingHours();

        $prompt = "انت مساعد مبيعات عراقي ودود.
الزبون يسأل عن ساعات العمل.

ساعات العمل:
- الدوام: {$hours['hours']}
- الأيام: {$hours['days']}
- العطل: {$hours['holidays']}

اجب على سؤاله واسأله اذا يحتاج شي.
لا تستخدم ايموجي.";

        $reply = $this->callAIForResponse($session, $message, $prompt);
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'store_info_hours', $session, false);
    }

    /**
     * Handle payment info questions - FULL INFO from Agent
     */
    protected function handleStoreInfoPayment(AiChatSession $session, string $message): array
    {
        $session->addMessage('user', $message);

        $paymentMethods = $this->agent->getPaymentMethods();

        $prompt = "انت مساعد مبيعات عراقي ودود.
الزبون يسأل عن طرق الدفع.

طرق الدفع المتوفرة:
" . implode("\n- ", $paymentMethods) . "

اجب على سؤاله بشكل واضح.
اذا سأل عن طريقة غير متوفرة، اعتذر واخبره بالطرق المتوفرة.
لا تستخدم ايموجي.";

        $reply = $this->callAIForResponse($session, $message, $prompt);
        $session->addMessage('assistant', $reply);
        return $this->buildResponse($reply, 'store_info_payment', $session, false);
    }

    /**
     * Handle with AI - for complex or unknown queries
     * SMART AGENT: Uses multiple data sources to answer
     */
    protected function handleWithAI(AiChatSession $session, Lead $lead, string $message): array
    {
        $normalized = $this->normalize($message);
        $storeContext = $session->store_context ?? [];
        $lastShownProducts = $storeContext['last_shown_products'] ?? [];

        // CRITICAL FIX: Check if this is a SHORT follow-up question about last product
        // Examples: "كم سعره", "بكم", "شكد", "سعره", "كم"
        $priceFollowUpPatterns = ['كم سعره', 'سعره', 'بكم', 'شكد', 'كم', 'شكد سعره', 'كم السعر', 'السعر'];
        $isShortPriceQuestion = mb_strlen($normalized) < 20 && $this->containsKeyword($normalized, $priceFollowUpPatterns);

        if ($isShortPriceQuestion && !empty($lastShownProducts)) {
            // User asking about price of last discussed product
            $lastProduct = $lastShownProducts[0];

            Log::info('HandleWithAI: Follow-up price question about last product', [
                'message' => $message,
                'last_product' => $lastProduct['name'] ?? 'unknown',
            ]);

            $prompt = "{$lastProduct['name']}: {$lastProduct['price']} دينار.
اخبره بالسعر واسأل اذا يريد يطلبه. جملتين فقط. لا ايموجي.";

            $reply = $this->callAIForResponse($session, $message, $prompt);
            $session->addMessage('user', $message);
            $session->addMessage('assistant', $reply);

            $productId = $lastProduct['id'] ?? null;
            return $this->buildResponse($reply, 'product_price', $session, false, null, null, $productId ? [$productId] : null);
        }

        // Check if user is asking to see products in current category
        $wantsProducts = preg_match('/(?:اعرض|عرض|شنو|المنتجات|منتجات|٤|4|اربع|أربع)/u', $normalized);
        $currentCategory = $storeContext['current_category'] ?? null;

        if ($wantsProducts && $currentCategory) {
            // User wants to see products in the category they were browsing
            return $this->handleCategorySelection($session, $currentCategory['name']);
        }

        // Check if user wants to search for something specific
        $searchKeywords = ['ابحث', 'دور', 'لكي', 'اريد', 'عندكم', 'نظار', 'glasses'];
        if ($this->containsKeyword($normalized, $searchKeywords)) {
            // Try to extract what they want to search for
            $searchQuery = $message;

            // Search in products
            $searchResults = $this->searchProducts($searchQuery, null, 10);

            // Also search in current category if we have one
            if ($currentCategory && empty($searchResults)) {
                $searchResults = $this->searchProducts($searchQuery, $currentCategory['id'], 10);
            }

            if (!empty($searchResults)) {
                $this->storeShownProducts($session, $searchResults);
                $this->storeSearchQuery($session, $searchQuery);

                $productList = array_map(fn($p) => "- {$p['name']}: {$p['price']} دينار", $searchResults);

                $prompt = "انت مساعد مبيعات عراقي ودود ومفيد.
الزبون يدور على: {$message}

لكيت هاي المنتجات:
" . implode("\n", $productList) . "

اعرض له النتائج بشكل واضح.
اذا ما لكيت شي مطابق، اقترح عليه بدائل او اسأله يوضح اكثر.
كن طبيعي ومباشر. لا تستخدم ايموجي.";

                $reply = $this->callAIForResponse($session, $message, $prompt);
                $session->addMessage('user', $message);
                $session->addMessage('assistant', $reply);
                return $this->buildResponse($reply, 'search_results', $session, false);
            }
        }

        // CRITICAL FIX: Always try database search for product questions
        // This ensures we find products even if session doesn't have them
        $dbSearchResults = $this->searchProducts($message, null, 5);

        if (!empty($dbSearchResults)) {
            $bestMatch = $this->findBestMatchFromResults($normalized, $dbSearchResults);

            if ($bestMatch) {
                // Found a specific product in database
                $this->storeShownProducts($session, [$bestMatch]);

                $prompt = "المنتج موجود: {$bestMatch['name']}\nالسعر: {$bestMatch['price']} دينار\nالكمية: {$bestMatch['stock']} قطعة.
اخبره بالتفاصيل واسأل اذا يريد يطلبه. لا ايموجي. جملتين.";

                $reply = $this->callAIForResponse($session, $message, $prompt);
                $session->addMessage('user', $message);
                $session->addMessage('assistant', $reply);

                $productId = $bestMatch['id'] ?? null;
                return $this->buildResponse($reply, 'product_found', $session, false, null, null, $productId ? [$productId] : null);
            }

            // Multiple results - show them
            $this->storeShownProducts($session, $dbSearchResults);
            $productNames = array_map(fn($p) => $p['name'], array_slice($dbSearchResults, 0, 4));

            $prompt = "لكيت منتجات مشابهة: " . implode('، ', $productNames) . ".
اعرضها للزبون واسأل شنو يفضل. لا ايموجي. جملتين.";

            $reply = $this->callAIForResponse($session, $message, $prompt);
            $session->addMessage('user', $message);
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'search_results', $session, false);
        }

        // Fallback: Check if message contains product names in session (might be order)
        $products = $storeContext['products'] ?? [];
        $matchedProduct = $this->findProductInMessage($normalized, $products);

        if ($matchedProduct) {
            // CRITICAL: Check if it's an inquiry (asking about stock/price/attributes) vs order
            $isInquiry = $this->containsKeyword($normalized, [
                'كم', 'شكد', 'كميه', 'كمية', 'عدد', 'متوفر', 'موجود', 'سعر',
                'مقاس', 'مقاسات', 'قياس', 'قياسات', 'لون', 'الوان', 'ألوان',
                'خامة', 'تفاصيل', 'مواصفات', 'وصف', 'شنو', 'ايش', 'وش',
            ]);

            if ($isInquiry) {
                // User is asking about a product, not ordering
                $productInfo = $this->validateProductInDatabase($matchedProduct['name']);
                if ($productInfo) {
                    // CRITICAL: Store the product being discussed for image requests
                    $this->storeShownProducts($session, [$matchedProduct]);;

                    // Build detailed product info including attributes
                    $details = "الزبون يسأل عن: {$matchedProduct['name']}\n";
                    $details .= "السعر: " . number_format($productInfo['price']) . " د.ع\n";
                    $details .= "الكمية المتوفرة: {$productInfo['stock']} قطعة\n";

                    if (!empty($productInfo['attributes'])) {
                        $details .= "التفاصيل:\n";
                        foreach ($productInfo['attributes'] as $attr => $value) {
                            $details .= "- {$attr}: {$value}\n";
                        }
                    }

                    $prompt = $details . "\nاخبره بالتفاصيل واسأله اذا يريد يطلب.\nملاحظة: لا تستخدم ايموجي. لا تضيف شي للسلة.";

                    $reply = $this->callAIForResponse($session, $message, $prompt);
                    $session->addMessage('user', $message);
                    $session->addMessage('assistant', $reply);
                    return $this->buildResponse($reply, 'product_inquiry', $session, false);
                }
            }

            // It's an order - extract line items
            $lineItems = $this->extractLineItems($message, $products);
            if (!empty($lineItems)) {
                foreach ($lineItems as $item) {
                    $session->addToCart($item['name'], $item['price'], $item['quantity']);
                }

                $session->addMessage('user', $message);
                return $this->buildCartAndAskInfo($session, $lead);
            }
        }

        // Hard guardrail: do not answer outside store scope
        if (!$this->isStoreScope($session, $normalized)) {
            return $this->handleOutOfScope($session, $message);
        }

        // Build context for AI
        $categories = $this->getStoreCategories();
        $categoryNames = array_column($categories, 'name');
        $contextInfo = $this->buildStoreContextForAI($session);

        // Get last shown products for context
        $lastProducts = $storeContext['last_shown_products'] ?? [];
        $productContext = '';
        if (!empty($lastProducts)) {
            $productNames = array_column(array_slice($lastProducts, 0, 5), 'name');
            $productContext = "\nالمنتجات المعروضة سابقا: " . implode('، ', $productNames);
        }

        $prompt = "انت مساعد مبيعات عراقي ودود ومفيد جدا.
رسالة الزبون: {$message}

معلومات المتجر: {$contextInfo}
الأقسام: " . implode('، ', $categoryNames) . $productContext . "

ساعد الزبون بأفضل طريقة ممكنة.
اذا يسأل عن منتج، ابحث له وساعده يلكيه.
اذا يريد معلومات، اعطيه اياها بوضوح.
كن ودود وطبيعي مثل صديق. لا تستخدم ايموجي.";

        $reply = $this->callAIForResponse($session, $message, $prompt);

        $session->addMessage('user', $message);
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'ai_response', $session, false);
    }

    /**
     * True if message is within store scope (products/orders/delivery/return/hours).
     */
    protected function isStoreScope(AiChatSession $session, string $normalizedMessage): bool
    {
        // If merchant disabled strict scope, always treat as in-scope.
        if (($this->settings->strict_store_scope ?? true) === false) {
            return true;
        }

        // Explicit store/order keywords
        $scopeKeywords = array_merge(
            self::GREETING_KEYWORDS,
            self::THANKS_KEYWORDS,
            self::ORDER_KEYWORDS,
            self::INQUIRY_KEYWORDS,
            self::PRODUCT_LIST_KEYWORDS,
            self::REMOVE_KEYWORDS,
            self::CONFIRM_KEYWORDS,
            self::CANCEL_KEYWORDS,
            self::ORDER_STATUS_KEYWORDS,
            self::ORDER_REFERENCE_KEYWORDS,
            self::DELIVERY_KEYWORDS,
            self::RETURN_KEYWORDS,
            self::HOURS_KEYWORDS,
            self::PAYMENT_KEYWORDS,
            self::PRODUCT_ATTR_KEYWORDS
        );

        if ($this->containsKeyword($normalizedMessage, $scopeKeywords)) {
            return true;
        }

        // Digits often indicate quantity/price in orders
        if (preg_match('/\b\d{1,3}\b/u', $normalizedMessage)) {
            return true;
        }

        // Mentioning a known product keeps it in scope
        $products = $session->store_context['products'] ?? [];
        if ($this->findProductInMessage($normalizedMessage, $products)) {
            return true;
        }

        // Check if mentions a category
        if ($this->detectCategoryFromMessage($normalizedMessage) !== null) {
            return true;
        }

        return false;
    }

    /**
     * Reply for out-of-scope questions - Use AI to redirect
     */
    protected function handleOutOfScope(AiChatSession $session, string $message): array
    {
        $session->addMessage('user', $message);

        $categories = $this->getStoreCategories();
        $categoryNames = array_column($categories, 'name');

        $prompt = "الزبون سأل سؤال خارج نطاق المتجر.
سؤاله: {$message}

اعتذر بلطف واخبره انك تقدر تساعده بخصوص المتجر فقط.
اسأله شنو يحتاج من المنتجات او الخدمات.
الأقسام المتوفرة: " . implode('، ', $categoryNames) . "
ملاحظة: لا تستخدم ايموجي.";

        $reply = $this->callAIForResponse($session, $message, $prompt);
        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, 'out_of_scope', $session, false);
    }

    /**
     * Safety net: prevent product-list style dumps from model and remove emojis.
     */
    protected function sanitizeAiReply(string $reply): string
    {
        $reply = trim($reply);

        // Remove emojis
        $reply = $this->removeEmojis($reply);

        if ($reply === '') {
            return 'شنو تفضل؟';
        }

        // If it looks like a long list of priced items, replace with preference question.
        $lines = preg_split('/\R/u', $reply) ?: [];
        $pricedLines = 0;
        $bulletLines = 0;
        foreach ($lines as $line) {
            if (mb_stripos($line, 'دينار') !== false) {
                $pricedLines++;
            }
            if (preg_match('/^\s*[•\-]/u', $line)) {
                $bulletLines++;
            }
        }

        $categories = $this->getStoreCategories();
        $categoryNames = array_column($categories, 'name');
        $categoryStr = implode('، ', array_slice($categoryNames, 0, 3));

        if ($pricedLines >= 4 || $bulletLines >= 5 || mb_stripos($reply, 'المنتجات المتوفرة') !== false) {
            return "عدنا {$categoryStr}.\nشنو تفضل حتى أعرضلك المناسب؟";
        }

        return $reply;
    }

    /**
     * Remove emojis from text
     */
    protected function removeEmojis(string $text): string
    {
        // Remove all emoji characters
        $text = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $text); // Emoticons
        $text = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', '', $text); // Misc Symbols and Pictographs
        $text = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $text); // Transport and Map
        $text = preg_replace('/[\x{1F1E0}-\x{1F1FF}]/u', '', $text); // Flags
        $text = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $text);   // Misc symbols
        $text = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $text);   // Dingbats
        $text = preg_replace('/[\x{FE00}-\x{FE0F}]/u', '', $text);   // Variation Selectors
        $text = preg_replace('/[\x{1F900}-\x{1F9FF}]/u', '', $text); // Supplemental Symbols
        $text = preg_replace('/[\x{1FA00}-\x{1FA6F}]/u', '', $text); // Chess Symbols
        $text = preg_replace('/[\x{1FA70}-\x{1FAFF}]/u', '', $text); // Symbols Extended-A
        $text = preg_replace('/[\x{231A}\x{231B}\x{23E9}-\x{23F3}\x{23F8}-\x{23FA}]/u', '', $text);

        // Clean up extra spaces
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Call AI for response with custom prompt - INCLUDES CONVERSATION HISTORY
     * CRITICAL: AI must ONLY mention products that exist in database
     *
     * Now supports both OpenAI (ChatGPT) and Groq providers with usage tracking
     */
    protected function callAIForResponse(AiChatSession $session, string $message, string $customPrompt, string $requestType = 'chat'): string
    {
        try {
            // Build conversation history for context
            $conversationHistory = $this->buildConversationHistory($session);

            // Get current context (what user was looking at)
            $currentContext = $this->getCurrentContextSummary($session);

            // CRITICAL: Build list of REAL products from database for AI context
            $realProductsContext = $this->buildRealProductsContext($session);

            // Get current cart state
            $cartContext = $this->buildCartContext($session);

            // Check if this is a greeting/casual conversation
            $isGreetingOrCasual = str_contains($customPrompt, 'رحب') ||
                                  str_contains($customPrompt, 'حياك') ||
                                  str_contains($customPrompt, 'شكرك') ||
                                  str_contains($customPrompt, 'ودود');

            // STRICT RULES - but softer for greetings
            if ($isGreetingOrCasual) {
                $strictRules = "
قواعد مهمة:
1. كن ودود وطبيعي بالرد - لا تكن رسمي زيادة
2. رد على التحية بتحية مناسبة قبل اي شي
3. ممنوع استخدام ايموجي نهائيا
4. اجوبتك تكون مختصرة وطبيعية
5. لا تقل 'أنا هنا لمساعدتك بخصوص المتجر فقط' - كن ودود";
            } else {
                $strictRules = "
قواعد صارمة جدا - يجب اتباعها بدون استثناء:
1. ممنوع تماما ذكر اي منتج غير موجود في القائمة اعلاه
2. ممنوع تماما اختراع اسعار - استخدم الاسعار الحقيقية فقط
3. ممنوع ذكر كميات اكبر من 100 قطعة - الحد الاقصى للطلب 100 قطعة
4. اذا سأل الزبون عن منتج غير موجود، اعرض له الأقسام المتوفرة من القائمة أعلاه
5. لا تقل ابدا 'تم تأكيد الطلب' او 'تم تسجيل الطلب' - فقط النظام يؤكد الطلبات
6. لا تقل ابدا 'تمت إضافة للسلة' الا اذا كانت السلة فعلا تحتوي على المنتج
7. طريقة الدفع الوحيدة هي الدفع نقدا عند الاستلام - لا توجد طريقة دفع اخرى
8. ممنوع استخدام ايموجي نهائيا
9. قبل تأكيد اي طلب، يجب جمع: الاسم، رقم الهاتف، والعنوان الكامل
10. اذا السلة فارغة، لا تتحدث عن طلب او تأكيد
11. ممنوع تماما اقتراح أو ذكر أقسام غير موجودة في قائمة الأقسام أعلاه
12. اجوبتك تكون مختصرة - جملتين او ثلاث كحد اقصى
13. اذا سأل الزبون عن قسم غير موجود، قل له الأقسام المتوفرة فعلا من القائمة
14. اذا سأل الزبون عن المنتجات المتوفرة او شنو عدكم، اعرض له المنتجات فورا بدون اي تحفظ - حتى لو اشترى سابقا
15. لا تقل ابدا 'لا يمكنني عرض المنتجات' او اي عبارة تمنع الزبون من تصفح المنتجات
16. الطلبات السابقة لا تمنع الزبون من تصفح منتجات جديدة - دائما ساعده في التسوق
17. ممنوع تماما اختراع منتجات او باقات او اسعار - استخدم فقط المنتجات الموجودة في القائمة اعلاه
18. اذا طلب الزبون منتجا غير موجود في القائمة اعلاه، قل له بصدق انه غير متوفر واقترح له بديلا من القائمة
19. اذا كان لديك معلومات عن اهتمامات الزبون السابقة، استخدمها لتقديم اقتراحات مناسبة
20. اسأل الزبون عن تفضيلاته (لون، مقاس، نوع) بشكل طبيعي لتساعده يلكي المنتج المناسب";
            }

            $fullPrompt = $customPrompt . "\n\n" . $realProductsContext . "\n" . $cartContext . "\n" . $strictRules;

            if (!empty($currentContext)) {
                $fullPrompt .= "\n\nالسياق الحالي للمحادثة:\n{$currentContext}";
            }

            // Build messages array with history
            $messages = [
                [
                    'role' => 'system',
                    'content' => $fullPrompt,
                ],
            ];

            // Add conversation history
            foreach ($conversationHistory as $msg) {
                $messages[] = $msg;
            }

            // Add current message
            if (!empty($message)) {
                $messages[] = [
                    'role' => 'user',
                    'content' => $message,
                ];
            }

            // Use AiProviderService for multi-provider support with usage tracking
            $result = $this->aiProvider->chat(
                $messages,
                0.3, // Low temperature to reduce hallucination
                500,
                $requestType,
                $this->currentConversationId,
                $this->currentLeadId
            );

            if ($result['success'] && !empty($result['content'])) {
                $reply = $result['content'];

                Log::info('AI response received', [
                    'provider' => $result['provider'] ?? $this->provider,
                    'model' => $result['model'] ?? $this->model,
                    'tokens' => $result['usage']['total_tokens'] ?? 0,
                    'cost' => $result['usage']['estimated_cost'] ?? 0,
                    'response_time_ms' => $result['response_time_ms'] ?? 0,
                ]);

                return $this->removeEmojis(trim($reply));
            }

            Log::error('AI API error', [
                'provider' => $this->provider,
                'error' => $result['error'] ?? 'Unknown error',
            ]);
            return 'شلون اكدر اساعدك؟';
        } catch (\Exception $e) {
            Log::error('AI API exception', ['error' => $e->getMessage()]);
            return 'شلون اكدر اساعدك؟';
        }
    }

    /**
     * Build cart context for AI
     */
    protected function buildCartContext(AiChatSession $session): string
    {
        $cart = $session->cart ?? [];

        if (empty($cart)) {
            return "حالة السلة: السلة فارغة - لا يوجد اي منتج في السلة حاليا.";
        }

        $lines = ["حالة السلة الحالية:"];
        $total = 0;
        foreach ($cart as $item) {
            $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
            $total += $itemTotal;
            $lines[] = "- {$item['name']}: {$item['quantity']} قطعة × {$item['price']} = {$itemTotal} دينار";
        }
        $lines[] = "المجموع: {$total} دينار";

        // Customer data status
        $customerData = $session->customer_data ?? [];
        $missing = [];
        if (empty($customerData['name'])) $missing[] = 'الاسم';
        if (empty($customerData['phone'])) $missing[] = 'رقم الهاتف';
        if (empty($customerData['address'])) $missing[] = 'العنوان';

        if (!empty($missing)) {
            $lines[] = "المعلومات الناقصة لتأكيد الطلب: " . implode('، ', $missing);
        } else {
            $lines[] = "معلومات الزبون مكتملة - جاهز للتأكيد";
        }

        return implode("\n", $lines);
    }

    /**
     * Build REAL products context from database
     * This ensures AI ONLY mentions products that actually exist
     * IMPROVED: Now includes REAL categories to prevent AI from making up fake ones
     */
    protected function buildRealProductsContext(AiChatSession $session): string
    {
        $storeContext = $session->store_context ?? [];
        $lines = [];

        // CRITICAL: Always include REAL categories first
        // This prevents AI from suggesting categories that don't exist
        $categories = $this->getStoreCategories();
        if (!empty($categories)) {
            $catNames = array_filter(array_column($categories, 'name'), fn($n) => !empty($n) && $n !== 'first');
            if (!empty($catNames)) {
                $lines[] = "الأقسام المتوفرة في المتجر (هذه فقط - لا يوجد غيرها):";
                foreach ($catNames as $name) {
                    $lines[] = "- {$name}";
                }
                $lines[] = "";
            }
        }

        // Get relevant products from session context
        $lastShown = $storeContext['last_shown_products'] ?? [];
        $categoryProducts = $storeContext['category_products'] ?? [];
        $relevantProducts = array_merge($lastShown, $categoryProducts);

        // If no context products, get ALL products grouped by category
        if (empty($relevantProducts)) {
            // Get products by category for better context
            $allProductsByCategory = [];
            foreach ($categories as $cat) {
                if ($cat['name'] === 'first' || empty($cat['products_count'])) continue;
                $prods = $this->getProductsInCategory($cat['id']);
                if (!empty($prods)) {
                    $allProductsByCategory[$cat['name']] = array_slice($prods, 0, 3);
                }
            }

            if (!empty($allProductsByCategory)) {
                $lines[] = "المنتجات المتوفرة حسب القسم:";
                foreach ($allProductsByCategory as $catName => $products) {
                    $lines[] = "{$catName}:";
                    foreach ($products as $p) {
                        $lines[] = "  - {$p['name']}: {$p['price']} دينار";
                    }
                }
                return implode("\n", $lines);
            }
        }

        // Unique by name, limit to 15 products max
        $uniqueProducts = [];
        foreach ($relevantProducts as $p) {
            $name = $p['name'] ?? '';
            if (!isset($uniqueProducts[$name]) && count($uniqueProducts) < 15) {
                $uniqueProducts[$name] = $p;
            }
        }

        if (empty($uniqueProducts)) {
            $lines[] = "تحذير: لا توجد منتجات محملة في السياق. استخدم الأقسام أعلاه فقط.";
            return implode("\n", $lines);
        }

        $lines[] = "المنتجات ذات الصلة:";
        foreach (array_values($uniqueProducts) as $product) {
            $name = $product['name'] ?? '';
            $price = $product['price'] ?? 0;
            $lines[] = "- {$name}: {$price} دينار";
        }

        return implode("\n", $lines);
    }

    /**
     * Build conversation history for AI context
     * IMPROVED: Increased from 6 to 12 messages for better context retention
     */
    protected function buildConversationHistory(AiChatSession $session): array
    {
        $messages = $session->messages ?? [];
        $history = [];

        // Get last 12 messages (6 turns) - doubled for better context
        $recentMessages = array_slice($messages, -12);

        foreach ($recentMessages as $msg) {
            $history[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        return $history;
    }

    /**
     * Get current context summary (what user was doing)
     */
    protected function getCurrentContextSummary(AiChatSession $session): string
    {
        $context = [];
        $storeContext = $session->store_context ?? [];

        // Current category being browsed
        if (!empty($storeContext['current_category'])) {
            $cat = $storeContext['current_category'];
            $context[] = "الزبون يتصفح قسم: {$cat['name']}";
        }

        // Products shown to user
        if (!empty($storeContext['last_shown_products'])) {
            $products = $storeContext['last_shown_products'];
            $names = array_column($products, 'name');
            $context[] = "المنتجات المعروضة: " . implode('، ', array_slice($names, 0, 5));
        }

        // User's search query
        if (!empty($storeContext['last_search_query'])) {
            $context[] = "الزبون كان يدور على: {$storeContext['last_search_query']}";
        }

        // Cart items
        $cart = $session->cart ?? [];
        if (!empty($cart)) {
            $cartItems = array_map(fn($item) => "{$item['name']} x{$item['quantity']}", $cart);
            $context[] = "السلة: " . implode('، ', $cartItems);
        }

        // Customer interests from CRM (what they've inquired about before)
        if (!empty($session->lead_id)) {
            try {
                $lead = Lead::find($session->lead_id);
                if ($lead && !empty($lead->interests)) {
                    $recentInterests = collect($lead->interests)
                        ->sortByDesc('date')
                        ->take(5)
                        ->pluck('product_name')
                        ->unique()
                        ->values()
                        ->toArray();
                    if (!empty($recentInterests)) {
                        $context[] = "اهتمامات الزبون السابقة: " . implode('، ', $recentInterests);
                    }
                }
            } catch (\Exception $e) {
                // silently skip
            }
        }

        return implode("\n", $context);
    }

    /**
     * CRITICAL: Validate product exists in database and return REAL data
     * This prevents AI from making up fake products/prices
     */
    protected function validateProductInDatabase(string $productName): ?array
    {
        $normalized = $this->normalize($productName);

        // Try exact match first
        $product = Product::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->where(function($q) use ($productName, $normalized) {
                $q->where('name', $productName)
                  ->orWhere('name', $normalized)
                  ->orWhere('name', 'LIKE', "%{$productName}%")
                  ->orWhere('name', 'LIKE', "%{$normalized}%");
            })
            ->first();

        if (!$product) {
            Log::warning('Product validation failed - not in database', [
                'requested_name' => $productName,
                'user_id' => $this->user->id,
            ]);
            return null;
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => (int) $product->price,
            'stock' => (int) $product->quantity,
            'category_id' => $product->category_id,
        ];
    }

    /**
     * Smart product search - search by name, description, category
     */
    protected function searchProducts(string $query, ?int $categoryId = null, int $limit = 10): array
    {
        $normalized = $this->normalize($query);

        // Remove common inquiry/question words that aren't product names
        $ignoreWords = ['غير', 'متوفر', 'عندكم', 'عدكم', 'موجود', 'هل', 'في', 'فيه', 'كم', 'سعر', 'شكد', 'شنو', 'اريد', 'ابي', 'ممكن', 'تكدر', 'تقدر', 'بكم'];

        // Split query into words for better matching
        $words = preg_split('/\s+/u', $normalized);
        $words = array_filter($words, function($w) use ($ignoreWords) {
            return mb_strlen($w) > 1 && !in_array($w, $ignoreWords);
        });

        // If after filtering we have no words, use original words
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

        // Search in name and description - match ANY word from query
        $queryBuilder->where(function($q) use ($normalized, $query, $words) {
            // Full query match
            $q->where('name', 'LIKE', "%{$query}%")
              ->orWhere('name', 'LIKE', "%{$normalized}%")
              ->orWhere('description', 'LIKE', "%{$query}%")
              ->orWhere('description', 'LIKE', "%{$normalized}%");

            // Individual word matching - find products that contain ANY of the query words
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
                'description' => $p->description,
                'category_id' => $p->category_id,
            ])
            ->toArray();
    }

    /**
     * Get top/popular products
     * IMPROVED: Adds randomization to provide variety, excludes recently shown products
     */
    protected function getTopProducts(int $limit = 10, ?int $categoryId = null, ?AiChatSession $session = null): array
    {
        $query = Product::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // Get list of recently shown product IDs to exclude (for variety)
        $excludeIds = [];
        if ($session) {
            $storeContext = $session->store_context ?? [];
            $suggestedProducts = $storeContext['suggested_products'] ?? [];
            $excludeIds = array_column($suggestedProducts, 'id');

            // Only exclude if we have enough products remaining
            $totalCount = (clone $query)->count();
            if ($totalCount <= $limit + count($excludeIds)) {
                $excludeIds = []; // Don't exclude if it would leave too few products
            }
        }

        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        // IMPROVED: Use inRandomOrder() for variety instead of always showing same products
        // Still prioritize products with stock, but add randomness
        return $query
            ->orderByRaw('CASE WHEN quantity > 10 THEN 1 WHEN quantity > 5 THEN 2 ELSE 3 END')
            ->inRandomOrder()
            ->limit($limit)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (int) $p->price,
                'stock' => (int) $p->quantity,
                'description' => $p->description,
                'category_id' => $p->category_id,
            ])
            ->toArray();
    }

    /**
     * Track products that have been suggested to user (for variety)
     */
    protected function trackSuggestedProducts(AiChatSession $session, array $products): void
    {
        $storeContext = $session->store_context ?? [];
        $suggested = $storeContext['suggested_products'] ?? [];

        foreach ($products as $product) {
            if (!empty($product['id'])) {
                // Add to suggested list (max 20 products)
                $suggested[] = ['id' => $product['id'], 'timestamp' => time()];
            }
        }

        // Keep only last 20 suggested products, and remove old ones (older than 1 hour)
        $oneHourAgo = time() - 3600;
        $suggested = array_filter($suggested, fn($s) => ($s['timestamp'] ?? 0) > $oneHourAgo);
        $suggested = array_slice($suggested, -20);

        $storeContext['suggested_products'] = $suggested;
        $session->store_context = $storeContext;
        $session->save();
    }

    /**
     * Save customer product interests to Lead model for CRM tracking
     */
    protected function saveLeadInterests(AiChatSession $session, array $products): void
    {
        if (empty($products) || empty($session->lead_id)) return;

        try {
            $lead = Lead::find($session->lead_id);
            if (!$lead) return;

            foreach ($products as $product) {
                if (!empty($product['name'])) {
                    // Avoid saving duplicate interests for the same product in same session
                    $existing = collect($lead->interests ?? [])
                        ->where('product_name', $product['name'])
                        ->where('date', '>=', now()->subHours(2)->toDateTimeString())
                        ->first();
                    if (!$existing) {
                        $lead->addInterest($product['name'], $product['id'] ?? null);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('GroqChat: Failed to save lead interests', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Store products shown to user for context
     * IMPROVED: Maintains products_history instead of overwriting, tracks current_product_id
     */
    protected function storeShownProducts(AiChatSession $session, array $products, bool $setAsCurrent = true): void
    {
        $storeContext = $session->store_context ?? [];

        // Always update last_shown_products
        $storeContext['last_shown_products'] = $products;

        // If single product and setAsCurrent, track it as the currently discussed product
        if ($setAsCurrent && count($products) === 1 && !empty($products[0]['id'])) {
            $storeContext['current_product_id'] = $products[0]['id'];
            $storeContext['current_product'] = $products[0];
            // Save this specific interest to lead CRM
            $this->saveLeadInterests($session, $products);
        }

        // Maintain products_history (max 10 unique products)
        $history = $storeContext['products_history'] ?? [];
        foreach ($products as $product) {
            if (!empty($product['id'])) {
                // Remove if already in history to avoid duplicates
                $history = array_filter($history, fn($p) => ($p['id'] ?? null) !== $product['id']);
                // Add to beginning of history
                array_unshift($history, $product);
            }
        }
        // Keep only last 10 products in history
        $storeContext['products_history'] = array_slice($history, 0, 10);

        $session->store_context = $storeContext;
        $session->save();
    }

    /**
     * Set the currently discussed product explicitly
     */
    protected function setCurrentProduct(AiChatSession $session, array $product): void
    {
        if (empty($product['id'])) {
            return;
        }

        $storeContext = $session->store_context ?? [];
        $storeContext['current_product_id'] = $product['id'];
        $storeContext['current_product'] = $product;
        $session->store_context = $storeContext;
        $session->save();

        Log::info('GroqChat: Set current product', [
            'product_id' => $product['id'],
            'product_name' => $product['name'] ?? 'unknown',
        ]);
    }

    /**
     * Get the currently discussed product (with real-time stock check)
     */
    protected function getCurrentProduct(AiChatSession $session): ?array
    {
        $storeContext = $session->store_context ?? [];
        $currentProductId = $storeContext['current_product_id'] ?? null;

        if (!$currentProductId) {
            return null;
        }

        // CRITICAL: Verify product still exists and is in stock (real-time check)
        $product = Product::where('id', $currentProductId)
            ->where('user_id', $this->user->id)
            ->where('is_active', true)
            ->first();

        if (!$product) {
            // Product no longer available, clear from context
            unset($storeContext['current_product_id']);
            unset($storeContext['current_product']);
            $session->store_context = $storeContext;
            $session->save();
            return null;
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => (int) $product->price,
            'stock' => (int) $product->quantity,
            'category_id' => $product->category_id,
        ];
    }

    /**
     * Store user's search query for context
     */
    protected function storeSearchQuery(AiChatSession $session, string $query): void
    {
        $storeContext = $session->store_context ?? [];
        $storeContext['last_search_query'] = $query;
        $session->store_context = $storeContext;
        $session->save();
    }

    /**
     * Build store context for AI prompts
     */
    protected function buildStoreContextForAI(AiChatSession $session): string
    {
        $storeContext = $session->store_context ?? [];
        $storeName = $storeContext['store_name'] ?? $this->user->name ?? 'المتجر';

        // Get categories and counts
        $categories = $this->getStoreCategories();
        $totalProducts = $this->getTotalProductCount();

        // Build delivery info
        $deliveryInfo = '';
        if (!empty($this->settings->delivery_info)) {
            $deliveryInfo = "التوصيل: {$this->settings->delivery_info}";
        }

        // Build return policy
        $returnPolicy = '';
        if (!empty($this->settings->return_policy)) {
            $returnPolicy = "الاستبدال: {$this->settings->return_policy}";
        }

        // Build working hours
        $workingHours = '';
        if (!empty($this->settings->working_hours)) {
            $workingHours = "ساعات العمل: {$this->settings->working_hours}";
        }

        $context = "اسم المتجر: {$storeName}\n";
        $context .= "عدد المنتجات: {$totalProducts}\n";

        if ($deliveryInfo) $context .= "{$deliveryInfo}\n";
        if ($returnPolicy) $context .= "{$returnPolicy}\n";
        if ($workingHours) $context .= "{$workingHours}\n";

        return $context;
    }

    /**
     * Get total product count for store
     */
    protected function getTotalProductCount(): int
    {
        return Product::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->count();
    }

    // ==================== HELPER METHODS ====================

    /**
     * Resolve the latest known order for this lead/conversation.
     *
     * @return array{0: ?array, 1: ?string, 2: ?string}
     */
    protected function resolveLatestOrderData(AiChatSession $session, Lead $lead): array
    {
        $orderData = $session->current_order ?? null;
        $statusLabel = $orderData['status'] ?? null;
        $orderNumber = $orderData['order_number'] ?? null;

        if (!empty($orderData)) {
            if (empty($statusLabel)) {
                $statusLabel = 'قيد التجهيز';
            }
            return [$orderData, $statusLabel, $orderNumber];
        }

        $query = OnlineOrder::where('user_id', $this->user->id)
            ->where(function ($q) use ($lead, $session) {
                $q->where('lead_id', $lead->id);
                if (!empty($session->conversation_id)) {
                    $q->orWhere('conversation_id', $session->conversation_id);
                }
            })
            ->latest();

        $lastOrder = $query->with(['items', 'items.product'])->first();

        if (!$lastOrder) {
            return [null, null, null];
        }

        $items = $lastOrder->items->map(function ($item) {
            $name = $item->product_name ?? ($item->product->name ?? 'منتج');
            $qty = (int) ($item->quantity ?? 1);
            $price = (int) round((float) ($item->unit_price ?? 0));

            return [
                'name' => $name,
                'price' => $price,
                'quantity' => $qty,
            ];
        })->toArray();

        $orderData = [
            'items' => $items,
            'total_price' => (int) round((float) $lastOrder->total),
            'customer' => [
                'name' => $lastOrder->customer_name,
                'phone' => $lastOrder->customer_phone,
                'address' => $lastOrder->customer_address,
            ],
            'order_number' => $lastOrder->order_number,
            'status' => $lastOrder->status,
        ];

        $statusLabel = $lastOrder->status_label ?? $lastOrder->status;
        $orderNumber = $lastOrder->order_number;

        return [$orderData, $statusLabel, $orderNumber];
    }

    /**
     * Format OnlineOrder model to array format for session/response
     */
    protected function formatOrderFromModel(OnlineOrder $order): array
    {
        $items = $order->items->map(function ($item) {
            return [
                'name' => $item->product_name ?? ($item->product->name ?? 'منتج'),
                'quantity' => (int) $item->quantity,
                'price' => (int) $item->unit_price,
                'total' => (int) ($item->quantity * $item->unit_price),
            ];
        })->toArray();

        $statusLabels = [
            'pending' => 'قيد الانتظار',
            'confirmed' => 'مؤكد',
            'processing' => 'قيد التجهيز',
            'shipped' => 'تم الشحن',
            'delivered' => 'تم التوصيل',
            'cancelled' => 'ملغي',
            'returned' => 'مرتجع',
        ];

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $statusLabels[$order->status] ?? 'قيد التجهيز',
            'status_raw' => $order->status,
            'items' => $items,
            'total' => (int) $order->total,
            'total_price' => (int) $order->total,
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'address' => $order->customer_address,
            ],
            'created_at' => $order->created_at->toIso8601String(),
        ];
    }

    /**
     * Build a friendly status summary for the current order.
     */
    protected function formatOrderStatusReply(
        AiChatSession $session,
        array $orderData,
        ?string $statusLabel = null,
        ?string $orderNumber = null
    ): string {
        $storeContext = $session->store_context ?? [];
        $deliveryCost = $storeContext['delivery_cost'] ?? 5000;
        $deliveryTime = $storeContext['delivery_time'] ?? 'نفس اليوم';

        $items = $orderData['items'] ?? [];
        $total = (int) ($orderData['total_price'] ?? $session->getCartTotal());

        $lines = ['🧾 تفاصيل الطلب الحالي:'];
        if (!empty($orderNumber)) {
            $lines[] = "رقم الطلب: {$orderNumber}";
        }

        foreach ($items as $item) {
            $name = $item['name'] ?? 'منتج';
            $qty = (int) ($item['quantity'] ?? 1);
            $price = (int) ($item['price'] ?? 0);
            $lines[] = "• {$name} × {$qty} = " . ($price * $qty) . " دينار";
        }

        $lines[] = "💰 المجموع: {$total} دينار";
        if (!empty($statusLabel)) {
            $lines[] = "📦 حالة الطلب: {$statusLabel}";
        }
        $lines[] = "🚚 التوصيل: {$deliveryTime} (السعر: {$deliveryCost} دينار)";
        $lines[] = "اذا تحب تعدل او تعرف تحديثات جديدة خبرني.";
    return implode("\n", $lines);
}

    /**
     * Build cart preview and ask for missing info ONE at a time
     * IMPROVED: First checks for missing product attributes (size, color)
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

        // Build cart preview with attributes
        $cartPreview = $this->missingDataDetector
            ? $this->missingDataDetector->formatCartWithAttributes($cart)
            : $this->buildCartPreview($cart, $total);

        // If info is already complete, confirm reuse before asking again
        $storeContext = $session->store_context ?? [];
        if ($session->hasCompleteCustomerData() && empty($storeContext['customer_info_confirmed'])) {
            $storeContext['confirming_customer_info'] = true;
            $session->store_context = $storeContext;
            $session->save();

            $name = $customerData['name'] ?? '-';
            $phone = $customerData['phone'] ?? '-';
            $address = $customerData['address'] ?? '-';
            $reply = "{$cartPreview}\n\nهذه معلوماتك؟\nالاسم: {$name}\nالرقم: {$phone}\nالعنوان: {$address}\n\nنستخدمها للطلب؟ اذا تريد تغير، اكتب البيانات الجديدة.";
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'confirm_customer_info', $session, true);
        }

        // Check what info we're missing - ask ONE at a time
        $missingFields = $session->getMissingFields();

        if (in_array('name', $missingFields)) {
            $ask = AiFastReply::getRandomReply($this->user->id, 'ask_name') ?? 'شنو اسمك الكريم؟';
            $reply = "{$cartPreview}\n\n{$ask}";
            $intent = 'asking_name';
        } elseif (in_array('phone', $missingFields)) {
            $name = $customerData['name'] ?? '';
            $ask = AiFastReply::getRandomReply($this->user->id, 'ask_phone') ?? 'شنو رقم تلفونك؟';
            $reply = "{$cartPreview}\n\nزين يا {$name}، {$ask}";
            $intent = 'asking_phone';
        } elseif (in_array('address', $missingFields)) {
            $ask = AiFastReply::getRandomReply($this->user->id, 'ask_address') ?? 'وين نوصل إلك؟';
            $reply = "{$cartPreview}\n\nممتاز! {$ask}";
            $intent = 'asking_address';
        } else {
            // All info complete - ASK for confirmation (don't create order yet)
            $summary = $this->buildOrderSummaryWithAttributes($cart, $total, $customerData);
            $intent = 'asking_confirmation';
            $reply = $summary;
        }

        $session->addMessage('assistant', $reply);

        return $this->buildResponse($reply, $intent, $session, true);
    }

    /**
     * Build order summary for confirmation (with attributes)
     * IMPROVED: Cleaner, shorter format
     */
    protected function buildOrderSummaryWithAttributes(array $items, int $total, array $customer): string
    {
        $lines = [];
        $lines[] = "📋 ملخص الطلب";
        $lines[] = "─────────────────";

        foreach ($items as $item) {
            $itemTotal = $item['price'] * $item['quantity'];
            $formattedTotal = number_format($itemTotal);
            $line = "• {$item['name']} × {$item['quantity']} = {$formattedTotal} د.ع";

            // Add attributes on same line if present
            if (!empty($item['attributes'])) {
                $attrs = [];
                foreach ($item['attributes'] as $key => $value) {
                    $attrName = StoreType::getAttributeName($key);
                    $displayValue = $key === 'color' ? StoreType::getColorName($value) : $value;
                    $attrs[] = "{$attrName}: {$displayValue}";
                }
                $line .= " (" . implode('، ', $attrs) . ")";
            }
            $lines[] = $line;
        }

        $formattedTotal = number_format($total);
        $lines[] = "─────────────────";
        $lines[] = "💰 المجموع: {$formattedTotal} د.ع";
        $lines[] = "";
        $lines[] = "👤 " . ($customer['name'] ?? '-');
        $lines[] = "📱 " . ($customer['phone'] ?? '-');
        $lines[] = "📍 " . ($customer['address'] ?? '-');
        $lines[] = "";
        $lines[] = "✅ تأكيد؟ (نعم/لا)";

        return implode("\n", $lines);
    }

    /**
     * Build order summary for confirmation
     * IMPROVED: Cleaner, shorter format
     */
    protected function buildOrderSummary(array $items, int $total, array $customer): string
    {
        $lines = [];
        $lines[] = "📋 ملخص الطلب";
        $lines[] = "─────────────────";

        foreach ($items as $item) {
            $itemTotal = $item['price'] * $item['quantity'];
            $formattedTotal = number_format($itemTotal);
            $lines[] = "• {$item['name']} × {$item['quantity']} = {$formattedTotal} د.ع";
        }

        $formattedTotal = number_format($total);
        $lines[] = "─────────────────";
        $lines[] = "💰 المجموع: {$formattedTotal} د.ع";
        $lines[] = "";
        $lines[] = "👤 " . ($customer['name'] ?? '-');
        $lines[] = "📱 " . ($customer['phone'] ?? '-');
        $lines[] = "📍 " . ($customer['address'] ?? '-');
        $lines[] = "";
        $lines[] = "✅ تأكيد؟ (نعم/لا)";

        return implode("\n", $lines);
    }

    /**
     * Build cart preview
     */
    protected function buildCartPreview(array $items, int $total): string
    {
        if (empty($items)) {
            return "🛒 السلة فارغة";
        }

        $lines = ["━━━━━━━━━━━━━━━━━━"];
        $lines[] = "🛒 سلة الطلب";
        $lines[] = "━━━━━━━━━━━━━━━━━━";
        $lines[] = "";
        foreach ($items as $item) {
            $itemTotal = $item['price'] * $item['quantity'];
            $formattedPrice = number_format($item['price']);
            $formattedTotal = number_format($itemTotal);
            $lines[] = "  • {$item['name']}";
            $lines[] = "    {$item['quantity']} × {$formattedPrice} د.ع = {$formattedTotal} د.ع";
            $lines[] = "  ─────────────────";
        }
        $formattedGrandTotal = number_format($total);
        $lines[] = "";
        $lines[] = "💰 المجموع: {$formattedGrandTotal} د.ع";
        $lines[] = "━━━━━━━━━━━━━━━━━━";
        return implode("\n", $lines);
    }

    /**
     * Build order confirmation
     */
    protected function buildOrderConfirmation(array $items, int $total, array $customer): string
    {
        $lines = ["━━━━━━━━━━━━━━━━━━"];
        $lines[] = "✅ طلبك مؤكد!";
        $lines[] = "━━━━━━━━━━━━━━━━━━";
        $lines[] = "";
        $lines[] = "📦 المنتجات:";
        foreach ($items as $item) {
            $itemTotal = $item['price'] * $item['quantity'];
            $formattedPrice = number_format($item['price']);
            $formattedTotal = number_format($itemTotal);
            $lines[] = "  • {$item['name']}";
            $lines[] = "    {$item['quantity']} × {$formattedPrice} د.ع = {$formattedTotal} د.ع";
            $lines[] = "  ─────────────────";
        }
        $formattedGrandTotal = number_format($total);
        $lines[] = "";
        $lines[] = "💰 المجموع: {$formattedGrandTotal} د.ع";
        $lines[] = "━━━━━━━━━━━━━━━━━━";
        $lines[] = "";
        $lines[] = "👤 الاسم: " . ($customer['name'] ?? '-');
        $lines[] = "📱 الهاتف: " . ($customer['phone'] ?? '-');
        $lines[] = "📍 العنوان: " . ($customer['address'] ?? '-');
        $lines[] = "";
        $lines[] = "━━━━━━━━━━━━━━━━━━";
        $lines[] = "سنتواصل معك قريباً. شكراً!";
        $lines[] = "━━━━━━━━━━━━━━━━━━";
        return implode("\n", $lines);
    }

    /**
     * Extract line items (products with quantities) from message
     * CRITICAL: Works for ALL store types - clothes, electronics, food, etc.
     * IMPROVED: Handles multiple products in same message (قميصين وبنطرونين)
     */
    protected function extractLineItems(string $message, array $products): array
    {
        $text = $this->normalize($message);
        $items = [];

        // STEP 1: Split message by connectors (و، ,) to handle multiple products
        $parts = preg_split('/\s*[،,]\s*|\s+و\s+/u', $text);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part) || mb_strlen($part) < 2) continue;

            // Extract quantity for this part
            $quantity = $this->extractQuantityFromPart($part);

            // Remove quantity-related words from part to find product name
            $quantityWords = ['قطع', 'قطعه', 'قطعة', 'حبه', 'حبة', 'pieces', 'عدد', 'كمية', 'اريد'];
            $cleanPart = $part;
            foreach ($quantityWords as $qWord) {
                $cleanPart = str_ireplace($qWord, '', $cleanPart);
            }
            $cleanPart = preg_replace('/[0-9٠-٩]+/u', '', $cleanPart);
            $cleanPart = trim(preg_replace('/\s+/u', ' ', $cleanPart));

            if (empty($cleanPart) || mb_strlen($cleanPart) < 2) continue;

            // Find matching product for this part
            $matchedProduct = $this->findBestProductMatch($cleanPart, $products);

            if ($matchedProduct) {
                // Check if already in items (same product mentioned twice)
                $productKey = $matchedProduct['name'];
                if (isset($items[$productKey])) {
                    $items[$productKey]['quantity'] += $quantity;
                } else {
                    $items[$productKey] = [
                        'name' => $matchedProduct['name'],
                        'price' => (int) $matchedProduct['price'],
                        'quantity' => $quantity,
                    ];
                }

                Log::info('GroqChat: Product matched in part', [
                    'part' => $part,
                    'clean_part' => $cleanPart,
                    'product' => $matchedProduct['name'],
                    'quantity' => $quantity,
                ]);
            }
        }

        // If no matches found from parts, try matching the whole message
        if (empty($items)) {
            $quantity = $this->extractQuantityFromMessage($message);
            $cleanText = $text;
            $quantityWords = ['قطع', 'قطعه', 'قطعة', 'حبه', 'حبة', 'pieces', 'عدد', 'كمية', 'اريد'];
            foreach ($quantityWords as $qWord) {
                $cleanText = str_ireplace($qWord, '', $cleanText);
            }
            $cleanText = preg_replace('/[0-9٠-٩]+/u', '', $cleanText);
            $cleanText = trim(preg_replace('/\s+/u', ' ', $cleanText));

            $matchedProduct = $this->findBestProductMatch($cleanText, $products);
            if ($matchedProduct) {
                $items[$matchedProduct['name']] = [
                    'name' => $matchedProduct['name'],
                    'price' => (int) $matchedProduct['price'],
                    'quantity' => $quantity,
                ];
            }
        }

        Log::info('GroqChat: Extracted line items', [
            'message' => $message,
            'items' => $items,
        ]);

        return array_values($items);
    }

    /**
     * Extract quantity from a message part
     * Handles dual forms (قميصين = 2)
     */
    protected function extractQuantityFromPart(string $part): int
    {
        // Check for dual forms (ين suffix = 2)
        $dualPatterns = [
            '/قميصين|قميصتين/' => 2,
            '/بنطرونين|بنطلونين|بنطروناتين/' => 2,
            '/تشيرتين|تيشيرتين/' => 2,
            '/هوديين|هودياتين/' => 2,
            '/حذائين|جزمتين/' => 2,
            '/نظارتين|نضارتين/' => 2,
            '/ساعتين/' => 2,
            '/قطعتين|حبتين/' => 2,
        ];

        foreach ($dualPatterns as $pattern => $qty) {
            if (preg_match($pattern, $part)) {
                return $qty;
            }
        }

        // Check for Arabic number words
        foreach (self::NUM_WORDS as $word => $num) {
            if ($num > 0 && mb_stripos($part, $word) !== false) {
                return $num;
            }
        }

        // Check for digits
        if (preg_match('/(\d+)/', $part, $match)) {
            return max(1, min(100, (int)$match[1]));
        }

        // Arabic numerals
        if (preg_match('/([٠-٩]+)/', $part, $match)) {
            $arabicNum = $match[1];
            $englishNum = strtr($arabicNum, ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
            return max(1, min(100, (int)$englishNum));
        }

        // Check for dual suffix (general - words ending in ين for items)
        if (preg_match('/\p{Arabic}+ين$/u', $part) && !preg_match('/اثنين|ثنين/u', $part)) {
            // This might be a dual form, return 2
            return 2;
        }

        return 1;
    }

    /**
     * Find best matching product from a text part
     */
    protected function findBestProductMatch(string $text, array $products): ?array
    {
        $bestMatch = null;
        $bestScore = 0;

        foreach ($products as $product) {
            $productName = $product['name'];
            $normalizedName = $this->normalize($productName);
            $allAliases = array_merge([$normalizedName], $product['aliases'] ?? []);

            $score = 0;

            // Strategy 1: Exact match (highest score)
            foreach ($allAliases as $alias) {
                if (empty($alias) || mb_strlen($alias) < 2) continue;

                if ($text === $alias) {
                    $score = 1000;
                    break;
                }
            }

            // Strategy 2: Full alias found in text (high score)
            if ($score < 1000) {
                foreach ($allAliases as $alias) {
                    if (empty($alias) || mb_strlen($alias) < 3) continue;

                    if (mb_stripos($text, $alias) !== false) {
                        $aliasScore = 500 + (mb_strlen($alias) * 10);
                        if ($aliasScore > $score) {
                            $score = $aliasScore;
                        }
                    }
                }
            }

            // Strategy 3: Individual product words match
            if ($score < 500) {
                $productWords = preg_split('/[\s]+/u', $normalizedName, -1, PREG_SPLIT_NO_EMPTY);
                $textWords = preg_split('/[\s]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
                $matchedWords = 0;

                foreach ($productWords as $productWord) {
                    if (mb_strlen($productWord) < 3) continue;

                    foreach ($textWords as $textWord) {
                        if (mb_strlen($textWord) < 3) continue;

                        // Handle dual forms: remove ين suffix and compare
                        $cleanProductWord = preg_replace('/ين$|تين$/u', '', $productWord);
                        $cleanTextWord = preg_replace('/ين$|تين$/u', '', $textWord);

                        if ($productWord === $textWord ||
                            $cleanProductWord === $cleanTextWord ||
                            mb_stripos($textWord, $cleanProductWord) !== false ||
                            mb_stripos($productWord, $cleanTextWord) !== false) {
                            $matchedWords++;
                            break;
                        }

                        // Stem matching
                        $minLen = min(mb_strlen($productWord), mb_strlen($textWord));
                        if ($minLen >= 4) {
                            $stemLen = $minLen - 1;
                            if (mb_substr($productWord, 0, $stemLen) === mb_substr($textWord, 0, $stemLen)) {
                                $matchedWords++;
                                break;
                            }
                        }
                    }
                }

                if ($matchedWords > 0) {
                    $score = $matchedWords * 50;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $product;
            }
        }

        // If no good match, try AI
        if (!$bestMatch || $bestScore < 50) {
            $aiMatch = $this->findProductWithAI($text, $products);
            if ($aiMatch) {
                return $aiMatch;
            }
        }

        return $bestScore >= 50 ? $bestMatch : null;
    }

    /**
     * Use AI to find matching product semantically
     * Handles irregular Arabic plurals (جمع تكسير) like حقيبة→حقائب, حزام→احزمة
     */
    protected function findProductWithAI(string $userText, array $products): ?array
    {
        if (empty($products) || mb_strlen($userText) < 3) {
            return null;
        }

        // Limit to max 20 products for AI to avoid token limits
        $productsForAI = array_slice($products, 0, 20);

        $productNames = array_map(function($p) {
            return $p['name'];
        }, $productsForAI);

        $prompt = "المستخدم يقول: \"{$userText}\"

المنتجات المتاحة:
" . implode("\n", array_map(fn($i, $name) => ($i+1) . ". {$name}", array_keys($productNames), $productNames)) . "

هل يقصد المستخدم أحد هذه المنتجات؟ رد فقط برقم المنتج (1-" . count($productNames) . ") أو 0 إذا لا يقصد أي منتج.
رد فقط بالرقم، بدون أي نص إضافي.";

        try {
            // Use OpenAI for semantic matching
            $apiKey = $this->user->aiSetting->openai_api_key ?? config('services.openai.api_key');

            $response = Http::timeout(5)
                ->withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4.1-mini',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 10,
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                $productIndex = (int) trim($content);

                if ($productIndex > 0 && $productIndex <= count($productNames)) {
                    $matchedProduct = $productsForAI[$productIndex - 1];

                    Log::info('GroqChat: AI semantic match', [
                        'user_text' => $userText,
                        'matched_product' => $matchedProduct['name'],
                        'ai_response' => $content,
                    ]);

                    return $matchedProduct;
                }
            }
        } catch (\Exception $e) {
            Log::warning('GroqChat: AI semantic matching failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Extract quantity from message text
     * Handles Arabic numerals (٠-٩), English numerals (0-9), and Arabic words
     */
    protected function extractQuantityFromMessage(string $message): int
    {
        $text = $this->normalize($message);
        $originalText = $message; // Keep original for Arabic numerals

        // PRIORITY 1: Arabic numerals (٠-٩)
        if (preg_match('/[٠-٩]+/', $originalText, $match)) {
            $arabicNum = $match[0];
            // Convert Arabic numerals to English
            $englishNum = strtr($arabicNum, [
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ]);
            $qty = (int) $englishNum;
            if ($qty > 0) {
                return max(1, min(100, $qty));
            }
        }

        // PRIORITY 2: English numbers with context ("5 قطع", "قطع 5", etc.)
        if (preg_match('/(\d{1,3})\s*(?:قطع|قطعه|قطعة|حبه|حبة|pieces?)?/', $text, $match)) {
            $qty = (int) $match[1];
            if ($qty > 0) {
                return max(1, min(100, $qty));
            }
        }

        // PRIORITY 3: "قطع X" or "X قطعة" patterns
        if (preg_match('/(?:قطع|قطعه|قطعة|حبه|حبة)\s*(\d{1,3})/', $text, $match)) {
            $qty = (int) $match[1];
            if ($qty > 0) {
                return max(1, min(100, $qty));
            }
        }

        // PRIORITY 4: Dual forms (قطعتين = 2 pieces)
        if (preg_match('/قطعتين|قطعه?تين|حبتين|نظارتين|نضارتين/', $text)) {
            return 2;
        }

        // PRIORITY 5: Arabic number words
        $longestMatch = null;
        $longestLength = 0;

        foreach (self::NUM_WORDS as $word => $num) {
            if ($num > 0 && mb_stripos($text, $word) !== false && mb_strlen($word) > $longestLength) {
                $longestMatch = $num;
                $longestLength = mb_strlen($word);
            }
        }

        if ($longestMatch !== null) {
            return $longestMatch;
        }

        return 1; // Default to 1
    }

    /**
     * Extract customer info from message
     * IMPROVED: Better handling of multi-line input without labels
     */
    protected function extractCustomerInfo(string $message): array
    {
        $text = $this->normalize($message);
        $originalMessage = trim($message);
        $info = [];

        // Phone (Iraqi format) - check multiple lines
        if (preg_match_all('/\b(?:\+?964|0)?7[3-9]\d{8}\b/', $text, $matches)) {
            $info['phone'] = $matches[0][0]; // Take first match
        }

        // Name patterns - support multiple formats
        // Format 1: "اسمي احمد"
        if (preg_match('/(?:اسمي|انا|اني)\s+([\p{Arabic}]{2,}(?:\s+[\p{Arabic}]{2,})?)/u', $text, $match)) {
            $potentialName = trim($match[1]);
            if ($this->isValidName($potentialName)) {
                $info['name'] = $potentialName;
            }
        }

        // Format 2: Multi-line "اسمي عتوي\nرقمي..."
        $lines = preg_split('/[\r\n]+/', $message);
        foreach ($lines as $line) {
            $line = trim($line);

            // Name line with prefix
            if (preg_match('/^(?:اسمي|اسمه|الاسم|اسم)\s*[:=]?\s*([\p{Arabic}\s]+)$/u', $line, $match)) {
                $potentialName = trim($match[1]);
                // Additional check: name should be 2-4 words max
                $wordCount = count(preg_split('/\s+/u', $potentialName));
                if ($this->isValidName($potentialName) && $wordCount <= 4) {
                    $info['name'] = $potentialName;
                }
            }

            // Address line
            if (preg_match('/^(?:عنواني|عنوانه|العنوان|عنوان)\s*[:=]?\s*(.+)$/iu', $line, $match)) {
                $info['address'] = trim($match[1]);
            }

            // Phone line
            if (preg_match('/^(?:رقمي|رقمه|الرقم|رقم|هاتف|تلفون)\s*[:=]?\s*(\+?964|0)?7[3-9]\d{8}/u', $line, $match)) {
                $fullMatch = $match[0];
                if (preg_match('/(\+?964|0)?7[3-9]\d{8}/', $fullMatch, $phoneMatch)) {
                    $info['phone'] = $phoneMatch[0];
                }
            }
        }

        // IMPROVED: Handle multi-line input without labels (common format)
        // When user sends:
        // زيد اسامه
        // 0781348481
        // البصره الزبير
        if (count($lines) >= 2) {
            $phoneFound = false;
            $phoneLine = -1;

            // Find which line has the phone number
            foreach ($lines as $i => $line) {
                if (preg_match('/(?:\+?964|0)?7[3-9]\d{8}/', trim($line))) {
                    $phoneFound = true;
                    $phoneLine = $i;
                    break;
                }
            }

            if ($phoneFound) {
                // Line BEFORE phone is likely the name (if we don't have one yet)
                if (empty($info['name']) && $phoneLine > 0) {
                    $potentialName = trim($lines[$phoneLine - 1]);
                    // Must be Arabic text, 2-4 words, and pass validation
                    if (preg_match('/^[\p{Arabic}\s]+$/u', $potentialName)) {
                        $wordCount = count(preg_split('/\s+/u', $potentialName));
                        if ($wordCount >= 1 && $wordCount <= 4 && $this->isValidName($potentialName)) {
                            $info['name'] = $potentialName;
                        }
                    }
                }

                // Line AFTER phone is likely the address (if we don't have one yet)
                if (empty($info['address']) && $phoneLine < count($lines) - 1) {
                    $potentialAddress = trim($lines[$phoneLine + 1]);
                    // Must have at least 3 characters
                    if (mb_strlen($potentialAddress) >= 3) {
                        $info['address'] = $potentialAddress;
                    }
                }
            }
        }

        // Address patterns - single line
        if (empty($info['address']) && preg_match('/(?:عنواني|العنوان)\s+(.+)/iu', $message, $match)) {
            $info['address'] = trim($match[1]);
        } elseif (empty($info['address']) && preg_match('/(?:ساكن|بمنطقه)\s+(.+)/iu', $message, $match)) {
            $info['address'] = trim($match[1]);
        } elseif (empty($info['address']) && empty($info['name']) && empty($info['phone'])) {
            // If no name or phone found, check if it looks like an address
            // Iraqi cities and areas (normalized without ال and with ه instead of ة)
            $cities = [
                'بغداد', 'بصره', 'موصل', 'نجف', 'كربلاء', 'اربيل', 'سليمانيه',
                'ديالى', 'انبار', 'بابل', 'كركوك', 'واسط', 'صلاح الدين',
                'قادسيه', 'ذي قار', 'مثنى', 'ميسان', 'دهوك', 'زبير',
                'الكوت', 'الناصريه', 'السماوه', 'الحله', 'تكريت', 'الرمادي',
                'كربلا', 'نجف', 'كاظميه', 'اعظميه', 'كرخ', 'رصافه', 'سيديه',
                'دوره', 'شعله', 'حريه', 'كاظم', 'علي', 'فضل', 'طالبيه',
                'منصور', 'صدر', 'مدينه', 'جنوب', 'شمال', 'غرب', 'شرق'
            ];
            $areas = [
                'حي', 'منطقه', 'شارع', 'محله', 'قضاء', 'ناحيه',
                'ابي الخصيب', 'عارضات', 'قرنه', 'مدينه'
            ];

            $normalizedMessage = $this->normalize($originalMessage);
            foreach (array_merge($cities, $areas) as $area) {
                $normalizedArea = $this->normalize($area);
                if (mb_stripos($normalizedMessage, $normalizedArea) !== false && mb_strlen($originalMessage) < 100) {
                    $info['address'] = trim($originalMessage);
                    break;
                }
            }
        }

        Log::info('GroqChat: extractCustomerInfo result', [
            'original_message' => $originalMessage,
            'extracted' => $info,
            'lines_count' => count($lines),
        ]);

        return $info;
    }

    /**
     * Validate if text is a legitimate name (not city/area/keyword)
     */
    protected function isValidName(string $text): bool
    {
        $text = trim($text);

        // Minimum and maximum length check (names shouldn't be too short or too long)
        if (mb_strlen($text) < 2 || mb_strlen($text) > 50) {
            return false;
        }

        // Reject if contains numbers (names shouldn't have numbers)
        if (preg_match('/[0-9٠-٩]/u', $text)) {
            return false;
        }

        // Reject if it's a city or common area
        $cities = ['بغداد', 'البصره', 'الموصل', 'النجف', 'كربلاء', 'اربيل', 'السليمانيه', 'ديالى', 'الانبار', 'بابل', 'كركوك', 'واسط', 'صلاح الدين', 'القادسيه', 'ذي قار', 'المثنى', 'ميسان', 'دهوك'];
        $areas = ['حي', 'منطقه', 'شارع', 'محله', 'قضاء', 'ناحيه', 'ابي الخصيب', 'العارضات', 'السماوه', 'الناصريه', 'الحله', 'الكوت'];

        foreach (array_merge($cities, $areas) as $location) {
            if (mb_stripos($text, $location) !== false) {
                return false;
            }
        }

        // Reject if it contains product keywords
        $productKeywords = ['قميص', 'بنطلون', 'حذاء', 'جزمه', 'كندره', 'ملابس', 'ثوب', 'دشداشه', 'عباءه', 'حجاب', 'طرحه'];
        foreach ($productKeywords as $keyword) {
            if (mb_stripos($text, $keyword) !== false) {
                return false;
            }
        }

        // Reject common inquiry/order keywords
        $commandKeywords = ['اريد', 'ابي', 'اطلب', 'كم', 'شكد', 'سعر', 'متوفر', 'موجود'];
        foreach ($commandKeywords as $keyword) {
            if (mb_stripos($text, $keyword) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check stock availability
     */
    protected function checkStock(AiChatSession $session): array
    {
        $outOfStock = [];
        $products = $session->store_context['products'] ?? [];

        foreach ($session->cart as $cartItem) {
            $product = collect($products)->firstWhere('name', $cartItem['name']);
            $stock = $product['stock'] ?? 0;

            if ($cartItem['quantity'] > $stock) {
                $outOfStock[] = [
                    'name' => $cartItem['name'],
                    'requested' => $cartItem['quantity'],
                    'available' => $stock,
                ];
            }
        }

        return $outOfStock;
    }

    /**
     * Get or create chat session
     */
    protected function getOrCreateSession(Lead $lead, Conversation $conversation): AiChatSession
    {
        // Try to find existing active session
        $session = AiChatSession::where('user_id', $this->user->id)
            ->where('lead_id', $lead->id)
            ->where('expires_at', '>', now())
            ->first();

        if ($session) {
            $session->touchActivity();
            return $session;
        }

        // Create new session with products - LOAD ALL ACTIVE PRODUCTS
        $products = Product::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->orderBy('quantity', 'desc')
            ->limit(200) // Increased from 50 to support larger stores
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (int) $p->price,
                'stock' => (int) $p->quantity,
                'category_id' => $p->category_id,
                'aliases' => $this->generateAliases($p->name),
            ])
            ->toArray();

        $storeContext = [
            'name' => $this->user->name,
            'working_hours' => $this->settings->working_hours ?? '9am - 10pm',
            'delivery_time' => $this->settings->delivery_time ?? 'نفس اليوم',
            'delivery_cost' => $this->settings->delivery_cost ?? 5000,
            'return_policy' => $this->settings->store_policies ?? 'استرجاع خلال 7 أيام',
            'products' => $products,
        ];

        return AiChatSession::create([
            'user_id' => $this->user->id,
            'lead_id' => $lead->id,
            'conversation_id' => $conversation->id,
            'store_context' => array_merge($storeContext, [
                'conversation_state' => 'greeting', // Track conversation flow
                'payment_method' => 'cash_on_delivery', // Only cash on delivery
            ]),
            'messages' => [],
            'cart' => [],
            'customer_data' => [
                'name' => $lead->name,
                'phone' => $lead->phone,
                'address' => $lead->address,
            ],
            'expires_at' => now()->addMinutes($this->sessionTimeoutMinutes),
        ]);
    }

    /**
     * Generate Arabic aliases for product name
     */
    protected function generateAliases(string $name): array
    {
        $base = $this->normalize($name);
        $aliases = [$base];

        // Dual suffixes
        foreach (self::DUAL_SUFFIXES as $suffix) {
            $aliases[] = $base . $suffix;
        }

        // Plurals
        $aliases[] = $base . 'ات';

        // Common broken plurals / colloquial variants (Iraqi)
        if (mb_stripos($base, 'قميص') !== false) {
            $aliases[] = 'قمصان';
        }
        if (mb_stripos($base, 'بنطرون') !== false) {
            $aliases[] = 'بناطير';
        }
        if (mb_stripos($base, 'تشيرت') !== false || mb_stripos($base, 'تيشيرت') !== false) {
            $aliases[] = 'تشيرتات';
            $aliases[] = 'تيشيرت';
            $aliases[] = 'تيشيرتات';
        }

        // IMPORTANT: Add glasses/نظاره aliases
        if (mb_stripos($base, 'نظار') !== false || mb_stripos($base, 'نظاره') !== false) {
            $aliases[] = 'نظاره';
            $aliases[] = 'نظارات';
            $aliases[] = 'نظارة';
            $aliases[] = 'نضاره';
            $aliases[] = 'نضارات';
            $aliases[] = 'نضارة';
            $aliases[] = 'glasses';
        }

        // Common electronics
        if (mb_stripos($base, 'جهاز') !== false) {
            $aliases[] = 'جهاز';
            $aliases[] = 'اجهزه';
            $aliases[] = 'اجهزة';
        }

        // Bags
        if (mb_stripos($base, 'حقيب') !== false) {
            $aliases[] = 'حقيبه';
            $aliases[] = 'حقيبة';
            $aliases[] = 'شنطه';
            $aliases[] = 'شنطة';
        }

        return array_unique($aliases);
    }

    /**
     * Detect base category from message
     */
    protected function detectCategoryBase(string $normalizedMessage): ?string
    {
        // Shirt category
        if ($this->containsKeyword($normalizedMessage, ['قمصان', 'قميص'])) {
            return 'قميص';
        }

        // Pants category
        if ($this->containsKeyword($normalizedMessage, ['بناطير', 'بنطرون'])) {
            return 'بنطرون';
        }

        // T-shirt category
        if ($this->containsKeyword($normalizedMessage, ['تشيرتات', 'تشيرت', 'تيشيرت', 'تيشيرتات'])) {
            return 'تشيرت';
        }

        return null;
    }

    /**
     * Extract first quantity mentioned (digits or known Arabic number words).
     */
    protected function extractFirstQuantity(string $normalizedMessage): ?int
    {
        // PRIORITY 1: Arabic numerals (٠-٩)
        if (preg_match('/[٠-٩]+/u', $normalizedMessage, $match)) {
            $arabicNum = $match[0];
            // Convert Arabic numerals to English
            $englishNum = strtr($arabicNum, [
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ]);
            $n = (int) $englishNum;
            return $n > 0 ? min(100, $n) : null;
        }

        // PRIORITY 2: English numerals
        if (preg_match('/(\d{1,3})/u', $normalizedMessage, $m)) {
            $n = (int) $m[1];
            return $n > 0 ? min(100, $n) : null;
        }

        // PRIORITY 3: Arabic number words (check longer words first)
        $sortedNumWords = self::NUM_WORDS;
        uksort($sortedNumWords, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        foreach ($sortedNumWords as $word => $value) {
            if ($value > 0 && mb_stripos($normalizedMessage, $word) !== false) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * Detect dual quantity for category mentions (e.g. "قميصين" / "بنطرونين" / "تشيرتين").
     */
    protected function looksLikeDualForCategory(string $normalizedMessage, string $categoryBase): bool
    {
        // If message includes base and ends with a dual suffix, treat it as 2
        foreach (self::DUAL_SUFFIXES as $suffix) {
            if (mb_stripos($normalizedMessage, $categoryBase . $suffix) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * True if message looks like a pure quantity response ("2" / "ثنين" / "قطعتين").
     */
    protected function looksLikeQuantityOnly(string $message): bool
    {
        $normalized = $this->normalize($message);
        $normalized = preg_replace('/\s+/u', ' ', trim($normalized));

        // Extended max length to handle compound numbers like "اريد خمسة عشر قطعة"
        if (mb_strlen($normalized) > 35) {
            return false;
        }

        // Check for digits first
        if ($this->extractFirstQuantity($normalized) !== null) {
            return true;
        }

        // Check for compound Arabic number words (e.g., "خمسة عشر")
        // Must check longer compound words first before shorter ones
        $sortedNumWords = self::NUM_WORDS;
        uksort($sortedNumWords, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        foreach ($sortedNumWords as $word => $value) {
            if ($value > 0 && mb_stripos($normalized, $word) !== false) {
                return true;
            }
        }

        // Common quantity words
        if (mb_stripos($normalized, 'قطعه') !== false || mb_stripos($normalized, 'قطعة') !== false) {
            foreach (self::DUAL_SUFFIXES as $suffix) {
                if (mb_substr($normalized, -mb_strlen($suffix)) === $suffix) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Handle next-step quantity for the pending product selection.
     */
    protected function handlePendingQuantity(AiChatSession $session, Lead $lead, string $message): array
    {
        $storeContext = $session->store_context ?? [];
        $pending = $storeContext['pending_product'] ?? null;

        $session->addMessage('user', $message);

        if (empty($pending) || empty($pending['name'])) {
            unset($storeContext['pending_product']);
            $session->store_context = $storeContext;
            $session->save();

            $reply = 'تمام! شنو تفضل؟ قمصان ولا بناطير ولا تشيرتات؟';
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'asking_preference', $session, true);
        }

        $normalized = $this->normalize($message);
        $qty = $this->extractFirstQuantity($normalized);

        // Try compound Arabic number words (must check longer words first)
        if ($qty === null) {
            $sortedNumWords = self::NUM_WORDS;
            uksort($sortedNumWords, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

            foreach ($sortedNumWords as $word => $value) {
                if ($value > 0 && mb_stripos($normalized, $word) !== false) {
                    $qty = (int) $value;
                    break;
                }
            }
        }

        if ($qty === null) {
            foreach (self::DUAL_SUFFIXES as $suffix) {
                if (mb_substr($normalized, -mb_strlen($suffix)) === $suffix) {
                    $qty = 2;
                    break;
                }
            }
        }

        $qty = $qty ?? 1;

        $session->addToCart($pending['name'], (int) ($pending['price'] ?? 0), (int) $qty);

        unset($storeContext['pending_product']);
        $session->store_context = $storeContext;
        $session->save();

        $outOfStock = $this->checkStock($session);
        if (!empty($outOfStock)) {
            $lines = ["⚠️ بعض المنتجات غير متوفرة بالكمية المطلوبة:"];
            foreach ($outOfStock as $item) {
                $lines[] = "❌ {$item['name']}: المطلوب {$item['requested']}، المتوفر {$item['available']}";
            }
            $lines[] = "";
            $lines[] = "تريد أعدّل الكمية للمتوفر أو أحذفها؟";
            $reply = implode("\n", $lines);
            $session->addMessage('assistant', $reply);
            return $this->buildResponse($reply, 'stock_issue', $session, true);
        }

        return $this->buildCartAndAskInfo($session, $lead);
    }

    /**
     * Find the best matching product from search results
     * Returns null if no good match (need to show multiple options)
     * IMPROVED: Better handling for single-word queries like "فرن"
     */
    protected function findBestMatchFromResults(string $normalizedQuery, array $searchResults): ?array
    {
        if (empty($searchResults)) {
            return null;
        }

        // If only one result, return it (database search already filtered)
        if (count($searchResults) === 1) {
            return $searchResults[0];
        }

        // Split query into meaningful words (ignore common words)
        $ignoreWords = ['غير', 'متوفر', 'عندكم', 'عدكم', 'موجود', 'هل', 'في', 'فيه', 'كم', 'سعر', 'شكد', 'شنو', 'اريد', 'ابي'];
        $queryWords = preg_split('/\s+/u', $normalizedQuery);
        $queryWords = array_filter($queryWords, function($w) use ($ignoreWords) {
            return mb_strlen($w) > 2 && !in_array($w, $ignoreWords);
        });

        if (empty($queryWords)) {
            return null;
        }

        // CRITICAL FIX: For single-word queries, be more lenient
        // Example: "فرن" should match "فرن بيتزا كبير الحجم"
        $isSingleWordQuery = count($queryWords) === 1;

        $bestMatch = null;
        $bestScore = 0;

        foreach ($searchResults as $product) {
            $productName = $this->normalize($product['name'] ?? '');
            $score = 0;

            // Check how many query words appear in product name
            foreach ($queryWords as $word) {
                if (mb_stripos($productName, $word) !== false) {
                    $score += 10;

                    // BONUS: If the product name STARTS with the query word, it's likely a good match
                    // e.g., "فرن" matches "فرن بيتزا" better than "مقلاة فرن"
                    if (mb_strpos($productName, $word) === 0 || mb_strpos($productName, $word . ' ') !== false) {
                        $score += 15;
                    }
                }
            }

            // Bonus for exact phrase match
            if (mb_stripos($productName, implode(' ', $queryWords)) !== false) {
                $score += 50;
            }

            // Track best match
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $product;
            }
        }

        // IMPROVED: Dynamic threshold based on query complexity
        // Single word query: threshold = 10 (just needs to match the word)
        // Multi-word query: threshold = 30 (needs to match more words)
        $threshold = $isSingleWordQuery ? 10 : 30;

        if ($bestScore >= $threshold) {
            return $bestMatch;
        }

        return null;
    }

    /**
     * Find product mentioned in message
     * IMPROVED: Now includes real-time stock validation
     */
    protected function findProductInMessage(string $normalizedMessage, array $products): ?array
    {
        // Safety check for empty inputs
        if (empty($normalizedMessage) || empty($products)) {
            return null;
        }

        $bestMatch = null;
        $bestScore = 0;

        // Split message into words for better matching
        $messageWords = preg_split('/\s+/u', $normalizedMessage);
        $messageWords = array_filter($messageWords, fn($w) => mb_strlen($w) > 1);

        // SMART: Add variations of each word (remove ال prefix, add it, etc.)
        $expandedWords = [];
        foreach ($messageWords as $word) {
            $expandedWords[] = $word;
            // Remove Arabic "ال" (the) prefix
            if (mb_strpos($word, 'ال') === 0 && mb_strlen($word) > 3) {
                $expandedWords[] = mb_substr($word, 2);
            }
            // Remove "الـ" variant
            if (mb_strpos($word, 'الـ') === 0 && mb_strlen($word) > 3) {
                $expandedWords[] = mb_substr($word, 3);
            }
            // Add "ال" prefix if not present
            if (mb_strpos($word, 'ال') !== 0) {
                $expandedWords[] = 'ال' . $word;
            }
        }
        $messageWords = array_unique($expandedWords);

        // If no valid words after filtering, return null
        if (empty($messageWords)) {
            return null;
        }

        // Check each product and its aliases
        foreach ($products as $product) {
            $name = $this->normalize($product['name'] ?? '');
            $aliases = $product['aliases'] ?? [];

            // Calculate match score for product name
            $nameWords = preg_split('/\s+/u', $name);
            $nameWords = array_filter($nameWords, fn($w) => mb_strlen($w) > 1);

            // Also expand product name words
            $expandedNameWords = [];
            foreach ($nameWords as $nw) {
                $expandedNameWords[] = $nw;
                if (mb_strpos($nw, 'ال') === 0 && mb_strlen($nw) > 3) {
                    $expandedNameWords[] = mb_substr($nw, 2);
                }
                if (mb_strpos($nw, 'ال') !== 0) {
                    $expandedNameWords[] = 'ال' . $nw;
                }
            }
            $nameWords = array_unique($expandedNameWords);

            $matchCount = 0;
            foreach ($nameWords as $nameWord) {
                foreach ($messageWords as $msgWord) {
                    if (mb_stripos($msgWord, $nameWord) !== false || mb_stripos($nameWord, $msgWord) !== false) {
                        $matchCount++;
                        break;
                    }
                }
            }

            // Score is: (matched words / total product words) * 100
            $score = count($nameWords) > 0 ? ($matchCount / count($nameWords)) * 100 : 0;

            // Bonus points if all product words are matched
            if ($matchCount === count($nameWords) && count($nameWords) > 0) {
                $score += 50;
            }

            // IMPORTANT: Threshold at 55% to balance between catching partial names and avoiding false positives
            // "فرن بيتزا" should match "فرن بيتزا كبير الحجم" but "فستان" shouldn't match "حزام"
            $userWordsInProductBonus = 0;
            foreach ($messageWords as $msgWord) {
                if (mb_strlen($msgWord) > 2 && mb_stripos($name, $msgWord) !== false) {
                    $userWordsInProductBonus += 15; // Reduced from 20 to prevent over-matching
                }
            }
            $score += $userWordsInProductBonus;

            if ($score > $bestScore && $score >= 55) { // Increased from 30% to 55% to reduce false positives
                $bestScore = $score;
                $bestMatch = $product;
            }

            // Check aliases
            foreach ($aliases as $alias) {
                $aliasNorm = $this->normalize($alias);
                $aliasWords = preg_split('/\s+/u', $aliasNorm);
                $aliasWords = array_filter($aliasWords, fn($w) => mb_strlen($w) > 1);

                $aliasMatchCount = 0;
                foreach ($aliasWords as $aliasWord) {
                    foreach ($messageWords as $msgWord) {
                        if (mb_stripos($msgWord, $aliasWord) !== false || mb_stripos($aliasWord, $msgWord) !== false) {
                            $aliasMatchCount++;
                            break;
                        }
                    }
                }

                $aliasScore = count($aliasWords) > 0 ? ($aliasMatchCount / count($aliasWords)) * 100 : 0;
                if ($aliasMatchCount === count($aliasWords) && count($aliasWords) > 0) {
                    $aliasScore += 50;
                }

                if ($aliasScore > $bestScore && $aliasScore >= 30) { // Lowered from 50 to 30
                    $bestScore = $aliasScore;
                    $bestMatch = $product;
                }
            }
        }

        return $bestMatch;
    }

    /**
     * Find the closest product by a base word (e.g. 'قميص', 'بنطرون').
     */
    protected function findClosestProductByBase(AiChatSession $session, string $base): ?array
    {
        $products = $session->store_context['products'] ?? [];
        $baseNorm = $this->normalize($base);

        foreach ($products as $product) {
            $nameNorm = $this->normalize($product['name'] ?? '');
            if ($nameNorm === $baseNorm || mb_stripos($nameNorm, $baseNorm) !== false) {
                return $product;
            }
        }

        return null;
    }

    /**
     * Normalize Arabic text
     * IMPROVED: Added Iraqi/Kurdish letter variations
     */
    protected function normalize(string $text): string
    {
        $text = trim($text);
        $text = strtr($text, self::AR_DIGITS);
        // Normalize alif variations (including hamza)
        $text = str_replace(['أ', 'إ', 'آ', 'ء', 'ؤ'], 'ا', $text);
        // Normalize ta marbuta
        $text = str_replace('ة', 'ه', $text);
        // Normalize alif maksura
        $text = str_replace('ى', 'ي', $text);
        // Normalize ya with hamza
        $text = str_replace('ئ', 'ي', $text);
        // Normalize ظ/ض confusion (very common in dialectal Arabic)
        $text = str_replace('ظ', 'ض', $text);
        // Normalize Iraqi/Kurdish letters
        $text = str_replace(['گ', 'ڭ'], 'ك', $text);
        $text = str_replace('چ', 'ج', $text);
        $text = str_replace('پ', 'ب', $text);
        // Remove all diacritics (تشكيل)
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
        // Remove "ال" from all words for better matching
        $text = preg_replace('/(?:^|\s)ال/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', trim($text));
        return $text;
    }

    /**
     * Check if text contains any keyword
     */
    protected function containsKeyword(string $text, array $keywords): bool
    {
        // Normalize the text first
        $normalizedText = $this->normalize($text);

        foreach ($keywords as $keyword) {
            // Also normalize the keyword for fair comparison
            $normalizedKeyword = $this->normalize($keyword);
            if (mb_stripos($normalizedText, $normalizedKeyword) !== false) {
                return true;
            }
            // Also check original text against original keyword (for non-Arabic)
            if (mb_stripos($text, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build standard response array
     */
    protected function buildResponse(
        string $reply,
        string $intent,
        AiChatSession $session,
        bool $fastReply,
        ?string $actionRequired = null,
        ?array $currentOrder = null,
        ?array $productsToShow = null
    ): array {
        return [
            'reply' => $reply,
            'intent' => $intent,
            'fast_reply' => $fastReply,
            'cart' => $session->cart,
            'customer_data' => $session->customer_data,
            'current_order' => $currentOrder,
            'action_required' => $actionRequired,
            'products_to_show' => $productsToShow, // Product IDs to send images for
        ];
    }

    /**
     * Call Groq API - ONLY for complex queries
     * Minimal prompt - no product list to save tokens
     */
    protected function callGroq(AiChatSession $session, string $message): array
    {
        if (empty($this->apiKey)) {
            return [
                'reply' => 'الخدمة غير مهيأة. سيتواصل معك أحد موظفينا قريباً.',
                'intent' => 'error',
                'action_required' => 'human_agent_needed',
            ];
        }

        $storeContext = $session->store_context;
        $storeName = $storeContext['name'] ?? 'المتجر';
        $deliveryCost = $storeContext['delivery_cost'] ?? 5000;
        $deliveryTime = $storeContext['delivery_time'] ?? 'نفس اليوم';
        $returnPolicy = $storeContext['return_policy'] ?? 'استرجاع خلال 7 أيام';
        $workingHours = $storeContext['working_hours'] ?? '9am - 10pm';

        // Get dynamic categories
        $categories = $this->getStoreCategories();
        $categoryNames = array_column($categories, 'name');
        $categoryStr = implode('، ', $categoryNames);

        // Improved system prompt with store info - NO EMOJIS
        $systemPrompt = <<<PROMPT
أنت مساعد مبيعات ذكي لمتجر "{$storeName}" في العراق.
استخدم لهجة عراقية مؤدبة ومختصرة وطبيعية.

معلومات المتجر:
- التوصيل: {$deliveryCost} دينار، وقت التوصيل: {$deliveryTime}
- الاسترجاع: {$returnPolicy}
- ساعات العمل: {$workingHours}
- الأقسام: {$categoryStr}

التعليمات المهمة:
0. إذا كان السؤال خارج نطاق المتجر (سياسة/دين/طب/برمجة/أخبار... إلخ) اعتذر وارجع لمواضيع المتجر فقط
1. لا تُرجع قائمة كاملة للمنتجات أبداً
2. اسأل الزبون عن اهتماماته حسب الأقسام المتوفرة
3. إذا سأل عن سعر منتج معين، أجبه بسعره فقط
4. كن مختصراً وطبيعياً مثل محادثة حقيقية
5. ممنوع استخدام الإيموجي نهائياً
6. إذا طلب الزبون صورة للمنتج، قل له "حاضر" أو "أكيد" لأن النظام سيرسل الصور تلقائياً

أمثلة:
- "شنو عدكم؟" → "عدنا {$categoryStr}. شنو تفضل؟"
- "اريد" → "شنو تريد بالضبط؟"
- "كم سعر القميص؟" → "القميص بـ 15000 دينار"
- "ممكن صوره؟" → "حاضر، هاي الصورة"

أجب بصيغة JSON فقط:
{"intent": "conversation", "reply": "الرد"}
PROMPT;

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        // Add last 4 messages only
        $history = $session->messages ?? [];
        foreach (array_slice($history, -4) as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(15)->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.3,
                'max_tokens' => 200,
                'response_format' => ['type' => 'json_object'],
            ]);

            if ($response->successful()) {
                $content = $response->json()['choices'][0]['message']['content'] ?? '';
                $parsed = $this->parseAiResponse($content);
                // Remove any emojis from the reply
                $parsed['reply'] = $this->removeEmojis($parsed['reply']);
                return $parsed;
            }

            Log::error('Groq API failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

        } catch (\Exception $e) {
            Log::error('Groq API exception', ['error' => $e->getMessage()]);
        }

        return [
            'reply' => 'عذراً، صار خطأ. شنو تحتاج؟',
            'intent' => 'error',
        ];
    }

    /**
     * Parse AI response
     */
    protected function parseAiResponse(string $content): array
    {
        try {
            if (preg_match('/\{.*\}/s', $content, $match)) {
                $json = json_decode($match[0], true);
                if ($json) {
                    return [
                        'reply' => $json['reply'] ?? $content,
                        'intent' => $json['intent'] ?? 'unknown',
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to parse AI response', ['content' => $content]);
        }

        return ['reply' => $content, 'intent' => 'unknown'];
    }

    /**
     * Clear session cart
     */
    public function clearSessionCart(Lead $lead): void
    {
        $session = AiChatSession::where('user_id', $this->user->id)
            ->where('lead_id', $lead->id)
            ->where('expires_at', '>', now())
            ->first();

        if ($session) {
            $session->clearCart();
            $session->current_order = null;
            $session->save();
        }
    }

    /**
     * Check if we have a cached response for similar inquiry
     * OPTIMIZATION: Avoid redundant AI calls for similar questions
     */
    protected function checkCachedInquiry(AiChatSession $session, string $normalizedMessage): ?string
    {
        $history = $session->messages ?? [];
        if (count($history) < 4) {
            return null; // Need some history to cache
        }

        // Get last 10 exchanges
        $recentHistory = array_slice($history, -20);

        // Look for similar user questions
        foreach ($recentHistory as $i => $msg) {
            if ($msg['role'] !== 'user') {
                continue;
            }

            $pastNormalized = $this->normalize($msg['content']);
            $similarity = $this->calculateSimilarity($normalizedMessage, $pastNormalized);

            // If 85%+ similar and we have an assistant response
            if ($similarity > 0.85 && isset($recentHistory[$i + 1]) && $recentHistory[$i + 1]['role'] === 'assistant') {
                // Check if it's recent (within last 5 messages)
                $recency = count($recentHistory) - $i;
                if ($recency <= 5) {
                    Log::info('GroqChat: Using cached response', [
                        'similarity' => $similarity,
                        'original' => mb_substr($pastNormalized, 0, 30),
                        'current' => mb_substr($normalizedMessage, 0, 30),
                    ]);
                    return $recentHistory[$i + 1]['content'];
                }
            }
        }

        return null;
    }

    /**
     * Calculate similarity between two strings (simple algorithm)
     */
    protected function calculateSimilarity(string $str1, string $str2): float
    {
        $len1 = mb_strlen($str1);
        $len2 = mb_strlen($str2);

        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }

        // Use Levenshtein for short strings, word overlap for longer
        if ($len1 < 50 && $len2 < 50) {
            $lev = levenshtein($str1, $str2);
            $maxLen = max($len1, $len2);
            return 1.0 - ($lev / $maxLen);
        }

        // Word-based similarity for longer strings
        $words1 = preg_split('/\s+/u', $str1);
        $words2 = preg_split('/\s+/u', $str2);

        $intersection = count(array_intersect($words1, $words2));
        $union = count(array_unique(array_merge($words1, $words2)));

        return $union > 0 ? $intersection / $union : 0.0;
    }
}
