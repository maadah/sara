<?php

namespace App\Services\Chat\Tools;

use App\Models\ChatSession;
use App\Models\Lead;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OrderTool — creates orders, checks status, and handles cancellations.
 *
 * Rules:
 * - Cart must not be empty.
 * - name + phone + address are required.
 * - Phone must pass Iraqi format validation.
 * - Only pending orders can be cancelled.
 * - After order creation the cart is cleared and customer profile updated.
 */
class OrderTool
{
    /**
     * Create a new order from the session's cart.
     *
     * @return array Order details or error.
     */
    public function createOrder(
        ChatSession $session,
        string $customerName,
        string $customerPhone,
        string $customerAddress,
        ?string $notes = null,
    ): array {
        $cart = $session->cart ?? ['items' => []];

        if (empty($cart['items'])) {
            return ['status' => 'error', 'message' => __('chat.order_empty_cart')];
        }

        // Validate phone
        $phonePattern = config('chat.phone.pattern', '/^07[3-9]\d{8}$/');
        if (! preg_match($phonePattern, $customerPhone)) {
            return ['status' => 'error', 'message' => __('chat.invalid_phone')];
        }

        try {
            return DB::transaction(function () use ($session, $cart, $customerName, $customerPhone, $customerAddress, $notes) {

                $order = OnlineOrder::create([
                    'user_id'          => $session->store_id,
                    'lead_id'          => $session->lead_id,
                    'conversation_id'  => $session->conversation_id ? $this->resolveConversationId($session) : null,
                    'customer_name'    => $customerName,
                    'customer_phone'   => $customerPhone,
                    'customer_address' => $customerAddress,
                    'customer_city'    => $session->customer_data['city'] ?? null,
                    'source'           => 'ai_chat',
                    'status'           => 'pending',
                    'subtotal'         => $cart['total'],
                    'shipping_cost'    => $cart['delivery_cost'],
                    'total'            => $cart['grand_total'],
                    'currency'         => 'IQD',
                    'customer_notes'   => $notes,
                    'meta_data'        => ['chat_session_id' => $session->id],
                ]);

                // Create order items
                foreach ($cart['items'] as $item) {
                    OnlineOrderItem::create([
                        'online_order_id' => $order->id,
                        'product_id'      => $item['product_id'],
                        'product_name'    => $item['name'],
                        'unit_price'      => $item['price'],
                        'quantity'        => $item['quantity'],
                        'total'           => $item['subtotal'],
                    ]);
                }

                // Clear cart
                $session->cart = ['items' => [], 'total' => 0, 'delivery_cost' => 0, 'grand_total' => 0];
                $session->save();

                Log::info('Chat Tool: order created', [
                    'session_id'   => $session->id,
                    'order_id'     => $order->id,
                    'order_number' => $order->order_number,
                    'total'        => $order->total,
                ]);

                $deliveryTime = $session->store?->aiSetting?->delivery_time
                    ?? config('chat.cart.delivery_time', '24-48 ساعة');

                return [
                    'status'             => 'created',
                    'order_id'           => $order->id,
                    'order_number'       => $order->order_number,
                    'items_count'        => count($cart['items']),
                    'total'              => $cart['grand_total'],
                    'estimated_delivery' => $deliveryTime,
                ];
            });
        } catch (\Throwable $e) {
            Log::error('Chat Tool: order creation failed', [
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
            ]);

            return ['status' => 'error', 'message' => __('chat.error_tool_failed')];
        }
    }

    /**
     * Check order status by order_id, or latest order for the lead.
     */
    public function getOrderStatus(ChatSession $session, ?int $orderId = null): array
    {
        $query = OnlineOrder::where('user_id', $session->store_id);

        if ($orderId) {
            $query->where('id', $orderId);
        } else {
            $query->where('lead_id', $session->lead_id)
                  ->latest('created_at');
        }

        $order = $query->with('items')->first();

        if (! $order) {
            return ['status' => 'not_found', 'message' => 'ما لكيت طلب بهذا الرقم'];
        }

        return [
            'status'       => 'found',
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
            'order_status' => $order->status,
            'status_label' => $this->statusLabel($order->status),
            'items'        => $order->items->map(fn ($i) => [
                'name'     => $i->product_name,
                'quantity' => $i->quantity,
                'total'    => (int) $i->total,
            ])->all(),
            'total'        => (int) $order->total,
            'created_at'   => $order->created_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * Cancel an order (only if status = pending).
     */
    public function cancelOrder(ChatSession $session, int $orderId): array
    {
        $order = OnlineOrder::where('id', $orderId)
            ->where('user_id', $session->store_id)
            ->first();

        if (! $order) {
            return ['status' => 'not_found'];
        }

        if ($order->status !== 'pending') {
            $label = $this->statusLabel($order->status);
            return [
                'status'  => 'cannot_cancel',
                'reason'  => $label,
                'message' => str_replace(':status', $label, __('chat.order_cancel_failed')),
            ];
        }

        $order->update(['status' => 'cancelled']);

        Log::info('Chat Tool: order cancelled', [
            'session_id' => $session->id,
            'order_id'   => $orderId,
        ]);

        return [
            'status'       => 'cancelled',
            'order_id'     => $orderId,
            'order_number' => $order->order_number,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Private                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Map order status to Arabic label with emoji.
     */
    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending'    => __('chat.status_pending'),
            'confirmed'  => __('chat.status_confirmed'),
            'processing' => __('chat.status_processing'),
            'shipped'    => __('chat.status_shipped'),
            'delivered'  => __('chat.status_delivered'),
            'cancelled'  => __('chat.status_cancelled'),
            'returned'   => __('chat.status_returned'),
            default      => $status,
        };
    }

    /**
     * Resolve the conversation FK from the session's social-media participant ID.
     *
     * ChatSession.conversation_id stores the platform participant_id
     * (i.e. the Facebook/Instagram user ID that was passed into the orchestrator).
     * We therefore look up Conversation by participant_id, not thread_id.
     */
    private function resolveConversationId(ChatSession $session): ?int
    {
        if (! $session->conversation_id) {
            return null;
        }

        $conversation = \App\Models\Conversation::where('participant_id', $session->conversation_id)
            ->where('user_id', $session->store_id)
            ->when(
                $session->channel && $session->channel !== 'web',
                fn ($q) => $q->where('platform', $session->channel)
            )
            ->latest('updated_at')
            ->first();

        return $conversation?->id;
    }
}
