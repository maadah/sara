<?php

namespace App\Services\Chat\Tools;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * ProductSearchTool — searches the store's product catalogue.
 *
 * Implements: exact match → fuzzy match → synonym expansion → category match.
 * Arabic normalisation is applied to both query and product names.
 * Returns a compact string for AI context injection to save tokens.
 */
class ProductSearchTool
{
    /**
     * Arabic synonym map for common Iraqi dialect terms.
     */
    private const SYNONYMS = [
        'موبايل'       => ['جوال', 'تلفون', 'هاتف', 'موبايل', 'تلفون', 'سمارت فون', 'آيفون', 'ايفون'],
        'تلفزيون'      => ['شاشه', 'شاشة', 'تي في', 'تلفزيون', 'تلفاز'],
        'ثلاجه'        => ['برادة', 'براده', 'فريزر', 'ثلاجه', 'ثلاجة'],
        'غساله'        => ['غسالة', 'ماكنة غسيل', 'ماكنه غسيل', 'غساله'],
        'سياره'        => ['عربيه', 'عربية', 'سياره', 'سيارة'],
        'كمبيوتر'      => ['لابتوب', 'حاسوب', 'كمبيوتر', 'حاسبه', 'نوتبوك', 'تابلت', 'آيباد'],
        'قميص'         => ['تيشيرت', 'تيشرت', 'بلوزه', 'بلوزة', 'قميص'],
        'بنطلون'       => ['بنطرون', 'جينز', 'بنطلون', 'پنطلون'],
        'حذاء'         => ['صندل', 'كنادر', 'نعال', 'حذاء', 'بوط'],
        'ساعه'         => ['ساعة', 'ساعه', 'وقاته', 'ساعة ذكية', 'ساعه ذكيه', 'سمارت ووتش'],
        'عطر'          => ['بخور', 'بيرفيوم', 'عطر', 'ريحه'],
        'شنطه'         => ['حقيبه', 'حقيبة', 'شنطه', 'شنطة', 'باك'],
        // Electronics / tech devices — covers general queries like "أجهزة كهربائية"
        'جهاز'         => ['جهاز', 'أجهزة', 'ديفايس'],
        'الكترونيات'   => ['الكتروني', 'إلكتروني', 'الكترونيات', 'إلكترونيات', 'كهربائي', 'كهربائية', 'تقني', 'تقنية', 'تكنولوجيا'],
        'تعقب'         => ['تعقب', 'تتبع', 'tracker', 'تراكر', 'جي بي اس', 'gps'],
        'سماعه'        => ['سماعة', 'سماعه', 'هيدفون', 'ايربودز', 'هيدسيت', 'بلوتوث'],
        'شاحن'         => ['شاحن', 'باور بانك', 'باور بنك', 'كيبل', 'كابل', 'وايرلس'],
        'كاميرا'       => ['كاميرا', 'كامرا', 'مراقبة', 'سيكيوريتي كام'],
    ];

    /**
     * Search products by query within a specific store.
     *
     * @return array{products: array, count: int, query: string}
     */
    public function search(int $storeId, string $query, ?int $categoryId = null, int $limit = 5, ?int $minPrice = null, ?int $maxPrice = null): array
    {
        $normalised = $this->normaliseArabic($query);
        $products   = collect();

        // Step 1: Exact match
        $products = $this->exactMatch($storeId, $normalised, $categoryId);

        // Step 2: Fuzzy match (multi-word LIKE)
        if ($products->isEmpty()) {
            $products = $this->fuzzyMatch($storeId, $normalised, $categoryId);
        }

        // Step 3: Synonym expansion
        if ($products->isEmpty()) {
            $products = $this->synonymMatch($storeId, $normalised, $categoryId);
        }

        // Step 4: Category-name match
        if ($products->isEmpty()) {
            $products = $this->categoryMatch($storeId, $normalised);
        }

        // Apply price range filter (budget-aware search)
        if ($minPrice !== null) {
            $products = $products->filter(fn (Product $p) => (int) $p->price >= $minPrice);
        }
        if ($maxPrice !== null) {
            $products = $products->filter(fn (Product $p) => (int) $p->price <= $maxPrice);
        }

        $products = $products->take($limit);

        if ($products->isEmpty()) {
            Log::info('Chat Tool: product search — no results', [
                'store_id' => $storeId,
                'query'    => $query,
            ]);

            return [
                'products' => [],
                'count'    => 0,
                'query'    => $query,
                'status'   => 'not_found',
            ];
        }

        $formatted = $products->map(fn (Product $p, int $i) => [
            'id'           => $p->id,
            'name'         => $p->name,
            'price'        => (int) $p->price,
            'quantity'     => $p->quantity,
            'manage_stock' => $p->manage_stock ?? true,
            'description'  => mb_substr($p->description ?? '', 0, 100),
            'category'     => $p->category?->name ?? '',
        ])->values()->all();

        return [
            'products' => $formatted,
            'count'    => count($formatted),
            'query'    => $query,
            'status'   => 'found',
        ];
    }

    /**
     * Get all categories for a store.
     *
     * @return array
     */
    public function getCategories(int $storeId): array
    {
        // Only return categories that actually contain at least one active product.
        // This prevents the AI from listing a category and then saying "no products."
        return Category::where('user_id', $storeId)
            ->where('is_active', true)
            ->whereHas('products', function ($q) {
                $q->where('is_active', true);
            })
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Get full product details including attributes and images.
     */
    public function getProductDetails(int $storeId, int $productId): ?array
    {
        $product = Product::with(['category', 'images', 'attributes'])
            ->where('user_id', $storeId)
            ->where('id', $productId)
            ->where('is_active', true)
            ->first();

        if (! $product) {
            return null;
        }

        return [
            'id'          => $product->id,
            'name'        => $product->name,
            'description' => $product->description,
            'price'       => (int) $product->price,
            'quantity'    => $product->quantity,
            'category'    => $product->category?->name,
            'images'      => $product->images->map(fn ($img) => [
                'url'        => $img->image_path,
                'is_primary' => $img->is_primary,
            ])->all(),
            'variants'    => $product->attributes->map(fn ($attr) => [
                'key'       => $attr->attribute_key,
                'value'     => $attr->attribute_value,
                'price_mod' => (int) $attr->price_modifier,
                'stock'     => $attr->stock_quantity,
                'available' => $attr->is_available,
            ])->all(),
        ];
    }

    /**
     * Format products for compact AI context injection.
     */
    public function formatForContext(array $products): string
    {
        if (empty($products)) {
            return '';
        }

        $lines = [];
        foreach ($products as $i => $p) {
            $num          = $i + 1;
            $manageStock  = $p['manage_stock'] ?? true;
            if (! $manageStock) {
                // Service / digital product — no physical stock tracked
                $stock = 'متاح للطلب';
            } elseif ($p['quantity'] > 0) {
                $stock = "متوفر: {$p['quantity']}";
            } else {
                $stock = 'نفذ المخزون مؤقتاً';
            }
            $lines[] = "[{$num}] {$p['name']} — {$p['price']} د.ع — {$stock}";
        }

        return implode("\n", $lines);
    }

    /* ------------------------------------------------------------------ */
    /* Search strategies                                                   */
    /* ------------------------------------------------------------------ */

    private function exactMatch(int $storeId, string $query, ?int $catId): Collection
    {
        return $this->baseQuery($storeId, $catId)
            ->whereRaw("LOWER(name) = ?", [mb_strtolower($query)])
            ->get();
    }

    private function fuzzyMatch(int $storeId, string $query, ?int $catId): Collection
    {
        $words = preg_split('/\s+/', $query);

        // Try all words combined
        $q = $this->baseQuery($storeId, $catId);
        foreach ($words as $word) {
            $q->where(function ($sub) use ($word) {
                $sub->where('name', 'LIKE', "%{$word}%")
                    ->orWhere('description', 'LIKE', "%{$word}%");
            });
        }
        $results = $q->get();

        if ($results->isNotEmpty()) {
            return $results;
        }

        // Fallback: match any word
        if (count($words) > 1) {
            $q2 = $this->baseQuery($storeId, $catId);
            $q2->where(function ($sub) use ($words) {
                foreach ($words as $word) {
                    $sub->orWhere('name', 'LIKE', "%{$word}%")
                        ->orWhere('description', 'LIKE', "%{$word}%");
                }
            });

            return $q2->get();
        }

        return collect();
    }

    private function synonymMatch(int $storeId, string $query, ?int $catId): Collection
    {
        $synonyms = $this->findSynonyms($query);

        if (empty($synonyms)) {
            return collect();
        }

        $q = $this->baseQuery($storeId, $catId);
        $q->where(function ($sub) use ($synonyms) {
            foreach ($synonyms as $syn) {
                $sub->orWhere('name', 'LIKE', "%{$syn}%")
                    ->orWhere('description', 'LIKE', "%{$syn}%");
            }
        });

        return $q->get();
    }

    private function categoryMatch(int $storeId, string $query): Collection
    {
        // Build a word-by-word OR search so that a customer saying
        // "مواد التجميل والعناية بالبشرة" still matches a category named
        // "مواد تجميل وعناية بالبشرة" even after normalisation differences.
        $words = array_filter(preg_split('/\s+/', $query), fn ($w) => mb_strlen($w) >= 2);

        if (empty($words)) {
            return collect();
        }

        $q = Category::where('user_id', $storeId)->where('is_active', true);
        $q->where(function ($sub) use ($words) {
            foreach ($words as $word) {
                $sub->orWhereRaw('LOWER(name) LIKE ?', ["%{$word}%"]);
            }
        });

        // Pick the category whose name has the most word matches (most specific first)
        $categories = $q->get();
        if ($categories->isEmpty()) {
            return collect();
        }

        // Score each category by how many query words appear in its name
        $category = $categories->sortByDesc(function ($cat) use ($words) {
            $name = mb_strtolower($cat->name);
            return collect($words)->filter(fn ($w) => mb_strpos($name, $w) !== false)->count();
        })->first();

        return Product::where('user_id', $storeId)
            ->where('category_id', $category->id)
            ->where('is_active', true)
            ->with('category')
            ->limit(5)
            ->get();
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function baseQuery(int $storeId, ?int $catId)
    {
        $q = Product::where('user_id', $storeId)
            ->where('is_active', true)
            ->with('category');

        if ($catId) {
            $q->where('category_id', $catId);
        }

        return $q;
    }

    private function normaliseArabic(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
        $text = str_replace('ة', 'ه', $text);
        $text = str_replace('ى', 'ي', $text);

        // Strip ال prefix from each word
        $words = preg_split('/\s+/', $text);
        $words = array_map(function (string $w) {
            return preg_replace('/^ال/', '', $w);
        }, $words);

        return implode(' ', $words);
    }

    private function findSynonyms(string $query): array
    {
        $normalised = $this->normaliseArabic($query);
        $words      = preg_split('/\s+/', $normalised);
        $synonyms   = [];

        foreach ($words as $word) {
            foreach (self::SYNONYMS as $group) {
                $normGroup = array_map(fn ($s) => $this->normaliseArabic($s), $group);
                if (in_array($word, $normGroup, true)) {
                    $synonyms = array_merge($synonyms, $group);
                }
            }
        }

        return array_unique($synonyms);
    }
}
