<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'user_id',
        'invoice_number',
        'customer_name',
        'customer_phone',
        'subtotal',
        'discount_amount',
        'discount_percentage',
        'total',
        'currency',
        'notes',
        'status',
        'payment_method',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Get the user (merchant) that owns the sale
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the sale items
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Generate unique invoice number
     */
    public static function generateInvoiceNumber($userId): string
    {
        $prefix = 'INV';
        $date = now()->format('Ymd');
        $count = self::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count() + 1;

        return $prefix . '-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get currency symbol
     */
    public function getCurrencySymbolAttribute(): string
    {
        return $this->currency === 'USD' ? '$' : 'د.ع';
    }
}
