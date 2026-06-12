<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_id
 * @property string|null $provider_token
 * @property \App\Models\User $user
 */
class SocialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'provider_token',
        'provider_refresh_token',
        'token_expires_at',
        'name',
        'email',
        'avatar',
        'meta_data',
        'is_primary',
    ];

    protected $casts = [
        'meta_data' => 'array',
        'is_primary' => 'boolean',
        'token_expires_at' => 'datetime',
    ];

    protected $hidden = [
        'provider_token',
        'provider_refresh_token',
    ];

    /**
     * Get the user that owns the social account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the token is expired.
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }

    /**
     * Scope to get accounts by provider.
     */
    public function scopeProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to get Facebook accounts.
     */
    public function scopeFacebook($query)
    {
        return $query->where('provider', 'facebook');
    }

    /**
     * Scope to get Instagram accounts.
     */
    public function scopeInstagram($query)
    {
        return $query->where('provider', 'instagram');
    }

    /**
     * Scope to get WhatsApp accounts.
     */
    public function scopeWhatsapp($query)
    {
        return $query->where('provider', 'whatsapp');
    }

    /**
     * Scope to get Facebook pages.
     */
    public function scopeFacebookPages($query)
    {
        return $query->where('provider', 'facebook_page');
    }

    /**
     * Get conversations for this social account.
     */
    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Get the platform type for messaging.
     */
    public function getMessagingPlatformAttribute(): string
    {
        return match($this->provider) {
            'facebook_page' => 'facebook',
            'instagram' => 'instagram',
            'whatsapp' => 'whatsapp',
            default => 'facebook',
        };
    }
}
