<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'external_id',
        'direction',
        'content',
        'message_type',
        'attachments',
        'status',
        'is_read',
        'is_ai_generated',
        'is_from_customer',
        'read_at',
        'delivered_at',
        'reply_to_id',
        'meta_data',
        'platform_created_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_ai_generated' => 'boolean',
        'is_from_customer' => 'boolean',
        'attachments' => 'array',
        'meta_data' => 'array',
        'read_at' => 'datetime',
        'delivered_at' => 'datetime',
        'platform_created_at' => 'datetime',
    ];

    /**
     * The conversation this message belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * The user who sent this message (if outgoing).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The message this is a reply to.
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    /**
     * Replies to this message.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    /**
     * Check if message is incoming.
     */
    public function isIncoming(): bool
    {
        return $this->direction === 'incoming';
    }

    /**
     * Check if message is outgoing.
     */
    public function isOutgoing(): bool
    {
        return $this->direction === 'outgoing';
    }

    /**
     * Check if message has attachments.
     */
    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    /**
     * Get formatted time.
     */
    public function getFormattedTimeAttribute(): string
    {
        $date = $this->platform_created_at ?? $this->created_at;

        if ($date->isToday()) {
            return $date->format('H:i');
        }

        if ($date->isYesterday()) {
            return 'أمس ' . $date->format('H:i');
        }

        return $date->format('Y/m/d H:i');
    }

    /**
     * Get message preview (truncated content).
     */
    public function getPreviewAttribute(): string
    {
        if ($this->message_type !== 'text') {
            return match ($this->message_type) {
                'image' => '📷 صورة',
                'video' => '🎥 فيديو',
                'audio' => '🎵 رسالة صوتية',
                'file' => '📎 ملف',
                'sticker' => '😊 ملصق',
                'story_mention' => '📸 إشارة في قصة',
                'story_reply' => '📸 رد على قصة',
                'share' => '🔗 مشاركة',
                'reaction' => '❤️ تفاعل',
                default => 'رسالة',
            };
        }

        return \Str::limit($this->content, 50);
    }

    /**
     * Scope for incoming messages.
     */
    public function scopeIncoming($query)
    {
        return $query->where('direction', 'incoming');
    }

    /**
     * Scope for outgoing messages.
     */
    public function scopeOutgoing($query)
    {
        return $query->where('direction', 'outgoing');
    }

    /**
     * Scope for unread messages.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Mark as read.
     */
    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
    }

    /**
     * Update status.
     */
    public function updateStatus(string $status): void
    {
        $data = ['status' => $status];

        if ($status === 'delivered') {
            $data['delivered_at'] = now();
        } elseif ($status === 'read') {
            $data['is_read'] = true;
            $data['read_at'] = now();
        }

        $this->update($data);
    }
}
