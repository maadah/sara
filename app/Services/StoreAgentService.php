<?php

namespace App\Services;

use App\Models\AiChatSession;
use App\Models\AiSetting;
use App\Models\Category;
use App\Models\Lead;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Store Agent Service - Full access to store data
 * 
 * This service acts as an intelligent agent that can:
 * - Access all store information (products, categories, orders)
 * - Search and filter products
 * - Lookup orders by phone, name, or order number
 * - Get delivery info, return policies, working hours
 * - Make smart decisions to reduce token usage
 * 
 * TOKEN OPTIMIZATION STRATEGY:
 * 1. Simple queries (greetings, thanks, prices) are handled locally without AI
 * 2. Complex queries use AI with minimal context
 * 3. Product searches use database, not AI
 * 4. Order lookups are done via database queries
 */
class StoreAgentService
{
    protected User $user;
    protected ?AiSetting $settings;
    
    // Cache for expensive queries
    protected ?array $categoriesCache = null;
    protected ?int $productCountCache = null;
    
    public function __construct(User $user)
    {
        $this->user = $user;
        $this->settings = $user->aiSetting;
    }
    
    // ==================== STORE INFO ====================
    
    /**
     * Get complete store profile
     */
    public function getStoreProfile(): array
    {
        return [
            'name' => $this->user->name,
            'description' => $this->settings->store_description ?? '',
            'delivery_info' => $this->getDeliveryInfo(),
            'return_policy' => $this->getReturnPolicy(),
            'working_hours' => $this->getWorkingHours(),
            'payment_methods' => $this->getPaymentMethods(),
            'contact_info' => $this->getContactInfo(),
            'total_products' => $this->getTotalProductCount(),
            'total_categories' => count($this->getCategories()),
        ];
    }
    
    /**
     * Get delivery information
     */
    public function getDeliveryInfo(): array
    {
        return [
            'cost' => $this->settings->delivery_cost ?? 5000,
            'time' => $this->settings->delivery_time ?? 'نفس اليوم',
            'areas' => $this->settings->delivery_areas ?? 'جميع مناطق العراق',
            'free_above' => $this->settings->free_delivery_above ?? null,
            'description' => $this->settings->delivery_info ?? 'توصيل سريع لجميع المناطق',
        ];
    }
    
    /**
     * Get return policy
     */
    public function getReturnPolicy(): array
    {
        return [
            'allowed' => $this->settings->allow_returns ?? true,
            'days' => $this->settings->return_days ?? 7,
            'conditions' => $this->settings->return_conditions ?? 'المنتج يجب ان يكون بحالته الأصلية',
            'description' => $this->settings->return_policy ?? 'استرجاع خلال 7 أيام',
        ];
    }
    
    /**
     * Get working hours
     */
    public function getWorkingHours(): array
    {
        return [
            'hours' => $this->settings->working_hours ?? '9:00 صباحاً - 10:00 مساءً',
            'days' => $this->settings->working_days ?? 'كل يوم',
            'holidays' => $this->settings->holidays ?? 'الجمعة',
        ];
    }
    
    /**
     * Get payment methods
     */
    public function getPaymentMethods(): array
    {
        $methods = [];
        
        if ($this->settings->accept_cash ?? true) {
            $methods[] = 'نقد عند الاستلام';
        }
        if ($this->settings->accept_card ?? false) {
            $methods[] = 'بطاقة ائتمان';
        }
        if ($this->settings->accept_zain_cash ?? false) {
            $methods[] = 'زين كاش';
        }
        if ($this->settings->accept_qi_card ?? false) {
            $methods[] = 'كي كارد';
        }
        
        return empty($methods) ? ['نقد عند الاستلام'] : $methods;
    }
    
    /**
     * Get contact info
     */
    public function getContactInfo(): array
    {
        return [
            'phone' => $this->settings->contact_phone ?? $this->user->phone ?? '',
            'whatsapp' => $this->settings->whatsapp ?? '',
            'instagram' => $this->settings->instagram ?? '',
            'facebook' => $this->settings->facebook ?? '',
        ];
    }
    
    // ==================== CATEGORIES ====================
    
    /**
     * Get all categories with product counts
     */
    public function getCategories(): array
    {
        if ($this->categoriesCache !== null) {
            return $this->categoriesCache;
        }
        
        $this->categoriesCache = Category::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->withCount(['products' => function($q) {
                $q->where('is_active', true)->where('quantity', '>', 0);
            }])
            ->orderBy('sort_order')
            ->get()
            ->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description,
                'products_count' => $cat->products_count,
            ])
            ->toArray();
            
        return $this->categoriesCache;
    }
    
    /**
     * Find category by name (fuzzy match)
     */
    public function findCategory(string $name): ?array
    {
        $normalized = $this->normalize($name);
        $categories = $this->getCategories();
        
        foreach ($categories as $category) {
            $catNormalized = $this->normalize($category['name']);
            
            // Exact match
            if ($catNormalized === $normalized) {
                return $category;
            }
            
            // Partial match
            if (mb_stripos($catNormalized, $normalized) !== false || 
                mb_stripos($normalized, $catNormalized) !== false) {
                return $category;
            }
        }
        
        return null;
    }
    
    // ==================== PRODUCTS ====================
    
    /**
     * Get total product count
     */
    public function getTotalProductCount(): int
    {
        if ($this->productCountCache !== null) {
            return $this->productCountCache;
        }
        
        $this->productCountCache = Product::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->count();
            
        return $this->productCountCache;
    }
    
    /**
     * Get products in category
     */
    public function getProductsInCategory(int $categoryId, int $limit = 20): array
    {
        return Product::where('user_id', $this->user->id)
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->orderBy('quantity', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($p) => $this->formatProduct($p))
            ->toArray();
    }
    
    /**
     * Search products by keyword
     */
    public function searchProducts(string $query, ?int $categoryId = null, int $limit = 10): array
    {
        $normalized = $this->normalize($query);
        
        $queryBuilder = Product::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0);
            
        if ($categoryId) {
            $queryBuilder->where('category_id', $categoryId);
        }
        
        // Search in name and description
        $queryBuilder->where(function($q) use ($normalized, $query) {
            $q->where('name', 'LIKE', "%{$query}%")
              ->orWhere('name', 'LIKE', "%{$normalized}%")
              ->orWhere('description', 'LIKE', "%{$query}%")
              ->orWhere('description', 'LIKE', "%{$normalized}%");
        });
        
        return $queryBuilder
            ->orderBy('quantity', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($p) => $this->formatProduct($p))
            ->toArray();
    }
    
    /**
     * Get top/popular products
     */
    public function getTopProducts(int $limit = 10, ?int $categoryId = null): array
    {
        $query = Product::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0);
            
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        
        return $query
            ->orderBy('quantity', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($p) => $this->formatProduct($p))
            ->toArray();
    }
    
    /**
     * Find product by name (exact or fuzzy)
     */
    public function findProduct(string $name): ?array
    {
        $normalized = $this->normalize($name);
        
        // Try exact match first
        $product = Product::where('user_id', $this->user->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->where('name', 'LIKE', "%{$name}%")
            ->first();
            
        if (!$product) {
            // Try normalized
            $product = Product::where('user_id', $this->user->id)
                ->where('is_active', true)
                ->where('quantity', '>', 0)
                ->whereRaw('LOWER(name) LIKE ?', ["%{$normalized}%"])
                ->first();
        }
        
        return $product ? $this->formatProduct($product) : null;
    }
    
    /**
     * Get product by ID
     */
    public function getProduct(int $id): ?array
    {
        $product = Product::where('user_id', $this->user->id)
            ->where('id', $id)
            ->first();
            
        return $product ? $this->formatProduct($product) : null;
    }
    
    /**
     * Format product for response
     */
    protected function formatProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => (int) $product->price,
            'stock' => (int) $product->quantity,
            'description' => $product->description,
            'category_id' => $product->category_id,
            'image' => $product->image,
        ];
    }
    
    // ==================== ORDERS ====================
    
    /**
     * Find orders by lead
     */
    public function getOrdersByLead(int $leadId, int $limit = 5): array
    {
        return OnlineOrder::where('user_id', $this->user->id)
            ->where('lead_id', $leadId)
            ->with(['items', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($order) => $this->formatOrder($order))
            ->toArray();
    }
    
    /**
     * Find order by phone number
     */
    public function findOrderByPhone(string $phone): ?array
    {
        // Normalize phone (remove spaces, dashes)
        $phone = preg_replace('/[\s\-]/', '', $phone);
        
        $order = OnlineOrder::where('user_id', $this->user->id)
            ->where('customer_phone', 'LIKE', "%{$phone}%")
            ->with(['items', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->first();
            
        return $order ? $this->formatOrder($order) : null;
    }
    
    /**
     * Find order by customer name
     */
    public function findOrderByName(string $name): ?array
    {
        $order = OnlineOrder::where('user_id', $this->user->id)
            ->where('customer_name', 'LIKE', "%{$name}%")
            ->with(['items', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->first();
            
        return $order ? $this->formatOrder($order) : null;
    }
    
    /**
     * Find order by order number
     */
    public function findOrderByNumber(string $orderNumber): ?array
    {
        $order = OnlineOrder::where('user_id', $this->user->id)
            ->where('order_number', $orderNumber)
            ->with(['items', 'items.product'])
            ->first();
            
        return $order ? $this->formatOrder($order) : null;
    }
    
    /**
     * Get latest order for lead
     */
    public function getLatestOrder(int $leadId): ?array
    {
        $order = OnlineOrder::where('user_id', $this->user->id)
            ->where('lead_id', $leadId)
            ->with(['items', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->first();
            
        return $order ? $this->formatOrder($order) : null;
    }
    
    /**
     * Format order for response
     */
    protected function formatOrder(OnlineOrder $order): array
    {
        $items = $order->items->map(function($item) {
            return [
                'name' => $item->product_name ?? ($item->product->name ?? 'منتج'),
                'quantity' => (int) $item->quantity,
                'price' => (int) $item->unit_price,
                'total' => (int) ($item->quantity * $item->unit_price),
            ];
        })->toArray();
        
        return [
            'order_number' => $order->order_number,
            'status' => $this->translateOrderStatus($order->status),
            'status_raw' => $order->status,
            'items' => $items,
            'total' => (int) $order->total,
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'address' => $order->customer_address,
            ],
            'created_at' => $order->created_at->format('Y-m-d H:i'),
            'notes' => $order->notes,
        ];
    }
    
    /**
     * Translate order status to Arabic
     */
    protected function translateOrderStatus(string $status): string
    {
        return match($status) {
            'pending' => 'قيد الانتظار',
            'confirmed' => 'مؤكد',
            'processing' => 'قيد التجهيز',
            'shipped' => 'تم الشحن',
            'delivered' => 'تم التوصيل',
            'cancelled' => 'ملغي',
            'refunded' => 'مسترجع',
            default => 'قيد التجهيز',
        };
    }
    
    // ==================== SMART HANDLERS (No AI Needed) ====================
    
    /**
     * Check if we can handle this query without AI (saves tokens)
     * Returns response if handled, null if AI needed
     */
    public function tryHandleLocally(string $message, AiChatSession $session): ?string
    {
        $normalized = $this->normalize($message);
        
        // Direct price query
        if ($priceResponse = $this->handlePriceQuery($normalized)) {
            return $priceResponse;
        }
        
        // Direct stock/availability query
        if ($stockResponse = $this->handleStockQuery($normalized)) {
            return $stockResponse;
        }
        
        // Simple greetings
        if ($greetingResponse = $this->handleSimpleGreeting($normalized)) {
            return $greetingResponse;
        }
        
        // Simple thanks
        if ($thanksResponse = $this->handleSimpleThanks($normalized)) {
            return $thanksResponse;
        }
        
        // Direct category count query
        if ($categoryResponse = $this->handleCategoryCountQuery($normalized)) {
            return $categoryResponse;
        }
        
        return null; // Need AI
    }
    
    /**
     * Handle simple greetings locally
     */
    protected function handleSimpleGreeting(string $normalized): ?string
    {
        $greetings = ['مرحبا', 'مرحبه', 'هلا', 'هاي', 'السلام عليكم', 'سلام'];
        
        foreach ($greetings as $greeting) {
            if ($normalized === $this->normalize($greeting)) {
                $storeName = $this->user->name;
                $categories = $this->getCategories();
                $categoryNames = array_column(array_slice($categories, 0, 3), 'name');
                
                return "اهلا وسهلا بك في {$storeName}! عندنا " . implode(' و', $categoryNames) . " وغيرها. شنو تحتاج؟";
            }
        }
        
        return null;
    }
    
    /**
     * Handle simple thanks locally
     */
    protected function handleSimpleThanks(string $normalized): ?string
    {
        $thanks = ['شكرا', 'شكراً', 'مشكور', 'تسلم'];
        
        foreach ($thanks as $thank) {
            if (mb_stripos($normalized, $this->normalize($thank)) !== false && mb_strlen($normalized) < 20) {
                return "عفوا! اذا تحتاج اي شي ثاني انا موجود.";
            }
        }
        
        return null;
    }
    
    /**
     * Handle category count queries locally
     */
    protected function handleCategoryCountQuery(string $normalized): ?string
    {
        // "كم قسم عندكم" or "شكد قسم"
        if (preg_match('/(?:كم|شكد)\s*(?:قسم|فئة|نوع)/u', $normalized)) {
            $categories = $this->getCategories();
            $count = count($categories);
            $names = array_column($categories, 'name');
            
            return "عندنا {$count} أقسام: " . implode('، ', $names);
        }
        
        // "كم منتج عندكم"
        if (preg_match('/(?:كم|شكد)\s*(?:منتج|سلعة|بضاعة)/u', $normalized)) {
            $count = $this->getTotalProductCount();
            return "عندنا {$count} منتج متوفر حاليا";
        }
        
        return null;
    }
    
    /**
     * Handle direct price queries locally
     */
    protected function handlePriceQuery(string $normalized): ?string
    {
        // Pattern: "كم سعر X" or "شكد X"
        if (preg_match('/(?:كم|شكد|شگد)\s*(?:سعر|بـ?|ب)?\s*(.+)/u', $normalized, $matches)) {
            $productName = trim($matches[1]);
            $product = $this->findProduct($productName);
            
            if ($product) {
                return "{$product['name']} سعره {$product['price']} دينار";
            }
        }
        
        return null;
    }
    
    /**
     * Handle direct stock queries locally
     */
    protected function handleStockQuery(string $normalized): ?string
    {
        // Pattern: "موجود X" or "متوفر X"
        if (preg_match('/(?:موجود|متوفر|عندكم|هل يوجد)\s*(.+)/u', $normalized, $matches)) {
            $productName = trim($matches[1]);
            $product = $this->findProduct($productName);
            
            if ($product) {
                if ($product['stock'] > 0) {
                    return "نعم، {$product['name']} متوفر. الكمية: {$product['stock']} قطعة. السعر: {$product['price']} دينار";
                } else {
                    return "للأسف {$product['name']} غير متوفر حاليا";
                }
            }
        }
        
        return null;
    }
    
    // ==================== UTILITIES ====================
    
    /**
     * Normalize Arabic text
     */
    protected function normalize(string $text): string
    {
        // Remove diacritics
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
        
        // Normalize Arabic letters
        $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
        $text = str_replace(['ة', 'ه'], 'ه', $text);
        $text = str_replace(['ى'], 'ي', $text);
        $text = str_replace(['ؤ'], 'و', $text);
        $text = str_replace(['ئ'], 'ي', $text);
        
        // Lowercase and trim
        return mb_strtolower(trim($text));
    }
    
    /**
     * Build comprehensive context for AI
     */
    public function buildAIContext(AiChatSession $session): array
    {
        $storeProfile = $this->getStoreProfile();
        $categories = $this->getCategories();
        $currentCategory = $session->store_context['current_category'] ?? null;
        $lastShownProducts = $session->store_context['last_shown_products'] ?? [];
        $cart = $session->cart ?? [];
        
        return [
            'store' => $storeProfile,
            'categories' => $categories,
            'current_category' => $currentCategory,
            'last_shown_products' => array_slice($lastShownProducts, 0, 5),
            'cart' => $cart,
            'cart_total' => $session->getCartTotal(),
        ];
    }
    
    /**
     * Format context as text for AI prompt
     */
    public function formatContextForPrompt(array $context): string
    {
        $text = "معلومات المتجر:\n";
        $text .= "- الاسم: {$context['store']['name']}\n";
        $text .= "- المنتجات: {$context['store']['total_products']} منتج في {$context['store']['total_categories']} قسم\n";
        
        // Delivery
        $delivery = $context['store']['delivery_info'];
        $text .= "- التوصيل: {$delivery['description']} ({$delivery['cost']} دينار)\n";
        
        // Return
        $return = $context['store']['return_policy'];
        $text .= "- الاستبدال: {$return['description']}\n";
        
        // Hours
        $hours = $context['store']['working_hours'];
        $text .= "- ساعات العمل: {$hours['hours']}\n";
        
        // Payment
        $payment = $context['store']['payment_methods'];
        $text .= "- طرق الدفع: " . implode('، ', $payment) . "\n";
        
        // Categories
        if (!empty($context['categories'])) {
            $catList = array_map(fn($c) => "{$c['name']} ({$c['products_count']})", $context['categories']);
            $text .= "\nالأقسام: " . implode('، ', $catList) . "\n";
        }
        
        // Current browsing context
        if (!empty($context['current_category'])) {
            $text .= "\nالزبون يتصفح: {$context['current_category']['name']}\n";
        }
        
        // Last shown products
        if (!empty($context['last_shown_products'])) {
            $names = array_column($context['last_shown_products'], 'name');
            $text .= "آخر منتجات عرضتها: " . implode('، ', $names) . "\n";
        }
        
        // Cart
        if (!empty($context['cart'])) {
            $items = array_map(fn($i) => "{$i['name']} x{$i['quantity']}", $context['cart']);
            $text .= "\nالسلة: " . implode('، ', $items) . " (المجموع: {$context['cart_total']} دينار)\n";
        }
        
        return $text;
    }
}
