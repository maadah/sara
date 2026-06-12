<?php

namespace App\Listeners;

use App\Events\CartAbandoned;
use App\Models\CartAbandonment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Records a cart abandonment snapshot when the CartAbandoned event fires.
 *
 * This runs on the queue so it doesn't block the conversation flow.
 */
class CartAbandonedListener implements ShouldQueue
{
    public $queue = 'default';

    public function handle(CartAbandoned $event): void
    {
        $session = $event->session;

        if (empty($session->cart) || empty($session->cart['items'])) {
            return;
        }

        try {
            CartAbandonment::create([
                'session_id'    => $session->id,
                'lead_id'       => $session->lead_id,
                'store_id'      => $session->store_id,
                'cart_snapshot'  => $session->cart,
                'cart_total'     => $session->cart['total'] ?? 0,
                'abandoned_at'  => $session->cart_abandoned_at ?? now(),
            ]);

            Log::info('Chat: cart abandonment recorded', [
                'session_id' => $session->id,
                'store_id'   => $session->store_id,
                'lead_id'    => $session->lead_id,
                'item_count' => count($session->cart['items']),
                'total'      => $session->cart['total'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('Chat: failed to record cart abandonment', [
                'error'      => $e->getMessage(),
                'session_id' => $session->id,
            ]);
        }
    }
}
