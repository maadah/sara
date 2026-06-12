<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\StoreType;
use App\Models\User;

/**
 * AI Prompt Builder Service
 * 
 * Builds dynamic AI prompts based on store settings and type.
 * Uses the store owner's AiSetting.system_instruction when available,
 * otherwise generates prompts based on store type.
 */
class AiPromptBuilder
{
    protected User $user;
    protected AiSetting $settings;
    protected ?StoreType $storeType;

    /**
     * Iraqi Arabic expressions for natural conversation
     */
    protected const IRAQI_EXPRESSIONS = [
        'greetings' => ['هلا والله', 'أهلين', 'هلا بيك', 'مرحبا بيك'],
        'confirmations' => ['تمام', 'اكيد', 'اي والله', 'زين'],
        'affirmations' => ['عيوني', 'حبيبي', 'خويه', 'اخي'],
        'questions' => ['شنو', 'شكد', 'شلون', 'يمته'],
        'polite' => ['إن شاء الله', 'الله يوفقك', 'تسلم'],
    ];

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->settings = $user->aiSetting ?? new AiSetting();
        $this->storeType = $user->storeType;
    }

    /**
     * Build the main system prompt for AI
     */
    public function buildSystemPrompt(): string
    {
        // If store owner has custom instruction, use it
        if (!empty($this->settings->system_instruction)) {
            return $this->enhanceWithStoreTypeRules($this->settings->system_instruction);
        }

        // Build dynamic prompt
        return $this->buildDynamicPrompt();
    }

    /**
     * Build prompt with full store context for sales conversations
     */
    public function buildSalesPrompt(array $products, array $categories): string
    {
        $basePrompt = $this->buildSystemPrompt();
        
        $productList = $this->formatProductList($products);
        $categoryList = implode('، ', array_column($categories, 'name'));

        return <<<PROMPT
{$basePrompt}

=== معلومات المتجر ===
الأقسام المتوفرة: {$categoryList}

المنتجات:
{$productList}

=== تعليمات إضافية ===
- عند السؤال عن منتج، اعرض السعر والمواصفات
- لا تذكر منتجات غير موجودة في القائمة
- إذا طلب الزبون منتج غير موجود، اقترح البدائل المتوفرة
PROMPT;
    }

    /**
     * Build prompt for order collection
     */
    public function buildOrderPrompt(array $cartItems): string
    {
        $basePrompt = $this->buildSystemPrompt();
        $cartSummary = $this->formatCartSummary($cartItems);
        $storeTypeQuestions = $this->getStoreTypeOrderQuestions();

        return <<<PROMPT
{$basePrompt}

=== السلة الحالية ===
{$cartSummary}

=== المطلوب جمعه من الزبون ===
1. الاسم الكامل
2. رقم الهاتف (يبدأ بـ 07)
3. العنوان بالتفصيل (المحافظة، المنطقة، أقرب نقطة دالة)
{$storeTypeQuestions}

=== تعليمات ===
- اجمع المعلومات بشكل طبيعي خلال المحادثة
- لا تطلب كل المعلومات دفعة واحدة
- تأكد من صحة رقم الهاتف (11 رقم يبدأ بـ 07)
PROMPT;
    }

    /**
     * Build dynamic prompt based on store configuration
     */
    protected function buildDynamicPrompt(): string
    {
        $storeName = $this->settings->store_name ?? $this->user->name;
        $storeDesc = $this->settings->store_description ?? '';
        $storeType = $this->storeType?->display_name ?? 'متجر';
        
        $expressions = self::IRAQI_EXPRESSIONS;
        $greeting = $expressions['greetings'][array_rand($expressions['greetings'])];
        
        $prompt = <<<PROMPT
انت مساعد مبيعات ذكي لـ "{$storeName}" ({$storeType}).
تتحدث باللهجة العراقية بشكل طبيعي وودود.

=== شخصيتك ===
- ودود ومحترف
- مختصر وواضح في الردود
- تستخدم تعابير عراقية مثل: عيوني، تمام، شكد، شنو
- لا تستخدم ايموجي أبداً
- ترحب بـ "{$greeting}" أو ما شابهها

=== مهامك ===
1. الترحيب بالزبون والسؤال عن احتياجاته
2. عرض المنتجات والإجابة على الاستفسارات
3. مساعدة الزبون في اختيار المنتج المناسب
4. جمع معلومات الزبون عند الطلب (الاسم، الهاتف، العنوان)
5. إتمام الطلب وتأكيده

=== قواعد مهمة ===
- لا تكذب على الزبون
- لا تذكر منتجات غير موجودة
- إذا لم تعرف شيء، قل "خليني أسأل واردلك"
- الأسعار بالدينار العراقي (IQD)
PROMPT;

        // Add store description if available
        if (!empty($storeDesc)) {
            $prompt .= "\n\n=== عن المتجر ===\n{$storeDesc}";
        }

        // Add store policies
        if (!empty($this->settings->store_policies)) {
            $prompt .= "\n\n=== سياسات المتجر ===\n{$this->settings->store_policies}";
        }

        // Add delivery info
        $deliveryInfo = $this->buildDeliveryInfo();
        if (!empty($deliveryInfo)) {
            $prompt .= "\n\n=== معلومات التوصيل ===\n{$deliveryInfo}";
        }

        return $prompt;
    }

    /**
     * Enhance custom instruction with store type specific rules
     */
    protected function enhanceWithStoreTypeRules(string $instruction): string
    {
        if (!$this->storeType || empty($this->storeType->ai_template)) {
            return $instruction;
        }

        return $instruction . "\n\n=== قواعد إضافية لنوع المتجر ===\n" . $this->storeType->ai_template;
    }

    /**
     * Get order questions specific to store type
     */
    protected function getStoreTypeOrderQuestions(): string
    {
        if (!$this->storeType) {
            return '';
        }

        $questions = [];
        $required = $this->storeType->required_attributes ?? [];

        foreach ($required as $attr) {
            $attrName = StoreType::getAttributeName($attr);
            $questions[] = "- {$attrName} (مطلوب)";
        }

        if (empty($questions)) {
            return '';
        }

        return "\n=== معلومات إضافية مطلوبة ===\n" . implode("\n", $questions);
    }

    /**
     * Build delivery information string
     */
    protected function buildDeliveryInfo(): string
    {
        $parts = [];

        if (!empty($this->settings->delivery_time)) {
            $parts[] = "وقت التوصيل: {$this->settings->delivery_time}";
        }

        if (!empty($this->settings->delivery_cost)) {
            $parts[] = "كلفة التوصيل: {$this->settings->delivery_cost} دينار";
        }

        if (!empty($this->settings->working_hours)) {
            $parts[] = "ساعات العمل: {$this->settings->working_hours}";
        }

        return implode("\n", $parts);
    }

    /**
     * Format product list for AI context
     */
    protected function formatProductList(array $products): string
    {
        if (empty($products)) {
            return "لا توجد منتجات حالياً";
        }

        $lines = [];
        foreach ($products as $product) {
            $name = $product['name'] ?? $product->name ?? 'منتج';
            $price = $product['price'] ?? $product->price ?? 0;
            $category = $product['category']['name'] ?? $product->category?->name ?? '';
            
            $line = "- {$name}: {$price} دينار";
            if ($category) {
                $line .= " ({$category})";
            }
            
            // Add attributes if available
            if (isset($product['attributes']) && !empty($product['attributes'])) {
                $attrs = [];
                foreach ($product['attributes'] as $key => $values) {
                    $attrName = StoreType::getAttributeName($key);
                    $attrs[] = "{$attrName}: " . implode(', ', $values);
                }
                if (!empty($attrs)) {
                    $line .= " [" . implode(' | ', $attrs) . "]";
                }
            }
            
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Format cart summary for AI
     */
    protected function formatCartSummary(array $cartItems): string
    {
        if (empty($cartItems)) {
            return "السلة فارغة";
        }

        $lines = [];
        $total = 0;

        foreach ($cartItems as $item) {
            $name = $item['name'] ?? 'منتج';
            $qty = $item['quantity'] ?? 1;
            $price = $item['price'] ?? 0;
            $subtotal = $price * $qty;
            $total += $subtotal;

            $line = "{$qty}x {$name} = {$subtotal} دينار";
            
            // Add selected attributes
            if (!empty($item['attributes'])) {
                $attrs = [];
                foreach ($item['attributes'] as $key => $value) {
                    $attrName = StoreType::getAttributeName($key);
                    $attrs[] = "{$attrName}: {$value}";
                }
                $line .= " (" . implode(', ', $attrs) . ")";
            }
            
            $lines[] = $line;
        }

        $lines[] = "---";
        $lines[] = "المجموع: {$total} دينار";

        return implode("\n", $lines);
    }

    /**
     * Get prompt for asking about missing attribute
     */
    public function getMissingAttributeQuestion(string $attribute, string $productName): string
    {
        $attrName = StoreType::getAttributeName($attribute);
        
        $questions = [
            'size' => "شنو المقاس تريده لـ {$productName}؟",
            'color' => "شنو اللون تفضله لـ {$productName}؟",
            'material' => "شنو نوع الخامة تريدها؟",
            'datetime' => "شنو الموعد المناسب لك؟",
        ];

        return $questions[$attribute] ?? "شنو {$attrName} تريده لـ {$productName}؟";
    }

    /**
     * Get available options prompt for an attribute
     */
    public function getAttributeOptionsPrompt(string $attribute, array $options): string
    {
        if (empty($options)) {
            return '';
        }

        $attrName = StoreType::getAttributeName($attribute);
        
        if ($attribute === 'color') {
            $options = array_map(fn($c) => StoreType::getColorName($c), $options);
        }

        return "الخيارات المتوفرة لـ {$attrName}: " . implode('، ', $options);
    }
}
