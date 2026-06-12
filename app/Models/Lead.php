<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lead extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_id',
        'name',
        'phone',
        'whatsapp',
        'email',
        'address',
        'city',
        'area',
        'source',
        'platform_user_id',
        'status',
        'interest_score',
        'total_messages',
        'total_orders',
        'total_spent',
        'first_contact_at',
        'last_contact_at',
        'notes',
        'meta_data',
    ];

    protected $casts = [
        'meta_data' => 'array',
        'total_spent' => 'decimal:2',
        'first_contact_at' => 'datetime',
        'last_contact_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(OnlineOrder::class);
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class, 'lead_id');
    }

    /**
     * Get status label in Arabic
     */
    public function getStatusLabelAttribute(): string
    {
        if (!$this->status) {
            return 'غير محدد';
        }

        return match($this->status) {
            'new' => 'جديد',
            'contacted' => 'تم التواصل',
            'converted' => 'تم التحويل',
            'lost' => 'فقدان',
            default => $this->status,
        };
    }

    /**
     * Get source label in Arabic
     */
    public function getSourceLabelAttribute(): string
    {
        if (!$this->source) {
            return 'غير محدد';
        }

        return match($this->source) {
            'facebook' => 'فيسبوك',
            'instagram' => 'انستقرام',
            'whatsapp' => 'واتساب',
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
            'new' => 'info',
            'contacted' => 'warning',
            'converted' => 'success',
            'lost' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get display name attribute
     * Returns the lead's name or falls back to conversation participant name
     */
    public function getDisplayNameAttribute(): string
    {
        if (!empty($this->name)) {
            return $this->name;
        }

        if ($this->conversation && !empty($this->conversation->participant_name)) {
            return $this->conversation->participant_name;
        }

        return 'عميل غير معرف';
    }

    /**
     * Get profile image attribute
     * Returns the lead's avatar from conversation if available
     */
    public function getProfileImageAttribute(): ?string
    {
        if ($this->conversation && !empty($this->conversation->participant_avatar)) {
            return $this->conversation->participant_avatar;
        }

        return null;
    }

    /**
     * Update contact timestamp
     */
    public function updateContactTimestamp(): bool
    {
        $this->last_contact_at = now();
        if (!$this->first_contact_at) {
            $this->first_contact_at = now();
        }
        return $this->save();
    }

    /**
     * Get interests array from meta_data
     */
    public function getInterestsAttribute(): array
    {
        return $this->meta_data['interests'] ?? [];
    }

    /**
     * Add an interest to the lead
     */
    public function addInterest(string $productName, $productId = null): bool
    {
        $metaData = $this->meta_data ?? [];
        $interests = $metaData['interests'] ?? [];

        // Avoid exact duplicate on same day
        foreach ($interests as $interest) {
            if ($interest['product_name'] === $productName && 
                isset($interest['date']) && 
                now()->parse($interest['date'])->isToday()) {
                return false;
            }
        }

        $interests[] = [
            'product_name' => $productName,
            'product_id' => $productId,
            'date' => now()->toIso8601String(),
        ];

        $metaData['interests'] = $interests;
        $this->meta_data = $metaData;
        
        return $this->save();
    }
}

