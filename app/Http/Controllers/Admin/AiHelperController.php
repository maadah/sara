<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiKnowledgeBase;
use App\Models\UnansweredQuestion;
use App\Models\AiFastReply;
use Illuminate\Http\Request;

class AiHelperController extends Controller
{
    /**
     * Display AI Helper dashboard
     */
    public function index()
    {
        $userId = auth()->id();
        
        // Get statistics
        $stats = [
            'knowledge_base_count' => AiKnowledgeBase::where('user_id', $userId)->count(),
            'active_kb_count' => AiKnowledgeBase::where('user_id', $userId)->active()->count(),
            'pending_questions_count' => UnansweredQuestion::where('user_id', $userId)->pending()->count(),
            'answered_questions_count' => UnansweredQuestion::where('user_id', $userId)->where('status', 'answered')->count(),
            'fast_replies_count' => AiFastReply::where('user_id', $userId)->count(),
            'active_fast_replies_count' => AiFastReply::where('user_id', $userId)->active()->count(),
        ];
        
        // Most used knowledge base entries
        $topKnowledge = AiKnowledgeBase::where('user_id', $userId)
            ->active()
            ->orderBy('usage_count', 'desc')
            ->limit(5)
            ->get();
        
        // Recent unanswered questions
        $recentQuestions = UnansweredQuestion::where('user_id', $userId)
            ->pending()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->with('conversation')
            ->get();
        
        // Urgent questions
        $urgentQuestions = UnansweredQuestion::where('user_id', $userId)
            ->urgent()
            ->pending()
            ->count();
        
        return view('admin.ai-helper.index', compact(
            'stats',
            'topKnowledge',
            'recentQuestions',
            'urgentQuestions'
        ));
    }
    
    /**
     * Get notification count for badge
     */
    public function getNotificationCount()
    {
        $userId = auth()->id();
        
        $count = UnansweredQuestion::where('user_id', $userId)
            ->where('is_reviewed', false)
            ->count();
        
        return response()->json(['count' => $count]);
    }
    
    /**
     * Get AI performance metrics
     */
    public function getMetrics(Request $request)
    {
        $userId = auth()->id();
        $days = $request->get('days', 7);
        
        $startDate = now()->subDays($days);
        
        // Questions answered over time
        $questionsAnswered = UnansweredQuestion::where('user_id', $userId)
            ->where('status', 'answered')
            ->where('answered_at', '>=', $startDate)
            ->selectRaw('DATE(answered_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // Knowledge base growth
        $kbGrowth = AiKnowledgeBase::where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // Most common categories
        $categories = UnansweredQuestion::where('user_id', $userId)
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();
        
        return response()->json([
            'questions_answered' => $questionsAnswered,
            'kb_growth' => $kbGrowth,
            'categories' => $categories,
        ]);
    }
}
