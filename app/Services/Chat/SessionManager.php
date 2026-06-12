<?php

namespace App\Services\Chat;

use App\Enums\ConversationState;
use App\Events\CartAbandoned;
use App\Models\ChatSession;
use App\Models\CustomerProfile;
use Illuminate\Support\Facades\Log;

/**
 * SessionManager — creates, loads, resets, and persists chat sessions.
 *
 * Responsible for:
 * - Loading or creating a chat session for a given store + lead + conversation.
 * - Detecting session expiry (> 3 hours) and resetting state.
 * - Detecting cart abandonment when a user returns.
 * - Trimming history to the last N message pairs.
 * - Persisting session changes after each message turn.
 */
class SessionManager
{
    /**
     * Load an existing session or create a brand-new one.
     */
    public function loadOrCreate(int $storeId, int $leadId, ?string $conversationId = null, string $channel = 'web'): ChatSession
    {
        $session = ChatSession::where('store_id', $storeId)
            ->where('lead_id', $leadId)
            ->when($conversationId, fn ($q) => $q->where('conversation_id', $conversationId))
            ->latest('last_activity_at')
            ->first();

        if ($session) {
            return $this->handleExistingSession($session);
        }

        return $this->createSession($storeId, $leadId, $conversationId, $channel);
    }

    /**
     * Persist all mutable session data after a turn.
     */
    public function save(ChatSession $session): void
    {
        $session->last_activity_at = now();
        $session->save();
    }

    /**
     * Append a message pair to session history and trim to max pairs.
     *
     * @param array{role: string, content: string} $message
     */
    public function appendHistory(ChatSession $session, array $message): void
    {
        $history = $session->history ?? [];
        $history[] = $message;

        $maxPairs = config('chat.conversation.max_history_pairs', 10) * 2;
        if (count($history) > $maxPairs) {
            $history = array_slice($history, -$maxPairs);
        }

        $session->history = array_values($history);
    }

    /**
     * Update customer_data bag on the session (merge, not overwrite).
     */
    public function mergeCustomerData(ChatSession $session, array $data): void
    {
        $existing = $session->customer_data ?? [];
        $session->customer_data = array_merge($existing, array_filter($data));
    }

    /**
     * Mark the session cart as abandoned and log it.
     */
    public function markCartAbandoned(ChatSession $session): void
    {
        if ($session->hasCartItems() && ! $session->cart_abandoned_at) {
            $session->cart_abandoned_at = now();

            CartAbandoned::dispatch($session);

            Log::warning('Chat: cart abandoned', [
                'session_id' => $session->id,
                'store_id'   => $session->store_id,
                'lead_id'    => $session->lead_id,
                'cart_total'  => $session->cart['grand_total'] ?? 0,
            ]);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Private                                                             */
    /* ------------------------------------------------------------------ */

    private function createSession(int $storeId, int $leadId, ?string $conversationId, string $channel): ChatSession
    {
        $session = ChatSession::create([
            'store_id'        => $storeId,
            'lead_id'         => $leadId,
            'conversation_id' => $conversationId,
            'channel'         => $channel,
            'state'           => ConversationState::IDLE,
            'cart'            => null,
            'customer_data'   => null,
            'history'         => [],
            'meta'            => [
                'browse_count'  => 0,
                'lead_score'    => 0,
                'upsell_shown'  => [],
                'tone'          => 'neutral',
            ],
            'last_activity_at' => now(),
        ]);

        // Pre-fill customer data from existing profile if available
        $profile = CustomerProfile::where('store_id', $storeId)
            ->where('lead_id', $leadId)
            ->first();

        if ($profile) {
            $session->customer_data = array_filter([
                'name'    => $profile->name,
                'phone'   => $profile->phone,
                'address' => $profile->address,
                'city'    => $profile->city,
            ]);
            $session->save();
        }

        Log::info('Chat: session created', [
            'session_id' => $session->id,
            'store_id'   => $storeId,
            'lead_id'    => $leadId,
            'channel'    => $channel,
        ]);

        return $session;
    }

    private function handleExistingSession(ChatSession $session): ChatSession
    {
        // Check expiry
        if ($session->isExpired()) {
            Log::info('Chat: session expired, resetting', [
                'session_id' => $session->id,
                'last_activity' => $session->last_activity_at?->toDateTimeString(),
            ]);

            // If cart was active, flag as abandoned before resetting
            if ($session->hasCartItems()) {
                $this->markCartAbandoned($session);
            }

            $session->state   = ConversationState::IDLE;
            $session->history = [];
            $session->meta    = array_merge($session->meta ?? [], [
                'browse_count'  => 0,
                'upsell_shown'  => [],
                'tone'          => 'neutral',
            ]);
            $session->cart_abandoned_at = null;
            $session->save();

            return $session;
        }

        // Detect returning user with abandoned cart
        if ($session->wasCartAbandoned()) {
            Log::info('Chat: user returned with abandoned cart', [
                'session_id' => $session->id,
            ]);
            // cart_abandoned_at stays set — ConversationEngine will remind them
        }

        Log::info('Chat: session resumed', [
            'session_id' => $session->id,
            'state'      => $session->state->value,
        ]);

        return $session;
    }
}
