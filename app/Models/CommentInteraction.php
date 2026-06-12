<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentInteraction extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'platform',
        'commenter_id',
        'commenter_name',
        'comment_id',
        'post_id',
        'comment_text',
        'replied',
        'dm_sent',
        'expires_at',
    ];

    protected $casts = [
        'replied' => 'boolean',
        'dm_sent' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Store owner
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Product the commenter asked about
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope: only non-expired interactions
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope: expired interactions (for cleanup)
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Check if interaction is still valid (within 24h window)
     */
    public function isActive(): bool
    {
        return $this->expires_at->isFuture();
    }
}
