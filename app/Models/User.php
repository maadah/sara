<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'whatsapp',
        'password',
        'role',
        'status',
        'facebook_link',
        'instagram_link',
        'store_address',
        'subscription_id',
        'subscription_expires_at',
        'store_type_id',
        'merchant_id',
        'team_role',
        'settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'subscription_expires_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if user is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Get subscription relationship
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Check if subscription is active
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription_id &&
               $this->subscription_expires_at &&
               $this->subscription_expires_at->isFuture();
    }

    /**
     * Get connected social accounts
     */
    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Get Facebook accounts
     */
    public function facebookAccounts()
    {
        return $this->socialAccounts()->facebook();
    }

    /**
     * Get Instagram accounts
     */
    public function instagramAccounts()
    {
        return $this->socialAccounts()->instagram();
    }

    /**
     * Check if user has a social account connected
     */
    public function hasSocialAccount(string $provider): bool
    {
        return $this->socialAccounts()->where('provider', $provider)->exists();
    }

    /**
     * Get user's conversations
     */
    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Get user's messages
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get unread conversations count
     */
    public function getUnreadConversationsCountAttribute(): int
    {
        return $this->conversations()->where('is_read', false)->count();
    }

    /**
     * Get AI settings
     */
    public function aiSetting()
    {
        return $this->hasOne(AiSetting::class);
    }

    /**
     * Get or create AI settings
     */
    public function getOrCreateAiSetting(): AiSetting
    {
        return $this->aiSetting ?? AiSetting::create([
            'user_id' => $this->id,
            'system_instruction' => AiSetting::getDefaultSystemInstruction(),
        ]);
    }

    /**
     * Get user's leads
     */
    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Get user's online orders
     */
    public function onlineOrders()
    {
        return $this->hasMany(OnlineOrder::class);
    }

    /**
     * Get user's products
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get user's categories
     */
    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get store type
     */
    public function storeType()
    {
        return $this->belongsTo(StoreType::class);
    }

    /**
     * Get store type with default fallback
     */
    public function getStoreTypeOrDefault(): StoreType
    {
        return $this->storeType ?? StoreType::where('name', 'general')->first() ?? new StoreType([
            'name' => 'general',
            'display_name' => 'متجر عام',
            'required_attributes' => [],
            'optional_attributes' => [],
        ]);
    }

    /**
     * Get team members (users who work under this merchant)
     */
    public function teamMembers()
    {
        return $this->hasMany(User::class, 'merchant_id');
    }

    /**
     * Get the merchant this team member belongs to
     */
    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    /**
     * Get user's competitors
     */
    public function competitors()
    {
        return $this->hasMany(Competitor::class);
    }
}
