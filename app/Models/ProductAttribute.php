<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttribute extends Model
{
    protected $fillable = [
        'product_id',
        'attribute_key',
        'attribute_value',
        'price_modifier',
        'stock_quantity',
        'is_available',
    ];

    protected $casts = [
        'price_modifier' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_available' => 'boolean',
    ];

    /**
     * Get the product this attribute belongs to
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get Arabic display name for this attribute
     */
    public function getDisplayNameAttribute(): string
    {
        return StoreType::getAttributeName($this->attribute_key);
    }

    /**
     * Get display value (with Arabic translation for colors)
     */
    public function getDisplayValueAttribute(): string
    {
        if ($this->attribute_key === 'color') {
            return StoreType::getColorName($this->attribute_value);
        }
        return $this->attribute_value;
    }

    /**
     * Check if this variant is in stock
     */
    public function isInStock(): bool
    {
        if ($this->stock_quantity === null) {
            return $this->is_available;
        }
        return $this->stock_quantity > 0 && $this->is_available;
    }

    /**
     * Decrement stock when ordered
     */
    public function decrementStock(int $quantity = 1): bool
    {
        if ($this->stock_quantity === null) {
            return true;
        }

        if ($this->stock_quantity < $quantity) {
            return false;
        }

        $this->decrement('stock_quantity', $quantity);
        return true;
    }

    /**
     * Scope: Get available attributes
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)
            ->where(function ($q) {
                $q->whereNull('stock_quantity')
                    ->orWhere('stock_quantity', '>', 0);
            });
    }

    /**
     * Scope: Get by attribute key
     */
    public function scopeForKey($query, string $key)
    {
        return $query->where('attribute_key', $key);
    }
}
