<?php

namespace App\Services\Orders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Product Service - Product Search and Lookup
 *
 * Handles all product-related operations:
 * - Search by name/keywords
 * - Category browsing
 * - Stock checking
 * - Price lookup
 * - Caching for efficiency
 */
class ProductService
{
    /**
     * Cache TTL in seconds (30 minutes)
     */
    protected const CACHE_TTL = 1800;

    /**
     * Search products by query
     *
     * @param User $store
     * @param string $query Search term
     * @param int $limit Max results
     * @return Collection
     */
    public function search(User $store, string $query, int $limit = 5): Collection
    {
        $variants = $this->getSearchVariants($query);

        Log::debug('ProductService: Searching', [
            'store_id' => $store->id,
            'query' => $query,
            'variants' => $variants
        ]);

        // Get products using ALL Arabic character variants
        $products = Product::where('user_id', $store->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->where(function ($q) use ($variants) {
                foreach ($variants as $variant) {
                    $q->orWhere('name', 'LIKE', "%{$variant}%")
                      ->orWhere('description', 'LIKE', "%{$variant}%");
                }
            })
            ->limit($limit * 2) // Get more for sorting
            ->get();

        // If no results and query has "ال" prefix, try without it
        if ($products->isEmpty()) {
            $strippedQuery = $this->stripArabicDefiniteArticle($query);
            if ($strippedQuery !== $query) {
                $strippedVariants = $this->getSearchVariants($strippedQuery);
                $products = Product::where('user_id', $store->id)
                    ->where('is_active', true)
                    ->where('quantity', '>', 0)
                    ->where(function ($q) use ($strippedVariants) {
                        foreach ($strippedVariants as $variant) {
                            $q->orWhere('name', 'LIKE', "%{$variant}%")
                              ->orWhere('description', 'LIKE', "%{$variant}%");
                        }
                    })
                    ->limit($limit * 2)
                    ->get();
            }
        }

        // If still no results, try splitting into keywords and matching any
        if ($products->isEmpty()) {
            $normalizedWords = $this->extractSearchKeywords($query);
            if (count($normalizedWords) > 0) {
                $products = Product::where('user_id', $store->id)
                    ->where('is_active', true)
                    ->where('quantity', '>', 0)
                    ->where(function ($q) use ($normalizedWords) {
                        foreach ($normalizedWords as $word) {
                            $q->orWhere('name', 'LIKE', "%{$word}%")
                              ->orWhere('description', 'LIKE', "%{$word}%");
                        }
                    })
                    ->limit($limit * 2)
                    ->get();
            }
        }

        // Score and sort results using normalized query
        $normalizedQuery = $this->normalizeQuery($query);
        $scored = $products->map(function ($product) use ($normalizedQuery) {
            $score = $this->calculateMatchScore($product, $normalizedQuery);
            $product->match_score = $score;
            return $product;
        })->sortByDesc('match_score');

        return $scored->take($limit)->values();
    }

    /**
     * Find single best matching product
     */
    public function findBestMatch(User $store, string $query): ?Product
    {
        $results = $this->search($store, $query, 1);
        return $results->first();
    }

    /**
     * Get product by ID
     */
    public function getById(int $productId): ?Product
    {
        return Product::with(['images', 'attributes', 'category'])
            ->find($productId);
    }

    /**
     * Get products in category
     */
    public function getByCategory(User $store, int $categoryId, int $limit = 10): Collection
    {
        return Product::where('user_id', $store->id)
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->limit($limit)
            ->get();
    }

    /**
     * Get store categories
     */
    public function getCategories(User $store): Collection
    {
        $cacheKey = "store_{$store->id}_categories";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($store) {
            return Category::where('user_id', $store->id)
                ->withCount(['products' => function ($q) {
                    $q->where('is_active', true)->where('quantity', '>', 0);
                }])
                ->get()
                ->filter(fn($cat) => $cat->products_count > 0)
                ->values(); // Re-index array after filter
        });
    }

    /**
     * Get category by name (fuzzy match + normalized comparison)
     *
     * First tries LIKE query with variants, then falls back to normalized in-memory comparison
     * to handle all Arabic diacritics/spell variations
     */
    public function findCategory(User $store, string $name): ?Category
    {
        $variants = $this->getSearchVariants($name);
        $inputNormalized = $this->normalizeQuery($name);

        // FIRST: Try LIKE query with variants (fast, database-level)
        $result = Category::where('user_id', $store->id)
            ->where(function ($q) use ($variants) {
                foreach ($variants as $variant) {
                    $q->orWhere('name', 'LIKE', "%{$variant}%");
                }
            })
            ->first();

        if ($result) {
            return $result;
        }

        // SECOND: Fallback to normalized comparison (slower but more accurate)
        // Load all categories for this store and match by normalized name
        $categories = Category::where('user_id', $store->id)
            ->get();

        foreach ($categories as $cat) {
            $catNormalized = $this->normalizeQuery($cat->name);

            // Exact match on normalized name
            if ($catNormalized === $inputNormalized) {
                return $cat;
            }

            // Substring match on normalized name (for partial category searches)
            if (mb_strpos($catNormalized, $inputNormalized) !== false ||
                mb_strpos($inputNormalized, $catNormalized) !== false) {
                return $cat;
            }
        }

        // THIRD: Word-level matching - match if ANY significant word from input appears
        // in a category name. Handles: "الكترونيه" matching "اجهزه الكترونيه",
        // "كهربائيه" matching "اجهزه كهربائيه", "منزليه" matching "ادوات منزليه"
        $inputWords = array_filter(preg_split('/\s+/u', $inputNormalized), fn($w) => mb_strlen($w) >= 3);
        // Also strip "ال" prefix from input for matching
        $strippedInput = $this->stripArabicDefiniteArticle($inputNormalized);
        $strippedWords = array_filter(preg_split('/\s+/u', $strippedInput), fn($w) => mb_strlen($w) >= 3);
        $allInputWords = array_unique(array_merge($inputWords, $strippedWords));

        $bestMatch = null;
        $bestScore = 0;

        foreach ($categories as $cat) {
            $catNormalized = $this->normalizeQuery($cat->name);
            $catWords = preg_split('/\s+/u', $catNormalized);
            $catStripped = $this->stripArabicDefiniteArticle($catNormalized);
            $catStrippedWords = preg_split('/\s+/u', $catStripped);
            $allCatWords = array_unique(array_merge($catWords, $catStrippedWords));

            $score = 0;
            foreach ($allInputWords as $iWord) {
                foreach ($allCatWords as $cWord) {
                    // Exact word match
                    if ($iWord === $cWord) {
                        $score += 10;
                    }
                    // Substring match (e.g., "كترونيه" in "الكترونيه")
                    elseif (mb_strlen($iWord) >= 3 && (mb_strpos($cWord, $iWord) !== false || mb_strpos($iWord, $cWord) !== false)) {
                        $score += 7;
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $cat;
            }
        }

        // Require a minimum match score to avoid false positives
        if ($bestMatch && $bestScore >= 7) {
            return $bestMatch;
        }

        return null;
    }

    /**
     * Get product index for AI (lightweight)
     */
    public function getProductIndex(User $store): array
    {
        $cacheKey = "store_{$store->id}_product_index";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($store) {
            return Product::where('user_id', $store->id)
                ->where('is_active', true)
                ->where('quantity', '>', 0)
                ->get(['id', 'name', 'price'])
                ->toArray();
        });
    }

    /**
     * Get best sellers
     */
    public function getBestSellers(User $store, int $limit = 5): Collection
    {
        $cacheKey = "store_{$store->id}_best_sellers";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($store, $limit) {
            return Product::where('user_id', $store->id)
                ->where('is_active', true)
                ->where('quantity', '>', 0)
                ->orderBy('sold_count', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Check stock availability
     */
    public function checkStock(Product $product, int $quantity = 1, array $attributes = []): array
    {
        $available = $product->quantity ?? 0;

        // Check attribute-specific stock if applicable
        if (!empty($attributes)) {
            $attr = $product->attributes()
                ->where(function ($q) use ($attributes) {
                    foreach ($attributes as $key => $value) {
                        $q->where('attribute_key', $key)
                            ->where('attribute_value', $value);
                    }
                })
                ->first();

            if ($attr) {
                $available = $attr->stock_quantity ?? $available;
            }
        }

        return [
            'available' => $available,
            'requested' => $quantity,
            'in_stock' => $available >= $quantity,
            'message' => $available >= $quantity
                ? ''
                : ($available <= 0 ? 'غير متوفر' : "متوفر فقط {$available} قطع"),
        ];
    }

    /**
     * Get product with images
     */
    public function getWithImages(int $productId): ?Product
    {
        return Product::with('images')->find($productId);
    }

    /**
     * Get primary image URL
     */
    public function getPrimaryImageUrl(Product $product): ?string
    {
        // Eager load images if not loaded
        if (!$product->relationLoaded('images')) {
            $product->load('images');
        }

        // Get primary image
        $primary = $product->images->where('is_primary', true)->first();

        if ($primary && !empty($primary->image_path)) {
            return $primary->image_url;
        }

        // Fallback to first image
        $first = $product->images->first();
        if ($first && !empty($first->image_path)) {
            return $first->image_url;
        }

        return null;
    }

    /**
     * Get all product images
     */
    public function getImageUrls(Product $product): array
    {
        return $product->images()
            ->orderBy('is_primary', 'desc')
            ->pluck('image_url')
            ->toArray();
    }

    /**
     * Get product variants/attributes
     */
    public function getVariants(Product $product): array
    {
        $attributes = $product->attributes()->get();

        $grouped = [];
        foreach ($attributes as $attr) {
            $key = $attr->attribute_key;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = [
                'value' => $attr->attribute_value,
                'stock' => $attr->stock_quantity,
                'price_modifier' => $attr->price_modifier ?? 0,
            ];
        }

        return $grouped;
    }

    /**
     * Check if product has specific attribute
     */
    public function hasAttribute(Product $product, string $key, string $value): bool
    {
        return $product->attributes()
            ->where('attribute_key', $key)
            ->where('attribute_value', $value)
            ->exists();
    }

    /**
     * Format product for display
     */
    public function formatForDisplay(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => (int) $product->price, // Integer price without .00
            'price_formatted' => number_format((int) $product->price, 0, '', ',') . ' دينار',
            'description' => $product->description,
            'in_stock' => ($product->quantity ?? 0) > 0,
            'stock' => $product->quantity ?? 0,
            'category' => $product->category?->name,
            'image' => $this->getPrimaryImageUrl($product),
            'variants' => $this->getVariants($product),
        ];
    }

    /**
     * Format products list for display
     */
    public function formatListForDisplay(Collection $products): array
    {
        return $products->map(fn($p) => $this->formatForDisplay($p))->toArray();
    }

    /**
     * Normalize search query
     */
    protected function normalizeQuery(string $query): string
    {
        // Trim and lowercase
        $query = trim(mb_strtolower($query));

        // Normalize Arabic characters
        $query = str_replace(['أ', 'إ', 'آ'], 'ا', $query);
        $query = str_replace('ة', 'ه', $query);
        $query = str_replace('ى', 'ي', $query);

        return $query;
    }

    /**
     * Generate search variants to handle Arabic character ambiguity (ة/ه, ى/ي)
     * Returns array of query strings to try with LIKE
     */
    protected function getSearchVariants(string $query): array
    {
        $normalized = $this->normalizeQuery($query);
        $variants = [$normalized];

        // Keep original (un-normalized) for direct matching
        $original = trim(mb_strtolower($query));
        if ($original !== $normalized) {
            $variants[] = $original;
        }

        // ه → ة variant (taa marbuta)
        $withTaaMarbuta = str_replace('ه', 'ة', $normalized);
        if ($withTaaMarbuta !== $normalized) {
            $variants[] = $withTaaMarbuta;
        }

        // ي → ى variant (alef maqsura)
        $withAlefMaqsura = str_replace('ي', 'ى', $normalized);
        if ($withAlefMaqsura !== $normalized) {
            $variants[] = $withAlefMaqsura;
        }

        // Combined: both ة and ى
        $combined = str_replace(['ه', 'ي'], ['ة', 'ى'], $normalized);
        if ($combined !== $normalized) {
            $variants[] = $combined;
        }

        return array_unique($variants);
    }

    /**
     * Get Arabic word variations (plural↔singular, common synonyms)
     * Returns alternative search terms to try when direct search fails
     */
    public function getArabicVariations(string $query): array
    {
        $query = $this->normalizeQuery($query);
        $variations = [];

        // Common Arabic plural→singular and synonym mappings
        $mappings = [
            // Clothing
            'قمصان' => ['قميص', 'تيشيرت', 'تيشرت', 'بلوزه'],
            'قميص' => ['قمصان', 'تيشيرت', 'تيشرت', 'بلوزه'],
            'ملابس' => ['قميص', 'قمصان', 'بنطلون', 'فستان', 'بلوزه', 'تيشيرت', 'جاكيت', 'عبايه'],
            'بناطيل' => ['بنطلون', 'بنطال', 'سروال'],
            'بنطلون' => ['بناطيل', 'بنطال', 'سروال'],
            'فساتين' => ['فستان'],
            'فستان' => ['فساتين'],
            'احذيه' => ['حذاء', 'جزمه', 'صندل', 'نعال'],
            'حذاء' => ['احذيه', 'جزمه', 'صندل'],
            'جزمه' => ['حذاء', 'احذيه', 'بوط'],
            'شنط' => ['شنطه', 'حقيبه'],
            'شنطه' => ['شنط', 'حقيبه'],
            'اكسسوارات' => ['اكسسوار', 'خاتم', 'سلسله', 'ساعه'],
            'ساعات' => ['ساعه'],
            'ساعه' => ['ساعات'],
            'نظارات' => ['نظاره'],
            'نظاره' => ['نظارات'],
            'عطور' => ['عطر', 'بارفيوم'],
            'عطر' => ['عطور', 'بارفيوم'],

            // General
            'اجهزه' => ['جهاز', 'موبايل', 'تابلت', 'لابتوب'],
            'جهاز' => ['اجهزه', 'موبايل'],
            'موبايلات' => ['موبايل', 'تلفون', 'هاتف', 'جوال'],
            'موبايل' => ['موبايلات', 'تلفون', 'هاتف'],
            'سماعات' => ['سماعه'],
            'سماعه' => ['سماعات'],

            // Try removing common suffixes/prefixes
            'تيشيرت' => ['تيشرت', 'قميص'],
            'تيشرت' => ['تيشيرت', 'قميص'],
            'جاكيت' => ['جاكت', 'سترة'],
        ];

        // Check direct mappings
        foreach ($mappings as $word => $alternatives) {
            if (mb_strpos($query, $word) !== false) {
                foreach ($alternatives as $alt) {
                    $variations[] = str_replace($word, $alt, $query);
                }
            }
        }

        // Try removing common Arabic plural suffix ات
        if (preg_match('/^(.+)ات$/u', $query, $m) && mb_strlen($m[1]) >= 2) {
            $variations[] = $m[1];
            $variations[] = $m[1] . 'ه';
        }

        // Try adding plural suffix
        if (!preg_match('/ات$/u', $query) && mb_strlen($query) >= 3) {
            $variations[] = $query . 'ات';
        }

        // Try removing ين suffix (masculine plural)
        if (preg_match('/^(.+)ين$/u', $query, $m) && mb_strlen($m[1]) >= 2) {
            $variations[] = $m[1];
        }

        return array_unique(array_filter($variations));
    }

    /**
     * Calculate match score for sorting
     */
    protected function calculateMatchScore(Product $product, string $query): int
    {
        $score = 0;
        $name = $this->normalizeQuery($product->name);
        $query = mb_strtolower($query);

        // Exact match = 100
        if ($name === $query) {
            $score += 100;
        }
        // Starts with query = 80
        elseif (str_starts_with($name, $query)) {
            $score += 80;
        }
        // Contains query = 50
        elseif (str_contains($name, $query)) {
            $score += 50;
        }

        // Has stock = +20
        if (($product->quantity ?? 0) > 0) {
            $score += 20;
        }

        // Has image = +10
        if ($product->images()->exists()) {
            $score += 10;
        }

        return $score;
    }

    /**
     * Strip Arabic definite article "ال" from a word/query
     */
    public function stripArabicDefiniteArticle(string $text): string
    {
        $text = trim($text);

        // Strip "ال" from the beginning of the text
        if (mb_strpos($text, 'ال') === 0 && mb_strlen($text) > 3) {
            $text = mb_substr($text, 2);
        }

        // Also strip "ال" from individual words in multi-word queries
        $words = explode(' ', $text);
        $stripped = [];
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strpos($word, 'ال') === 0 && mb_strlen($word) > 3) {
                $stripped[] = mb_substr($word, 2);
            } else {
                $stripped[] = $word;
            }
        }

        return implode(' ', $stripped);
    }

    /**
     * Extract individual search keywords from a query,
     * normalizing and stripping "ال" from each
     */
    protected function extractSearchKeywords(string $query): array
    {
        $normalized = $this->normalizeQuery($query);
        $words = preg_split('/\s+/', $normalized);

        $keywords = [];
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word) < 2) {
                continue;
            }

            // Strip "ال" from the word
            if (mb_strpos($word, 'ال') === 0 && mb_strlen($word) > 3) {
                $keywords[] = mb_substr($word, 2);
            }
            $keywords[] = $word;
        }

        return array_unique($keywords);
    }

    /**
     * Clear product cache for store
     */
    public function clearCache(User $store): void
    {
        Cache::forget("store_{$store->id}_categories");
        Cache::forget("store_{$store->id}_product_index");
        Cache::forget("store_{$store->id}_best_sellers");
    }
}
