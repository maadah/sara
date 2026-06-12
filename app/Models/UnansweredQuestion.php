<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnansweredQuestion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'conversation_id',
        'lead_id',
        'question',
        'context',
        'detected_intent',
        'confidence_score',
        'admin_answer',
        'answered_by',
        'answered_at',
        'status',
        'is_reviewed',
        'occurrence_count',
        'category',
        'similar_questions',
        'needs_urgent_attention',
    ];

    protected $casts = [
        'similar_questions' => 'array',
        'confidence_score' => 'float',
        'is_reviewed' => 'boolean',
        'needs_urgent_attention' => 'boolean',
        'occurrence_count' => 'integer',
        'answered_at' => 'datetime',
    ];

    /**
     * Get the user that owns the question
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the conversation
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the lead
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the admin who answered
     */
    public function answeredBy()
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    /**
     * Scope for pending questions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for unreviewed questions
     */
    public function scopeUnreviewed($query)
    {
        return $query->where('is_reviewed', false);
    }

    /**
     * Scope for urgent questions
     */
    public function scopeUrgent($query)
    {
        return $query->where('needs_urgent_attention', true);
    }

    /**
     * Mark as answered
     */
    public function markAsAnswered(string $answer, int $adminId)
    {
        $this->update([
            'admin_answer' => $answer,
            'answered_by' => $adminId,
            'answered_at' => now(),
            'status' => 'answered',
            'is_reviewed' => true,
        ]);
    }

    /**
     * Add to knowledge base
     */
    public function addToKnowledgeBase(array $attributes = [])
    {
        $kb = AiKnowledgeBase::create(array_merge([
            'user_id' => $this->user_id,
            'question' => $this->question,
            'answer' => $this->admin_answer,
            'category' => $this->category,
            'keywords' => AiKnowledgeBase::extractKeywords($this->question),
            'status' => 'active',
            'is_verified' => true,
        ], $attributes));

        $this->update(['status' => 'added_to_kb']);

        return $kb;
    }

    /**
     * Find or create unanswered question
     */
    public static function findOrCreate(array $data)
    {
        // Try to find similar question
        $existing = static::where('user_id', $data['user_id'])
            ->where('status', 'pending')
            ->whereRaw('LOWER(question) = ?', [mb_strtolower($data['question'])])
            ->first();

        if ($existing) {
            $existing->increment('occurrence_count');
            return $existing;
        }

        return static::create($data);
    }

    /**
     * Get count of pending questions for user
     */
    public static function getPendingCount(int $userId): int
    {
        return static::where('user_id', $userId)
            ->where('status', 'pending')
            ->count();
    }

    /**
     * Get count of unreviewed questions for user
     */
    public static function getUnreviewedCount(int $userId): int
    {
        return static::where('user_id', $userId)
            ->where('is_reviewed', false)
            ->count();
    }
}
