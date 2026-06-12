<?php

namespace App\Models;

use App\Enums\ConversationState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ChatSession — one conversation thread between a lead and a store's AI assistant.
 *
 * Holds the current conversation state, cart JSON, collected customer data,
 * the last N message pairs (history), and a meta bag for analytics signals.
 *
 * @property int                $id
 * @property int                $store_id
 * @property int                $lead_id
 * @property string|null        $conversation_id
 * @property string             $channel
 * @property ConversationState  $state
 * @property array|null         $cart
 * @property array|null         $customer_data
 * @property array|null         $history
 * @property array|null         $meta
 * @property \Carbon\Carbon|null $last_activity_at
 * @property \Carbon\Carbon|null $cart_abandoned_at
 */
class ChatSession extends Model
{
    protected $table = 'chat_sessions';

    protected $fillable = [
        'store_id',
        'lead_id',
        'conversation_id',
        'channel',
        'state',
        'cart',
        'customer_data',
        'history',
        'meta',
        'last_activity_at',
        'cart_abandoned_at',
    ];

    protected function casts(): array
    {
        return [
            'state'            => ConversationState::class,
            'cart'             => 'array',
            'customer_data'    => 'array',
            'history'          => 'array',
            'meta'             => 'array',
            'last_activity_at' => 'datetime',
            'cart_abandoned_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relations                                                           */
    /* ------------------------------------------------------------------ */

    public function store(): BelongsTo
    {
        return $this->belongsTo(User::class, 'store_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'session_id');
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Is the session expired based on chat.conversation.session_timeout_hours?
     */
    public function isExpired(): bool
    {
        if (! $this->last_activity_at) {
            return false;
        }

        $hours = config('chat.conversation.session_timeout_hours', 3);

        return $this->last_activity_at->diffInHours(now()) >= $hours;
    }

    /**
     * Does the cart have items?
     */
    public function hasCartItems(): bool
    {
        $cart = $this->cart;

        return ! empty($cart['items']);
    }

    /**
     * Was the cart abandoned (flagged but user returned)?
     */
    public function wasCartAbandoned(): bool
    {
        return $this->cart_abandoned_at !== null && $this->hasCartItems();
    }

    /**
     * Get a value from the meta bag.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return data_get($this->meta, $key, $default);
    }

    /**
     * Set a value in the meta bag (does NOT save automatically).
     */
    public function setMeta(string $key, mixed $value): void
    {
        $meta = $this->meta ?? [];
        data_set($meta, $key, $value);
        $this->meta = $meta;
    }

    /**
     * Is the required customer data complete for placing an order?
     */
    public function hasCompleteCustomerData(): bool
    {
        $data = $this->customer_data ?? [];
        $phonePattern = config('chat.phone.pattern', '/^07[3-9]\d{8}$/');

        return ! empty($data['name'])
            && ! empty($data['phone'])
            && preg_match($phonePattern, $data['phone'])
            && ! empty($data['address']);
    }
}
