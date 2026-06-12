<?php

namespace App\Services\Conversation;

use App\Enums\ConversationState;
use App\Models\AiChatSession;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Conversation Manager - Orchestrates Conversation Lifecycle
 *
 * Handles:
 * - Session creation and retrieval
 * - Context management (last product, shown products, etc.)
 * - Customer info collection and tracking
 * - Message history management
 * - Session expiration
 */
class ConversationManager
{
    /**
     * Session expiration in hours
     */
    protected const SESSION_EXPIRY_HOURS = 24;

    /**
     * Max messages to keep in history
     */
    protected const MAX_HISTORY_MESSAGES = 20;

    /**
     * Get or create session for a conversation
     *
     * @param User $store
     * @param Lead $lead
     * @param Conversation|null $conversation
     * @return AiChatSession
     */
    public function getOrCreateSession(
        User $store,
        Lead $lead,
        ?Conversation $conversation = null
    ): AiChatSession {
        // Try to find active session
        $session = AiChatSession::where('user_id', $store->id)
            ->where('lead_id', $lead->id)
            ->where('updated_at', '>=', now()->subHours(self::SESSION_EXPIRY_HOURS))
            ->whereNotIn('conversation_state', [
                ConversationState::ORDER_COMPLETED->value,
                ConversationState::CANCELLED->value,
            ])
            ->first();

        if ($session) {
            // Touch to extend expiration
            $session->touch();
            return $session;
        }

        // Create new session
        $session = AiChatSession::create([
            'user_id' => $store->id,
            'lead_id' => $lead->id,
            'conversation_id' => $conversation?->id,
            'conversation_state' => ConversationState::IDLE->value,
            'store_context' => [
                'store_name' => $store->name ?? $store->business_name ?? 'المتجر',
                'delivery_cost' => $store->aiSetting->delivery_cost ?? 5000,
                'delivery_time' => $store->aiSetting->delivery_time ?? 'نفس اليوم',
            ],
            'cart' => [],
            'customer_data' => [],
            'messages' => [],
        ]);

        Log::info('ConversationManager: New session created', [
            'session_id' => $session->id,
            'store_id' => $store->id,
            'lead_id' => $lead->id
        ]);

        return $session;
    }

    /**
     * Get session by ID
     */
    public function getSession(int $sessionId): ?AiChatSession
    {
        return AiChatSession::find($sessionId);
    }

    /**
     * Get current conversation state
     */
    public function getState(AiChatSession $session): ConversationState
    {
        return ConversationState::tryFrom($session->conversation_state ?? 'idle')
            ?? ConversationState::IDLE;
    }

    /**
     * Update conversation context
     *
     * @param AiChatSession $session
     * @param array $context Key-value pairs to merge into context
     */
    public function updateContext(AiChatSession $session, array $context): void
    {
        $storeContext = $session->store_context ?? [];
        $session->store_context = array_merge($storeContext, $context);
        $session->save();
    }

    /**
     * Set last mentioned product
     */
    public function setLastMentionedProduct(AiChatSession $session, Product $product): void
    {
        $this->updateContext($session, [
            'last_mentioned_product' => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
            ],
        ]);

        Log::debug('ConversationManager: Set last product', [
            'session_id' => $session->id,
            'product_id' => $product->id
        ]);
    }

    /**
     * Get last mentioned product
     */
    public function getLastMentionedProduct(AiChatSession $session): ?array
    {
        return $session->store_context['last_mentioned_product'] ?? null;
    }

    /**
     * Set shown products (for reference in selection)
     */
    public function setShownProducts(AiChatSession $session, array $products): void
    {
        $shown = array_map(fn($p) => [
            'id' => $p['id'] ?? $p->id ?? null,
            'name' => $p['name'] ?? $p->name ?? '',
            'price' => $p['price'] ?? $p->price ?? 0,
        ], $products);

        $this->updateContext($session, ['shown_products' => $shown]);
    }

    /**
     * Get shown products
     */
    public function getShownProducts(AiChatSession $session): array
    {
        return $session->store_context['shown_products'] ?? [];
    }

    /**
     * Collect customer info
     *
     * Merges new info with existing, updating Lead if complete
     *
     * @param AiChatSession $session
     * @param array $info Info to collect (name, phone, address, city)
     * @param Lead|null $lead
     */
    public function collectCustomerInfo(
        AiChatSession $session,
        array $info,
        ?Lead $lead = null
    ): void {
        $customerData = $session->customer_data ?? [];

        // Merge new info (don't overwrite existing with empty)
        foreach (['name', 'phone', 'address', 'city', 'notes'] as $field) {
            if (!empty($info[$field])) {
                $customerData[$field] = $info[$field];
            }
        }

        $session->customer_data = $customerData;
        $session->save();

        // Update Lead if we have complete info
        if ($lead && $this->hasCompleteInfo($session)) {
            $lead->name = $customerData['name'] ?? $lead->name;
            $lead->phone = $customerData['phone'] ?? $lead->phone;
            $lead->address = $customerData['address'] ?? $lead->address;
            $lead->city = $customerData['city'] ?? $lead->city;
            $lead->save();
        }

        Log::debug('ConversationManager: Customer info collected', [
            'session_id' => $session->id,
            'collected' => array_keys(array_filter($customerData))
        ]);
    }

    /**
     * Check if all required customer info is collected
     */
    public function hasCompleteInfo(AiChatSession $session, ?Lead $lead = null): bool
    {
        $missing = $this->getMissingInfo($session, $lead);
        return empty($missing);
    }

    /**
     * Get list of missing customer info fields
     */
    public function getMissingInfo(AiChatSession $session, ?Lead $lead = null): array
    {
        $customerData = $session->customer_data ?? [];
        $missing = [];

        // Check name
        $name = $customerData['name'] ?? $lead?->name ?? null;
        if (empty($name)) {
            $missing[] = 'الاسم';
        }

        // Check phone
        $phone = $customerData['phone'] ?? $lead?->phone ?? null;
        if (empty($phone)) {
            $missing[] = 'رقم الهاتف';
        }

        // Check address
        $address = $customerData['address'] ?? $lead?->address ?? null;
        if (empty($address)) {
            $missing[] = 'العنوان';
        }

        return $missing;
    }

    /**
     * Get collected customer data
     */
    public function getCustomerData(AiChatSession $session, ?Lead $lead = null): array
    {
        $sessionData = $session->customer_data ?? [];

        return [
            'name' => $sessionData['name'] ?? $lead?->name ?? null,
            'phone' => $sessionData['phone'] ?? $lead?->phone ?? null,
            'address' => $sessionData['address'] ?? $lead?->address ?? null,
            'city' => $sessionData['city'] ?? $lead?->city ?? null,
            'notes' => $sessionData['notes'] ?? null,
        ];
    }

    /**
     * Add message to history
     */
    public function addMessage(
        AiChatSession $session,
        string $role,
        string $content
    ): void {
        $messages = $session->messages ?? [];

        $messages[] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => now()->toIso8601String(),
        ];

        // Keep only last N messages
        if (count($messages) > self::MAX_HISTORY_MESSAGES) {
            $messages = array_slice($messages, -self::MAX_HISTORY_MESSAGES);
        }

        $session->messages = $messages;
        $session->save();
    }

    /**
     * Get message history
     *
     * @param AiChatSession $session
     * @param int $limit Max messages to return
     * @return array
     */
    public function getMessages(AiChatSession $session, int $limit = 10): array
    {
        $messages = $session->messages ?? [];
        return array_slice($messages, -$limit);
    }

    /**
     * Get messages formatted for AI
     */
    public function getMessagesForAI(AiChatSession $session, int $limit = 10): array
    {
        $messages = $this->getMessages($session, $limit);

        return array_map(fn($m) => [
            'role' => $m['role'],
            'content' => $m['content'],
        ], $messages);
    }

    /**
     * Clear message history
     */
    public function clearMessages(AiChatSession $session): void
    {
        $session->messages = [];
        $session->save();
    }

    /**
     * Reset session for new order
     *
     * Keeps customer info, clears cart and context
     */
    public function resetForNewOrder(AiChatSession $session): void
    {
        $customerData = $session->customer_data;

        $session->conversation_state = ConversationState::IDLE->value;
        $session->cart = [];
        $session->current_order = null;
        $session->store_context = [
            'store_name' => $session->store_context['store_name'] ?? 'المتجر',
            'delivery_cost' => $session->store_context['delivery_cost'] ?? 5000,
            'delivery_time' => $session->store_context['delivery_time'] ?? 'نفس اليوم',
        ];
        // Keep customer data
        $session->customer_data = $customerData;
        $session->messages = [];
        $session->save();

        Log::info('ConversationManager: Session reset for new order', [
            'session_id' => $session->id
        ]);
    }

    /**
     * Check if session is expired
     */
    public function isExpired(AiChatSession $session): bool
    {
        return $session->updated_at->lt(now()->subHours(self::SESSION_EXPIRY_HOURS));
    }

    /**
     * Get session context summary for AI prompts
     */
    public function getContextSummary(AiChatSession $session): array
    {
        $state = $this->getState($session);
        $lastProduct = $this->getLastMentionedProduct($session);
        $customerData = $session->customer_data ?? [];
        $cart = $session->cart ?? [];

        return [
            'state' => $state->value,
            'state_label' => $state->label(),
            'last_product' => $lastProduct ? $lastProduct['name'] : 'لا يوجد',
            'cart_empty' => empty($cart),
            'cart_item_count' => count($cart),
            'has_customer_name' => !empty($customerData['name']),
            'has_customer_phone' => !empty($customerData['phone']),
            'has_customer_address' => !empty($customerData['address']),
            'info_complete' => $this->hasCompleteInfo($session),
        ];
    }

    /**
     * Store current order reference
     */
    public function setCurrentOrder(AiChatSession $session, int $orderId, int $total): void
    {
        $session->current_order = [
            'order_id' => $orderId,
            'total' => $total,
            'created_at' => now()->toIso8601String(),
        ];
        $session->save();
    }

    /**
     * Get current order reference
     */
    public function getCurrentOrder(AiChatSession $session): ?array
    {
        return $session->current_order;
    }

    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions(): int
    {
        $count = AiChatSession::where('updated_at', '<', now()->subHours(self::SESSION_EXPIRY_HOURS * 2))
            ->whereIn('conversation_state', [
                ConversationState::ORDER_COMPLETED->value,
                ConversationState::CANCELLED->value,
                ConversationState::IDLE->value,
            ])
            ->delete();

        Log::info('ConversationManager: Cleaned up expired sessions', ['count' => $count]);

        return $count;
    }
}
