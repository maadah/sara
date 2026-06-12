<?php

namespace App\Services\Orders;

use App\Models\AiChatSession;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Cart Service - Business Logic Layer
 *
 * Handles all cart operations with validation.
 * AI cannot modify cart directly - only through this service.
 *
 * RULES:
 * - Idempotent operations (same request = same result)
 * - Stock validation before adding
 * - Price locking at addition time
 * - No duplicate items (update quantity instead)
 */
class CartService
{
    /**
     * Add item to cart
     *
     * If item already exists, updates quantity instead of duplicating.
     *
     * @param AiChatSession $session
     * @param Product $product
     * @param int $quantity
     * @param array $attributes Optional attributes (size, color)
     * @return array{success: bool, message: string, cart: array}
     */
    public function addItem(
        AiChatSession $session,
        Product $product,
        int $quantity = 1,
        array $attributes = []
    ): array {
        // Validate stock
        $stockValidation = $this->validateStock($product, $quantity, $attributes);
        if (!$stockValidation['valid']) {
            return [
                'success' => false,
                'message' => $stockValidation['message'],
                'cart' => $this->getCart($session),
            ];
        }

        $cart = $this->getCart($session);
        $itemKey = $this->getItemKey($product->id, $attributes);

        // Check if item already in cart
        $existingIndex = $this->findItemIndex($cart, $product->id, $attributes);

        if ($existingIndex !== null) {
            // Update existing item quantity
            $newQty = $cart[$existingIndex]['quantity'] + $quantity;

            // Re-validate stock for new total
            $stockValidation = $this->validateStock($product, $newQty, $attributes);
            if (!$stockValidation['valid']) {
                return [
                    'success' => false,
                    'message' => $stockValidation['message'],
                    'cart' => $cart,
                ];
            }

            $cart[$existingIndex]['quantity'] = $newQty;
            $cart[$existingIndex]['subtotal'] = $newQty * $cart[$existingIndex]['price'];

            Log::info('CartService: Updated item quantity', [
                'session_id' => $session->id,
                'product_id' => $product->id,
                'new_quantity' => $newQty
            ]);
        } else {
            // Add new item
            $price = $this->getEffectivePrice($product, $attributes);

            $cart[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => $price,
                'quantity' => $quantity,
                'subtotal' => $price * $quantity,
                'attributes' => $attributes,
                'added_at' => now()->toIso8601String(),
            ];

            Log::info('CartService: Added item to cart', [
                'session_id' => $session->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'price' => $price
            ]);
        }

        // Save cart
        $this->saveCart($session, $cart);

        return [
            'success' => true,
            'message' => 'تم الإضافة للسلة',
            'cart' => $cart,
        ];
    }

    /**
     * Update item quantity in cart
     *
     * @param AiChatSession $session
     * @param int $productId
     * @param int $newQuantity
     * @param array $attributes
     * @return array{success: bool, message: string, cart: array}
     */
    public function updateQuantity(
        AiChatSession $session,
        int $productId,
        int $newQuantity,
        array $attributes = []
    ): array {
        $cart = $this->getCart($session);
        $index = $this->findItemIndex($cart, $productId, $attributes);

        if ($index === null) {
            return [
                'success' => false,
                'message' => 'المنتج غير موجود في السلة',
                'cart' => $cart,
            ];
        }

        // If quantity is 0 or less, remove item
        if ($newQuantity <= 0) {
            return $this->removeItem($session, $productId, $attributes);
        }

        // Validate stock
        $product = Product::find($productId);
        if (!$product) {
            return [
                'success' => false,
                'message' => 'المنتج غير موجود',
                'cart' => $cart,
            ];
        }

        $stockValidation = $this->validateStock($product, $newQuantity, $attributes);
        if (!$stockValidation['valid']) {
            return [
                'success' => false,
                'message' => $stockValidation['message'],
                'cart' => $cart,
            ];
        }

        // Update quantity
        $cart[$index]['quantity'] = $newQuantity;
        $cart[$index]['subtotal'] = $newQuantity * $cart[$index]['price'];

        $this->saveCart($session, $cart);

        Log::info('CartService: Updated quantity', [
            'session_id' => $session->id,
            'product_id' => $productId,
            'new_quantity' => $newQuantity
        ]);

        return [
            'success' => true,
            'message' => 'تم تحديث الكمية',
            'cart' => $cart,
        ];
    }

    /**
     * Remove item from cart
     *
     * @param AiChatSession $session
     * @param int $productId
     * @param array $attributes
     * @return array{success: bool, message: string, cart: array}
     */
    public function removeItem(
        AiChatSession $session,
        int $productId,
        array $attributes = []
    ): array {
        $cart = $this->getCart($session);
        $index = $this->findItemIndex($cart, $productId, $attributes);

        if ($index === null) {
            return [
                'success' => false,
                'message' => 'المنتج غير موجود في السلة',
                'cart' => $cart,
            ];
        }

        // Remove item
        array_splice($cart, $index, 1);
        $this->saveCart($session, $cart);

        Log::info('CartService: Removed item', [
            'session_id' => $session->id,
            'product_id' => $productId
        ]);

        return [
            'success' => true,
            'message' => 'تم حذف المنتج من السلة',
            'cart' => $cart,
        ];
    }

    /**
     * Get cart contents
     *
     * @param AiChatSession $session
     * @return array
     */
    public function getCart(AiChatSession $session): array
    {
        return $session->cart ?? [];
    }

    /**
     * Get cart total
     *
     * @param AiChatSession $session
     * @return int
     */
    public function getTotal(AiChatSession $session): int
    {
        $cart = $this->getCart($session);
        return array_sum(array_column($cart, 'subtotal'));
    }

    /**
     * Get cart item count
     *
     * @param AiChatSession $session
     * @return int
     */
    public function getItemCount(AiChatSession $session): int
    {
        $cart = $this->getCart($session);
        return array_sum(array_column($cart, 'quantity'));
    }

    /**
     * Check if cart is empty
     *
     * @param AiChatSession $session
     * @return bool
     */
    public function isEmpty(AiChatSession $session): bool
    {
        return empty($this->getCart($session));
    }

    /**
     * Clear cart completely
     *
     * @param AiChatSession $session
     * @return void
     */
    public function clearCart(AiChatSession $session): void
    {
        $this->saveCart($session, []);

        Log::info('CartService: Cart cleared', ['session_id' => $session->id]);
    }

    /**
     * Validate stock availability
     *
     * @param Product $product
     * @param int $quantity
     * @param array $attributes
     * @return array{valid: bool, message: string, available: int}
     */
    public function validateStock(Product $product, int $quantity, array $attributes = []): array
    {
        // Check if product is active
        if (!$product->is_active) {
            return [
                'valid' => false,
                'message' => 'هذا المنتج غير متوفر حالياً',
                'available' => 0,
            ];
        }

        // Get available stock (considering attributes if any)
        $available = $this->getAvailableStock($product, $attributes);

        if ($available <= 0) {
            return [
                'valid' => false,
                'message' => 'عذراً، هذا المنتج غير متوفر بالمخزن حالياً',
                'available' => 0,
            ];
        }

        if ($quantity > $available) {
            return [
                'valid' => false,
                'message' => "عذراً، متوفر فقط {$available} قطع من هذا المنتج",
                'available' => $available,
            ];
        }

        return [
            'valid' => true,
            'message' => '',
            'available' => $available,
        ];
    }

    /**
     * Get available stock for product (considering attributes)
     */
    protected function getAvailableStock(Product $product, array $attributes = []): int
    {
        // If no specific attributes, return product quantity
        if (empty($attributes)) {
            return $product->quantity ?? 0;
        }

        // Check attribute-specific stock
        $attribute = $product->attributes()
            ->where(function ($query) use ($attributes) {
                foreach ($attributes as $key => $value) {
                    $query->where('attribute_key', $key)
                          ->where('attribute_value', $value);
                }
            })
            ->first();

        if ($attribute) {
            return $attribute->stock_quantity ?? $product->quantity ?? 0;
        }

        return $product->quantity ?? 0;
    }

    /**
     * Get effective price for product (considering attributes)
     */
    protected function getEffectivePrice(Product $product, array $attributes = []): int
    {
        $basePrice = $product->price;

        if (empty($attributes)) {
            return $basePrice;
        }

        // Check for attribute price modifiers
        $attribute = $product->attributes()
            ->where(function ($query) use ($attributes) {
                foreach ($attributes as $key => $value) {
                    $query->where('attribute_key', $key)
                          ->where('attribute_value', $value);
                }
            })
            ->first();

        if ($attribute && $attribute->price_modifier) {
            return $basePrice + $attribute->price_modifier;
        }

        return $basePrice;
    }

    /**
     * Generate unique key for cart item
     */
    protected function getItemKey(int $productId, array $attributes = []): string
    {
        $key = "product_{$productId}";
        if (!empty($attributes)) {
            ksort($attributes);
            $key .= '_' . md5(json_encode($attributes));
        }
        return $key;
    }

    /**
     * Find item index in cart
     */
    protected function findItemIndex(array $cart, int $productId, array $attributes = []): ?int
    {
        foreach ($cart as $index => $item) {
            if ($item['product_id'] === $productId) {
                // If no attributes specified, match any
                if (empty($attributes)) {
                    return $index;
                }
                // Match with attributes
                if (($item['attributes'] ?? []) == $attributes) {
                    return $index;
                }
            }
        }
        return null;
    }

    /**
     * Save cart to session
     */
    protected function saveCart(AiChatSession $session, array $cart): void
    {
        $session->cart = $cart;
        $session->save();
    }

    /**
     * Get cart summary as text
     */
    public function getCartSummary(AiChatSession $session): string
    {
        $cart = $this->getCart($session);

        if (empty($cart)) {
            return 'فارغة';
        }

        $items = [];
        foreach ($cart as $item) {
            $items[] = "{$item['name']} × {$item['quantity']}";
        }

        return implode('، ', $items);
    }

    /**
     * Get cart items formatted for display
     */
    public function getFormattedCart(AiChatSession $session): array
    {
        $cart = $this->getCart($session);
        $total = 0;

        $formatted = [];
        foreach ($cart as $item) {
            $formatted[] = [
                'product_id' => $item['product_id'] ?? null,
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
                'attributes' => $item['attributes'] ?? [],
            ];
            $total += $item['subtotal'];
        }

        return [
            'items' => $formatted,
            'total' => $total,
            'item_count' => count($formatted),
        ];
    }

    /**
     * Get product from cart by ID
     */
    public function getCartItem(AiChatSession $session, int $productId): ?array
    {
        $cart = $this->getCart($session);

        foreach ($cart as $item) {
            if ($item['product_id'] === $productId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Check if product is in cart
     */
    public function hasProduct(AiChatSession $session, int $productId): bool
    {
        return $this->getCartItem($session, $productId) !== null;
    }
}
