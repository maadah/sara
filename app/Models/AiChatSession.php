<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

class AiChatSession extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'lead_id',
        'conversation_id',
        'conversation_state',
        'store_context',
        'messages',
        'cart',
        'customer_data',
        'current_order',
        'message_count',
        'last_activity_at',
        'expires_at',
    ];

    protected $casts = [
        'store_context' => 'array',
        'messages' => 'array',
        'cart' => 'array',
        'customer_data' => 'array',
        'current_order' => 'array',
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Generate new UUID on creation
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Check if session is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Touch session activity
     */
    public function touchActivity(): void
    {
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Add message to history
     */
    public function addMessage(string $role, string $content): void
    {
        $messages = $this->messages ?? [];
        $messages[] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => now()->toIso8601String(),
        ];

        // Keep only last N turns (configurable)
        $maxMessages = 20; // 10 turns * 2 messages
        if (count($messages) > $maxMessages) {
            $messages = array_slice($messages, -$maxMessages);
        }

        $this->messages = $messages;
        $this->message_count++;
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Get cart total
     */
    public function getCartTotal(): int
    {
        $total = 0;
        foreach ($this->cart ?? [] as $item) {
            $total += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
        }
        return $total;
    }

    /**
     * Add item to cart (REPLACE quantity, don't accumulate)
     * FIX: Added optional attributes parameter for product variants
     */
    public function addToCart(string $name, int $price, int $quantity, array $attributes = []): void
    {
        $cart = $this->cart ?? [];

        // Check if item already in cart - REPLACE quantity
        foreach ($cart as &$item) {
            if ($item['name'] === $name) {
                $item['quantity'] = $quantity; // Replace, not add
                $item['price'] = $price; // Update price too
                if (!empty($attributes)) {
                    $item['attributes'] = $attributes;
                }
                $this->cart = $cart;
                $this->save();
                return;
            }
        }

        // Add new item
        $newItem = [
            'name' => $name,
            'price' => $price,
            'quantity' => $quantity,
        ];
        if (!empty($attributes)) {
            $newItem['attributes'] = $attributes;
        }
        $cart[] = $newItem;

        $this->cart = $cart;
        $this->save();
    }

    /**
     * Remove item from cart
     */
    public function removeFromCart(string $name): void
    {
        $cart = $this->cart ?? [];
        $this->cart = array_values(array_filter($cart, fn($item) => $item['name'] !== $name));
        $this->save();
    }

    /**
     * Update cart item quantity
     */
    public function updateCartQuantity(string $name, int $quantity): void
    {
        $cart = $this->cart ?? [];
        foreach ($cart as &$item) {
            if ($item['name'] === $name) {
                $item['quantity'] = $quantity;
                break;
            }
        }
        $this->cart = $cart;
        $this->save();
    }

    /**
     * Clear cart
     */
    public function clearCart(): void
    {
        $this->cart = [];
        $this->save();
    }

    /**
     * Update customer data
     */
    public function updateCustomerData(array $data): void
    {
        $customerData = $this->customer_data ?? [];
        foreach ($data as $key => $value) {
            if ($value) {
                $customerData[$key] = $value;
            }
        }
        $this->customer_data = $customerData;
        $this->save();
    }

    /**
     * Check if customer data is complete for order
     */
    public function hasCompleteCustomerData(): bool
    {
        $data = $this->customer_data ?? [];
        return !empty($data['name']) && !empty($data['phone']) && !empty($data['address']);
    }

    /**
     * Get missing customer data fields
     */
    public function getMissingFields(): array
    {
        $data = $this->customer_data ?? [];
        $missing = [];

        if (empty($data['name'])) $missing[] = 'name';
        if (empty($data['phone'])) $missing[] = 'phone';
        if (empty($data['address'])) $missing[] = 'address';

        return $missing;
    }
}
