<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property float $price
 * @property string $currency
 * @property int $quantity
 * @property \App\Models\User $user
 */
class Product extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'description',
        'price',
        'currency',
        'quantity',
        'reserved_quantity',
        'unit',
        'sell_unit',
        'conversion_factor',
        'expiry_date',
        'is_active',
        'manage_stock',
        'facebook_post_url',
        'facebook_post_id',
        'instagram_post_url',
        'instagram_post_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'conversion_factor' => 'decimal:4',
        'expiry_date' => 'date',
        'is_active' => 'boolean',
        'manage_stock' => 'boolean',
    ];

    /**
     * Get the user that owns the product
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the product images
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Get the primary image
     */
    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    /**
     * Get product attributes (size, color, etc.)
     */
    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    /**
     * Get comment interactions for this product
     */
    public function commentInteractions(): HasMany
    {
        return $this->hasMany(CommentInteraction::class);
    }

    /**
     * Check if product has a linked social post
     */
    public function hasSocialPost(): bool
    {
        return !empty($this->facebook_post_url) || !empty($this->instagram_post_url);
    }

    /**
     * Get available attributes grouped by key
     */
    public function getAvailableAttributes(): array
    {
        return $this->attributes()
            ->available()
            ->get()
            ->groupBy('attribute_key')
            ->map(fn($group) => $group->pluck('attribute_value')->unique()->values()->toArray())
            ->toArray();
    }

    /**
     * Get required attributes for this product based on store type
     */
    public function getRequiredAttributes(): array
    {
        $storeType = $this->user->storeType;
        if (!$storeType) {
            return [];
        }
        return $storeType->required_attributes ?? [];
    }

    /**
     * Check if product has a specific attribute value available
     */
    public function hasAttributeValue(string $key, string $value): bool
    {
        return $this->attributes()
            ->where('attribute_key', $key)
            ->where('attribute_value', $value)
            ->available()
            ->exists();
    }

    /**
     * Get attribute stock for a specific variant
     */
    public function getVariantStock(array $selectedAttributes): ?int
    {
        // If no attributes, return main product quantity
        if (empty($selectedAttributes)) {
            return $this->quantity;
        }

        // Find matching attribute with stock info
        foreach ($selectedAttributes as $key => $value) {
            $attr = $this->attributes()
                ->where('attribute_key', $key)
                ->where('attribute_value', $value)
                ->first();

            if ($attr && $attr->stock_quantity !== null) {
                return $attr->stock_quantity;
            }
        }

        return $this->quantity;
    }

    /**
     * Calculate final price with attribute modifiers
     */
    public function calculatePrice(array $selectedAttributes = []): float
    {
        $basePrice = (float) $this->price;

        foreach ($selectedAttributes as $key => $value) {
            $attr = $this->attributes()
                ->where('attribute_key', $key)
                ->where('attribute_value', $value)
                ->first();

            if ($attr) {
                $basePrice += (float) $attr->price_modifier;
            }
        }

        return $basePrice;
    }

    /**
     * Get available quantity
     */
    public function getAvailableQuantityAttribute(): int
    {
        return $this->quantity - $this->reserved_quantity;
    }

    /**
     * Check if product is in stock
     */
    public function isInStock(): bool
    {
        return $this->is_active && $this->available_quantity > 0;
    }

    /**
     * Get formatted price without decimal places (for Iraqi Dinar)
     * Converts "12000.00" to "12,000"
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format((int) $this->price, 0, '', ',');
    }

    /**
     * Get price as integer (removes .00)
     */
    public function getPriceIntAttribute(): int
    {
        return (int) $this->price;
    }

    /**
     * Scope: Active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: In stock products
     */
    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    /**
     * Get inventory movements for this product
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class)->latest('created_at');
    }

    /**
     * Adjust stock and record movement
     */
    public function adjustStock(int $quantity, string $type, ?string $notes = null): void
    {
        $stockBefore = $this->quantity;
        $this->quantity += $quantity;
        $this->save();

        $this->inventoryMovements()->create([
            'user_id' => auth()->id(),
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $this->quantity,
            'notes' => $notes,
        ]);
    }
}
