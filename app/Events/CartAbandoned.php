<?php

namespace App\Events;

use App\Models\ChatSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a chat session's cart is detected as abandoned.
 *
 * Conditions: the session has cart items but was inactive for the configured
 * timeout period. The SessionManager detects this and fires the event.
 */
class CartAbandoned
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly ChatSession $session,
    ) {}
}
