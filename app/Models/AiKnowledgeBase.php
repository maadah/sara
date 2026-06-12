<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiKnowledgeBase extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_knowledge_base';

    protected $fillable = [
        'user_id',
        'question',
        'answer',
        'category',
        'keywords',
        'usage_count',
        'status',
        'is_verified',
        'use_for_training',
        'priority',
    ];

    protected $casts = [
        'keywords' => 'array',
        'is_verified' => 'boolean',
        'use_for_training' => 'boolean',
        'usage_count' => 'integer',
        'priority' => 'integer',
    ];

    /**
     * Get the user that owns the knowledge base entry
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for active entries
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for verified entries
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope for training-ready entries
     */
    public function scopeForTraining($query)
    {
        return $query->where('use_for_training', true)
                     ->where('status', 'active');
    }

    /**
     * Search for similar questions in knowledge base (SQLite compatible)
     */
    public static function findSimilar(string $question, int $userId, int $limit = 5)
    {
        $questionLower = mb_strtolower(trim($question));
        $questionNormalized = preg_replace('/[؟?]+/', '', $questionLower);
        $questionNormalized = preg_replace('/\s+/', ' ', $questionNormalized);
        
        // Extract words from the question
        $words = explode(' ', $questionNormalized);
        $words = array_filter($words, function($word) {
            return mb_strlen($word) > 2; // Only words longer than 2 chars
        });
        
        $entries = static::where('user_id', $userId)
            ->where('status', 'active')
            ->get();
        
        $scored = [];
        foreach ($entries as $entry) {
            $score = 0;
            $entryQuestion = mb_strtolower($entry->question);
            $entryAnswer = mb_strtolower($entry->answer);
            
            // Score based on word matches
            foreach ($words as $word) {
                if (mb_strpos($entryQuestion, $word) !== false) {
                    $score += 3; // Question match = higher score
                }
                if (mb_strpos($entryAnswer, $word) !== false) {
                    $score += 1; // Answer match = lower score
                }
            }
            
            if ($score > 0) {
                $scored[] = [
                    'entry' => $entry,
                    'score' => $score
                ];
            }
        }
        
        // Sort by score descending
        usort($scored, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Return top results
        return collect(array_slice($scored, 0, $limit))->map(function($item) {
            return $item['entry'];
        });
    }

    /**
     * Increment usage count
     */
    public function incrementUsage()
    {
        $this->increment('usage_count');
    }

    /**
     * Extract keywords from question
     */
    public static function extractKeywords(string $text): array
    {
        // Simple Arabic keyword extraction
        $stopWords = ['في', 'من', 'إلى', 'على', 'عن', 'هل', 'ما', 'كيف', 'اريد', 'ابي', 'شنو', 'وين', 'الى', 'هذا', 'هذه'];
        
        $words = preg_split('/\s+/', mb_strtolower($text));
        $keywords = [];
        
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word) > 2 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }
        
        return array_unique(array_slice($keywords, 0, 10));
    }
}
