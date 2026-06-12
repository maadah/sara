<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\StoreType;
use App\Models\User;

/**
 * Product Attribute Service
 * 
 * Handles product attributes (size, color, etc.) for different store types.
 * Validates selections, checks availability, and calculates prices.
 */
class ProductAttributeService
{
    /**
     * Size pattern mappings (Arabic/English)
     */
    protected const SIZE_PATTERNS = [
        // Standard sizes
        'xs' => 'XS', 'اكس سمول' => 'XS', 'extra small' => 'XS',
        's' => 'S', 'سمول' => 'S', 'صغير' => 'S', 'small' => 'S',
        'm' => 'M', 'ميديم' => 'M', 'وسط' => 'M', 'medium' => 'M', 'متوسط' => 'M',
        'l' => 'L', 'لارج' => 'L', 'كبير' => 'L', 'large' => 'L',
        'xl' => 'XL', 'اكس لارج' => 'XL', 'extra large' => 'XL',
        'xxl' => 'XXL', 'اكس اكس لارج' => 'XXL', 'دبل اكس لارج' => 'XXL',
        'xxxl' => 'XXXL', 'ثري اكس' => 'XXXL',
        // Numeric (for shoes/rings)
        '36' => '36', '37' => '37', '38' => '38', '39' => '39', '40' => '40',
        '41' => '41', '42' => '42', '43' => '43', '44' => '44', '45' => '45',
    ];

    /**
     * Color pattern mappings (Arabic to English key)
     */
    protected const COLOR_PATTERNS = [
        'أحمر' => 'red', 'احمر' => 'red', 'red' => 'red',
        'أزرق' => 'blue', 'ازرق' => 'blue', 'blue' => 'blue',
        'أخضر' => 'green', 'اخضر' => 'green', 'green' => 'green',
        'أسود' => 'black', 'اسود' => 'black', 'black' => 'black',
        'أبيض' => 'white', 'ابيض' => 'white', 'white' => 'white',
        'أصفر' => 'yellow', 'اصفر' => 'yellow', 'yellow' => 'yellow',
        'وردي' => 'pink', 'زهري' => 'pink', 'pink' => 'pink',
        'بنفسجي' => 'purple', 'موف' => 'purple', 'purple' => 'purple',
        'بني' => 'brown', 'brown' => 'brown',
        'رمادي' => 'gray', 'gray' => 'gray', 'grey' => 'gray',
        'برتقالي' => 'orange', 'orange' => 'orange',
        'بيج' => 'beige', 'beige' => 'beige',
        'كحلي' => 'navy', 'navy' => 'navy',
        'ذهبي' => 'gold', 'gold' => 'gold',
        'فضي' => 'silver', 'silver' => 'silver',
    ];

    /**
     * Extract product attributes from a message
     */
    public function extractAttributesFromMessage(string $message): array
    {
        $attributes = [];
        $normalized = $this->normalizeText($message);

        // Extract size
        $size = $this->extractSize($normalized);
        if ($size) {
            $attributes['size'] = $size;
        }

        // Extract color
        $color = $this->extractColor($normalized);
        if ($color) {
            $attributes['color'] = $color;
        }

        return $attributes;
    }

    /**
     * Extract size from text
     */
    protected function extractSize(string $text): ?string
    {
        foreach (self::SIZE_PATTERNS as $pattern => $size) {
            if (mb_stripos($text, $pattern) !== false) {
                return $size;
            }
        }
        return null;
    }

    /**
     * Extract color from text
     */
    protected function extractColor(string $text): ?string
    {
        foreach (self::COLOR_PATTERNS as $pattern => $color) {
            if (mb_stripos($text, $pattern) !== false) {
                return $color;
            }
        }
        return null;
    }

    /**
     * Get available attribute options for a product
     */
    public function getAvailableOptions(Product $product): array
    {
        return $product->attributes()
            ->available()
            ->get()
            ->groupBy('attribute_key')
            ->map(fn($group) => $group->pluck('attribute_value')->unique()->values()->toArray())
            ->toArray();
    }

    /**
     * Check if selected attributes are available for a product
     */
    public function checkAvailability(Product $product, array $selectedAttributes): array
    {
        $available = true;
        $messages = [];
        $unavailableAttrs = [];

        foreach ($selectedAttributes as $key => $value) {
            $attribute = $product->attributes()
                ->where('attribute_key', $key)
                ->where('attribute_value', $value)
                ->first();

            if (!$attribute) {
                $available = false;
                $attrName = StoreType::getAttributeName($key);
                $displayValue = $key === 'color' ? StoreType::getColorName($value) : $value;
                $messages[] = "{$attrName} {$displayValue} غير متوفر";
                $unavailableAttrs[] = $key;
            } elseif (!$attribute->isInStock()) {
                $available = false;
                $attrName = StoreType::getAttributeName($key);
                $displayValue = $key === 'color' ? StoreType::getColorName($value) : $value;
                $messages[] = "{$attrName} {$displayValue} نفذ من المخزون";
                $unavailableAttrs[] = $key;
            }
        }

        // If unavailable, suggest alternatives
        $alternatives = [];
        if (!$available) {
            foreach ($unavailableAttrs as $key) {
                $availableValues = $product->attributes()
                    ->where('attribute_key', $key)
                    ->available()
                    ->pluck('attribute_value')
                    ->toArray();

                if (!empty($availableValues)) {
                    $attrName = StoreType::getAttributeName($key);
                    if ($key === 'color') {
                        $availableValues = array_map(fn($c) => StoreType::getColorName($c), $availableValues);
                    }
                    $alternatives[$key] = [
                        'name' => $attrName,
                        'options' => $availableValues,
                    ];
                }
            }
        }

        return [
            'available' => $available,
            'messages' => $messages,
            'alternatives' => $alternatives,
        ];
    }

    /**
     * Get missing required attributes for a product
     */
    public function getMissingRequiredAttributes(Product $product, array $providedAttributes): array
    {
        $storeType = $product->user->storeType;
        if (!$storeType) {
            return [];
        }

        $required = $storeType->required_attributes ?? [];
        $provided = array_keys($providedAttributes);

        // Also check if product actually has these attributes defined
        $productAttributes = $product->attributes()->pluck('attribute_key')->unique()->toArray();

        $missing = [];
        foreach ($required as $attr) {
            if (!in_array($attr, $provided) && in_array($attr, $productAttributes)) {
                $missing[] = $attr;
            }
        }

        return $missing;
    }

    /**
     * Calculate final price including attribute modifiers
     */
    public function calculateFinalPrice(Product $product, array $selectedAttributes): float
    {
        $basePrice = (float) $product->price;
        $modifier = 0;

        foreach ($selectedAttributes as $key => $value) {
            $attribute = $product->attributes()
                ->where('attribute_key', $key)
                ->where('attribute_value', $value)
                ->first();

            if ($attribute) {
                $modifier += (float) $attribute->price_modifier;
            }
        }

        return $basePrice + $modifier;
    }

    /**
     * Decrement stock for selected attributes
     */
    public function decrementStock(Product $product, array $selectedAttributes, int $quantity = 1): bool
    {
        foreach ($selectedAttributes as $key => $value) {
            $attribute = $product->attributes()
                ->where('attribute_key', $key)
                ->where('attribute_value', $value)
                ->first();

            if ($attribute && $attribute->stock_quantity !== null) {
                if (!$attribute->decrementStock($quantity)) {
                    return false;
                }
            }
        }

        // Also decrement main product quantity if no attribute-level stock
        if (empty($selectedAttributes) || !$this->hasAttributeLevelStock($product)) {
            $product->decrement('quantity', $quantity);
        }

        return true;
    }

    /**
     * Check if product has attribute-level stock tracking
     */
    protected function hasAttributeLevelStock(Product $product): bool
    {
        return $product->attributes()
            ->whereNotNull('stock_quantity')
            ->exists();
    }

    /**
     * Format attributes for display
     */
    public function formatAttributesForDisplay(array $attributes): string
    {
        if (empty($attributes)) {
            return '';
        }

        $parts = [];
        foreach ($attributes as $key => $value) {
            $attrName = StoreType::getAttributeName($key);
            $displayValue = $key === 'color' ? StoreType::getColorName($value) : $value;
            $parts[] = "{$attrName}: {$displayValue}";
        }

        return implode(' | ', $parts);
    }

    /**
     * Build question for missing attribute
     */
    public function buildAttributeQuestion(string $attribute, Product $product): string
    {
        $attrName = StoreType::getAttributeName($attribute);
        $productName = $product->name;

        // Get available options
        $options = $product->attributes()
            ->where('attribute_key', $attribute)
            ->available()
            ->pluck('attribute_value')
            ->toArray();

        if ($attribute === 'color') {
            $options = array_map(fn($c) => StoreType::getColorName($c), $options);
        }

        $optionsStr = implode('، ', $options);

        $questions = [
            'size' => "شنو المقاس تريده لـ {$productName}؟\nالمتوفر: {$optionsStr}",
            'color' => "شنو اللون تفضله لـ {$productName}؟\nالمتوفر: {$optionsStr}",
        ];

        return $questions[$attribute] ?? "شنو {$attrName} تريده؟\nالمتوفر: {$optionsStr}";
    }

    /**
     * Normalize text for matching
     */
    protected function normalizeText(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
        $text = str_replace('ة', 'ه', $text);
        $text = str_replace('ى', 'ي', $text);
        return $text;
    }

    /**
     * Detect missing data that AI should ask about
     */
    public function detectMissingDataForOrder(User $storeOwner, array $cartItems): array
    {
        $storeType = $storeOwner->storeType;
        $missingData = [];

        if (!$storeType) {
            return $missingData;
        }

        foreach ($cartItems as $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId) continue;

            $product = Product::find($productId);
            if (!$product) continue;

            $providedAttrs = $item['attributes'] ?? [];
            $missing = $this->getMissingRequiredAttributes($product, $providedAttrs);

            if (!empty($missing)) {
                $missingData[$productId] = [
                    'product_name' => $product->name,
                    'missing_attributes' => $missing,
                ];
            }
        }

        return $missingData;
    }
}
