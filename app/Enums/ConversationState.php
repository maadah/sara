<?php

namespace App\Enums;

/**
 * Conversation State Machine States
 *
 * Defines all possible states for a customer conversation in the AI marketing chatbot.
 * State transitions are managed exclusively by StateMachine — the AI never changes states directly.
 * Every transition is logged for analytics and debugging.
 *
 * @see \App\Services\Chat\StateMachine
 */
enum ConversationState: string
{
    /** No active conversation, fresh start */
    case IDLE = 'idle';

    /** Just greeted, waiting for first request */
    case GREETING = 'greeting';

    /** Customer is looking at products / categories */
    case BROWSING = 'browsing';

    /** A specific product is in focus */
    case PRODUCT_SELECTED = 'product_selected';

    /** Waiting for color / size / variant selection */
    case COLLECTING_VARIANT = 'collecting_variant';

    /** In the process of adding an item to cart */
    case ADDING_TO_CART = 'adding_to_cart';

    /** Customer is reviewing their cart */
    case CART_REVIEW = 'cart_review';

    /** Collecting customer name / phone / address */
    case COLLECTING_INFO = 'collecting_info';

    /** Showing order summary, waiting for confirm / cancel */
    case AWAITING_CONFIRMATION = 'awaiting_confirmation';

    /** Order was placed successfully */
    case ORDER_CONFIRMED = 'order_confirmed';

    /** Order was cancelled */
    case ORDER_CANCELLED = 'order_cancelled';

    /** Bot was unsure, asked customer to clarify */
    case AWAITING_CLARIFICATION = 'awaiting_clarification';

    /** Escalated to human agent */
    case HUMAN_HANDOVER = 'human_handover';

    /* ------------------------------------------------------------------ */

    /**
     * Human-readable Arabic label for this state.
     */
    public function label(): string
    {
        return match ($this) {
            self::IDLE                    => 'جديد',
            self::GREETING                => 'ترحيب',
            self::BROWSING                => 'يتصفح المنتجات',
            self::PRODUCT_SELECTED        => 'اختار منتج',
            self::COLLECTING_VARIANT      => 'ينتظر اختيار اللون/المقاس',
            self::ADDING_TO_CART          => 'يضيف للسلة',
            self::CART_REVIEW             => 'يراجع السلة',
            self::COLLECTING_INFO         => 'يجمع المعلومات',
            self::AWAITING_CONFIRMATION   => 'ينتظر التأكيد',
            self::ORDER_CONFIRMED         => 'طلب مؤكد',
            self::ORDER_CANCELLED         => 'ملغي',
            self::AWAITING_CLARIFICATION  => 'ينتظر توضيح',
            self::HUMAN_HANDOVER          => 'محول لموظف',
        };
    }

    /**
     * Is this a terminal state (conversation ended)?
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::ORDER_CONFIRMED,
            self::ORDER_CANCELLED,
            self::HUMAN_HANDOVER,
        ]);
    }

    /**
     * Can the customer modify the cart in this state?
     */
    public function allowsCartOperations(): bool
    {
        return in_array($this, [
            self::BROWSING,
            self::PRODUCT_SELECTED,
            self::COLLECTING_VARIANT,
            self::ADDING_TO_CART,
            self::CART_REVIEW,
        ]);
    }

    /**
     * Is the bot actively collecting customer info?
     */
    public function isCollectingInfo(): bool
    {
        return $this === self::COLLECTING_INFO;
    }

    /**
     * Can the order be confirmed from this state?
     */
    public function canConfirmOrder(): bool
    {
        return $this === self::AWAITING_CONFIRMATION;
    }

    /**
     * Is the customer actively browsing products?
     */
    public function isBrowsing(): bool
    {
        return in_array($this, [
            self::BROWSING,
            self::PRODUCT_SELECTED,
            self::COLLECTING_VARIANT,
        ]);
    }

    /**
     * Is the conversation in an order-related flow?
     */
    public function isOrderFlow(): bool
    {
        return in_array($this, [
            self::COLLECTING_INFO,
            self::AWAITING_CONFIRMATION,
            self::ORDER_CONFIRMED,
            self::ORDER_CANCELLED,
        ]);
    }
}
