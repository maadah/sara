<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineOrder extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'lead_id',
        'conversation_id',
        'customer_name',
        'customer_phone',
        'customer_whatsapp',
        'customer_address',
        'customer_city',
        'customer_area',
        'source',
        'status',
        'subtotal',
        'discount',
        'shipping_cost',
        'total',
        'currency',
        'payment_method',
        'payment_status',
        'customer_notes',
        'internal_notes',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
        'meta_data',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'meta_data' => 'array',
        'confirmed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (!$order->order_number) {
                $order->order_number = self::generateOrderNumber($order->user_id);
            }
        });
    }

    /**
     * Generate unique order number
     */
    public static function generateOrderNumber(int $userId): string
    {
        $prefix = 'ORD';
        $date = now()->format('ymd');
        $count = self::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count() + 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $count);
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

    public function items(): HasMany
    {
        return $this->hasMany(OnlineOrderItem::class);
    }

    /**
     * Get status label in Arabic
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'بانتظار التأكيد',
            'confirmed' => 'مؤكد',
            'processing' => 'قيد التحضير',
            'shipped' => 'تم الشحن',
            'delivered' => 'تم التوصيل',
            'cancelled' => 'ملغي',
            'returned' => 'مرتجع',
            default => $this->status,
        };
    }

    /**
     * Get source label in Arabic
     */
    public function getSourceLabelAttribute(): string
    {
        return match($this->source) {
            'facebook' => 'فيسبوك',
            'instagram' => 'انستقرام',
            'whatsapp' => 'واتساب',
            'ai_chat' => 'محادثة AI',
            'manual' => 'يدوي',
            default => $this->source,
        };
    }

    /**
     * Get status color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'confirmed' => 'info',
            'processing' => 'primary',
            'shipped' => 'purple',
            'delivered' => 'success',
            'cancelled' => 'danger',
            'returned' => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Get payment status label
     */
    public function getPaymentStatusLabelAttribute(): string
    {
        return match($this->payment_status) {
            'pending' => 'بانتظار الدفع',
            'paid' => 'مدفوع',
            'failed' => 'فشل الدفع',
            'refunded' => 'مسترد',
            default => $this->payment_status,
        };
    }

    /**
     * Calculate totals
     */
    public function calculateTotals(): void
    {
        $this->subtotal = $this->items->sum('total');
        $this->total = $this->subtotal - $this->discount + $this->shipping_cost;
        $this->save();
    }

    /**
     * Mark as confirmed
     */
    public function confirm(): void
    {
        $this->status = 'confirmed';
        $this->confirmed_at = now();
        $this->save();
    }

    /**
     * Mark as processing
     */
    public function process(): void
    {
        $this->status = 'processing';
        $this->save();
    }

    /**
     * Mark as shipped
     */
    public function ship(?string $trackingNumber = null): void
    {
        $this->status = 'shipped';
        $this->shipped_at = now();

        if (!empty($trackingNumber)) {
            $meta = $this->meta_data ?? [];
            $meta['tracking_number'] = $trackingNumber;
            $this->meta_data = $meta;
        }

        $this->save();
    }

    /**
     * Mark as delivered
     */
    public function deliver(): void
    {
        $this->status = 'delivered';
        $this->delivered_at = now();
        $this->payment_status = 'paid';
        $this->save();

        // Update lead stats
        if ($this->lead) {
            $this->lead->increment('total_orders');
            $this->lead->increment('total_spent', $this->total);
            $this->lead->status = 'converted';
            $this->lead->save();
        }
    }

    /**
     * Mark as cancelled
     * IMPORTANT: Cannot cancel orders that are already confirmed, shipped, or delivered
     */
    public function cancel(?string $reason = null): bool
    {
        // Protect confirmed orders from cancellation
        $protectedStatuses = ['confirmed', 'processing', 'shipped', 'delivered'];
        if (in_array($this->status, $protectedStatuses)) {
            return false; // Cannot cancel
        }
        
        $this->status = 'cancelled';

        if (!empty($reason)) {
            $meta = $this->meta_data ?? [];
            $meta['cancellation_reason'] = $reason;
            $meta['cancelled_at'] = now()->toIso8601String();
            $this->meta_data = $meta;
        }

        $this->save();
        return true;
    }
    
    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Mark as returned
     */
    public function markReturned(): void
    {
        $this->status = 'returned';
        $this->payment_status = 'refunded';
        $this->save();
    }
}
