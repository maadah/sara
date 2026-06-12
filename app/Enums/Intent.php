<?php

namespace App\Enums;

/**
 * Intent Types for Customer Messages
 *
 * Complete intent taxonomy for the AI marketing chatbot.
 * Used by IntentClassifier (gpt-4.1-nano) to route customer messages
 * to the correct conversation flow.
 *
 * @see \App\Services\Chat\IntentClassifier
 */
enum Intent: string
{
    /* ---- Greetings ---- */
    case GREETING            = 'greeting';

    /* ---- Browsing ---- */
    case BROWSE_GENERAL      = 'browse_general';
    case BROWSE_CATEGORY     = 'browse_category';
    case SEARCH_PRODUCT      = 'search_product';

    /* ---- Product Inquiry ---- */
    case ASK_PRICE           = 'ask_price';
    case ASK_AVAILABILITY    = 'ask_availability';
    case ASK_DETAILS         = 'ask_details';
    case REQUEST_IMAGE       = 'request_image';

    /* ---- Cart ---- */
    case ADD_TO_CART         = 'add_to_cart';
    case UPDATE_QUANTITY     = 'update_quantity';
    case REMOVE_FROM_CART    = 'remove_from_cart';
    case VIEW_CART           = 'view_cart';
    case CLEAR_CART          = 'clear_cart';

    /* ---- Checkout ---- */
    case CHECKOUT            = 'checkout';

    /* ---- Customer Info ---- */
    case PROVIDE_NAME        = 'provide_name';
    case PROVIDE_PHONE       = 'provide_phone';
    case PROVIDE_ADDRESS     = 'provide_address';

    /* ---- Order ---- */
    case CONFIRM_ORDER       = 'confirm_order';
    case CANCEL_ORDER        = 'cancel_order';
    case CHECK_ORDER_STATUS  = 'check_order_status';

    /* ---- Delivery ---- */
    case ASK_DELIVERY        = 'ask_delivery';

    /* ---- Negotiation ---- */
    case NEGOTIATE_PRICE     = 'negotiate_price';
    case SALES_OBJECTION     = 'sales_objection';

    /* ---- Promotions ---- */
    case ASK_PROMOTION       = 'ask_promotion';
    case WHOLESALE_INQUIRY   = 'wholesale_inquiry';

    /* ---- Feedback ---- */
    case FEEDBACK            = 'feedback';

    /* ---- Other ---- */
    case OUT_OF_SCOPE        = 'out_of_scope';
    case UNKNOWN             = 'unknown';

    /* ================================================================== */

    /**
     * Arabic trigger keywords used as hints during intent classification.
     * These are NOT the sole basis — they are injected into the classifier prompt.
     *
     * @return string[]
     */
    public function triggerKeywords(): array
    {
        return match ($this) {
            self::GREETING           => ['سلام', 'هلا', 'مرحبا', 'صباح الخير', 'هاي', 'شلونك'],
            self::BROWSE_GENERAL     => ['شنو عدكم', 'شنو منتجاتكم', 'وريني', 'شنو تبيعون'],
            self::BROWSE_CATEGORY    => ['كهربائيات', 'ملابس', 'اثاث', 'اكسسوارات', 'عطور'],
            self::SEARCH_PRODUCT     => ['ابي', 'اريد', 'اشوف', 'عندكم'],
            self::ASK_PRICE          => ['بكم', 'شكد سعر', 'كم ثمن', 'شكد', 'سعره'],
            self::ASK_AVAILABILITY   => ['موجود', 'متوفر', 'اكو', 'عدكم'],
            self::ASK_DETAILS        => ['وصف', 'تفاصيل', 'ميزات', 'شنو يشمل', 'مواصفات'],
            self::REQUEST_IMAGE      => ['صورة', 'شوفلي صوره', 'صور'],
            self::ADD_TO_CART        => ['اريد', 'ابي اشتري', 'ضيف بالسله', 'خذ', 'اضيف'],
            self::UPDATE_QUANTITY    => ['غير الكميه', 'اريد اثنين', 'خليها', 'عدل الكميه'],
            self::REMOVE_FROM_CART   => ['شيل', 'احذف', 'ما اريده', 'شيله'],
            self::VIEW_CART          => ['سلتي', 'شنو بالسله', 'وريني طلبي', 'السله'],
            self::CLEAR_CART         => ['فرغ السله', 'امسح كل شي', 'كانسل السله'],
            self::CHECKOUT           => ['خلص', 'اكمل', 'اريد اطلب', 'اكد الطلب'],
            self::PROVIDE_NAME       => ['اسمي', 'انا'],
            self::PROVIDE_PHONE      => ['رقمي', 'هاتفي', 'تلفوني'],
            self::PROVIDE_ADDRESS    => ['عنواني', 'محلتي', 'منطقتي', 'اسكن في'],
            self::CONFIRM_ORDER      => ['نعم', 'اكيد', 'تمام', 'اكد', 'صح', 'اي'],
            self::CANCEL_ORDER       => ['لا', 'الغي', 'كانسل', 'ما اريد'],
            self::CHECK_ORDER_STATUS => ['وين طلبي', 'وصل طلبي', 'حالة الطلب'],
            self::ASK_DELIVERY       => ['متى توصل', 'كم يوم', 'سعر التوصيل', 'التوصيل'],
            self::NEGOTIATE_PRICE    => ['غالي', 'خفض', 'خصم', 'تخفيض'],
            self::SALES_OBJECTION    => ['حصلت بمكان ثاني', 'عند غيركم ارخص', 'بمكان ثاني'],
            self::ASK_PROMOTION      => ['اكو عرض', 'في تخفيض', 'عندكم اوفر', 'عرض'],
            self::WHOLESALE_INQUIRY  => ['بالجمله', 'كميه كبيره', 'بالكرتون', 'جمله'],
            self::FEEDBACK           => ['استلمت', 'وصل', 'رأيي', 'تقييم'],
            self::OUT_OF_SCOPE       => [],
            self::UNKNOWN            => [],
        };
    }

    /**
     * Intents that indicate an affirmative / forward-moving response.
     *
     * @return self[]
     */
    public static function affirmativeIntents(): array
    {
        return [
            self::CONFIRM_ORDER,
            self::ADD_TO_CART,
            self::CHECKOUT,
        ];
    }

    /**
     * Intents that require product context to process.
     */
    public function requiresProductContext(): bool
    {
        return in_array($this, [
            self::SEARCH_PRODUCT,
            self::ASK_PRICE,
            self::ASK_AVAILABILITY,
            self::ASK_DETAILS,
            self::REQUEST_IMAGE,
            self::ADD_TO_CART,
            self::UPDATE_QUANTITY,
            self::REMOVE_FROM_CART,
        ]);
    }

    /**
     * Intents that indicate browsing behaviour.
     */
    public function isBrowsingIntent(): bool
    {
        return in_array($this, [
            self::BROWSE_GENERAL,
            self::BROWSE_CATEGORY,
            self::SEARCH_PRODUCT,
            self::ASK_PRICE,
            self::ASK_AVAILABILITY,
            self::ASK_DETAILS,
            self::REQUEST_IMAGE,
        ]);
    }

    /**
     * Intents related to cart operations.
     */
    public function isCartIntent(): bool
    {
        return in_array($this, [
            self::ADD_TO_CART,
            self::UPDATE_QUANTITY,
            self::REMOVE_FROM_CART,
            self::VIEW_CART,
            self::CLEAR_CART,
        ]);
    }

    /**
     * Intents related to order operations.
     */
    public function isOrderIntent(): bool
    {
        return in_array($this, [
            self::CHECKOUT,
            self::CONFIRM_ORDER,
            self::CANCEL_ORDER,
            self::CHECK_ORDER_STATUS,
        ]);
    }

    /**
     * Intents where the customer is providing personal info.
     */
    public function isInfoIntent(): bool
    {
        return in_array($this, [
            self::PROVIDE_NAME,
            self::PROVIDE_PHONE,
            self::PROVIDE_ADDRESS,
        ]);
    }

    /**
     * Build the intent list (name → keywords) for the classifier prompt.
     *
     * @return string
     */
    public static function classifierList(): string
    {
        $lines = [];
        foreach (self::cases() as $case) {
            $kw = $case->triggerKeywords();
            $kwStr = $kw ? implode('، ', $kw) : '—';
            $lines[] = "{$case->value}: {$kwStr}";
        }
        return implode("\n", $lines);
    }
}
