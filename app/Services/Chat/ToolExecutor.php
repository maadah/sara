<?php

namespace App\Services\Chat;

use App\Models\ChatSession;
use App\Services\Chat\Tools\CartTool;
use App\Services\Chat\Tools\CustomerTool;
use App\Services\Chat\Tools\OrderTool;
use App\Services\Chat\Tools\ProductSearchTool;
use App\Services\Chat\Tools\StoreTool;
use Illuminate\Support\Facades\Log;

/**
 * ToolExecutor — routes OpenAI function-call requests to the correct Tool service.
 *
 * For every tool call returned by the AI, this class:
 * 1. Validates inputs.
 * 2. Dispatches to the correct Tool class method.
 * 3. Measures execution time.
 * 4. Logs the call with parameters, result summary, and duration.
 * 5. Returns a structured result ready for injection as a "tool" role message.
 */
class ToolExecutor
{
    public function __construct(
        private readonly ProductSearchTool $productSearch,
        private readonly CartTool $cart,
        private readonly OrderTool $order,
        private readonly CustomerTool $customer,
        private readonly StoreTool $store,
    ) {}

    /**
     * Execute a single tool call.
     *
     * @param  string       $toolName   The function name from the AI response.
     * @param  array        $arguments  The parsed arguments from the AI response.
     * @param  ChatSession  $session    The current chat session.
     * @return array        The tool result to inject back as a "tool" message.
     */
    public function execute(string $toolName, array $arguments, ChatSession $session): array
    {
        $start = microtime(true);

        try {
            $result = $this->dispatch($toolName, $arguments, $session);
        } catch (\Throwable $e) {
            Log::error('Chat: tool execution failed', [
                'tool'       => $toolName,
                'arguments'  => $arguments,
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
            ]);

            $result = [
                'error'   => 'tool_failed',
                'tool'    => $toolName,
                'message' => __('chat.error_tool_failed'),
            ];
        }

        $durationMs = round((microtime(true) - $start) * 1000, 2);

        Log::info('Chat: tool executed', [
            'tool'          => $toolName,
            'arguments'     => $arguments,
            'session_id'    => $session->id,
            'duration_ms'   => $durationMs,
            'result_status' => $result['status'] ?? ($result['error'] ?? 'ok'),
        ]);

        return $result;
    }

    /**
     * Execute multiple tool calls and return results keyed by call ID.
     *
     * @param  array       $toolCalls Array of tool call objects from OpenAI response.
     * @param  ChatSession $session
     * @return array       Array of [call_id => result].
     */
    public function executeMany(array $toolCalls, ChatSession $session): array
    {
        $results = [];

        foreach ($toolCalls as $call) {
            $callId    = $call['id'] ?? uniqid('tool_');
            $name      = $call['function']['name'] ?? '';
            $arguments = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];

            $results[$callId] = [
                'name'   => $name,
                'result' => $this->execute($name, $arguments, $session),
            ];
        }

        return $results;
    }

    /* ------------------------------------------------------------------ */
    /* Tool dispatch router                                                */
    /* ------------------------------------------------------------------ */

    private function dispatch(string $toolName, array $args, ChatSession $session): array
    {
        return match ($toolName) {
            'search_products'      => $this->productSearch->search(
                $session->store_id,
                $args['query'] ?? '',
                $args['category_id'] ?? null,
                $args['limit'] ?? 5,
                isset($args['min_price']) ? (int) $args['min_price'] : null,
                isset($args['max_price']) ? (int) $args['max_price'] : null,
            ),

            'get_categories'       => ['categories' => $this->productSearch->getCategories($session->store_id)],

            'get_product_details'  => $this->handleProductDetails($session, $args),

            'get_cart'             => $this->cart->getCart($session),

            'add_to_cart'          => $this->cart->addToCart(
                $session,
                $args['product_id'] ?? 0,
                $args['quantity'] ?? 1,
                $args['color'] ?? null,
                $args['size'] ?? null,
            ),

            'update_cart_item'     => $this->cart->updateCartItem(
                $session,
                $args['product_id'] ?? 0,
                $args['quantity'] ?? 1,
            ),

            'remove_from_cart'     => $this->cart->removeFromCart($session, $args['product_id'] ?? 0),

            'clear_cart'           => $this->cart->clearCart($session),

            'get_customer_profile' => $this->customer->getProfile($session),

            'save_customer_data'   => $this->customer->saveData(
                $session,
                $args['name'] ?? null,
                $args['phone'] ?? null,
                $args['address'] ?? null,
                $args['city'] ?? null,
                $args['notes'] ?? null,
                // Demographics
                isset($args['age']) ? (int) $args['age'] : null,
                $args['gender'] ?? null,
                isset($args['budget_min']) ? (int) $args['budget_min'] : null,
                isset($args['budget_max']) ? (int) $args['budget_max'] : null,
                $args['occupation'] ?? null,
                $args['marital_status'] ?? null,
                isset($args['interests']) ? (array) $args['interests'] : null,
                $args['social_platform'] ?? null,
            ),

            'create_order'         => $this->order->createOrder(
                $session,
                $args['customer_name'] ?? '',
                $args['customer_phone'] ?? '',
                $args['customer_address'] ?? '',
                $args['notes'] ?? null,
            ),

            'get_order_status'     => $this->order->getOrderStatus($session, $args['order_id'] ?? null),

            'cancel_order'         => $this->order->cancelOrder($session, $args['order_id'] ?? 0),

            'get_store_info'       => $this->store->getStoreInfo($session->store_id),

            default                => [
                'error'   => 'unknown_tool',
                'tool'    => $toolName,
                'message' => "Tool '{$toolName}' is not registered.",
            ],
        };
    }

    private function handleProductDetails(ChatSession $session, array $args): array
    {
        $productId = $args['product_id'] ?? 0;
        $details   = $this->productSearch->getProductDetails($session->store_id, $productId);

        if (! $details) {
            return ['status' => 'not_found', 'product_id' => $productId];
        }

        return $details;
    }
}
