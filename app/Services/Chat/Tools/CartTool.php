<?php

namespace App\Services\Chat\Tools;

use App\Models\AiSetting;
use App\Models\ChatSession;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * CartTool — manages the session-based shopping cart (JSON in chat_sessions.cart).
 *
 * Rules:
 * - Stock must be checked before adding.
 * - After any change, totals are recalculated.
 * - Max items per cart is read from store settings.
 * - Cart display uses Iraqi Arabic formatting with numbering.
 */
class CartTool
{
    /**
     * Get the current cart contents.
     *
     * @return array The cart structure or an empty cart.
     */
    public function getCart(ChatSession $session): array
    {
        $cart = $session->cart;

        if (empty($cart['items'])) {
            return [
                'items'         => [],
                'total'         => 0,
                'delivery_cost' => $this->getDeliveryCost($session->store_id),
                'grand_total'   => 0,
                'status'        => 'empty',
            ];
        }

        return $cart;
    }

    /**
     * Add a product to the cart.
     *
     * @return array Updated cart or error structure.
     */
    public function addToCart(
        ChatSession $session,
        int $productId,
        int $quantity = 1,
        ?string $color = null,
        ?string $size = null,
    ): array {
        // Primary lookup: by product_id scoped to this store
        $product = $productId > 0
            ? Product::where('id', $productId)
                ->where('user_id', $session->store_id)
                ->where('is_active', true)
                ->first()
            : null;

        if (! $product) {
            // Tell the AI to re-search instead of saying "غير متوفر"
            return [
                'status'  => 'not_found',
                'message' => 'product_id غير صحيح أو المنتج غير نشط. استدعِ search_products الآن للحصول على product_id الصحيح ثم أعد استدعاء add_to_cart',
            ];
        }

        if ($product->quantity <= 0) {
            return ['status' => 'out_of_stock', 'product' => $product->name];
        }

        if ($product->quantity < $quantity) {
            return [
                'status'    => 'insufficient_stock',
                'product'   => $product->name,
                'available' => $product->quantity,
                'requested' => $quantity,
            ];
        }

        $cart  = $session->cart ?? ['items' => [], 'total' => 0, 'delivery_cost' => 0, 'grand_total' => 0];
        $items = $cart['items'] ?? [];

        // Check max items
        $maxItems = $this->getMaxCartItems($session->store_id);
        if (count($items) >= $maxItems) {
            return ['status' => 'max_items_reached', 'max' => $maxItems];
        }

        // Check if product already in cart — update quantity
        $found = false;
        foreach ($items as &$item) {
            if ($item['product_id'] === $productId) {
                $newQty = $item['quantity'] + $quantity;
                if ($newQty > $product->quantity) {
                    return [
                        'status'    => 'insufficient_stock',
                        'product'   => $product->name,
                        'available' => $product->quantity,
                        'requested' => $newQty,
                    ];
                }
                $item['quantity'] = $newQty;
                $item['subtotal'] = (int) $product->price * $newQty;
                if ($color) $item['color'] = $color;
                if ($size) $item['size'] = $size;
                $found = true;
                break;
            }
        }
        unset($item);

        if (! $found) {
            $items[] = [
                'product_id' => $productId,
                'name'       => $product->name,
                'price'      => (int) $product->price,
                'quantity'   => $quantity,
                'color'      => $color,
                'size'       => $size,
                'subtotal'   => (int) $product->price * $quantity,
            ];
        }

        $cart = $this->recalculate($items, $session->store_id);
        $session->cart = $cart;
        $session->cart_abandoned_at = null; // Reset abandonment flag
        $session->save();

        Log::info('Chat Tool: add_to_cart', [
            'session_id' => $session->id,
            'product_id' => $productId,
            'quantity'   => $quantity,
            'cart_total'  => $cart['grand_total'],
        ]);

        return array_merge($cart, ['status' => 'added', 'product_name' => $product->name]);
    }

    /**
     * Update quantity of a product already in the cart.
     */
    public function updateCartItem(ChatSession $session, int $productId, int $quantity): array
    {
        $cart  = $session->cart ?? ['items' => []];
        $items = $cart['items'] ?? [];

        $found = false;
        foreach ($items as &$item) {
            if ($item['product_id'] === $productId) {
                $product = Product::find($productId);
                if ($product && $quantity > $product->quantity) {
                    return [
                        'status'    => 'insufficient_stock',
                        'product'   => $item['name'],
                        'available' => $product->quantity,
                    ];
                }
                $item['quantity'] = $quantity;
                $item['subtotal'] = $item['price'] * $quantity;
                $found = true;
                break;
            }
        }
        unset($item);

        if (! $found) {
            return ['status' => 'not_in_cart'];
        }

        $cart = $this->recalculate($items, $session->store_id);
        $session->cart = $cart;
        $session->save();

        return array_merge($cart, ['status' => 'updated']);
    }

    /**
     * Remove a product from the cart.
     */
    public function removeFromCart(ChatSession $session, int $productId): array
    {
        $cart  = $session->cart ?? ['items' => []];
        $items = $cart['items'] ?? [];

        $items = array_values(array_filter($items, fn ($i) => $i['product_id'] !== $productId));

        $cart = $this->recalculate($items, $session->store_id);
        $session->cart = $cart;
        $session->save();

        return array_merge($cart, ['status' => 'removed']);
    }

    /**
     * Empty the entire cart.
     */
    public function clearCart(ChatSession $session): array
    {
        $session->cart = [
            'items'         => [],
            'total'         => 0,
            'delivery_cost' => $this->getDeliveryCost($session->store_id),
            'grand_total'   => 0,
        ];
        $session->save();

        return ['status' => 'cleared'];
    }

    /**
     * Format cart for display to the customer (Arabic).
     */
    public function formatCartDisplay(array $cart): string
    {
        if (empty($cart['items'])) {
            return __('chat.cart_empty');
        }

        $lines   = ["━━━━━━━━━━━━━━━", __('chat.cart_header')];
        $counter = 1;

        foreach ($cart['items'] as $item) {
            $extra = '';
            if (! empty($item['color'])) $extra .= " ({$item['color']})";
            if (! empty($item['size'])) $extra .= " [{$item['size']}]";

            $lines[] = "{$counter}. {$item['name']}{$extra} × {$item['quantity']} = " . number_format($item['subtotal']) . ' د.ع';
            $counter++;
        }

        $lines[] = '━━━━━━━━━━━━━━━';
        $lines[] = __('chat.cart_total_label') . ': ' . number_format($cart['total']) . ' د.ع';
        $lines[] = __('chat.cart_delivery_label') . ': ' . number_format($cart['delivery_cost']) . ' د.ع';
        $lines[] = __('chat.cart_grand_total_label') . ': ' . number_format($cart['grand_total']) . ' د.ع';
        $lines[] = '━━━━━━━━━━━━━━━';

        return implode("\n", $lines);
    }

    /* ------------------------------------------------------------------ */
    /* Private helpers                                                     */
    /* ------------------------------------------------------------------ */

    private function recalculate(array $items, int $storeId): array
    {
        $total        = array_sum(array_column($items, 'subtotal'));
        $deliveryCost = count($items) > 0 ? $this->getDeliveryCost($storeId) : 0;

        return [
            'items'         => $items,
            'total'         => $total,
            'delivery_cost' => $deliveryCost,
            'grand_total'   => $total + $deliveryCost,
        ];
    }

    private function getDeliveryCost(int $storeId): int
    {
        $settings = AiSetting::where('user_id', $storeId)->first();

        return (int) ($settings?->delivery_cost ?? config('chat.cart.delivery_cost', 5000));
    }

    private function getMaxCartItems(int $storeId): int
    {
        $settings = AiSetting::where('user_id', $storeId)->first();

        return (int) ($settings?->max_cart_items ?? config('chat.cart.max_items', 10));
    }
}
