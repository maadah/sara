<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiFastReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'trigger_type',
        'trigger_keywords',
        'reply_text',
        'use_emojis',
        'tone',
        'variables',
        'when_to_use',
        'priority',
        'is_active',
        'usage_count',
        'last_used_at',
    ];

    protected $casts = [
        'trigger_keywords' => 'array',
        'variables' => 'array',
        'use_emojis' => 'boolean',
        'is_active' => 'boolean',
        'usage_count' => 'integer',
        'priority' => 'integer',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the user that owns the fast reply
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for active replies
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by trigger type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('trigger_type', $type);
    }

    /**
     * Get formatted reply with variable replacement
     */
    public function getFormattedReply(array $data = []): string
    {
        $reply = $this->reply_text;
        
        // Replace variables
        $variables = $this->variables ?? [];
        foreach ($variables as $variable) {
            $value = $data[$variable] ?? '';
            $reply = str_replace('{' . $variable . '}', $value, $reply);
        }
        
        // Remove emojis if needed
        if (!$this->use_emojis) {
            $reply = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $reply);
            $reply = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', '', $reply);
            $reply = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $reply);
            $reply = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $reply);
            $reply = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $reply);
        }
        
        return trim($reply);
    }

    /**
     * Increment usage count
     */
    public function incrementUsage()
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Check if this reply should be used for the given message
     */
    public function shouldTrigger(string $message): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $messageLower = mb_strtolower($message);
        $keywords = $this->trigger_keywords ?? [];

        foreach ($keywords as $keyword) {
            if (mb_strpos($messageLower, mb_strtolower($keyword)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get default fast replies for a store
     */
    public static function getDefaults(int $userId): array
    {
        return [
            [
                'name' => 'رسالة الترحيب',
                'trigger_type' => 'welcome',
                'trigger_keywords' => ['السلام عليكم', 'مرحبا', 'هلا', 'هاي'],
                'reply_text' => 'هلا وغلا! 👋 أهلاً بيك في {store_name}، شنو اقدر أساعدك به اليوم؟ 😊',
                'use_emojis' => true,
                'tone' => 'friendly',
                'variables' => ['store_name', 'customer_name'],
                'when_to_use' => 'first_message',
                'priority' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'رسالة الوداع',
                'trigger_type' => 'goodbye',
                'trigger_keywords' => ['الله يحفظك', 'باي', 'مع السلامة', 'شكراً'],
                'reply_text' => 'حياك الله! تشرفنا بخدمتك. إذا احتجت أي شي، احنا موجودين! 🙏',
                'use_emojis' => true,
                'tone' => 'friendly',
              'when_to_use' => 'always',
                'priority' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'شكراً على الطلب',
                'trigger_type' => 'thank_you',
                'trigger_keywords' => [],
                'reply_text' => 'شكراً لثقتك يا {customer_name}! 🎉 طلبك راح يوصلك قريباً. نتمنى يعجبك! ❤️',
                'use_emojis' => true,
                'tone' => 'friendly',
                'variables' => ['customer_name'],
                'when_to_use' => 'after_order',
                'priority' => 8,
                'is_active' => true,
            ],
        ];
    }

    /**
     * Get random reply by type (used for asking customer info)
     */
    public static function getRandomReply(int $userId, string $type): ?string
    {
        $reply = static::where('user_id', $userId)
            ->where('trigger_type', $type)
            ->where('is_active', true)
            ->inRandomOrder()
            ->first();
        
        if ($reply) {
            $reply->incrementUsage();
            return $reply->reply_text;
        }
        
        return null;
    }
}
