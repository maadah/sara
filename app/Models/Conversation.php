<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'social_account_id',
        'lead_id',
        'participant_id',
        'participant_name',
        'participant_avatar',
        'platform',
        'thread_id',
        'status',
        'is_read',
        'unread_count',
        'last_message',
        'last_message_at',
        'meta_data',
        'ai_enabled',
        'ai_context',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'ai_enabled' => 'boolean',
        'unread_count' => 'integer',
        'last_message_at' => 'datetime',
        'meta_data' => 'array',
        'ai_context' => 'array',
    ];

    /**
     * The user (customer) who owns this conversation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The social account (FB Page or IG account) this conversation is on.
     */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /**
     * Messages in this conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get unread messages count.
     */
    public function getUnreadMessagesCountAttribute(): int
    {
        return $this->messages()->where('direction', 'incoming')->where('is_read', false)->count();
    }

    /**
     * Mark conversation as read.
     */
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'unread_count' => 0,
        ]);

        $this->messages()->where('direction', 'incoming')->where('is_read', false)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Increment unread count and update last message.
     */
    public function updateWithNewMessage(Message $message): void
    {
        $updateData = [
            'last_message' => $message->content,
            'last_message_at' => $message->created_at,
        ];

        if ($message->direction === 'incoming') {
            $updateData['is_read'] = false;
            $updateData['unread_count'] = $this->unread_count + 1;
        }

        $this->update($updateData);
    }

    /**
     * Scope to filter by platform.
     */
    public function scopePlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope to filter active conversations.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter unread conversations.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Get the platform icon class.
     */
    public function getPlatformIconAttribute(): string
    {
        return $this->platform === 'instagram' ? 'instagram' : 'facebook';
    }

    /**
     * Get the display name for the participant.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->participant_name ?? 'مستخدم ' . $this->platform;
    }

    /**
     * Get the lead for this conversation.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get online orders for this conversation.
     */
    public function onlineOrders(): HasMany
    {
        return $this->hasMany(OnlineOrder::class);
    }
}
