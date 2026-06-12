<?php

namespace App\Services\Orders;

use App\Enums\ConversationState;
use App\Events\NewOrderReceived;
use App\Models\AiChatSession;
use App\Models\Lead;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Conversation\StateMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Order Service - Business Logic Layer
 *
 * Handles order creation with transaction safety.
 * AI cannot create orders directly - only through this service.
 *
 * RULES:
 * - All-or-nothing transactions (atomic)
 * - Stock reduction on creation
 * - Order verification after creation
 * - Deduplication by cart hash
 */
class OrderService
{
    protected CartService $cartService;
    protected StateMachine $stateMachine;

    public function __construct(CartService $cartService, StateMachine $stateMachine)
    {
        $this->cartService = $cartService;
        $this->stateMachine = $stateMachine;
    }

    /**
     * Create order from session cart
     *
     * @param AiChatSession $session
     * @param User $store
     * @param Lead $customer
     * @return array{success: bool, order: ?OnlineOrder, message: string}
     */
    public function createOrder(
        AiChatSession $session,
        User $store,
        Lead $customer
    ): array {
        // Validate cart is not empty
        if ($this->cartService->isEmpty($session)) {
            return [
                'success' => false,
                'order' => null,
                'message' => 'السلة فارغة، لا يمكن إنشاء طلب',
            ];
        }

        // Validate customer info
        $infoValidation = $this->validateCustomerInfo($session, $customer);
        if (!$infoValidation['valid']) {
            return [
                'success' => false,
                'order' => null,
                'message' => $infoValidation['message'],
            ];
        }

        // Check for duplicate order (same cart hash within 5 minutes)
        $cartHash = $this->getCartHash($session);
        $duplicate = $this->checkDuplicateOrder($customer->id, $cartHash);
        if ($duplicate) {
            Log::warning('OrderService: Duplicate order attempt', [
                'session_id' => $session->id,
                'existing_order_id' => $duplicate->id
            ]);
            return [
                'success' => true,
                'order' => $duplicate,
                'message' => 'الطلب موجود مسبقاً',
            ];
        }

        // Start transaction
        DB::beginTransaction();

        try {
            // Get cart data
            $cart = $this->cartService->getFormattedCart($session);
            $subtotal = $cart['total'];
            $deliveryInfo = $this->getDeliveryInfo($store);
            $deliveryCost = $deliveryInfo['cost'];
            $total = $subtotal + $deliveryCost;

            // Get customer data
            $customerData = $this->getCustomerData($session, $customer);

            // Create order
            $order = OnlineOrder::create([
                'user_id' => $store->id,
                'lead_id' => $customer->id,
                'conversation_id' => $session->conversation_id,
                'customer_name' => $customerData['name'],
                'customer_phone' => $customerData['phone'],
                'customer_address' => $customerData['address'],
                'customer_city' => $customerData['city'] ?? null,
                'subtotal' => $subtotal,
                'delivery_cost' => $deliveryCost,
                'total' => $total,
                'status' => 'pending',
                'cart_hash' => $cartHash,
                'notes' => $customerData['notes'] ?? null,
                'source' => 'ai_chat',
            ]);

            // Create order items and reduce stock
            foreach ($cart['items'] as $item) {
                // Create order item - use unit_price and total (not price/subtotal)
                OnlineOrderItem::create([
                    'online_order_id' => $order->id,
                    'product_id' => $item['product_id'] ?? null,
                    'product_name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'] ?? $item['unit_price'] ?? 0,
                    'total' => $item['subtotal'] ?? $item['total'] ?? 0,
                ]);

                // Reduce stock
                if (isset($item['product_id'])) {
                    $this->reduceStock($item['product_id'], $item['quantity'], $item['attributes'] ?? []);
                }
            }

            // Clear cart
            $this->cartService->clearCart($session);

            // Update session state
            $session->conversation_state = ConversationState::ORDER_CONFIRMED->value;
            $session->current_order = [
                'order_id' => $order->id,
                'total' => $total,
                'created_at' => now()->toIso8601String(),
            ];
            $session->save();

            // Update customer stats
            $customer->total_orders = (int) ($customer->total_orders ?? 0) + 1;
            $customer->total_spent = (string) (($customer->total_spent ?? 0) + $total);
            $customer->save();

            DB::commit();

            Log::info('OrderService: Order created successfully', [
                'order_id' => $order->id,
                'session_id' => $session->id,
                'customer_id' => $customer->id,
                'total' => $total
            ]);

            // Fire broadcast event for real-time audio notification
            try {
                event(new NewOrderReceived($store->id));
            } catch (\Throwable $e) {
                Log::warning('OrderService: Failed to broadcast NewOrderReceived', ['error' => $e->getMessage()]);
            }

            return [
                'success' => true,
                'order' => $order,
                'message' => 'تم إنشاء الطلب بنجاح',
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('OrderService: Order creation failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'order' => null,
                'message' => 'حدث خطأ أثناء إنشاء الطلب، يرجى المحاولة مرة أخرى',
            ];
        }
    }

    /**
     * Verify order exists and is valid
     */
    public function verifyOrder(int $orderId): bool
    {
        $order = OnlineOrder::find($orderId);

        if (!$order) {
            return false;
        }

        // Check order has items
        if ($order->items()->count() === 0) {
            Log::error('OrderService: Order verification failed - no items', [
                'order_id' => $orderId
            ]);
            return false;
        }

        return true;
    }

    /**
     * Cancel order
     */
    public function cancelOrder(int $orderId, string $reason = ''): array
    {
        $order = OnlineOrder::find($orderId);

        if (!$order) {
            return [
                'success' => false,
                'message' => 'الطلب غير موجود',
            ];
        }

        // Only pending orders can be cancelled
        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return [
                'success' => false,
                'message' => 'لا يمكن إلغاء هذا الطلب',
            ];
        }

        DB::beginTransaction();

        try {
            // Restore stock for each item
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    $this->restoreStock($item->product_id, $item->quantity);
                }
            }

            // Update order status
            $order->status = 'cancelled';
            $order->notes = ($order->notes ? $order->notes . "\n" : '') . "سبب الإلغاء: {$reason}";
            $order->save();

            // Update customer stats
            $customer = $order->lead;
            if ($customer) {
                $customer->total_orders = max(0, ($customer->total_orders ?? 1) - 1);
                $customer->total_spent = max(0, ($customer->total_spent ?? $order->total) - $order->total);
                $customer->save();
            }

            DB::commit();

            Log::info('OrderService: Order cancelled', [
                'order_id' => $orderId,
                'reason' => $reason
            ]);

            return [
                'success' => true,
                'message' => 'تم إلغاء الطلب',
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('OrderService: Order cancellation failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إلغاء الطلب',
            ];
        }
    }

    /**
     * Get order by ID
     */
    public function getOrder(int $orderId): ?OnlineOrder
    {
        return OnlineOrder::with('items')->find($orderId);
    }

    /**
     * Get customer's recent orders
     */
    public function getCustomerOrders(int $customerId, int $limit = 5): array
    {
        return OnlineOrder::where('lead_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get order status text in Arabic
     */
    public function getStatusText(string $status): string
    {
        return match($status) {
            'pending' => 'قيد الانتظار',
            'confirmed' => 'مؤكد',
            'shipped' => 'تم الشحن',
            'delivered' => 'تم التوصيل',
            'cancelled' => 'ملغي',
            default => 'غير معروف',
        };
    }

    /**
     * Validate customer info is complete
     */
    protected function validateCustomerInfo(AiChatSession $session, Lead $customer): array
    {
        $customerData = $session->customer_data ?? [];
        $missing = [];

        // Check name
        $name = $customerData['name'] ?? $customer->name ?? null;
        if (empty($name)) {
            $missing[] = 'الاسم';
        }

        // Check phone
        $phone = $customerData['phone'] ?? $customer->phone ?? null;
        if (empty($phone)) {
            $missing[] = 'رقم الهاتف';
        }

        // Check address
        $address = $customerData['address'] ?? $customer->address ?? null;
        if (empty($address)) {
            $missing[] = 'العنوان';
        }

        if (!empty($missing)) {
            return [
                'valid' => false,
                'message' => 'معلومات ناقصة: ' . implode('، ', $missing),
                'missing' => $missing,
            ];
        }

        return [
            'valid' => true,
            'message' => '',
            'missing' => [],
        ];
    }

    /**
     * Get combined customer data from session and lead
     */
    protected function getCustomerData(AiChatSession $session, Lead $customer): array
    {
        $sessionData = $session->customer_data ?? [];

        return [
            'name' => $sessionData['name'] ?? $customer->name ?? '',
            'phone' => $sessionData['phone'] ?? $customer->phone ?? '',
            'address' => $sessionData['address'] ?? $customer->address ?? '',
            'city' => $sessionData['city'] ?? $customer->city ?? '',
            'notes' => $sessionData['notes'] ?? '',
        ];
    }

    /**
     * Get delivery info from store settings
     */
    protected function getDeliveryInfo(User $store): array
    {
        $settings = $store->aiSetting;

        return [
            'cost' => $settings->delivery_cost ?? 5000,
            'time' => $settings->delivery_time ?? 'نفس اليوم',
        ];
    }

    /**
     * Generate hash for cart contents (deduplication)
     */
    protected function getCartHash(AiChatSession $session): string
    {
        $cart = $this->cartService->getCart($session);

        // Sort and normalize for consistent hashing
        $normalized = array_map(function ($item) {
            return [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'attributes' => $item['attributes'] ?? [],
            ];
        }, $cart);

        usort($normalized, fn($a, $b) => $a['product_id'] <=> $b['product_id']);

        return md5(json_encode($normalized));
    }

    /**
     * Check for duplicate order (same cart within 5 minutes)
     */
    protected function checkDuplicateOrder(int $customerId, string $cartHash): ?OnlineOrder
    {
        return OnlineOrder::where('lead_id', $customerId)
            ->where('cart_hash', $cartHash)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->whereIn('status', ['pending', 'confirmed'])
            ->first();
    }

    /**
     * Reduce product stock
     */
    protected function reduceStock(int $productId, int $quantity, array $attributes = []): void
    {
        $product = Product::find($productId);

        if (!$product) {
            return;
        }

        // If specific attributes, try to reduce attribute stock first
        if (!empty($attributes)) {
            /** @var \App\Models\ProductAttribute|null $attribute */
            $attribute = $product->attributes()
                ->where(function ($query) use ($attributes) {
                    foreach ($attributes as $key => $value) {
                        $query->where('attribute_key', $key)
                              ->where('attribute_value', $value);
                    }
                })
                ->first();

            if ($attribute) {
                $attribute->stock_quantity = max(0, ($attribute->stock_quantity ?? 0) - $quantity);
                $attribute->save();
                return;
            }
        }

        // Reduce main product stock
        $product->quantity = max(0, ($product->quantity ?? 0) - $quantity);
        $product->save();
    }

    /**
     * Restore product stock (for cancellations)
     */
    protected function restoreStock(int $productId, int $quantity): void
    {
        $product = Product::find($productId);

        if (!$product) {
            return;
        }

        $product->quantity = ($product->quantity ?? 0) + $quantity;
        $product->save();
    }

    /**
     * Build order summary for display
     */
    public function buildOrderSummary(OnlineOrder $order): array
    {
        $items = $order->items->map(function ($item) {
            return [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'subtotal' => $item->subtotal,
            ];
        })->toArray();

        return [
            'order_id' => $order->id,
            'items' => $items,
            'subtotal' => $order->subtotal,
            'delivery_cost' => $order->delivery_cost,
            'total' => $order->total,
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'address' => $order->customer_address,
            ],
            'status' => $order->status,
            'status_text' => $this->getStatusText($order->status),
            'created_at' => $order->created_at->toIso8601String(),
        ];
    }
}
