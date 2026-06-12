# 🚀 Implementation Roadmap for SaaS Platform

## Overview
This roadmap provides step-by-step implementation guides for fixing all critical issues identified in the deep analysis.

---

## 📍 Phase 1: CRITICAL SECURITY FIXES (Week 1)

### Task 1.1: Secure Public API Routes

**File**: `routes/api.php`

**Current Problem**:
```php
Route::get('/products', [ProductController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);
```

**Solution**: Add authentication or scope to user
```php
// Option A: Require authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/categories', [CategoryController::class, 'index']);
});

// Option B: Public but scoped (for storefront embeds)
Route::get('/store/{userId}/products', [ProductController::class, 'publicIndex']);
Route::get('/store/{userId}/categories', [CategoryController::class, 'publicIndex']);
```

---

### Task 1.2: Protect/Remove Test Routes

**File**: `routes/api.php`

**Solution**:
```php
// Wrap test routes in environment check
if (app()->environment('local', 'testing')) {
    Route::prefix('ai-test')->group(function () {
        Route::post('/chat', [AiChatTestController::class, 'chat']);
        Route::get('/scenario/{id}', [AiChatTestController::class, 'runScenario']);
        Route::get('/full-test', [AiChatTestController::class, 'fullTest']);
        Route::get('/scenarios', [AiChatTestController::class, 'listScenarios']);
    });
}
```

---

### Task 1.3: Use AiSetting.system_instruction (Remove Hardcoded Prompts)

**File**: `app/Services/GroqChatService.php`

**Current Problem** (appears 20+ times):
```php
$systemPrompt = "انت مساعد مبيعات عراقي ودود ومحترف...";
```

**Solution**: Create prompt builder that uses AiSetting
```php
// New file: app/Services/AiPromptBuilder.php
<?php

namespace App\Services;

use App\Models\AiSetting;

class AiPromptBuilder
{
    private AiSetting $settings;
    private array $dialectTemplates = [
        'ar-iq' => [
            'expressions' => ['عيوني', 'أكيد', 'يمعود', 'شكو'],
            'greeting' => 'هلا وغلا',
            'confirmation' => 'تمام',
        ],
        'ar-eg' => [
            'expressions' => ['يا باشا', 'تمام', 'حاضر', 'ماشي'],
            'greeting' => 'أهلاً وسهلاً',
            'confirmation' => 'حاضر',
        ],
        'ar-sa' => [
            'expressions' => ['يالغالي', 'إن شاء الله', 'تكرم'],
            'greeting' => 'حياك الله',
            'confirmation' => 'تم',
        ],
        'en' => [
            'expressions' => ['sure', 'absolutely', 'of course'],
            'greeting' => 'Hello',
            'confirmation' => 'Done',
        ],
    ];

    public function __construct(int $userId)
    {
        $this->settings = AiSetting::where('user_id', $userId)->first() 
            ?? $this->getDefaultSettings();
    }

    public function buildSystemPrompt(): string
    {
        // Use custom instruction if provided
        if (!empty($this->settings->system_instruction)) {
            return $this->settings->system_instruction;
        }

        // Build from template
        $dialect = $this->settings->dialect ?? 'ar-iq';
        $template = $this->dialectTemplates[$dialect] ?? $this->dialectTemplates['ar-iq'];
        
        return $this->buildFromTemplate($template);
    }

    public function buildSalesPrompt(array $products, array $categories): string
    {
        $basePrompt = $this->buildSystemPrompt();
        
        return $basePrompt . "\n\n" . 
            "المنتجات المتاحة:\n" . $this->formatProducts($products) . "\n\n" .
            "الأقسام:\n" . $this->formatCategories($categories);
    }

    private function buildFromTemplate(array $template): string
    {
        $storeName = $this->settings->store_name ?? 'المتجر';
        $storeDesc = $this->settings->store_description ?? '';
        
        return "أنت مساعد مبيعات ودود ومحترف لمتجر {$storeName}. " .
            ($storeDesc ? "وصف المتجر: {$storeDesc}. " : "") .
            "استخدم التعابير التالية بشكل طبيعي: " . 
            implode(', ', $template['expressions']) . ". " .
            "رد بـ '{$template['greeting']}' للتحية.";
    }

    private function formatProducts(array $products): string
    {
        return collect($products)->map(fn($p) => 
            "- {$p->name}: {$p->price} ({$p->category->name ?? 'عام'})"
        )->implode("\n");
    }

    private function formatCategories(array $categories): string
    {
        return collect($categories)->pluck('name')->implode(', ');
    }

    private function getDefaultSettings(): AiSetting
    {
        return new AiSetting([
            'dialect' => 'ar-iq',
            'store_name' => 'المتجر',
        ]);
    }
}
```

---

### Task 1.4: Add Session Cleanup Scheduled Task

**File**: `app/Console/Kernel.php`

**Add to schedule method**:
```php
protected function schedule(Schedule $schedule)
{
    // Clean up old conversation sessions (older than 30 days)
    $schedule->call(function () {
        \App\Models\ConversationSession::where('updated_at', '<', now()->subDays(30))
            ->delete();
        
        \Log::info('Cleaned up old conversation sessions');
    })->daily()->at('03:00');

    // Archive completed orders older than 90 days
    $schedule->call(function () {
        // Move to archive table or mark as archived
        \App\Models\OnlineOrder::where('status', 'delivered')
            ->where('updated_at', '<', now()->subDays(90))
            ->update(['archived' => true]);
    })->weekly();

    // Generate daily analytics summary
    $schedule->command('analytics:daily-summary')
        ->dailyAt('23:55');
}
```

---

## 📍 Phase 2: STORE TYPE SYSTEM (Week 2-3)

### Task 2.1: Database Migrations

**Create new migration**:
```php
// database/migrations/2024_xx_xx_create_store_types_table.php
public function up()
{
    Schema::create('store_types', function (Blueprint $table) {
        $table->id();
        $table->string('name', 50);           // clothing, electronics, food
        $table->string('display_name', 100);  // متجر ملابس
        $table->json('required_attributes');  // ['size', 'color']
        $table->json('optional_attributes');  // ['material', 'brand']
        $table->text('ai_template')->nullable();
        $table->text('order_flow_config')->nullable();
        $table->boolean('requires_stock')->default(false);
        $table->timestamps();
    });

    Schema::create('product_attributes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('product_id')->constrained()->onDelete('cascade');
        $table->string('attribute_key', 50);
        $table->string('attribute_value', 255);
        $table->decimal('price_modifier', 10, 2)->default(0);
        $table->integer('stock_quantity')->nullable();
        $table->timestamps();

        $table->index(['product_id', 'attribute_key']);
    });

    Schema::table('users', function (Blueprint $table) {
        $table->foreignId('store_type_id')->nullable()->constrained();
        $table->string('preferred_dialect', 10)->default('ar-iq');
        $table->string('timezone')->default('Asia/Baghdad');
    });
}
```

### Task 2.2: Seed Store Types

```php
// database/seeders/StoreTypeSeeder.php
public function run()
{
    $storeTypes = [
        [
            'name' => 'clothing',
            'display_name' => 'متجر ملابس',
            'required_attributes' => json_encode(['size', 'color']),
            'optional_attributes' => json_encode(['material', 'brand']),
            'ai_template' => 'عند طلب ملابس، اسأل عن المقاس واللون قبل إتمام الطلب.',
            'requires_stock' => true,
        ],
        [
            'name' => 'electronics',
            'display_name' => 'متجر إلكترونيات',
            'required_attributes' => json_encode([]),
            'optional_attributes' => json_encode(['warranty', 'model', 'specs']),
            'ai_template' => 'قدم معلومات عن المواصفات والضمان.',
            'requires_stock' => true,
        ],
        [
            'name' => 'food',
            'display_name' => 'مطعم / طعام',
            'required_attributes' => json_encode([]),
            'optional_attributes' => json_encode(['ingredients', 'allergens', 'spice_level']),
            'ai_template' => 'اسأل عن الحساسية الغذائية. وضح وقت التحضير.',
            'requires_stock' => false,
        ],
        [
            'name' => 'cosmetics',
            'display_name' => 'متجر مستحضرات تجميل',
            'required_attributes' => json_encode([]),
            'optional_attributes' => json_encode(['skin_type', 'ingredients']),
            'ai_template' => 'اسأل عن نوع البشرة للمنتجات المناسبة.',
            'requires_stock' => true,
        ],
        [
            'name' => 'medical',
            'display_name' => 'صيدلية / مستلزمات طبية',
            'required_attributes' => json_encode([]),
            'optional_attributes' => json_encode(['prescription_required', 'dosage']),
            'ai_template' => 'تنبيه: لا تقدم نصائح طبية. وجه العميل للطبيب عند الحاجة.',
            'requires_stock' => true,
        ],
        [
            'name' => 'services',
            'display_name' => 'خدمات',
            'required_attributes' => json_encode(['datetime']),
            'optional_attributes' => json_encode(['duration', 'location']),
            'ai_template' => 'اسأل عن الموعد المناسب وتأكد من التوفر.',
            'requires_stock' => false,
        ],
        [
            'name' => 'general',
            'display_name' => 'متجر عام',
            'required_attributes' => json_encode([]),
            'optional_attributes' => json_encode([]),
            'ai_template' => '',
            'requires_stock' => false,
        ],
    ];

    foreach ($storeTypes as $type) {
        \App\Models\StoreType::create($type);
    }
}
```

### Task 2.3: Product Attributes Service

```php
// app/Services/ProductAttributeService.php
<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductAttribute;

class ProductAttributeService
{
    public function getAvailableOptions(Product $product): array
    {
        $attributes = ProductAttribute::where('product_id', $product->id)
            ->get()
            ->groupBy('attribute_key');

        return $attributes->map(fn($group) => 
            $group->pluck('attribute_value')->unique()->values()
        )->toArray();
    }

    public function checkAvailability(Product $product, array $selectedOptions): array
    {
        $available = true;
        $messages = [];

        foreach ($selectedOptions as $key => $value) {
            $attribute = ProductAttribute::where('product_id', $product->id)
                ->where('attribute_key', $key)
                ->where('attribute_value', $value)
                ->first();

            if (!$attribute) {
                $available = false;
                $messages[] = "الخيار {$value} غير متوفر لـ {$key}";
            } elseif ($attribute->stock_quantity !== null && $attribute->stock_quantity <= 0) {
                $available = false;
                $messages[] = "{$key}: {$value} نفذ من المخزون";
            }
        }

        return [
            'available' => $available,
            'messages' => $messages,
        ];
    }

    public function getMissingRequiredAttributes(Product $product, array $providedAttributes): array
    {
        $storeType = $product->user->storeType;
        if (!$storeType) return [];

        $required = json_decode($storeType->required_attributes, true) ?? [];
        $provided = array_keys($providedAttributes);

        return array_diff($required, $provided);
    }

    public function calculateFinalPrice(Product $product, array $selectedOptions): float
    {
        $basePrice = $product->price;
        $modifier = 0;

        foreach ($selectedOptions as $key => $value) {
            $attribute = ProductAttribute::where('product_id', $product->id)
                ->where('attribute_key', $key)
                ->where('attribute_value', $value)
                ->first();

            if ($attribute) {
                $modifier += $attribute->price_modifier;
            }
        }

        return $basePrice + $modifier;
    }
}
```

---

## 📍 Phase 3: ENHANCED AI HANDLING (Week 3-4)

### Task 3.1: Smart Attribute Detection in Chat

**Update GroqChatService.php** to detect attributes:

```php
// Add to GroqChatService.php
private function extractProductAttributes(string $message): array
{
    $attributes = [];

    // Size detection (Arabic)
    $sizePatterns = [
        'سمول|صغير|small|s' => 'S',
        'ميديم|وسط|medium|m' => 'M',
        'لارج|كبير|large|l' => 'L',
        'اكس لارج|xl|extra large' => 'XL',
        'اكس اكس لارج|xxl' => 'XXL',
    ];

    foreach ($sizePatterns as $pattern => $size) {
        if (preg_match("/($pattern)/iu", $message)) {
            $attributes['size'] = $size;
            break;
        }
    }

    // Color detection (Arabic)
    $colorPatterns = [
        'أحمر|احمر|red' => 'red',
        'أزرق|ازرق|blue' => 'blue',
        'أخضر|اخضر|green' => 'green',
        'أسود|اسود|black' => 'black',
        'أبيض|ابيض|white' => 'white',
        'أصفر|اصفر|yellow' => 'yellow',
        'وردي|pink' => 'pink',
        'بني|brown' => 'brown',
        'رمادي|gray|grey' => 'gray',
    ];

    foreach ($colorPatterns as $pattern => $color) {
        if (preg_match("/($pattern)/iu", $message)) {
            $attributes['color'] = $color;
            break;
        }
    }

    return $attributes;
}

private function askForMissingAttributes(Product $product, array $currentAttributes): ?string
{
    $service = new ProductAttributeService();
    $missing = $service->getMissingRequiredAttributes($product, $currentAttributes);

    if (empty($missing)) {
        return null;
    }

    $questions = [
        'size' => 'شنو المقاس المطلوب؟ (S, M, L, XL)',
        'color' => 'شنو اللون المطلوب؟',
        'datetime' => 'شنو الوقت المناسب؟',
    ];

    $missingQuestion = $questions[$missing[0]] ?? "شنو {$missing[0]} المطلوب؟";
    
    return $missingQuestion;
}
```

### Task 3.2: Synonym Matching for Products

```php
// app/Services/ProductSynonymService.php
<?php

namespace App\Services;

class ProductSynonymService
{
    private array $synonyms = [
        // Clothing
        'قميص' => ['تيشيرت', 'تيشرت', 'بلوزة', 'shirt', 't-shirt'],
        'بنطلون' => ['بنطال', 'جينز', 'سروال', 'pants', 'jeans'],
        'فستان' => ['دريس', 'dress'],
        
        // Electronics
        'موبايل' => ['تلفون', 'جوال', 'هاتف', 'phone', 'mobile'],
        'لابتوب' => ['حاسوب', 'كمبيوتر', 'laptop', 'computer'],
        'سماعة' => ['سماعات', 'هيدفون', 'headphones', 'earbuds'],
        
        // General
        'حقيبة' => ['شنطة', 'bag'],
        'نظارة' => ['نظارات', 'glasses'],
    ];

    public function expandQuery(string $query): array
    {
        $expanded = [$query];

        foreach ($this->synonyms as $main => $alts) {
            if (mb_stripos($query, $main) !== false) {
                $expanded = array_merge($expanded, $alts);
            }
            foreach ($alts as $alt) {
                if (mb_stripos($query, $alt) !== false) {
                    $expanded[] = $main;
                    $expanded = array_merge($expanded, $alts);
                    break;
                }
            }
        }

        return array_unique($expanded);
    }

    public function findBestMatch(string $query, array $products): ?object
    {
        $expandedQueries = $this->expandQuery($query);

        foreach ($products as $product) {
            foreach ($expandedQueries as $q) {
                if (mb_stripos($product->name, $q) !== false) {
                    return $product;
                }
            }
        }

        return null;
    }
}
```

---

## 📍 Phase 4: AUTHORIZATION & POLICIES (Week 4)

### Task 4.1: Create Missing Policies

```bash
php artisan make:policy ProductPolicy --model=Product
php artisan make:policy CategoryPolicy --model=Category
php artisan make:policy AiSettingPolicy --model=AiSetting
php artisan make:policy ConversationSessionPolicy --model=ConversationSession
php artisan make:policy SocialAccountPolicy --model=SocialAccount
```

### Task 4.2: Implement ProductPolicy

```php
// app/Policies/ProductPolicy.php
<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Can view their own products
    }

    public function view(User $user, Product $product): bool
    {
        return $user->id === $product->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Product $product): bool
    {
        return $user->id === $product->user_id;
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->id === $product->user_id;
    }
}
```

### Task 4.3: Register Policies

```php
// app/Providers/AuthServiceProvider.php
protected $policies = [
    Product::class => ProductPolicy::class,
    Category::class => CategoryPolicy::class,
    AiSetting::class => AiSettingPolicy::class,
    ConversationSession::class => ConversationSessionPolicy::class,
    SocialAccount::class => SocialAccountPolicy::class,
    OnlineOrder::class => OnlineOrderPolicy::class,
    Lead::class => LeadPolicy::class,
];
```

---

## 📍 Phase 5: ANALYTICS ENHANCEMENTS (Month 2)

### Task 5.1: Add Analytics Events Tracking

```php
// app/Services/AnalyticsService.php
<?php

namespace App\Services;

use App\Models\AnalyticsEvent;

class AnalyticsService
{
    public function track(int $userId, string $event, array $data = []): void
    {
        AnalyticsEvent::create([
            'user_id' => $userId,
            'event_type' => $event,
            'event_data' => json_encode($data),
            'occurred_at' => now(),
        ]);
    }

    // Event types to track:
    // - conversation_started
    // - product_viewed
    // - product_added_to_cart
    // - order_created
    // - order_completed
    // - ai_response_sent
    // - manual_takeover
    // - cart_abandoned

    public function getConversionRate(int $userId, string $period = 'day'): float
    {
        $conversations = $this->getCount($userId, 'conversation_started', $period);
        $orders = $this->getCount($userId, 'order_created', $period);

        return $conversations > 0 ? ($orders / $conversations) * 100 : 0;
    }

    public function getPopularProducts(int $userId, int $limit = 10): array
    {
        return AnalyticsEvent::where('user_id', $userId)
            ->where('event_type', 'product_viewed')
            ->selectRaw('JSON_EXTRACT(event_data, "$.product_id") as product_id, COUNT(*) as views')
            ->groupBy('product_id')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
```

---

## 📋 Implementation Checklist

### Week 1
- [ ] Secure public API routes
- [ ] Protect test routes in production
- [ ] Create AiPromptBuilder service
- [ ] Update GroqChatService to use AiPromptBuilder
- [ ] Add session cleanup scheduled task
- [ ] Test all changes

### Week 2
- [ ] Create store_types migration
- [ ] Create product_attributes migration
- [ ] Run migrations
- [ ] Seed store types
- [ ] Create StoreType model
- [ ] Create ProductAttribute model

### Week 3
- [ ] Create ProductAttributeService
- [ ] Update product creation UI for attributes
- [ ] Implement attribute detection in chat
- [ ] Test attribute flow

### Week 4
- [ ] Create all missing policies
- [ ] Register policies
- [ ] Add policy checks to controllers
- [ ] Audit all routes for authorization

### Month 2
- [ ] Add analytics tracking
- [ ] Create analytics dashboard widgets
- [ ] Implement conversion rate tracking
- [ ] Add product popularity tracking
- [ ] Create reporting exports

---

## 🎯 Success Criteria

After implementation:

1. **Security**: All API routes properly authenticated
2. **AI Flexibility**: Stores can customize AI personality and language
3. **Store Types**: Different store types handled appropriately
4. **Test Pass Rate**: Target 85%+ (up from 62.7%)
5. **Performance**: Session cleanup running, no database bloat
6. **Authorization**: All resources properly policy-protected

---

*Roadmap Version: 1.0*
*Estimated Total Time: 6-8 weeks*
