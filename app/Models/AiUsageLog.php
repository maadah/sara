<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AiUsageLog extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'model',
        'request_type',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'estimated_cost',
        'conversation_id',
        'lead_id',
        'metadata',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
        'estimated_cost' => 'decimal:6',
        'metadata' => 'array',
    ];

    /**
     * Pricing per 1M tokens (USD) - Updated January 2026
     * Format: [input_price, output_price]
     */
    public static array $modelPricing = [
        // OpenAI Models
        'gpt-4.1-mini' => ['input' => 0.15, 'output' => 0.60],       // Recommended - balanced
        'gpt-5-nano' => ['input' => 0.15, 'output' => 0.60],          // Recommended - Most cost effective
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],             // High quality
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],         // Fast & cheap
        'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],       // Legacy
        'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],       // Legacy cheap
        
        // Groq Models (Free tier limits apply)
        'llama-3.3-70b-versatile' => ['input' => 0.59, 'output' => 0.79],
        'llama-3.1-70b-versatile' => ['input' => 0.59, 'output' => 0.79],
        'llama-3.1-8b-instant' => ['input' => 0.05, 'output' => 0.08],
        'mixtral-8x7b-32768' => ['input' => 0.24, 'output' => 0.24],
        'gemma2-9b-it' => ['input' => 0.20, 'output' => 0.20],
    ];

    /**
     * Get the store owner
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate estimated cost based on tokens and model
     */
    public static function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $pricing = self::$modelPricing[$model] ?? ['input' => 1.00, 'output' => 2.00];
        
        // Price is per 1M tokens
        $inputCost = ($inputTokens / 1000000) * $pricing['input'];
        $outputCost = ($outputTokens / 1000000) * $pricing['output'];
        
        return round($inputCost + $outputCost, 6);
    }

    /**
     * Log an API call
     */
    public static function logUsage(
        int $userId,
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        string $requestType = 'chat',
        ?string $conversationId = null,
        ?string $leadId = null,
        ?array $metadata = null
    ): self {
        $totalTokens = $inputTokens + $outputTokens;
        $estimatedCost = self::calculateCost($model, $inputTokens, $outputTokens);

        return self::create([
            'user_id' => $userId,
            'provider' => $provider,
            'model' => $model,
            'request_type' => $requestType,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $totalTokens,
            'estimated_cost' => $estimatedCost,
            'conversation_id' => $conversationId,
            'lead_id' => $leadId,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get usage summary for a user (today)
     * Pass null for userId to get all users' usage
     */
    public static function getTodayUsage(?int $userId = null): array
    {
        $today = now()->startOfDay();
        
        $query = self::where('created_at', '>=', $today);
        
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        
        $result = $query->selectRaw('
                COUNT(*) as total_requests,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(total_tokens) as total_tokens,
                SUM(estimated_cost) as total_cost
            ')
            ->first();
            
        return $result ? $result->toArray() : [
            'total_requests' => 0,
            'total_input_tokens' => 0,
            'total_output_tokens' => 0,
            'total_tokens' => 0,
            'total_cost' => 0,
        ];
    }

    /**
     * Get usage summary for a user (this month)
     * Pass null for userId to get all users' usage
     */
    public static function getMonthUsage(?int $userId = null): array
    {
        $startOfMonth = now()->startOfMonth();
        
        $query = self::where('created_at', '>=', $startOfMonth);
        
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        
        $result = $query->selectRaw('
                COUNT(*) as total_requests,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(total_tokens) as total_tokens,
                SUM(estimated_cost) as total_cost
            ')
            ->first();
            
        return $result ? $result->toArray() : [
            'total_requests' => 0,
            'total_input_tokens' => 0,
            'total_output_tokens' => 0,
            'total_tokens' => 0,
            'total_cost' => 0,
        ];
    }

    /**
     * Get usage by model for a user
     * Pass null for userId to get all users' usage
     */
    public static function getUsageByModel(?int $userId = null, ?string $period = 'month'): array
    {
        $query = self::query();
        
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        
        if ($period === 'today') {
            $query->where('created_at', '>=', now()->startOfDay());
        } elseif ($period === 'week') {
            $query->where('created_at', '>=', now()->startOfWeek());
        } elseif ($period === 'month') {
            $query->where('created_at', '>=', now()->startOfMonth());
        }
        
        return $query->selectRaw('
                model,
                provider,
                COUNT(*) as requests,
                SUM(total_tokens) as tokens,
                SUM(estimated_cost) as cost
            ')
            ->groupBy('model', 'provider')
            ->orderByDesc('cost')
            ->get()
            ->toArray();
    }

    /**
     * Get usage by request type for a user
     * Pass null for userId to get all users' usage
     */
    public static function getUsageByType(?int $userId = null, ?string $period = 'month'): array
    {
        $query = self::query();
        
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        
        if ($period === 'today') {
            $query->where('created_at', '>=', now()->startOfDay());
        } elseif ($period === 'week') {
            $query->where('created_at', '>=', now()->startOfWeek());
        } elseif ($period === 'month') {
            $query->where('created_at', '>=', now()->startOfMonth());
        }
        
        return $query->selectRaw('
                request_type,
                COUNT(*) as requests,
                SUM(total_tokens) as tokens,
                SUM(estimated_cost) as cost
            ')
            ->groupBy('request_type')
            ->orderByDesc('requests')
            ->get()
            ->toArray();
    }

    /**
     * Get daily usage for charts
     * Pass null for userId to get all users' usage
     */
    public static function getDailyUsage(?int $userId = null, int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        
        $query = self::where('created_at', '>=', $startDate);
        
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        
        return $query->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as requests,
                SUM(total_tokens) as tokens,
                SUM(estimated_cost) as cost
            ')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Get all stores usage summary (for admin)
     */
    public static function getAllStoresUsage(?string $period = 'month'): array
    {
        $query = self::query();
        
        if ($period === 'today') {
            $query->where('created_at', '>=', now()->startOfDay());
        } elseif ($period === 'week') {
            $query->where('created_at', '>=', now()->startOfWeek());
        } elseif ($period === 'month') {
            $query->where('created_at', '>=', now()->startOfMonth());
        }
        
        return $query->with('user:id,name,email')
            ->selectRaw('
                user_id,
                COUNT(*) as requests,
                SUM(total_tokens) as tokens,
                SUM(estimated_cost) as cost
            ')
            ->groupBy('user_id')
            ->orderByDesc('cost')
            ->get()
            ->toArray();
    }
}
