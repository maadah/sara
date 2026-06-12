<?php

namespace App\Services\AI;

use App\Enums\ConversationState;
use App\Models\User;
use App\Services\AiProviderService;
use Illuminate\Support\Facades\Log;

/**
 * Response Generator V2 - Natural Iraqi Arabic Responses
 *
 * COMPLETELY REWRITTEN:
 * ✅ Warm Iraqi dialect (شلونك، شقدر اساعدك)
 * ✅ Emojis allowed (📱🔌⚡✅🌟)
 * ✅ Cart format: product: qty قطعة (price × qty = subtotal د.ع)
 * ✅ Categories-only for "شنو عدكم"
 * ✅ 15-20 items for category browse
 * ✅ Delivery cost always shown in cart
 * ✅ Clean separators (━━━━━━━━━━━━━━━)
 */
class ResponseGenerator
{
    // ─── TEMPLATES ─────────────────────────────────────────────

    protected const TEMPLATES = [
        // Cart
        'cart_empty' => 'السلة فاضية. شنو تحب تطلب؟',
        'cart_empty_decline' => 'السلة فاضية. اذا تريد تطلب شي، موجود 🌟',

        // Error/fallback
        'product_not_found' => 'ما لقيت هذا المنتج. ممكن توضح اكثر او تكتب اسمه بشكل ثاني؟',
        'error_generic' => 'صار خطأ بسيط، ممكن تعيد رسالتك؟',

        // Stock
        'out_of_stock' => 'عذراً، هذا المنتج غير متوفر حالياً.',

        // Cancel
        'order_cancelled' => 'تم إلغاء الطلب. اذا تريد شي ثاني، موجود! 🌟',
        'nothing_to_cancel' => 'ما عندك طلب حالياً. تريد تطلب شي؟',
        'cannot_cancel' => 'عذراً، هذا الطلب مؤكد ولا يمكن إلغاؤه. تقدر تتواصل مع الدعم.',

        // Confirmations
        'item_removed' => 'تم حذف المنتج من السلة.',
        'quantity_updated' => 'تم تحديث الكمية.',

        // Questions
        'ask_which_product' => 'شنو تريد بالضبط؟',
        'ask_quantity' => 'كم قطعة تحتاج؟',
        'ask_confirm' => 'تأكد الطلب؟ نعم / لا',
        'no_order_found' => 'ما لقيت طلب باسمك. اذا تريد تطلب شي جديد، موجود! 🌟',
    ];

    // ─── GREETING ──────────────────────────────────────────────

    /**
     * Warm greeting - short and friendly
     */
    public function greeting(bool $hasActiveOrder = false, bool $hasCart = false): string
    {
        if ($hasCart) {
            return "اهلاً وسهلاً! 🌟 سلتك موجودة، تريد تكمل طلبك؟";
        }
        if ($hasActiveOrder) {
            return "اهلا وسهلا مرة ثانية! 🌟 شقدر اساعدك اليوم؟";
        }
        return "اهلاً وسهلاً! شلونك؟ شقدر اساعدك اليوم؟ 🌟";
    }

    // ─── CATEGORIES (شنو عدكم) ─────────────────────────────────────

    /**
     * Show ONLY categories when customer asks "شنو عدكم؟"
     * Rule: Don't list products yet, ask which category
     */
    public function showCategories(array $categories): string
    {
        $lines = [];
        $lines[] = "اهلاً وسهلاً 🌟";
        $lines[] = "الاقسام المتوفرة:";
        $lines[] = "━━━━━━━━━━━━━━━";

        foreach ($categories as $cat) {
            $name = is_array($cat) ? ($cat['name'] ?? '') : ($cat->name ?? '');
            $count = is_array($cat) ? ($cat['products_count'] ?? '') : ($cat->products_count ?? '');
            $countStr = $count ? " ({$count} منتج)" : '';
            $lines[] = "• {$name}{$countStr}";
        }

        $lines[] = "━━━━━━━━━━━━━━━";
        $lines[] = "اي قسم تفضل او شنو تحب تطلب؟";

        return implode("\n", $lines);
    }

    // ─── CATEGORY PRODUCTS ─────────────────────────────────────

    /**
     * Show products from a category (up to 20 items)
     */
    public function showCategoryProducts(array $products, string $categoryName): string
    {
        if (empty($products)) {
            return "ما في منتجات بقسم {$categoryName} حالياً. جرب قسم ثاني؟";
        }

        $lines = [];
        $lines[] = "اكيد! هاي بعض المنتجات من قسم {$categoryName}:";
        $lines[] = "━━━━━━━━━━━━━━━";

        foreach (array_slice($products, 0, 20) as $i => $p) {
            $name = $p['name'] ?? '';
            $price = $this->formatPrice($p['price'] ?? 0);
            $num = $i + 1;
            $lines[] = "{$num}. {$name} - {$price} د.ع";
        }

        $lines[] = "━━━━━━━━━━━━━━━";
        $lines[] = "تحب تشوف تفاصيل اي منتج او تطلب شي معين؟";

        return implode("\n", $lines);
    }

    // ─── PRODUCT DETAIL ────────────────────────────────────────

    /**
     * Show single product detail with image placeholder
     * Rule: name, price, availability, ask to add to cart + how many
     */
    public function showProductDetail(array $product): string
    {
        $name = $product['name'] ?? '';
        $price = $this->formatPrice($product['price'] ?? 0);
        $inStock = ($product['stock'] ?? $product['in_stock'] ?? 0) > 0;
        $stockLabel = $inStock ? '✅ متوفر' : '❌ غير متوفر';
        $desc = $product['description'] ?? '';

        $lines = [];
        $lines[] = "{$name}";
        $lines[] = "السعر: {$price} د.ع";
        $lines[] = "الحالة: {$stockLabel}";
        if (!empty($desc)) {
            $lines[] = $desc;
        }
        $lines[] = "━━━━━━━━━━━━━━━";

        if ($inStock) {
            $lines[] = "تبي تضيفه للسلة؟ وكم قطعة تحتاج؟";
        } else {
            $lines[] = "للأسف غير متوفر حالياً. تبي اعرضلك بدائل؟";
        }

        return implode("\n", $lines);
    }

    /**
     * Show multiple products
     */
    public function showProducts(array $products, string $query = ''): string
    {
        if (empty($products)) {
            return self::TEMPLATES['product_not_found'];
        }

        if (count($products) === 1) {
            return $this->showProductDetail($products[0]);
        }

        $lines = [];
        $lines[] = "عندنا هاي المنتجات:";
        $lines[] = "━━━━━━━━━━━━━━━";

        foreach (array_slice($products, 0, 10) as $i => $p) {
            $num = $i + 1;
            $name = $p['name'] ?? '';
            $price = $this->formatPrice($p['price'] ?? 0);
            $lines[] = "{$num}. {$name} - {$price} د.ع";
        }

        $lines[] = "━━━━━━━━━━━━━━━";
        $lines[] = "شنو تفضل؟";

        return implode("\n", $lines);
    }

    /**
     * Product not found - suggest alternatives
     */
    public function productNotFound(string $query, array $categories = [], array $bestSellers = []): string
    {
        $lines = [];
        $lines[] = "ما لقيت \"{$query}\" 😔";
        $lines[] = "━━━━━━━━━━━━━━━";

        if (!empty($categories)) {
            $lines[] = "الاقسام المتوفرة:";
            foreach (array_slice($categories, 0, 6) as $cat) {
                $name = is_array($cat) ? ($cat['name'] ?? '') : ($cat->name ?? '');
                $count = is_array($cat) ? ($cat['products_count'] ?? '') : ($cat->products_count ?? '');
                $countStr = $count ? " ({$count} منتج)" : '';
                $lines[] = "• {$name}{$countStr}";
            }
        }

        if (!empty($bestSellers)) {
            $lines[] = "";
            $lines[] = "الاكثر مبيعاً:";
            foreach ($bestSellers as $p) {
                $name = is_array($p) ? ($p['name'] ?? '') : ($p->name ?? '');
                $price = is_array($p) ? ($p['price'] ?? 0) : ($p->price ?? 0);
                $lines[] = "- {$name} ({$this->formatPrice($price)} د.ع)";
            }
        }

        $lines[] = "━━━━━━━━━━━━━━━";
        $lines[] = "اكتب اسم المنتج او القسم اللي تبحث عنه.";

        return implode("\n", $lines);
    }

    // ─── CART RESPONSES ────────────────────────────────────────

    /**
     * Item added to cart - show FULL cart with delivery
     *
     * EXACT FORMAT:
     * تم اضافة المنتج للسلة ✅
     * ━━━━━━━━━━━━━━━
     * قميص ابيض: 2 قطعة (10,000 × 2 = 20,000 د.ع)
     * بنطال جينز: 1 قطعة (15,000 د.ع)
     * ━━━━━━━━━━━━━━━
     * سعر التوصيل: 5,000 د.ع
     * المجموع الكلي: 40,000 د.ع
     * ━━━━━━━━━━━━━━━
     * تبي تضيف منتجات اخرى او تأكد الطلب؟
     */
    public function itemAdded($nameOrItems, $quantityOrTotal = 0, int $cartTotal = 0, int $deliveryCost = 5000): string
    {
        // Support new signature: itemAdded(array $cartItems, int $cartTotal, int $deliveryCost)
        if (is_array($nameOrItems)) {
            $cartItems = $nameOrItems;
            $cartTotal = (int)$quantityOrTotal;
        } else {
            // Legacy single-item call: itemAdded(string $name, int $qty, int $total, int $delivery)
            // Build a fake items array for unified formatting
            $cartItems = [['name' => $nameOrItems, 'quantity' => $quantityOrTotal, 'price' => 0, 'subtotal' => $cartTotal]];
            // In legacy mode, cartTotal is already the total
        }

        $lines = [];
        $lines[] = "تم اضافة المنتج للسلة ✅";
        $lines[] = "━━━━━━━━━━━━━━━";

        foreach ($cartItems as $item) {
            $lines[] = $this->formatCartLine($item);
        }

        $grandTotal = $cartTotal + $deliveryCost;
        $lines[] = "━━━━━━━━━━━━━━━";
        $lines[] = "سعر التوصيل: {$this->formatPrice($deliveryCost)} د.ع";
        $lines[] = "المجموع الكلي: {$this->formatPrice($grandTotal)} د.ع";
        $lines[] = "━━━━━━━━━━━━━━━";
        $lines[] = "تبي تضيف منتجات اخرى او تأكد الطلب؟";

        return implode("\n", $lines);
    }

    /**
     * Cart updated (quantity change or item removal)
     */
    public function cartUpdated(string $action, array $cartItems, int $cartTotal, int $deliveryCost = 5000): string
    {
        $lines = [];
        $lines[] = "تم التحديث! ✅";
        $lines[] = "━━━━━━━━━━━━━━━";

        foreach ($cartItems as $item) {
            $lines[] = $this->formatCartLine($item);
        }

        $grandTotal = $cartTotal + $deliveryCost;
        $lines[] = "━━━━━━━━━━━━━━━";
        $lines[] = "سعر التوصيل: {$this->formatPrice($deliveryCost)} د.ع";
        $lines[] = "المجموع الكلي: {$this->formatPrice($grandTotal)} د.ع";
        $lines[] = "━━━━━━━━━━━━━━━";
        $lines[] = "تبي تأكد الطلب او تضيف شي ثاني؟";

        return implode("\n", $lines);
    }

    /**
     * Full cart summary display
     */
    public function cartSummary(array $items, int $total, bool $askMore = true, int $deliveryCost = 5000): string
    {
        if (empty($items)) {
            return self::TEMPLATES['cart_empty'];
        }

        $lines = [];
        $lines[] = "سلتك:";
        $lines[] = "━━━━━━━━━━━━━━━";

        foreach ($items as $item) {
            $lines[] = $this->formatCartLine($item);
        }

        $grandTotal = $total + $deliveryCost;
        $lines[] = "━━━━━━━━━━━━━━━";
        $lines[] = "سعر التوصيل: {$this->formatPrice($deliveryCost)} د.ع";
        $lines[] = "المجموع الكلي: {$this->formatPrice($grandTotal)} د.ع";

        if ($askMore) {
            $lines[] = "━━━━━━━━━━━━━━━";
            $lines[] = "تبي تأكد الطلب او تضيف شي ثاني؟";
        }

        return implode("\n", $lines);
    }

    // ─── CUSTOMER INFO ─────────────────────────────────────────

    /**
     * Request missing customer info BEFORE checkout
     */
    public function requestMissingInfo(array $missing): string
    {
        if (empty($missing)) {
            return "معلوماتك مكتملة! تأكد الطلب؟";
        }

        $count = count($missing);

        if ($count === 3) {
            $lines = [];
            $lines[] = "تمام! باقي نحتاج معلوماتك للتوصيل:";
            $lines[] = "📝 الاسم الكامل:";
            $lines[] = "📱 رقم الهاتف:";
            $lines[] = "📍 العنوان بالتفصيل:";
            return implode("\n", $lines);
        }

        if ($count === 2) {
            if (in_array('الاسم', $missing) && in_array('رقم الهاتف', $missing)) {
                return "احتاج منك 📝 الاسم و 📱 رقم الهاتف.";
            }
            if (in_array('رقم الهاتف', $missing) && in_array('العنوان', $missing)) {
                return "احتاج منك 📱 رقم الهاتف و 📍 العنوان.";
            }
            if (in_array('الاسم', $missing) && in_array('العنوان', $missing)) {
                return "احتاج منك 📝 الاسم و 📍 العنوان.";
            }
        }

        return match($missing[0]) {
            'الاسم' => "شنو اسمك الكريم؟ 📝",
            'رقم الهاتف' => "شنو رقم هاتفك؟ 📱",
            'العنوان' => "شنو عنوانك بالتفصيل للتوصيل؟ 📍",
            default => "احتاج منك {$missing[0]}.",
        };
    }

    // ─── ORDER RESPONSES ───────────────────────────────────────

    /**
     * Order summary for final confirmation
     *
     * EXACT FORMAT from rules:
     * شكراً {name}!
     * تأكيد الطلب:
     * ━━━━━━━━━━━━━━━
     * المنتجات:
     * product: qty قطعة (subtotal د.ع)
     * ━━━━━━━━━━━━━━━
     * معلومات التوصيل:
     * الاسم: ...
     * الهاتف: ...
     * العنوان: ...
     * ━━━━━━━━━━━━━━━
     * سعر التوصيل: 5,000 د.ع
     * المجموع النهائي: 48,000 د.ع
     * ━━━━━━━━━━━━━━━
     * تأكد الطلب؟ نعم / لا
     */
    public function orderSummaryForConfirmation(array $items, int $subtotal, int $delivery, array $customer): string
    {
        $customerName = $customer['name'] ?? '';

        $lines = [];
        if (!empty($customerName)) {
            $lines[] = "شكراً {$customerName}!";
        }
        $lines[] = "تأكيد الطلب:";
        $lines[] = "━━━━━━━━━━━━━━━";
        $lines[] = "المنتجات:";

        foreach ($items as $item) {
            $lines[] = $this->formatCartLine($item);
        }

        $lines[] = "━━━━━━━━━━━━━━━";
        $lines[] = "معلومات التوصيل:";
        $lines[] = "الاسم: " . ($customer['name'] ?? '—');
        $lines[] = "الهاتف: " . ($customer['phone'] ?? '—');
        $lines[] = "العنوان: " . ($customer['address'] ?? '—');

        $total = $subtotal + $delivery;
        $lines[] = "━━━━━━━━━━━━━━━";
        $lines[] = "سعر التوصيل: {$this->formatPrice($delivery)} د.ع";
        $lines[] = "المجموع النهائي: {$this->formatPrice($total)} د.ع";
        $lines[] = "━━━━━━━━━━━━━━━";
        $lines[] = "تأكد الطلب؟ نعم / لا";

        return implode("\n", $lines);
    }

    /**
     * Order confirmed successfully
     */
    public function orderConfirmed(int $orderId, int $total, string $deliveryTime = 'خلال 24 ساعة'): string
    {
        $lines = [];
        $lines[] = "تم تأكيد طلبك بنجاح! ✅";
        $lines[] = "رقم الطلب: #{$orderId}";
        $lines[] = "المجموع: {$this->formatPrice($total)} د.ع";
        $lines[] = "الحالة: قيد المعالجة";
        $lines[] = "━━━━━━━━━━━━━━━";
        $lines[] = "راح نتواصل معاك {$deliveryTime} لتأكيد الطلب والتوصيل.";
        $lines[] = "شكراً لثقتك! 🌟";

        return implode("\n", $lines);
    }

    /**
     * Order status display (with emoji status labels)
     */
    public function orderStatus(int $orderId, string $status, array $items = [], int $total = 0): string
    {
        $statusLabels = [
            'pending' => '⏳ قيد الانتظار',
            'confirmed' => '✅ مؤكد',
            'processing' => '🔄 قيد التجهيز',
            'shipped' => '🚚 تم الشحن',
            'delivered' => '📦 تم التوصيل',
            'cancelled' => '❌ ملغي',
        ];

        $statusLabel = $statusLabels[$status] ?? 'غير معروف';

        $lines = [];
        $lines[] = "طلبك رقم #{$orderId}:";
        $lines[] = "━━━━━━━━━━━━━━━";

        if (!empty($items)) {
            foreach ($items as $item) {
                $itemName = $item['product_name'] ?? $item['name'] ?? '';
                $qty = $item['quantity'] ?? 1;
                $itemTotal = $item['total'] ?? $item['subtotal'] ?? 0;
                $lines[] = "• {$itemName} × {$qty} = {$this->formatPrice($itemTotal)} د.ع";
            }
            $lines[] = "━━━━━━━━━━━━━━━";
        }

        if ($total > 0) {
            $lines[] = "المجموع: {$this->formatPrice($total)} د.ع";
        }
        $lines[] = "الحالة: {$statusLabel}";

        $lines[] = "";
        $lines[] = match($status) {
            'pending' => "راح نتواصل وياك قريباً لتأكيد الطلب.\nهل تريد إلغاء الطلب؟ (الغي الطلب)",
            'confirmed' => "طلبك مؤكد وراح يجهز قريباً.",
            'processing' => "طلبك قيد التجهيز، انتظر شوية.",
            'shipped' => "طلبك بالطريق اليك! 🚚",
            'delivered' => "طلبك تم توصيله. شكراً لتعاملك معنا! 🌟",
            'cancelled' => "هذا الطلب ملغي. تريد تطلب شي جديد؟",
            default => "",
        };

        return implode("\n", $lines);
    }

    // ─── TEMPLATE ACCESS ───────────────────────────────────────

    /**
     * Get static template by key
     */
    public function template(string $key, array $context = []): string
    {
        $template = self::TEMPLATES[$key] ?? self::TEMPLATES['error_generic'];
        return $this->interpolateTemplate($template, $context);
    }

    // ─── AI GENERATION (fallback for complex scenarios) ────────────────

    /**
     * Generate response using AI for complex scenarios
     */
    public function generate(ConversationState $state, array $context = [], ?User $user = null): string
    {
        $scenario = $context['scenario'] ?? null;
        if ($scenario && isset(self::TEMPLATES[$scenario])) {
            return $this->interpolateTemplate(self::TEMPLATES[$scenario], $context);
        }

        if ($user) {
            return $this->generateWithAI($state, $context, $user);
        }

        return self::TEMPLATES['error_generic'];
    }

    protected function generateWithAI(ConversationState $state, array $context, User $user): string
    {
        $storeName = $context['store_name'] ?? 'المتجر';
        $cartItems = $context['cart_summary'] ?? 'فارغة';

        $prompt = <<<PROMPT
أنت مساعد طلبات ودود لمتجر {$storeName}.
الحالة: {$state->label()}
السلة: {$cartItems}

اكتب رد قصير وطبيعي باللهجة العراقية.
- استخدم اللهجة العراقية: شنو، تريد، عدنا، يوجد
- كن ودود ومختصر (2-3 جمل)
- يمكنك استخدام الرموز التعبيرية بشكل محدود
- الأسعار بالدينار العراقي (د.ع)

اكتب الرد فقط:
PROMPT;

        try {
            $aiProvider = new AiProviderService($user);
            $response = $aiProvider->chat(
                messages: [['role' => 'system', 'content' => $prompt]],
                maxTokens: 200,
                temperature: 0.7,
            );
            $content = $response['content'] ?? '';
            if (is_array($content)) {
                $content = json_encode($content, JSON_UNESCAPED_UNICODE);
            }
            return trim($content) ?: self::TEMPLATES['error_generic'];
        } catch (\Exception $e) {
            Log::error('ResponseGenerator: AI call failed', ['error' => $e->getMessage()]);
            return self::TEMPLATES['error_generic'];
        }
    }

    // ─── FAQ RESPONSES ─────────────────────────────────────────

    /**
     * Answer FAQ questions based on store settings
     * Topics: delivery_cost, return_policy, payment_methods, working_hours, delivery_time, store_location
     */
    public function answerFaq(string $topic, array $storeSettings): string
    {
        return match ($topic) {
            'delivery_cost' => $this->faqDeliveryCost($storeSettings),
            'return_policy' => $this->faqReturnPolicy($storeSettings),
            'payment_methods' => $this->faqPaymentMethods($storeSettings),
            'working_hours' => $this->faqWorkingHours($storeSettings),
            'delivery_time' => $this->faqDeliveryTime($storeSettings),
            'store_location' => $this->faqStoreLocation($storeSettings),
            default => "عذراً، ما فهمت سؤالك. ممكن توضح اكثر؟ 🌟",
        };
    }

    protected function faqDeliveryCost(array $settings): string
    {
        $cost = $settings['delivery_cost'] ?? 5000;
        $formattedCost = $this->formatPrice($cost);

        return <<<TEXT
اجور التوصيل: {$formattedCost} د.ع 🚚

التوصيل لكل مناطق العراق. اي سؤال او استفسار ثاني، موجودين! 🌟
TEXT;
    }

    protected function faqReturnPolicy(array $settings): string
    {
        $policy = $settings['store_policies'] ?? '';

        if (!empty($policy) && mb_stripos($policy, 'استرجاع') !== false) {
            return $policy . "\n\nاي استفسار اضافي، موجودين! 🌟";
        }

        return <<<TEXT
سياسة الاسترجاع والاستبدال:

• يمكن استرجاع او استبدال المنتج خلال 7 ايام من الاستلام
• المنتج لازم يكون بنفس الحالة (غير مستخدم)
• الاسترجاع متاح في حالة العيوب المصنعية
• لا يمكن استرجاع المنتجات المخصصة او الملابس الداخلية

لاي سؤال، تواصل معانا! 🌟
TEXT;
    }

    protected function faqPaymentMethods(array $settings): string
    {
        return <<<TEXT
طرق الدفع المتاحة:

💵 الدفع عند الاستلام (كاش)
🏦 حوالة بنكية
💳 المحافظ الالكترونية

اختر الطريقة اللي تناسبك! 🌟
TEXT;
    }

    protected function faqWorkingHours(array $settings): string
    {
        $hours = $settings['working_hours'] ?? '';

        if (!empty($hours)) {
            return "ساعات العمل:\n{$hours}\n\nموجودين بخدمتك! 🌟";
        }

        return <<<TEXT
ساعات العمل:

من السبت الى الخميس: 9 صباحاً - 9 مساءً
الجمعة: عطلة

موجودين بخدمتك! 🌟
TEXT;
    }

    protected function faqDeliveryTime(array $settings): string
    {
        $time = $settings['delivery_time'] ?? 'خلال 24-48 ساعة';

        return <<<TEXT
وقت التوصيل:

{$time} من تأكيد الطلب 🚚

المناطق البعيدة ممكن تاخذ وقت اضافي بسيط.
TEXT;
    }

    protected function faqStoreLocation(array $settings): string
    {
        $storeName = $settings['store_name'] ?? 'متجرنا';

        return <<<TEXT
نوصل لكل مناطق العراق! 🚚

للطلب، فقط اختر المنتجات وزودنا بمعلوماتك وراح نوصلك اينما كنت.

اي استفسار، موجودين! 🌟
TEXT;
    }

    // ─── HELPERS ───────────────────────────────────────────────

    /**
     * Format a single cart line item
     *
     * qty > 1: "اسم المنتج: 2 قطعة (10,000 × 2 = 20,000 د.ع)"
     * qty = 1: "اسم المنتج: 1 قطعة (10,000 د.ع)"
     */
    protected function formatCartLine(array $item): string
    {
        $name = $item['name'] ?? '';
        $qty = (int)($item['quantity'] ?? 1);
        $price = (int)($item['price'] ?? $item['unit_price'] ?? 0);
        $subtotal = (int)($item['subtotal'] ?? $item['total'] ?? ($price * $qty));

        if ($qty > 1) {
            return "{$name}: {$qty} قطعة ({$this->formatPrice($price)} × {$qty} = {$this->formatPrice($subtotal)} د.ع)";
        }

        return "{$name}: 1 قطعة ({$this->formatPrice($subtotal)} د.ع)";
    }

    /**
     * Format price: 12000 → "12,000"
     */
    public function formatPrice($price): string
    {
        return number_format((int) $price, 0, '', ',');
    }

    protected function interpolateTemplate(string $template, array $context): string
    {
        $replacements = [
            '{qty}' => $context['available_qty'] ?? '',
            '{product}' => $context['product_name'] ?? '',
            '{price}' => $context['price'] ?? '',
            '{total}' => $context['total'] ?? '',
            '{order_id}' => $context['order_id'] ?? '',
            '{delivery_time}' => $context['delivery_time'] ?? 'خلال 24 ساعة',
            '{store_name}' => $context['store_name'] ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
