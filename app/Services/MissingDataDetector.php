<?php

namespace App\Services;

use App\Models\AiChatSession;
use App\Models\Lead;
use App\Models\Product;
use App\Models\StoreType;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Missing Data Detector Service
 * 
 * Detects missing information that the AI should ask about:
 * - Product attributes (size, color) based on store type
 * - Customer info (name, phone, address)
 * - Order confirmation
 */
class MissingDataDetector
{
    protected User $user;
    protected ?StoreType $storeType;
    protected ProductAttributeService $attributeService;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->storeType = $user->storeType;
        $this->attributeService = new ProductAttributeService();
    }

    /**
     * Get all missing data for current cart/order state
     */
    public function detectMissingData(AiChatSession $session): array
    {
        $missing = [
            'attributes' => [],
            'customer_info' => [],
            'has_missing' => false,
        ];

        // Check missing product attributes
        $cart = $session->cart ?? [];
        foreach ($cart as $index => $item) {
            $productId = $item['product_id'] ?? null;
            if (!$productId) continue;

            $product = Product::with('attributes')->find($productId);
            if (!$product) continue;

            $selectedAttrs = $item['attributes'] ?? [];
            $missingAttrs = $this->getMissingProductAttributes($product, $selectedAttrs);

            if (!empty($missingAttrs)) {
                $missing['attributes'][$index] = [
                    'product_id' => $productId,
                    'product_name' => $product->name,
                    'missing' => $missingAttrs,
                    'available_options' => $this->getAvailableOptions($product, $missingAttrs),
                ];
                $missing['has_missing'] = true;
            }
        }

        // Check missing customer info
        $customerData = $session->customer_data ?? [];
        $missingFields = $session->getMissingFields();
        if (!empty($missingFields)) {
            $missing['customer_info'] = $missingFields;
            $missing['has_missing'] = true;
        }

        return $missing;
    }

    /**
     * Get missing required attributes for a product
     */
    public function getMissingProductAttributes(Product $product, array $selectedAttributes): array
    {
        if (!$this->storeType) {
            return [];
        }

        $required = $this->storeType->required_attributes ?? [];
        if (empty($required)) {
            return [];
        }

        // Check which attributes the product actually has
        $productAttrs = $product->attributes()
            ->pluck('attribute_key')
            ->unique()
            ->toArray();

        $missing = [];
        foreach ($required as $attr) {
            // Only ask if product has this attribute AND user hasn't provided it
            if (in_array($attr, $productAttrs) && !isset($selectedAttributes[$attr])) {
                $missing[] = $attr;
            }
        }

        return $missing;
    }

    /**
     * Get available options for missing attributes
     */
    protected function getAvailableOptions(Product $product, array $missingAttrs): array
    {
        $options = [];
        
        foreach ($missingAttrs as $attr) {
            $values = $product->attributes()
                ->where('attribute_key', $attr)
                ->where('is_available', true)
                ->where(function ($q) {
                    $q->whereNull('stock_quantity')
                        ->orWhere('stock_quantity', '>', 0);
                })
                ->pluck('attribute_value')
                ->unique()
                ->toArray();

            if (!empty($values)) {
                $options[$attr] = [
                    'name' => StoreType::getAttributeName($attr),
                    'values' => $values,
                    'display_values' => $attr === 'color' 
                        ? array_map(fn($v) => StoreType::getColorName($v), $values)
                        : $values,
                ];
            }
        }

        return $options;
    }

    /**
     * Build question for first missing attribute
     */
    public function buildMissingAttributeQuestion(array $missingData): ?string
    {
        if (empty($missingData['attributes'])) {
            return null;
        }

        // Get first product with missing attributes
        $firstMissing = reset($missingData['attributes']);
        $productName = $firstMissing['product_name'];
        $firstAttr = $firstMissing['missing'][0] ?? null;

        if (!$firstAttr) {
            return null;
        }

        $attrName = StoreType::getAttributeName($firstAttr);
        $options = $firstMissing['available_options'][$firstAttr] ?? null;

        if (!$options) {
            return "شنو {$attrName} تريده لـ {$productName}؟";
        }

        $optionsStr = implode('، ', $options['display_values']);
        
        $questions = [
            'size' => "شنو المقاس تريده لـ {$productName}؟\nالمتوفر: {$optionsStr}",
            'color' => "شنو اللون تفضله لـ {$productName}؟\nالمتوفر: {$optionsStr}",
        ];

        return $questions[$firstAttr] ?? "شنو {$attrName} تريده لـ {$productName}؟\nالمتوفر: {$optionsStr}";
    }

    /**
     * Build question for first missing customer info
     */
    public function buildMissingCustomerInfoQuestion(array $missingFields, array $customerData = []): ?string
    {
        if (empty($missingFields)) {
            return null;
        }

        $firstMissing = $missingFields[0];
        $name = $customerData['name'] ?? '';

        $questions = [
            'name' => 'شنو اسمك الكريم؟',
            'phone' => $name 
                ? "زين يا {$name}، شنو رقم تلفونك؟" 
                : 'شنو رقم تلفونك؟',
            'address' => 'وين نوصل إلك؟ (المحافظة، المنطقة، أقرب نقطة دالة)',
        ];

        return $questions[$firstMissing] ?? null;
    }

    /**
     * Try to extract attribute value from message
     */
    public function extractAttributeFromMessage(string $message, string $attributeKey): ?string
    {
        return match ($attributeKey) {
            'size' => $this->attributeService->extractAttributesFromMessage($message)['size'] ?? null,
            'color' => $this->attributeService->extractAttributesFromMessage($message)['color'] ?? null,
            default => null,
        };
    }

    /**
     * Update cart item with extracted attribute
     */
    public function updateCartItemAttribute(
        AiChatSession $session, 
        int $cartIndex, 
        string $attributeKey, 
        string $attributeValue
    ): bool {
        $cart = $session->cart ?? [];
        
        if (!isset($cart[$cartIndex])) {
            return false;
        }

        if (!isset($cart[$cartIndex]['attributes'])) {
            $cart[$cartIndex]['attributes'] = [];
        }

        $cart[$cartIndex]['attributes'][$attributeKey] = $attributeValue;
        $session->cart = $cart;
        $session->save();

        Log::info('MissingDataDetector: Updated cart attribute', [
            'cart_index' => $cartIndex,
            'attribute_key' => $attributeKey,
            'attribute_value' => $attributeValue,
        ]);

        return true;
    }

    /**
     * Get the next thing to ask for (attribute or customer info)
     */
    public function getNextQuestion(AiChatSession $session): ?array
    {
        $missingData = $this->detectMissingData($session);

        if (!$missingData['has_missing']) {
            return null;
        }

        // First, ask for missing attributes
        if (!empty($missingData['attributes'])) {
            $question = $this->buildMissingAttributeQuestion($missingData);
            if ($question) {
                $firstMissing = reset($missingData['attributes']);
                return [
                    'type' => 'attribute',
                    'question' => $question,
                    'cart_index' => array_key_first($missingData['attributes']),
                    'attribute_key' => $firstMissing['missing'][0],
                    'product_name' => $firstMissing['product_name'],
                ];
            }
        }

        // Then, ask for missing customer info
        if (!empty($missingData['customer_info'])) {
            $question = $this->buildMissingCustomerInfoQuestion(
                $missingData['customer_info'],
                $session->customer_data ?? []
            );
            if ($question) {
                return [
                    'type' => 'customer_info',
                    'question' => $question,
                    'field' => $missingData['customer_info'][0],
                ];
            }
        }

        return null;
    }

    /**
     * Check if order is ready to confirm (all data collected)
     */
    public function isOrderReadyToConfirm(AiChatSession $session): bool
    {
        $missingData = $this->detectMissingData($session);
        return !$missingData['has_missing'] && !empty($session->cart);
    }

    /**
     * Format cart with attributes for display
     */
    public function formatCartWithAttributes(array $cart): string
    {
        if (empty($cart)) {
            return "السلة فارغة";
        }

        $lines = ["سلة الطلب:"];
        $total = 0;

        foreach ($cart as $item) {
            $name = $item['name'] ?? 'منتج';
            $qty = $item['quantity'] ?? 1;
            $price = $item['price'] ?? 0;
            $subtotal = $price * $qty;
            $total += $subtotal;

            $line = "• {$name} × {$qty} = {$subtotal} دينار";

            // Add selected attributes
            if (!empty($item['attributes'])) {
                $attrs = [];
                foreach ($item['attributes'] as $key => $value) {
                    $attrName = StoreType::getAttributeName($key);
                    $displayValue = $key === 'color' ? StoreType::getColorName($value) : $value;
                    $attrs[] = "{$attrName}: {$displayValue}";
                }
                if (!empty($attrs)) {
                    $line .= "\n  (" . implode('، ', $attrs) . ")";
                }
            }

            $lines[] = $line;
        }

        $lines[] = "المجموع: {$total} دينار";

        return implode("\n", $lines);
    }
}
