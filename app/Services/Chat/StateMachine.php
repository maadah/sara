<?php

namespace App\Services\Chat;

use App\Enums\ConversationState;
use App\Enums\Intent;
use App\Models\ChatSession;
use Illuminate\Support\Facades\Log;

/**
 * StateMachine — manages all conversation state transitions.
 *
 * The AI never sets state directly. This class is the single authority
 * that decides what the next state should be based on the current state,
 * detected intent, and session context.
 *
 * Every transition is logged for analytics and debugging.
 */
class StateMachine
{
    /**
     * Compute and apply the next state based on current state + intent + session context.
     *
     * @return ConversationState The new state (may be same as current).
     */
    public function transition(ChatSession $session, Intent $intent, array $context = []): ConversationState
    {
        $current  = $session->state;
        $newState = $this->resolveNextState($current, $intent, $session, $context);

        if ($newState !== $current) {
            Log::info('Chat: state transition', [
                'session_id' => $session->id,
                'from'       => $current->value,
                'to'         => $newState->value,
                'intent'     => $intent->value,
            ]);

            $session->state = $newState;
        }

        return $newState;
    }

    /**
     * Force a specific state (used for resets, human handover, etc.).
     */
    public function forceState(ChatSession $session, ConversationState $state): void
    {
        $old = $session->state;
        $session->state = $state;

        Log::info('Chat: state forced', [
            'session_id' => $session->id,
            'from'       => $old->value,
            'to'         => $state->value,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Transition Rules                                                    */
    /* ------------------------------------------------------------------ */

    private function resolveNextState(
        ConversationState $current,
        Intent $intent,
        ChatSession $session,
        array $context,
    ): ConversationState {
        // From any terminal state, a new greeting resets to GREETING
        if ($current->isTerminal() && $intent === Intent::GREETING) {
            return ConversationState::GREETING;
        }

        // Human handover is sticky — only greeting resets it
        if ($current === ConversationState::HUMAN_HANDOVER) {
            return ConversationState::HUMAN_HANDOVER;
        }

        return match ($current) {
            ConversationState::IDLE                   => $this->fromIdle($intent),
            ConversationState::GREETING               => $this->fromGreeting($intent),
            ConversationState::BROWSING               => $this->fromBrowsing($intent, $session),
            ConversationState::PRODUCT_SELECTED       => $this->fromProductSelected($intent),
            ConversationState::COLLECTING_VARIANT     => $this->fromCollectingVariant($intent),
            ConversationState::ADDING_TO_CART         => $this->fromAddingToCart($intent),
            ConversationState::CART_REVIEW            => $this->fromCartReview($intent, $session),
            ConversationState::COLLECTING_INFO        => $this->fromCollectingInfo($intent, $session),
            ConversationState::AWAITING_CONFIRMATION  => $this->fromAwaitingConfirmation($intent),
            ConversationState::AWAITING_CLARIFICATION => $this->fromAwaitingClarification($intent),
            ConversationState::ORDER_CONFIRMED        => $this->fromOrderConfirmed($intent),
            ConversationState::ORDER_CANCELLED        => $this->fromOrderCancelled($intent),
            ConversationState::HUMAN_HANDOVER         => ConversationState::HUMAN_HANDOVER,
        };
    }

    /* ---- IDLE ---- */
    private function fromIdle(Intent $intent): ConversationState
    {
        return match ($intent) {
            Intent::GREETING        => ConversationState::GREETING,
            Intent::BROWSE_GENERAL,
            Intent::BROWSE_CATEGORY,
            Intent::SEARCH_PRODUCT  => ConversationState::BROWSING,
            Intent::CHECK_ORDER_STATUS => ConversationState::IDLE,
            default                 => ConversationState::GREETING,
        };
    }

    /* ---- GREETING ---- */
    private function fromGreeting(Intent $intent): ConversationState
    {
        return match (true) {
            $intent->isBrowsingIntent()  => ConversationState::BROWSING,
            $intent->isCartIntent()      => ConversationState::CART_REVIEW,
            $intent->isOrderIntent()     => ConversationState::COLLECTING_INFO,
            $intent === Intent::CHECKOUT => ConversationState::COLLECTING_INFO,
            default                      => ConversationState::GREETING,
        };
    }

    /* ---- BROWSING ---- */
    private function fromBrowsing(Intent $intent, ChatSession $session): ConversationState
    {
        return match ($intent) {
            Intent::ASK_PRICE,
            Intent::ASK_AVAILABILITY,
            Intent::ASK_DETAILS,
            Intent::REQUEST_IMAGE    => ConversationState::PRODUCT_SELECTED,
            Intent::ADD_TO_CART      => ConversationState::ADDING_TO_CART,
            Intent::VIEW_CART        => ConversationState::CART_REVIEW,
            Intent::CHECKOUT         => $session->hasCartItems()
                ? ConversationState::COLLECTING_INFO
                : ConversationState::BROWSING,
            Intent::CANCEL_ORDER     => ConversationState::ORDER_CANCELLED,
            default                  => ConversationState::BROWSING,
        };
    }

    /* ---- PRODUCT_SELECTED ---- */
    private function fromProductSelected(Intent $intent): ConversationState
    {
        return match ($intent) {
            Intent::ADD_TO_CART     => ConversationState::ADDING_TO_CART,
            Intent::BROWSE_GENERAL,
            Intent::BROWSE_CATEGORY,
            Intent::SEARCH_PRODUCT  => ConversationState::BROWSING,
            Intent::NEGOTIATE_PRICE,
            Intent::SALES_OBJECTION => ConversationState::PRODUCT_SELECTED,
            default                 => ConversationState::PRODUCT_SELECTED,
        };
    }

    /* ---- COLLECTING_VARIANT ---- */
    private function fromCollectingVariant(Intent $intent): ConversationState
    {
        return match ($intent) {
            Intent::ADD_TO_CART     => ConversationState::ADDING_TO_CART,
            Intent::CANCEL_ORDER    => ConversationState::BROWSING,
            default                 => ConversationState::COLLECTING_VARIANT,
        };
    }

    /* ---- ADDING_TO_CART ---- */
    private function fromAddingToCart(Intent $intent): ConversationState
    {
        return match ($intent) {
            Intent::BROWSE_GENERAL,
            Intent::BROWSE_CATEGORY,
            Intent::SEARCH_PRODUCT  => ConversationState::BROWSING,
            Intent::VIEW_CART       => ConversationState::CART_REVIEW,
            Intent::CHECKOUT        => ConversationState::COLLECTING_INFO,
            Intent::ADD_TO_CART     => ConversationState::ADDING_TO_CART,
            default                 => ConversationState::CART_REVIEW,
        };
    }

    /* ---- CART_REVIEW ---- */
    private function fromCartReview(Intent $intent, ChatSession $session): ConversationState
    {
        return match ($intent) {
            Intent::CHECKOUT        => ConversationState::COLLECTING_INFO,
            Intent::CLEAR_CART      => ConversationState::BROWSING,
            Intent::REMOVE_FROM_CART,
            Intent::UPDATE_QUANTITY => ConversationState::CART_REVIEW,
            Intent::ADD_TO_CART     => ConversationState::ADDING_TO_CART,
            Intent::BROWSE_GENERAL,
            Intent::BROWSE_CATEGORY,
            Intent::SEARCH_PRODUCT  => ConversationState::BROWSING,
            Intent::CONFIRM_ORDER   => $session->hasCompleteCustomerData()
                ? ConversationState::AWAITING_CONFIRMATION
                : ConversationState::COLLECTING_INFO,
            default                 => ConversationState::CART_REVIEW,
        };
    }

    /* ---- COLLECTING_INFO ---- */
    private function fromCollectingInfo(Intent $intent, ChatSession $session): ConversationState
    {
        // If customer just provided info, check completeness
        if ($intent->isInfoIntent()) {
            return $session->hasCompleteCustomerData()
                ? ConversationState::AWAITING_CONFIRMATION
                : ConversationState::COLLECTING_INFO;
        }

        return match ($intent) {
            Intent::CANCEL_ORDER => ConversationState::ORDER_CANCELLED,
            Intent::VIEW_CART    => ConversationState::CART_REVIEW,
            Intent::CONFIRM_ORDER => $session->hasCompleteCustomerData()
                ? ConversationState::AWAITING_CONFIRMATION
                : ConversationState::COLLECTING_INFO,
            default              => ConversationState::COLLECTING_INFO,
        };
    }

    /* ---- AWAITING_CONFIRMATION ---- */
    private function fromAwaitingConfirmation(Intent $intent): ConversationState
    {
        return match ($intent) {
            Intent::CONFIRM_ORDER => ConversationState::ORDER_CONFIRMED,
            Intent::CANCEL_ORDER  => ConversationState::ORDER_CANCELLED,
            Intent::VIEW_CART     => ConversationState::CART_REVIEW,
            default               => ConversationState::AWAITING_CONFIRMATION,
        };
    }

    /* ---- AWAITING_CLARIFICATION ---- */
    private function fromAwaitingClarification(Intent $intent): ConversationState
    {
        return match (true) {
            $intent->isBrowsingIntent() => ConversationState::BROWSING,
            $intent->isCartIntent()     => ConversationState::CART_REVIEW,
            $intent->isOrderIntent()    => ConversationState::COLLECTING_INFO,
            $intent === Intent::GREETING => ConversationState::GREETING,
            default                     => ConversationState::AWAITING_CLARIFICATION,
        };
    }

    /* ---- ORDER_CONFIRMED ---- */
    private function fromOrderConfirmed(Intent $intent): ConversationState
    {
        return match ($intent) {
            Intent::GREETING       => ConversationState::GREETING,
            Intent::BROWSE_GENERAL,
            Intent::BROWSE_CATEGORY,
            Intent::SEARCH_PRODUCT => ConversationState::BROWSING,
            Intent::CHECK_ORDER_STATUS => ConversationState::ORDER_CONFIRMED,
            default                => ConversationState::ORDER_CONFIRMED,
        };
    }

    /* ---- ORDER_CANCELLED ---- */
    private function fromOrderCancelled(Intent $intent): ConversationState
    {
        return match ($intent) {
            Intent::GREETING       => ConversationState::GREETING,
            Intent::BROWSE_GENERAL,
            Intent::BROWSE_CATEGORY,
            Intent::SEARCH_PRODUCT => ConversationState::BROWSING,
            default                => ConversationState::ORDER_CANCELLED,
        };
    }
}
