<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * StoreAssistantManager - Manages OpenAI Assistants for each store
 *
 * Each store gets:
 * 1. A dedicated Assistant with custom instructions
 * 2. A Vector Store containing all products for semantic search
 * 3. Function calling capabilities for cart/order operations
 */
class StoreAssistantManager
{
    private string $apiKey;
    private string $baseUrl = 'https://api.openai.com/v1';

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.openai.api_key', '');
    }

    /**
     * Set API key for a specific store
     */
    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * Create or update Assistant for a store
     */
    public function syncStoreAssistant(User $store): array
    {
        $aiSettings = $store->aiSetting;

        if (!$aiSettings) {
            return ['success' => false, 'error' => 'AI settings not found'];
        }

        // Use store's OpenAI key or global key
        $apiKey = $aiSettings->openai_api_key ?: $this->apiKey;

        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'OpenAI API key not configured'];
        }

        $this->setApiKey($apiKey);

        try {
            // Step 1: Create/Update Vector Store with products
            $vectorStoreResult = $this->syncVectorStore($store);

            if (!$vectorStoreResult['success']) {
                return $vectorStoreResult;
            }

            // Step 2: Create/Update Assistant
            $assistantResult = $this->syncAssistant($store, $vectorStoreResult['vector_store_id']);

            if (!$assistantResult['success']) {
                return $assistantResult;
            }

            // Update AI settings with IDs
            $aiSettings->update([
                'openai_assistant_id' => $assistantResult['assistant_id'],
                'openai_vector_store_id' => $vectorStoreResult['vector_store_id'],
                'openai_file_id' => $vectorStoreResult['file_id'] ?? null,
                'assistant_synced_at' => now(),
            ]);

            Log::info("Store assistant synced", [
                'store_id' => $store->id,
                'assistant_id' => $assistantResult['assistant_id'],
                'vector_store_id' => $vectorStoreResult['vector_store_id'],
            ]);

            return [
                'success' => true,
                'assistant_id' => $assistantResult['assistant_id'],
                'vector_store_id' => $vectorStoreResult['vector_store_id'],
                'products_count' => $vectorStoreResult['products_count'] ?? 0,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to sync store assistant", [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync Vector Store with products
     */
    private function syncVectorStore(User $store): array
    {
        $aiSettings = $store->aiSetting;
        $vectorStoreId = $aiSettings->openai_vector_store_id;

        // Get all products for this store
        $products = Product::where('user_id', $store->id)
            ->where('is_active', true)
            ->with(['category', 'images'])
            ->get();

        // Create products JSON file
        $productsData = $this->formatProductsForVectorStore($products, $store);
        $jsonContent = json_encode($productsData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Create vector store if doesn't exist
        if (!$vectorStoreId) {
            $createResponse = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/vector_stores", [
                    'name' => "store_{$store->id}_products",
                    'metadata' => [
                        'store_id' => (string) $store->id,
                        'store_name' => $store->name,
                    ],
                ]);

            if (!$createResponse->successful()) {
                return ['success' => false, 'error' => 'Failed to create vector store: ' . $createResponse->body()];
            }

            $vectorStoreId = $createResponse->json('id');
        }

        // Delete old file if exists
        if ($aiSettings->openai_file_id) {
            try {
                Http::withHeaders($this->getHeaders())
                    ->delete("{$this->baseUrl}/files/{$aiSettings->openai_file_id}");
            } catch (\Exception $e) {
                // Ignore errors when deleting old file
            }
        }

        // Upload new products file
        $tempFile = tempnam(sys_get_temp_dir(), 'products_') . '.json';
        file_put_contents($tempFile, $jsonContent);

        $uploadResponse = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->attach('file', file_get_contents($tempFile), "store_{$store->id}_products.json")
          ->post("{$this->baseUrl}/files", [
              'purpose' => 'assistants',
          ]);

        unlink($tempFile);

        if (!$uploadResponse->successful()) {
            return ['success' => false, 'error' => 'Failed to upload file: ' . $uploadResponse->body()];
        }

        $fileId = $uploadResponse->json('id');

        // Add file to vector store
        $addFileResponse = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/vector_stores/{$vectorStoreId}/files", [
                'file_id' => $fileId,
            ]);

        if (!$addFileResponse->successful()) {
            return ['success' => false, 'error' => 'Failed to add file to vector store: ' . $addFileResponse->body()];
        }

        return [
            'success' => true,
            'vector_store_id' => $vectorStoreId,
            'file_id' => $fileId,
            'products_count' => $products->count(),
        ];
    }

    /**
     * Format products for vector store
     */
    private function formatProductsForVectorStore($products, User $store): array
    {
        $aiSettings = $store->aiSetting;

        $data = [
            'store_info' => [
                'name' => $store->name,
                'description' => $aiSettings->store_description ?? '',
                'policies' => $aiSettings->store_policies ?? '',
                'delivery_time' => $aiSettings->delivery_time ?? '',
                'delivery_cost' => $aiSettings->delivery_cost ?? 0,
                'working_hours' => $aiSettings->working_hours ?? '',
                'greeting_message' => $aiSettings->greeting_message ?? '',
            ],
            'categories' => [],
            'products' => [],
            'total_products' => $products->count(),
            'last_updated' => now()->toIso8601String(),
        ];

        // Get categories
        $categories = Category::where('user_id', $store->id)->get();
        foreach ($categories as $category) {
            $categoryProducts = $products->where('category_id', $category->id);
            $data['categories'][] = [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description ?? '',
                'products_count' => $categoryProducts->count(),
                'available_products_count' => $categoryProducts->filter(fn($p) => ($p->quantity ?? 0) > 0)->count(),
            ];
        }

        // Format products with ALL details
        foreach ($products as $product) {
            $productData = [
                // Basic Info
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description ?? '',

                // Pricing
                'price' => (float) $product->price,
                'price_formatted' => number_format($product->price, 0) . ' ' . ($product->currency ?? 'IQD'),
                'currency' => $product->currency ?? 'IQD',

                // Stock & Availability
                'quantity' => (int) ($product->quantity ?? 0),
                'reserved_quantity' => (int) ($product->reserved_quantity ?? 0),
                'available_quantity' => max(0, (int) ($product->quantity ?? 0) - (int) ($product->reserved_quantity ?? 0)),
                'is_available' => ($product->quantity ?? 0) > ($product->reserved_quantity ?? 0),
                'is_active' => (bool) ($product->is_active ?? true),

                // Units
                'unit' => $product->unit ?? 'piece',
                'sell_unit' => $product->sell_unit ?? $product->unit ?? 'piece',
                'conversion_factor' => (float) ($product->conversion_factor ?? 1),

                // Category
                'category' => $product->category ? $product->category->name : 'غير مصنف',
                'category_id' => $product->category_id,

                // Dates
                'expiry_date' => $product->expiry_date ? $product->expiry_date->format('Y-m-d') : null,
                'is_expired' => $product->expiry_date ? $product->expiry_date->isPast() : false,

                // Search keywords (for better matching)
                'keywords' => $this->generateProductKeywords($product),
            ];

            // Add all images
            $productData['images'] = [];
            foreach ($product->images as $index => $image) {
                $productData['images'][] = [
                    'url' => asset('storage/' . $image->image_path),
                    'is_primary' => (bool) $image->is_primary,
                    'sort_order' => $index,
                ];
            }
            $productData['primary_image'] = $productData['images'][0]['url'] ?? null;
            $productData['has_images'] = count($productData['images']) > 0;

            // Add attributes if available
            if (method_exists($product, 'attributes') && $product->attributes->isNotEmpty()) {
                $productData['attributes'] = [];
                foreach ($product->attributes as $attr) {
                    $productData['attributes'][] = [
                        'key' => $attr->key,
                        'value' => $attr->value,
                        'price_modifier' => $attr->price_modifier ?? 0,
                        'available' => $attr->is_available ?? true,
                    ];
                }
            }

            $data['products'][] = $productData;
        }

        return $data;
    }

    /**
     * Generate search keywords for better product matching
     */
    private function generateProductKeywords(Product $product): string
    {
        $keywords = [];

        // Add name and description words
        $keywords[] = $product->name;
        if ($product->description) {
            $keywords[] = $product->description;
        }

        // Add category
        if ($product->category) {
            $keywords[] = $product->category->name;
        }

        // Add common Arabic variations
        $name = $product->name;
        // Add without "ال" prefix
        $keywords[] = preg_replace('/^ال/', '', $name);

        return implode(' ', $keywords);
    }

    /**
     * Sync Assistant
     */
    private function syncAssistant(User $store, string $vectorStoreId): array
    {
        $aiSettings = $store->aiSetting;
        $assistantId = $aiSettings->openai_assistant_id;

        $assistantConfig = [
            'name' => "مساعد متجر {$store->name}",
            'instructions' => $this->buildAssistantInstructions($store),
            'model' => $aiSettings->openai_model ?: 'gpt-4.1-mini',
            'tools' => $this->getAssistantTools(),
            'tool_resources' => [
                'file_search' => [
                    'vector_store_ids' => [$vectorStoreId],
                ],
            ],
            'metadata' => [
                'store_id' => (string) $store->id,
            ],
        ];

        if ($assistantId) {
            // Update existing assistant
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/assistants/{$assistantId}", $assistantConfig);
        } else {
            // Create new assistant
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/assistants", $assistantConfig);
        }

        if (!$response->successful()) {
            return ['success' => false, 'error' => 'Failed to sync assistant: ' . $response->body()];
        }

        return [
            'success' => true,
            'assistant_id' => $response->json('id'),
        ];
    }

    /**
     * Build assistant instructions
     */
    private function buildAssistantInstructions(User $store): string
    {
        $aiSettings = $store->aiSetting;

        $customInstruction = $aiSettings->system_instruction ?? '';

        $baseInstruction = <<<INSTRUCTIONS
أنت مساعد مبيعات ذكي لمتجر "{$store->name}".

## معلومات المتجر:
- الوصف: {$aiSettings->store_description}
- السياسات: {$aiSettings->store_policies}
- وقت التوصيل: {$aiSettings->delivery_time}
- تكلفة التوصيل: {$aiSettings->delivery_cost}

## قواعد مهمة:
1. تحدث بلهجة عراقية ودية ومختصرة
2. استخدم file_search للبحث عن المنتجات قبل الرد
3. عند سؤال الزبون عن منتج، ابحث في الملفات وأعطه النتائج
4. إذا طلب الزبون فئة معينة، اعرض المنتجات المتوفرة في تلك الفئة
5. جمع معلومات الطلب: الاسم، رقم الهاتف، العنوان
6. استخدم الدوال المتاحة لإضافة للسلة وتأكيد الطلب
7. لا تخترع منتجات - اعتمد فقط على البيانات من file_search
8. عند عرض منتج، اذكر: الاسم، السعر، الوصف، التوفر
9. كن مختصراً ولطيفاً

## عبارات مقترحة:
- "هلا بيك! شنو تحتاج؟"
- "عدنا [المنتج] بسعر [السعر] دينار"
- "صار تدلل، نحتاج اسمك ورقمك وعنوانك"
- "تم تأكيد طلبك، راح نوصله لك"

{$customInstruction}
INSTRUCTIONS;

        return $baseInstruction;
    }

    /**
     * Get assistant tools (functions)
     */
    private function getAssistantTools(): array
    {
        return [
            ['type' => 'file_search'],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'البحث عن منتجات في المتجر بالاسم أو الفئة أو الكلمات المفتاحية. استخدم هذه الدالة عندما يسأل الزبون عن منتج أو فئة معينة.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'كلمة البحث أو اسم المنتج أو الفئة بالعربي',
                            ],
                            'category' => [
                                'type' => 'string',
                                'description' => 'اسم الفئة للتصفية (اختياري)',
                            ],
                            'max_results' => [
                                'type' => 'integer',
                                'description' => 'الحد الأقصى للنتائج (افتراضي 5)',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_product_details',
                    'description' => 'الحصول على تفاصيل منتج معين بما في ذلك السعر والكمية والوصف والصور',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'رقم المنتج',
                            ],
                            'product_name' => [
                                'type' => 'string',
                                'description' => 'اسم المنتج إذا كان الرقم غير معروف',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'add_to_cart',
                    'description' => 'إضافة منتج إلى سلة التسوق',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'رقم المنتج',
                            ],
                            'product_name' => [
                                'type' => 'string',
                                'description' => 'اسم المنتج للتأكيد',
                            ],
                            'quantity' => [
                                'type' => 'integer',
                                'description' => 'الكمية المطلوبة (افتراضي 1)',
                            ],
                            'attributes' => [
                                'type' => 'object',
                                'description' => 'خصائص المنتج مثل اللون أو الحجم',
                                'properties' => [
                                    'color' => ['type' => 'string', 'description' => 'اللون'],
                                    'size' => ['type' => 'string', 'description' => 'الحجم'],
                                ],
                            ],
                        ],
                        'required' => ['product_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_cart_item',
                    'description' => 'تعديل كمية منتج في السلة أو حذفه',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'رقم المنتج',
                            ],
                            'quantity' => [
                                'type' => 'integer',
                                'description' => 'الكمية الجديدة (0 للحذف)',
                            ],
                        ],
                        'required' => ['product_id', 'quantity'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_cart',
                    'description' => 'عرض محتويات سلة التسوق الحالية',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'include_totals' => [
                                'type' => 'boolean',
                                'description' => 'تضمين المجموع الكلي',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'clear_cart',
                    'description' => 'إفراغ سلة التسوق بالكامل',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'confirm' => [
                                'type' => 'boolean',
                                'description' => 'تأكيد الإفراغ',
                            ],
                        ],
                        'required' => ['confirm'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'collect_customer_info',
                    'description' => 'تسجيل أو تحديث معلومات الزبون للطلب',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'اسم الزبون الكامل',
                            ],
                            'phone' => [
                                'type' => 'string',
                                'description' => 'رقم الهاتف',
                            ],
                            'address' => [
                                'type' => 'string',
                                'description' => 'عنوان التوصيل الكامل',
                            ],
                            'city' => [
                                'type' => 'string',
                                'description' => 'المدينة أو المنطقة',
                            ],
                            'notes' => [
                                'type' => 'string',
                                'description' => 'ملاحظات إضافية للتوصيل',
                            ],
                        ],
                        'required' => ['name', 'phone', 'address'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_customer_info',
                    'description' => 'الحصول على معلومات الزبون المسجلة',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'check_complete' => [
                                'type' => 'boolean',
                                'description' => 'التحقق من اكتمال المعلومات',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'calculate_order_total',
                    'description' => 'حساب إجمالي الطلب شاملاً التوصيل',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'include_delivery' => [
                                'type' => 'boolean',
                                'description' => 'تضمين تكلفة التوصيل',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'confirm_order',
                    'description' => 'تأكيد الطلب النهائي وإنشائه في النظام. يجب أن تكون السلة غير فارغة ومعلومات الزبون كاملة.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'payment_method' => [
                                'type' => 'string',
                                'description' => 'طريقة الدفع (كاش عند التوصيل)',
                                'enum' => ['cash_on_delivery', 'bank_transfer', 'electronic_payment'],
                            ],
                            'notes' => [
                                'type' => 'string',
                                'description' => 'ملاحظات إضافية على الطلب',
                            ],
                            'preferred_delivery_time' => [
                                'type' => 'string',
                                'description' => 'وقت التوصيل المفضل',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'show_product_image',
                    'description' => 'عرض صورة منتج للزبون',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'رقم المنتج',
                            ],
                            'product_name' => [
                                'type' => 'string',
                                'description' => 'اسم المنتج إذا كان الرقم غير معروف',
                            ],
                            'image_index' => [
                                'type' => 'integer',
                                'description' => 'رقم الصورة (افتراضي الصورة الرئيسية)',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_categories',
                    'description' => 'الحصول على قائمة الفئات المتاحة مع عدد المنتجات',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'include_empty' => [
                                'type' => 'boolean',
                                'description' => 'تضمين الفئات الفارغة',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_store_info',
                    'description' => 'الحصول على معلومات المتجر مثل ساعات العمل والتوصيل والسياسات',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'info_type' => [
                                'type' => 'string',
                                'description' => 'نوع المعلومات المطلوبة',
                                'enum' => ['all', 'delivery', 'working_hours', 'policies', 'payment_methods'],
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'check_product_availability',
                    'description' => 'التحقق من توفر منتج معين والكمية المتاحة',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'رقم المنتج',
                            ],
                            'product_name' => [
                                'type' => 'string',
                                'description' => 'اسم المنتج',
                            ],
                            'requested_quantity' => [
                                'type' => 'integer',
                                'description' => 'الكمية المطلوبة للتحقق',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Delete store assistant and vector store
     */
    public function deleteStoreAssistant(User $store): array
    {
        $aiSettings = $store->aiSetting;

        if (!$aiSettings) {
            return ['success' => true];
        }

        $apiKey = $aiSettings->openai_api_key ?: $this->apiKey;
        $this->setApiKey($apiKey);

        try {
            // Delete assistant
            if ($aiSettings->openai_assistant_id) {
                Http::withHeaders($this->getHeaders())
                    ->delete("{$this->baseUrl}/assistants/{$aiSettings->openai_assistant_id}");
            }

            // Delete vector store
            if ($aiSettings->openai_vector_store_id) {
                Http::withHeaders($this->getHeaders())
                    ->delete("{$this->baseUrl}/vector_stores/{$aiSettings->openai_vector_store_id}");
            }

            // Delete file
            if ($aiSettings->openai_file_id) {
                Http::withHeaders($this->getHeaders())
                    ->delete("{$this->baseUrl}/files/{$aiSettings->openai_file_id}");
            }

            // Clear IDs
            $aiSettings->update([
                'openai_assistant_id' => null,
                'openai_vector_store_id' => null,
                'openai_file_id' => null,
                'assistant_synced_at' => null,
            ]);

            return ['success' => true];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get headers for API requests
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'OpenAI-Beta' => 'assistants=v2',
        ];
    }

    /**
     * Create a thread for a conversation
     */
    public function createThread(User $store): ?string
    {
        $aiSettings = $store->aiSetting;
        $apiKey = $aiSettings->openai_api_key ?: $this->apiKey;
        $this->setApiKey($apiKey);

        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/threads", [
                'metadata' => [
                    'store_id' => (string) $store->id,
                ],
            ]);

        if ($response->successful()) {
            return $response->json('id');
        }

        Log::error("Failed to create thread", ['error' => $response->body()]);
        return null;
    }

    /**
     * Send message and get response
     */
    public function chat(User $store, string $threadId, string $message, array $context = []): array
    {
        $aiSettings = $store->aiSetting;
        $apiKey = $aiSettings->openai_api_key ?: $this->apiKey;
        $this->setApiKey($apiKey);

        $assistantId = $aiSettings->openai_assistant_id;

        if (!$assistantId) {
            return ['success' => false, 'error' => 'Assistant not configured'];
        }

        try {
            // Add message to thread
            $messageResponse = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/threads/{$threadId}/messages", [
                    'role' => 'user',
                    'content' => $message,
                ]);

            if (!$messageResponse->successful()) {
                return ['success' => false, 'error' => 'Failed to add message'];
            }

            // Create run
            $runResponse = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/threads/{$threadId}/runs", [
                    'assistant_id' => $assistantId,
                    'additional_instructions' => $this->buildContextInstructions($context),
                ]);

            if (!$runResponse->successful()) {
                return ['success' => false, 'error' => 'Failed to create run'];
            }

            $runId = $runResponse->json('id');

            // Poll for completion
            $maxAttempts = 30;
            $attempt = 0;
            $functionCalls = [];

            while ($attempt < $maxAttempts) {
                sleep(1);
                $attempt++;

                $statusResponse = Http::withHeaders($this->getHeaders())
                    ->get("{$this->baseUrl}/threads/{$threadId}/runs/{$runId}");

                $status = $statusResponse->json('status');

                if ($status === 'completed') {
                    break;
                } elseif ($status === 'requires_action') {
                    // Handle function calls
                    $toolCalls = $statusResponse->json('required_action.submit_tool_outputs.tool_calls', []);
                    $toolOutputs = [];

                    foreach ($toolCalls as $toolCall) {
                        $functionName = $toolCall['function']['name'];
                        $arguments = json_decode($toolCall['function']['arguments'], true);

                        $functionCalls[] = [
                            'name' => $functionName,
                            'arguments' => $arguments,
                        ];

                        // Execute function and get result
                        $result = $this->executeFunctionCall($store, $functionName, $arguments, $context);

                        $toolOutputs[] = [
                            'tool_call_id' => $toolCall['id'],
                            'output' => json_encode($result, JSON_UNESCAPED_UNICODE),
                        ];
                    }

                    // Submit tool outputs
                    Http::withHeaders($this->getHeaders())
                        ->post("{$this->baseUrl}/threads/{$threadId}/runs/{$runId}/submit_tool_outputs", [
                            'tool_outputs' => $toolOutputs,
                        ]);

                } elseif (in_array($status, ['failed', 'cancelled', 'expired'])) {
                    return ['success' => false, 'error' => "Run {$status}"];
                }
            }

            // Get assistant's response
            $messagesResponse = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/threads/{$threadId}/messages", [
                    'limit' => 1,
                    'order' => 'desc',
                ]);

            $messages = $messagesResponse->json('data', []);
            $assistantMessage = '';

            foreach ($messages as $msg) {
                if ($msg['role'] === 'assistant') {
                    foreach ($msg['content'] as $content) {
                        if ($content['type'] === 'text') {
                            $assistantMessage = $content['text']['value'];
                            break 2;
                        }
                    }
                }
            }

            return [
                'success' => true,
                'message' => $assistantMessage,
                'function_calls' => $functionCalls,
            ];

        } catch (\Exception $e) {
            Log::error("Chat error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build context instructions
     */
    private function buildContextInstructions(array $context): string
    {
        $instructions = [];

        if (!empty($context['cart'])) {
            $cartItems = [];
            foreach ($context['cart'] as $item) {
                $cartItems[] = "- {$item['name']} x{$item['quantity']} = {$item['subtotal']} دينار";
            }
            $instructions[] = "السلة الحالية:\n" . implode("\n", $cartItems);
        }

        if (!empty($context['customer_info'])) {
            $info = $context['customer_info'];
            $instructions[] = "معلومات الزبون: الاسم: {$info['name']}, الهاتف: {$info['phone']}, العنوان: {$info['address']}";
        }

        return implode("\n\n", $instructions);
    }

    /**
     * Execute function call
     */
    private function executeFunctionCall(User $store, string $functionName, array $arguments, array $context): array
    {
        switch ($functionName) {
            case 'search_products':
                return $this->searchProducts(
                    $store,
                    $arguments['query'] ?? '',
                    $arguments['category'] ?? null,
                    $arguments['max_results'] ?? 5
                );

            case 'get_product_details':
                return $this->getProductDetails(
                    $store,
                    $arguments['product_id'] ?? null,
                    $arguments['product_name'] ?? null
                );

            case 'add_to_cart':
                return [
                    'action' => 'add_to_cart',
                    'product_id' => $arguments['product_id'],
                    'product_name' => $arguments['product_name'] ?? null,
                    'quantity' => $arguments['quantity'] ?? 1,
                    'attributes' => $arguments['attributes'] ?? [],
                ];

            case 'update_cart_item':
                return [
                    'action' => 'update_cart_item',
                    'product_id' => $arguments['product_id'],
                    'quantity' => $arguments['quantity'],
                ];

            case 'get_cart':
                $cart = $context['cart'] ?? [];
                $total = 0;
                foreach ($cart as $item) {
                    $total += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                }
                return [
                    'action' => 'get_cart',
                    'cart' => $cart,
                    'items_count' => count($cart),
                    'subtotal' => $total,
                    'delivery_cost' => $store->aiSetting->delivery_cost ?? 0,
                    'total' => $total + ($store->aiSetting->delivery_cost ?? 0),
                    'currency' => 'IQD',
                ];

            case 'clear_cart':
                return [
                    'action' => 'clear_cart',
                    'confirmed' => $arguments['confirm'] ?? false,
                ];

            case 'collect_customer_info':
                return [
                    'action' => 'collect_customer_info',
                    'info' => [
                        'name' => $arguments['name'] ?? null,
                        'phone' => $arguments['phone'] ?? null,
                        'address' => $arguments['address'] ?? null,
                        'city' => $arguments['city'] ?? null,
                        'notes' => $arguments['notes'] ?? null,
                    ],
                ];

            case 'get_customer_info':
                $customerInfo = $context['customer_info'] ?? [];
                $isComplete = !empty($customerInfo['name']) &&
                              !empty($customerInfo['phone']) &&
                              !empty($customerInfo['address']);
                return [
                    'action' => 'get_customer_info',
                    'info' => $customerInfo,
                    'is_complete' => $isComplete,
                    'missing_fields' => $this->getMissingCustomerFields($customerInfo),
                ];

            case 'calculate_order_total':
                $cart = $context['cart'] ?? [];
                $subtotal = 0;
                foreach ($cart as $item) {
                    $subtotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                }
                $deliveryCost = ($arguments['include_delivery'] ?? true) ? ($store->aiSetting->delivery_cost ?? 0) : 0;
                return [
                    'subtotal' => $subtotal,
                    'delivery_cost' => $deliveryCost,
                    'total' => $subtotal + $deliveryCost,
                    'currency' => 'IQD',
                    'formatted_total' => number_format($subtotal + $deliveryCost, 0) . ' دينار عراقي',
                ];

            case 'confirm_order':
                return [
                    'action' => 'confirm_order',
                    'payment_method' => $arguments['payment_method'] ?? 'cash_on_delivery',
                    'notes' => $arguments['notes'] ?? '',
                    'preferred_delivery_time' => $arguments['preferred_delivery_time'] ?? null,
                ];

            case 'show_product_image':
                return $this->getProductImage(
                    $store,
                    $arguments['product_id'] ?? null,
                    $arguments['product_name'] ?? null,
                    $arguments['image_index'] ?? 0
                );

            case 'get_categories':
                return $this->getCategories($store, $arguments['include_empty'] ?? false);

            case 'get_store_info':
                return $this->getStoreInfo($store, $arguments['info_type'] ?? 'all');

            case 'check_product_availability':
                return $this->checkProductAvailability(
                    $store,
                    $arguments['product_id'] ?? null,
                    $arguments['product_name'] ?? null,
                    $arguments['requested_quantity'] ?? 1
                );

            default:
                Log::warning("Unknown function called", ['function' => $functionName, 'arguments' => $arguments]);
                return ['error' => 'دالة غير معروفة: ' . $functionName];
        }
    }

    /**
     * Get missing customer fields
     */
    private function getMissingCustomerFields(array $customerInfo): array
    {
        $missing = [];
        if (empty($customerInfo['name'])) $missing[] = 'الاسم';
        if (empty($customerInfo['phone'])) $missing[] = 'رقم الهاتف';
        if (empty($customerInfo['address'])) $missing[] = 'العنوان';
        return $missing;
    }

    /**
     * Search products locally
     */
    private function searchProducts(User $store, string $query, ?string $category = null, int $maxResults = 5): array
    {
        $productsQuery = Product::where('user_id', $store->id)
            ->where('is_active', true)
            ->with(['category', 'images']);

        if ($category) {
            $productsQuery->whereHas('category', function ($q) use ($category) {
                $q->where('name', 'like', "%{$category}%");
            });
        }

        if ($query) {
            // IMPROVEMENT: Use fuzzy matching for Arabic product search
            $normalizedQuery = $this->normalizeArabicText($query);
            $queryWords = array_filter(preg_split('/\s+/u', $normalizedQuery), fn($w) => mb_strlen($w) >= 2);

            $productsQuery->where(function ($q) use ($query, $normalizedQuery, $queryWords) {
                // Direct substring match (highest priority)
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhereRaw("LOWER(name) LIKE LOWER(?)", ["%{$normalizedQuery}%"])
                  // Match if any query word is in product name
                  ->orWhere(function ($subQ) use ($queryWords) {
                      foreach ($queryWords as $word) {
                          $subQ->orWhere('name', 'like', "%{$word}%");
                      }
                  })
                  ->orWhereHas('category', function ($cq) use ($query, $normalizedQuery, $queryWords) {
                      $cq->where('name', 'like', "%{$query}%")
                         ->orWhereRaw("LOWER(name) LIKE LOWER(?)", ["%{$normalizedQuery}%"])
                         ->orWhere(function ($catQ) use ($queryWords) {
                             foreach ($queryWords as $word) {
                                 $catQ->orWhere('name', 'like', "%{$word}%");
                             }
                         });
                  });
            });
        }

        $products = $productsQuery->limit($maxResults)->get();

        $results = [];
        foreach ($products as $product) {
            $primaryImage = $product->images->where('is_primary', true)->first() ?? $product->images->first();

            $results[] = [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description ?? '',
                'price' => (float) $product->price,
                'price_formatted' => number_format($product->price, 0) . ' دينار',
                'currency' => $product->currency ?? 'IQD',
                'quantity' => (int) ($product->quantity ?? 0),
                'is_available' => ($product->quantity ?? 0) > 0,
                'category' => $product->category ? $product->category->name : 'غير مصنف',
                'image_url' => $primaryImage ? asset('storage/' . $primaryImage->image_path) : null,
            ];
        }

        // IMPROVEMENT: Sort results by relevance (better matches first)
        if ($query && !empty($results)) {
            $normalizedQuery = $this->normalizeArabicText($query);
            usort($results, function ($a, $b) use ($normalizedQuery) {
                $aNorm = $this->normalizeArabicText($a['name']);
                $bNorm = $this->normalizeArabicText($b['name']);

                // Exact match gets highest score
                if ($aNorm === $normalizedQuery && $bNorm !== $normalizedQuery) return -1;
                if ($bNorm === $normalizedQuery && $aNorm !== $normalizedQuery) return 1;

                // Starts with query
                $aStarts = strpos($aNorm, $normalizedQuery) === 0 ? 1 : 0;
                $bStarts = strpos($bNorm, $normalizedQuery) === 0 ? 1 : 0;
                if ($aStarts !== $bStarts) return $aStarts ? -1 : 1;

                // Contains all query words
                $queryWords = array_filter(preg_split('/\s+/u', $normalizedQuery), fn($w) => mb_strlen($w) >= 2);
                $aMatches = 0;
                $bMatches = 0;
                foreach ($queryWords as $word) {
                    if (strpos($aNorm, $word) !== false) $aMatches++;
                    if (strpos($bNorm, $word) !== false) $bMatches++;
                }
                if ($aMatches !== $bMatches) return $aMatches > $bMatches ? -1 : 1;

                // Name length (shorter = closer match)
                return mb_strlen($a['name']) - mb_strlen($b['name']);
            });
        }

        return [
            'success' => true,
            'products' => $results,
            'count' => count($results),
            'query' => $query,
            'category_filter' => $category,
        ];
    }

    /**
     * Get product details
     */
    private function getProductDetails(User $store, ?int $productId = null, ?string $productName = null): array
    {
        $query = Product::where('user_id', $store->id)->where('is_active', true);

        if ($productId) {
            $query->where('id', $productId);
        } elseif ($productName) {
            $query->where('name', 'like', "%{$productName}%");
        } else {
            return ['error' => 'يجب تحديد رقم المنتج أو اسمه'];
        }

        $product = $query->with(['category', 'images', 'attributes'])->first();

        if (!$product) {
            return ['error' => 'المنتج غير موجود'];
        }

        $images = [];
        foreach ($product->images as $img) {
            $images[] = [
                'url' => asset('storage/' . $img->image_path),
                'is_primary' => (bool) $img->is_primary,
            ];
        }

        return [
            'success' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description ?? '',
                'price' => (float) $product->price,
                'price_formatted' => number_format($product->price, 0) . ' دينار',
                'currency' => $product->currency ?? 'IQD',
                'quantity' => (int) ($product->quantity ?? 0),
                'reserved_quantity' => (int) ($product->reserved_quantity ?? 0),
                'available_quantity' => max(0, ($product->quantity ?? 0) - ($product->reserved_quantity ?? 0)),
                'is_available' => ($product->quantity ?? 0) > ($product->reserved_quantity ?? 0),
                'unit' => $product->unit ?? 'piece',
                'category' => $product->category ? $product->category->name : 'غير مصنف',
                'images' => $images,
                'primary_image' => $images[0]['url'] ?? null,
                'expiry_date' => $product->expiry_date?->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Get product image
     */
    private function getProductImage(User $store, ?int $productId = null, ?string $productName = null, int $imageIndex = 0): array
    {
        $query = Product::where('user_id', $store->id);

        if ($productId) {
            $query->where('id', $productId);
        } elseif ($productName) {
            $query->where('name', 'like', "%{$productName}%");
        } else {
            return ['error' => 'يجب تحديد المنتج'];
        }

        $product = $query->with('images')->first();

        if (!$product) {
            return ['error' => 'المنتج غير موجود'];
        }

        $images = $product->images->sortByDesc('is_primary')->values();

        if ($images->isEmpty()) {
            return ['error' => 'لا توجد صور لهذا المنتج'];
        }

        $image = $images->get($imageIndex) ?? $images->first();

        return [
            'action' => 'show_image',
            'product_id' => $product->id,
            'product_name' => $product->name,
            'image_url' => asset('storage/' . $image->image_path),
            'total_images' => $images->count(),
            'current_index' => $imageIndex,
        ];
    }

    /**
     * Get categories
     */
    private function getCategories(User $store, bool $includeEmpty = false): array
    {
        $categoriesQuery = Category::where('user_id', $store->id)
            ->withCount(['products' => function ($q) {
                $q->where('is_active', true);
            }]);

        if (!$includeEmpty) {
            $categoriesQuery->having('products_count', '>', 0);
        }

        $categories = $categoriesQuery->get();

        $results = [];
        foreach ($categories as $cat) {
            $availableCount = Product::where('user_id', $store->id)
                ->where('category_id', $cat->id)
                ->where('is_active', true)
                ->where('quantity', '>', 0)
                ->count();

            $results[] = [
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description ?? '',
                'products_count' => $cat->products_count,
                'available_products_count' => $availableCount,
            ];
        }

        return [
            'success' => true,
            'categories' => $results,
            'count' => count($results),
        ];
    }

    /**
     * Get store info
     */
    private function getStoreInfo(User $store, string $infoType = 'all'): array
    {
        $aiSettings = $store->aiSetting;

        $info = [
            'store_name' => $store->name,
        ];

        if ($infoType === 'all' || $infoType === 'delivery') {
            $info['delivery'] = [
                'cost' => $aiSettings->delivery_cost ?? 0,
                'cost_formatted' => number_format($aiSettings->delivery_cost ?? 0, 0) . ' دينار',
                'time' => $aiSettings->delivery_time ?? 'غير محدد',
            ];
        }

        if ($infoType === 'all' || $infoType === 'working_hours') {
            $info['working_hours'] = $aiSettings->working_hours ?? 'غير محدد';
        }

        if ($infoType === 'all' || $infoType === 'policies') {
            $info['policies'] = $aiSettings->store_policies ?? 'لا توجد سياسات محددة';
        }

        if ($infoType === 'all' || $infoType === 'payment_methods') {
            $info['payment_methods'] = [
                'cash_on_delivery' => true,
                'bank_transfer' => false,
                'electronic_payment' => false,
            ];
        }

        return [
            'success' => true,
            'info' => $info,
        ];
    }

    /**
     * Check product availability
     */
    private function checkProductAvailability(User $store, ?int $productId = null, ?string $productName = null, int $requestedQuantity = 1): array
    {
        $query = Product::where('user_id', $store->id)->where('is_active', true);

        if ($productId) {
            $query->where('id', $productId);
        } elseif ($productName) {
            $query->where('name', 'like', "%{$productName}%");
        } else {
            return ['error' => 'يجب تحديد المنتج'];
        }

        $product = $query->first();

        if (!$product) {
            return [
                'is_available' => false,
                'error' => 'المنتج غير موجود',
            ];
        }

        $availableQuantity = max(0, ($product->quantity ?? 0) - ($product->reserved_quantity ?? 0));
        $canFulfill = $availableQuantity >= $requestedQuantity;

        return [
            'success' => true,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'is_available' => $availableQuantity > 0,
            'available_quantity' => $availableQuantity,
            'requested_quantity' => $requestedQuantity,
            'can_fulfill' => $canFulfill,
            'message' => $canFulfill
                ? "نعم، متوفر {$availableQuantity} قطعة من {$product->name}"
                : ($availableQuantity > 0
                    ? "متوفر فقط {$availableQuantity} قطعة من {$product->name}"
                    : "للأسف {$product->name} غير متوفر حالياً"),
        ];
    }

    /**
     * Normalize Arabic text for search/comparison (fuzzy matching support)
     */
    private function normalizeArabicText(string $text): string
    {
        $text = trim($text);
        // Normalize diacritics
        $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
        $text = str_replace('ة', 'ه', $text);
        $text = str_replace('ى', 'ي', $text);
        // Remove diacritics marks
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
        return mb_strtolower($text);
    }
}
