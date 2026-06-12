<?php

namespace App\Services\Conversation;

use App\Enums\ConversationState;
use App\Enums\Intent;
use App\Models\AiChatSession;
use Illuminate\Support\Facades\Log;

/**
 * State Machine for Conversation Flow Control
 *
 * CRITICAL: This class controls ALL state transitions.
 * AI cannot change states directly - only this class can.
 *
 * State Transition Rules (HARD CODED):
 * - IDLE → BROWSING_PRODUCTS (customer asks about products)
 * - BROWSING_PRODUCTS → WAITING_PRODUCT_SELECTION (show products)
 * - WAITING_PRODUCT_SELECTION → ADDING_TO_CART (customer selects product)
 * - ADDING_TO_CART → CART_REVIEW (item added, ask if want more)
 * - CART_REVIEW → WAITING_CUSTOMER_INFO (customer says no more items)
 * - CART_REVIEW → BROWSING_PRODUCTS (customer wants to add more)
 * - WAITING_CUSTOMER_INFO → WAITING_CONFIRMATION (all info collected)
 * - WAITING_CONFIRMATION → ORDER_COMPLETED (customer confirms)
 * - ANY_STATE → CANCELLED (customer explicitly cancels with "الغي الطلب")
 */
class StateMachine
{
    /**
     * Valid state transitions map
     * Format: [current_state => [allowed_next_states]]
     */
    protected const TRANSITIONS = [
        'idle' => [
            'browsing_products',
            'adding_to_cart', // Direct add if product is clear
            'cancelled',
        ],
        'browsing_products' => [
            'waiting_product_selection',
            'adding_to_cart', // Direct add if product is clear
            'cancelled',
        ],
        'waiting_product_selection' => [
            'adding_to_cart',
            'browsing_products', // Customer wants different products
            'cancelled',
        ],
        'adding_to_cart' => [
            'cart_review',
            'browsing_products', // Continue shopping
            'cancelled',
        ],
        'cart_review' => [
            'waiting_customer_info', // Customer done adding
            'browsing_products', // Want more items
            'adding_to_cart', // Direct add from cart review
            'cancelled',
        ],
        'waiting_customer_info' => [
            'waiting_confirmation', // All info collected
            'cart_review', // Back to cart
            'cancelled',
        ],
        'waiting_confirmation' => [
            'order_completed', // Confirmed
            'cancelled',
            'cart_review', // Want to modify cart
            'waiting_customer_info', // Want to update personal info
        ],
        'order_completed' => [
            'idle', // New conversation
            'browsing_products', // New order
        ],
        'cancelled' => [
            'idle', // Fresh start
            'browsing_products', // New order
        ],
    ];

    /**
     * Determine next state based on current state and detected intent
     *
     * @param ConversationState $currentState
     * @param Intent $intent
     * @param array $context Additional context (cart empty, info complete, etc.)
     * @return ConversationState
     */
    public function getNextState(
        ConversationState $currentState,
        Intent $intent,
        array $context = []
    ): ConversationState {
        // CRITICAL: Explicit cancel always goes to CANCELLED
        if ($intent === Intent::CANCEL_ORDER) {
            Log::info('StateMachine: Explicit cancel detected', [
                'from' => $currentState->value,
                'to' => 'cancelled'
            ]);
            return ConversationState::CANCELLED;
        }

        // Process based on current state
        $nextState = match($currentState) {
            ConversationState::IDLE => $this->fromIdle($intent, $context),
            ConversationState::BROWSING_PRODUCTS => $this->fromBrowsing($intent, $context),
            ConversationState::WAITING_PRODUCT_SELECTION => $this->fromWaitingSelection($intent, $context),
            ConversationState::ADDING_TO_CART => $this->fromAddingToCart($intent, $context),
            ConversationState::CART_REVIEW => $this->fromCartReview($intent, $context),
            ConversationState::WAITING_CUSTOMER_INFO => $this->fromWaitingInfo($intent, $context),
            ConversationState::WAITING_CONFIRMATION => $this->fromWaitingConfirmation($intent, $context),
            ConversationState::ORDER_COMPLETED => $this->fromCompleted($intent, $context),
            ConversationState::CANCELLED => $this->fromCancelled($intent, $context),
        };

        // Validate transition is allowed
        if (!$this->canTransition($currentState, $nextState)) {
            Log::warning('StateMachine: Invalid transition attempted', [
                'from' => $currentState->value,
                'to' => $nextState->value,
                'intent' => $intent->value
            ]);
            return $currentState; // Stay in current state
        }

        Log::info('StateMachine: State transition', [
            'from' => $currentState->value,
            'to' => $nextState->value,
            'intent' => $intent->value
        ]);

        return $nextState;
    }

    /**
     * Check if transition from one state to another is allowed
     */
    public function canTransition(ConversationState $from, ConversationState $to): bool
    {
        // Same state is always allowed (no change)
        if ($from === $to) {
            return true;
        }

        $allowedTransitions = self::TRANSITIONS[$from->value] ?? [];
        return in_array($to->value, $allowedTransitions);
    }

    /**
     * Transition from IDLE state
     */
    protected function fromIdle(Intent $intent, array $context): ConversationState
    {
        return match($intent) {
            Intent::GREETING => ConversationState::IDLE, // Stay, respond with greeting
            Intent::BROWSE_PRODUCTS,
            Intent::ASK_PRICE,
            Intent::SELECT_PRODUCT => ConversationState::BROWSING_PRODUCTS,
            Intent::ADD_TO_CART => ConversationState::ADDING_TO_CART,
            default => ConversationState::IDLE,
        };
    }

    /**
     * Transition from BROWSING_PRODUCTS state
     */
    protected function fromBrowsing(Intent $intent, array $context): ConversationState
    {
        return match($intent) {
            Intent::SELECT_PRODUCT,
            Intent::ADD_TO_CART => ConversationState::ADDING_TO_CART,
            Intent::BROWSE_PRODUCTS,
            Intent::ASK_PRICE,
            Intent::REQUEST_IMAGE,
            Intent::ASK_QUESTION => ConversationState::BROWSING_PRODUCTS, // Stay
            default => ConversationState::BROWSING_PRODUCTS, // Stay in browsing, don't jump to selection
        };
    }

    /**
     * Transition from WAITING_PRODUCT_SELECTION state
     */
    protected function fromWaitingSelection(Intent $intent, array $context): ConversationState
    {
        return match($intent) {
            Intent::SELECT_PRODUCT => ConversationState::WAITING_PRODUCT_SELECTION, // Stay: show details first
            Intent::ADD_TO_CART,
            Intent::CONFIRM_ORDER => ConversationState::ADDING_TO_CART, // "نعم" means add it
            Intent::BROWSE_PRODUCTS => ConversationState::BROWSING_PRODUCTS,
            Intent::DECLINE_MORE => ConversationState::BROWSING_PRODUCTS, // Don't want this, show others
            default => ConversationState::WAITING_PRODUCT_SELECTION,
        };
    }

    /**
     * Transition from ADDING_TO_CART state
     */
    protected function fromAddingToCart(Intent $intent, array $context): ConversationState
    {
        // After adding, always go to cart review
        return ConversationState::CART_REVIEW;
    }

    /**
     * Transition from CART_REVIEW state
     *
     * CRITICAL: "لا شكرا" here means done adding, NOT cancel!
     */
    protected function fromCartReview(Intent $intent, array $context): ConversationState
    {
        $cartEmpty = $context['cart_empty'] ?? false;

        return match($intent) {
            // CRITICAL: Decline more = proceed to checkout (NOT cancel)
            Intent::DECLINE_MORE => $cartEmpty
                ? ConversationState::CART_REVIEW  // Stay if cart empty
                : ConversationState::WAITING_CUSTOMER_INFO,

            Intent::WANT_MORE,
            Intent::BROWSE_PRODUCTS,
            Intent::SELECT_PRODUCT => ConversationState::BROWSING_PRODUCTS,

            Intent::ADD_TO_CART => ConversationState::ADDING_TO_CART,

            // Price inquiry from cart → browse to find product info
            Intent::ASK_PRICE => ConversationState::BROWSING_PRODUCTS,

            // Cart modifications stay in cart review
            Intent::UPDATE_QUANTITY,
            Intent::REMOVE_FROM_CART => ConversationState::CART_REVIEW,

            Intent::CONFIRM_ORDER => $cartEmpty
                ? ConversationState::CART_REVIEW
                : ConversationState::WAITING_CUSTOMER_INFO,

            default => ConversationState::CART_REVIEW,
        };
    }

    /**
     * Transition from WAITING_CUSTOMER_INFO state
     */
    protected function fromWaitingInfo(Intent $intent, array $context): ConversationState
    {
        $infoComplete = $context['info_complete'] ?? false;

        return match($intent) {
            Intent::PROVIDE_INFO => $infoComplete
                ? ConversationState::WAITING_CONFIRMATION
                : ConversationState::WAITING_CUSTOMER_INFO, // Need more info

            Intent::CONFIRM_ORDER => $infoComplete
                ? ConversationState::WAITING_CONFIRMATION
                : ConversationState::WAITING_CUSTOMER_INFO,

            default => ConversationState::WAITING_CUSTOMER_INFO,
        };
    }

    /**
     * Transition from WAITING_CONFIRMATION state
     */
    protected function fromWaitingConfirmation(Intent $intent, array $context): ConversationState
    {
        return match($intent) {
            Intent::CONFIRM_ORDER => ConversationState::ORDER_COMPLETED,
            Intent::DECLINE_MORE => ConversationState::CART_REVIEW, // Want to modify
            Intent::UPDATE_QUANTITY,
            Intent::REMOVE_FROM_CART => ConversationState::CART_REVIEW,
            default => ConversationState::WAITING_CONFIRMATION,
        };
    }

    /**
     * Transition from ORDER_COMPLETED state
     */
    protected function fromCompleted(Intent $intent, array $context): ConversationState
    {
        return match($intent) {
            Intent::GREETING => ConversationState::IDLE,
            Intent::BROWSE_PRODUCTS,
            Intent::SELECT_PRODUCT => ConversationState::BROWSING_PRODUCTS,
            Intent::ORDER_STATUS => ConversationState::ORDER_COMPLETED, // Stay, show status
            default => ConversationState::IDLE,
        };
    }

    /**
     * Transition from CANCELLED state
     */
    protected function fromCancelled(Intent $intent, array $context): ConversationState
    {
        return match($intent) {
            Intent::GREETING => ConversationState::IDLE,
            Intent::BROWSE_PRODUCTS,
            Intent::SELECT_PRODUCT => ConversationState::BROWSING_PRODUCTS,
            default => ConversationState::IDLE,
        };
    }

    /**
     * Apply state transition to session
     */
    public function transition(
        AiChatSession $session,
        Intent $intent,
        array $context = []
    ): ConversationState {
        $currentState = ConversationState::tryFrom($session->conversation_state ?? 'idle')
            ?? ConversationState::IDLE;

        $newState = $this->getNextState($currentState, $intent, $context);

        // Persist state change
        $session->conversation_state = $newState->value;
        $session->save();

        return $newState;
    }

    /**
     * Get current state from session
     */
    public function getCurrentState(AiChatSession $session): ConversationState
    {
        return ConversationState::tryFrom($session->conversation_state ?? 'idle')
            ?? ConversationState::IDLE;
    }

    /**
     * Force set state (for admin/debug purposes only)
     */
    public function forceState(AiChatSession $session, ConversationState $state): void
    {
        Log::warning('StateMachine: Force state change', [
            'session_id' => $session->id,
            'from' => $session->conversation_state,
            'to' => $state->value
        ]);

        $session->conversation_state = $state->value;
        $session->save();
    }
}
