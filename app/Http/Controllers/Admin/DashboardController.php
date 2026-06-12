<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Message;
use App\Models\Conversation;
use App\Models\AiFastReply;
use App\Models\AiKnowledgeBase;
use App\Models\AiChatSession;
use App\Models\UnansweredQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Show admin dashboard
     */
    public function index()
    {
        $stats = [
            'total_merchants' => User::where('role', 'customer')->count(),
            'pending_merchants' => User::where('role', 'customer')->where('status', 'pending')->count(),
            'approved_merchants' => User::where('role', 'customer')->where('status', 'approved')->count(),
            'total_subscriptions' => Subscription::count(),
        ];

        $recentMerchants = User::where('role', 'customer')
            ->latest()
            ->take(10)
            ->get();

        $pendingRequests = User::where('role', 'customer')
            ->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentMerchants', 'pendingRequests'));
    }

    /**
     * Show pending requests
     */
    public function pendingRequests()
    {
        $requests = User::where('role', 'customer')
            ->where('status', 'pending')
            ->latest()
            ->paginate(15);

        return view('admin.pending-requests', compact('requests'));
    }

    /**
     * Approve a user
     */
    public function approveUser(User $user)
    {
        $user->update(['status' => 'approved']);
        return back()->with('success', 'تم قبول التاجر بنجاح');
    }

    /**
     * Reject a user
     */
    public function rejectUser(User $user)
    {
        $user->update(['status' => 'rejected']);
        return back()->with('success', 'تم رفض التاجر');
    }

    /**
     * Show all merchants
     */
    public function merchants()
    {
        $merchants = User::where('role', 'customer')
            ->with('subscription')
            ->latest()
            ->paginate(15);

        return view('admin.merchants', compact('merchants'));
    }

    /**
     * Show merchant details
     */
    public function showMerchant(User $user)
    {
        $user->load('subscription');
        return view('admin.merchants-show', compact('user'));
    }

    /**
     * Edit merchant form
     */
    public function editMerchant(User $user)
    {
        $subscriptions = Subscription::all();
        return view('admin.merchants-edit', compact('user', 'subscriptions'));
    }

    /**
     * Update merchant
     */
    public function updateMerchant(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'status' => 'required|in:pending,approved,rejected,suspended',
            'subscription_id' => 'nullable|exists:subscriptions,id',
            'subscription_expires_at' => 'nullable|date',
        ]);

        // Handle empty date as null
        if (empty($validated['subscription_expires_at'])) {
            $validated['subscription_expires_at'] = null;
        }

        $user->update($validated);

        return redirect()->route('admin.merchants.show', $user)->with('success', 'تم تحديث بيانات التاجر بنجاح');
    }

    /**
     * Show subscriptions management
     */
    public function subscriptions()
    {
        $subscriptions = Subscription::latest()->paginate(15);
        return view('admin.subscriptions', compact('subscriptions'));
    }

    /**
     * AI Analytics Dashboard (Admin - All Stores)
     */
    public function aiAnalytics(Request $request)
    {
        // Date range filter (default last 30 days)
        $fromDate = $request->from_date ? Carbon::parse($request->from_date) : Carbon::now()->subDays(30);
        $toDate = $request->to_date ? Carbon::parse($request->to_date) : Carbon::now();

        // Optional: Filter by specific merchant
        $merchantId = $request->merchant_id;

        // 1. Total Messages Stats
        $totalMessages = Message::when($merchantId, function($q) use ($merchantId) {
            $q->whereHas('conversation', function($query) use ($merchantId) {
                $query->where('user_id', $merchantId);
            });
        })
        ->whereBetween('created_at', [$fromDate, $toDate])
        ->count();

        $incomingMessages = Message::when($merchantId, function($q) use ($merchantId) {
            $q->whereHas('conversation', function($query) use ($merchantId) {
                $query->where('user_id', $merchantId);
            });
        })
        ->where('direction', 'incoming')
        ->whereBetween('created_at', [$fromDate, $toDate])
        ->count();

        // 2. AI Generated vs Manual Messages
        $aiGeneratedMessages = Message::when($merchantId, function($q) use ($merchantId) {
            $q->whereHas('conversation', function($query) use ($merchantId) {
                $query->where('user_id', $merchantId);
            });
        })
        ->where('direction', 'outgoing')
        ->where('is_ai_generated', true)
        ->whereBetween('created_at', [$fromDate, $toDate])
        ->count();

        $manualMessages = Message::when($merchantId, function($q) use ($merchantId) {
            $q->whereHas('conversation', function($query) use ($merchantId) {
                $query->where('user_id', $merchantId);
            });
        })
        ->where('direction', 'outgoing')
        ->where('is_ai_generated', false)
        ->whereBetween('created_at', [$fromDate, $toDate])
        ->count();

        // 3. Fast Replies (Cached) Stats
        $fastRepliesUsed = AiFastReply::when($merchantId, function($q) use ($merchantId) {
            $q->where('user_id', $merchantId);
        })
        ->whereBetween('last_used_at', [$fromDate, $toDate])
        ->sum('usage_count');

        $totalFastReplies = AiFastReply::when($merchantId, function($q) use ($merchantId) {
            $q->where('user_id', $merchantId);
        })
        ->where('is_active', true)
        ->count();

        // 4. Knowledge Base Stats
        $knowledgeBaseHits = AiKnowledgeBase::when($merchantId, function($q) use ($merchantId) {
            $q->where('user_id', $merchantId);
        })
        ->whereBetween('last_used_at', [$fromDate, $toDate])
        ->sum('usage_count');

        $totalKnowledgeBase = AiKnowledgeBase::when($merchantId, function($q) use ($merchantId) {
            $q->where('user_id', $merchantId);
        })
        ->where('is_active', true)
        ->count();

        // 5. AI Sessions Stats
        $totalSessions = AiChatSession::when($merchantId, function($q) use ($merchantId) {
            $q->where('user_id', $merchantId);
        })
        ->whereBetween('created_at', [$fromDate, $toDate])
        ->count();

        $avgMessagesPerSession = AiChatSession::when($merchantId, function($q) use ($merchantId) {
            $q->where('user_id', $merchantId);
        })
        ->whereBetween('created_at', [$fromDate, $toDate])
        ->avg('message_count') ?? 0;

        // 6. Daily Message Breakdown (AI vs Manual vs Cached)
        $dailyBreakdown = [];
        $currentDate = $fromDate->copy();

        while ($currentDate->lte($toDate)) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayName = $this->getArabicDayName($currentDate->dayOfWeek);

            $dayAiMessages = Message::when($merchantId, function($q) use ($merchantId) {
                $q->whereHas('conversation', function($query) use ($merchantId) {
                    $query->where('user_id', $merchantId);
                });
            })
            ->where('direction', 'outgoing')
            ->where('is_ai_generated', true)
            ->whereDate('created_at', $dateKey)
            ->count();

            $dayManualMessages = Message::when($merchantId, function($q) use ($merchantId) {
                $q->whereHas('conversation', function($query) use ($merchantId) {
                    $query->where('user_id', $merchantId);
                });
            })
            ->where('direction', 'outgoing')
            ->where('is_ai_generated', false)
            ->whereDate('created_at', $dateKey)
            ->count();

            $dailyBreakdown[] = [
                'date' => $dateKey,
                'day' => $dayName,
                'ai_messages' => $dayAiMessages,
                'manual_messages' => $dayManualMessages,
                'total' => $dayAiMessages + $dayManualMessages,
            ];

            $currentDate->addDay();
        }

        // 7. Top Fast Replies (Most Used)
        $topFastReplies = AiFastReply::when($merchantId, function($q) use ($merchantId) {
            $q->where('user_id', $merchantId);
        })
        ->where('is_active', true)
        ->where('usage_count', '>', 0)
        ->orderBy('usage_count', 'desc')
        ->take(5)
        ->get();

        // 8. AI Efficiency Metrics
        $totalOutgoingMessages = $aiGeneratedMessages + $manualMessages;
        $aiEfficiencyRate = $totalOutgoingMessages > 0
            ? round(($aiGeneratedMessages / $totalOutgoingMessages) * 100, 1)
            : 0;

        $cachedResponseRate = $aiGeneratedMessages > 0
            ? round(($fastRepliesUsed / $aiGeneratedMessages) * 100, 1)
            : 0;

        // 9. Response Time Comparison
        $avgAiResponseTime = $this->getAverageResponseTime($fromDate, $toDate, true, $merchantId);
        $avgManualResponseTime = $this->getAverageResponseTime($fromDate, $toDate, false, $merchantId);

        // 10. Conversation Stats
        $totalConversations = Conversation::when($merchantId, function($q) use ($merchantId) {
            $q->where('user_id', $merchantId);
        })
        ->whereBetween('created_at', [$fromDate, $toDate])
        ->count();

        $aiHandledConversations = Conversation::when($merchantId, function($q) use ($merchantId) {
            $q->where('user_id', $merchantId);
        })
        ->where('ai_enabled', true)
        ->whereBetween('created_at', [$fromDate, $toDate])
        ->count();

        // 11. Unanswered Questions Stats
        $unansweredQuestions = UnansweredQuestion::when($merchantId, function($q) use ($merchantId) {
            $q->where('user_id', $merchantId);
        })
        ->whereBetween('created_at', [$fromDate, $toDate])
        ->count();

        $pendingUnanswered = UnansweredQuestion::when($merchantId, function($q) use ($merchantId) {
            $q->where('user_id', $merchantId);
        })
        ->where('is_reviewed', false)
        ->count();

        $urgentQuestions = UnansweredQuestion::when($merchantId, function($q) use ($merchantId) {
            $q->where('user_id', $merchantId);
        })
        ->where('needs_urgent_attention', true)
        ->where('is_reviewed', false)
        ->count();

        // 12. Top Knowledge Base Items
        $topKnowledgeBase = AiKnowledgeBase::when($merchantId, function($q) use ($merchantId) {
            $q->where('user_id', $merchantId);
        })
        ->where('status', 'active')
        ->where('usage_count', '>', 0)
        ->orderBy('usage_count', 'desc')
        ->take(5)
        ->get();

        // 13. Merchant AI Usage Stats
        $merchantsWithAI = User::where('role', 'customer')
            ->whereHas('conversations', function($q) use ($fromDate, $toDate) {
                $q->where('ai_enabled', true)
                  ->whereBetween('created_at', [$fromDate, $toDate]);
            })
            ->count();

        $totalMerchants = User::where('role', 'customer')
            ->where('status', 'approved')
            ->count();

        // 14. Error Rate & Success Rate
        $successfulAIResponses = Message::when($merchantId, function($q) use ($merchantId) {
            $q->whereHas('conversation', function($query) use ($merchantId) {
                $query->where('user_id', $merchantId);
            });
        })
        ->where('is_ai_generated', true)
        ->where('direction', 'outgoing')
        ->whereBetween('created_at', [$fromDate, $toDate])
        ->count();

        $failedAIAttempts = UnansweredQuestion::when($merchantId, function($q) use ($merchantId) {
            $q->where('user_id', $merchantId);
        })
        ->whereBetween('created_at', [$fromDate, $toDate])
        ->count();

        $totalAIAttempts = $successfulAIResponses + $failedAIAttempts;
        $aiSuccessRate = $totalAIAttempts > 0
            ? round(($successfulAIResponses / $totalAIAttempts) * 100, 1)
            : 0;

        // 15. Cost Savings Estimate (assuming $0.001 per AI message vs $0.05 per manual)
        $estimatedCostSavings = ($aiGeneratedMessages * 0.001) - ($aiGeneratedMessages * 0.05);
        $estimatedCostSavings = abs($estimatedCostSavings); // Savings is positive

        // 16. Most Active Merchants
        $topMerchants = User::where('role', 'customer')
            ->withCount(['conversations' => function($q) use ($fromDate, $toDate) {
                $q->where('ai_enabled', true)
                  ->whereBetween('created_at', [$fromDate, $toDate]);
            }])
            ->get()
            ->where('conversations_count', '>', 0)
            ->sortByDesc('conversations_count')
            ->take(5);

        // Get all merchants for dropdown filter
        $merchants = User::where('role', 'customer')
            ->where('status', 'approved')
            ->orderBy('name')
            ->get();

        $stats = [
            'total_messages' => $totalMessages,
            'incoming_messages' => $incomingMessages,
            'ai_generated' => $aiGeneratedMessages,
            'manual_messages' => $manualMessages,
            'fast_replies_used' => $fastRepliesUsed,
            'total_fast_replies' => $totalFastReplies,
            'knowledge_base_hits' => $knowledgeBaseHits,
            'total_knowledge_base' => $totalKnowledgeBase,
            'total_sessions' => $totalSessions,
            'avg_messages_per_session' => round($avgMessagesPerSession, 1),
            'ai_efficiency_rate' => $aiEfficiencyRate,
            'cached_response_rate' => $cachedResponseRate,
            'avg_ai_response_time' => $avgAiResponseTime,
            'avg_manual_response_time' => $avgManualResponseTime,
            'total_conversations' => $totalConversations,
            'ai_handled_conversations' => $aiHandledConversations,
            'unanswered_questions' => $unansweredQuestions,
            'pending_unanswered' => $pendingUnanswered,
            'urgent_questions' => $urgentQuestions,
            'merchants_with_ai' => $merchantsWithAI,
            'total_merchants' => $totalMerchants,
            'ai_success_rate' => $aiSuccessRate,
            'estimated_cost_savings' => $estimatedCostSavings,
        ];

        return view('admin.ai-analytics', compact(
            'stats',
            'dailyBreakdown',
            'topFastReplies',
            'topKnowledgeBase',
            'topMerchants',
            'fromDate',
            'toDate',
            'merchants',
            'merchantId'
        ));
    }

    /**
     * Get average response time for AI or Manual messages
     */
    private function getAverageResponseTime($fromDate, $toDate, $isAi = true, $merchantId = null)
    {
        // Get conversations with messages in date range
        $conversations = Conversation::when($merchantId, function($q) use ($merchantId) {
            $q->where('user_id', $merchantId);
        })
        ->with(['messages' => function($q) use ($fromDate, $toDate, $isAi) {
            $q->whereBetween('created_at', [$fromDate, $toDate])
              ->where('direction', 'outgoing')
              ->where('is_ai_generated', $isAi)
              ->orderBy('created_at');
        }])
        ->get();

        $responseTimes = [];

        foreach ($conversations as $conversation) {
            $messages = $conversation->messages->sortBy('created_at');
            $lastIncoming = null;

            foreach ($messages as $message) {
                if ($message->direction === 'incoming') {
                    $lastIncoming = $message;
                } elseif ($lastIncoming && $message->is_ai_generated === $isAi) {
                    $responseTime = $lastIncoming->created_at->diffInSeconds($message->created_at);
                    $responseTimes[] = $responseTime;
                    $lastIncoming = null;
                }
            }
        }

        return count($responseTimes) > 0 ? round(array_sum($responseTimes) / count($responseTimes), 1) : 0;
    }

    /**
     * Get Arabic day name
     */
    private function getArabicDayName($dayOfWeek)
    {
        $days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
        return $days[$dayOfWeek] ?? '';
    }

    /**
     * AI Management Dashboard
     */
    public function aiManagement(Request $request)
    {
        $merchantId = $request->merchant_id;

        // Knowledge Base Stats - Include store-specific AND global (user_id = null)
        $knowledgeBase = AiKnowledgeBase::when($merchantId, function($q) use ($merchantId) {
            // Show items for specific merchant OR global items
            $q->where(function($query) use ($merchantId) {
                $query->where('user_id', $merchantId)
                      ->orWhereNull('user_id');
            });
        }, function($q) {
            // If no merchant selected, show all
            return $q;
        })
        ->with('user')
        ->orderBy('usage_count', 'desc')
        ->paginate(15, ['*'], 'kb_page');

        // Fast Replies Stats - Include store-specific AND global
        $fastReplies = AiFastReply::when($merchantId, function($q) use ($merchantId) {
            $q->where(function($query) use ($merchantId) {
                $query->where('user_id', $merchantId)
                      ->orWhereNull('user_id');
            });
        }, function($q) {
            return $q;
        })
        ->with('user')
        ->orderBy('usage_count', 'desc')
        ->paginate(15, ['*'], 'fr_page');

        // Unanswered Questions - Only store-specific
        $unansweredQuestions = UnansweredQuestion::when($merchantId, function($q) use ($merchantId) {
            $q->where('user_id', $merchantId);
        })
        ->with(['user', 'conversation'])
        ->where('is_reviewed', false)
        ->orderBy('needs_urgent_attention', 'desc')
        ->orderBy('occurrence_count', 'desc')
        ->paginate(15, ['*'], 'uq_page');

        // Get merchants for filter
        $merchants = User::where('role', 'customer')
            ->where('status', 'approved')
            ->orderBy('name')
            ->get();

        $stats = [
            'total_kb' => AiKnowledgeBase::when($merchantId, function($q) use ($merchantId) {
                $q->where(function($query) use ($merchantId) {
                    $query->where('user_id', $merchantId)->orWhereNull('user_id');
                });
            })->count(),
            'active_kb' => AiKnowledgeBase::when($merchantId, function($q) use ($merchantId) {
                $q->where(function($query) use ($merchantId) {
                    $query->where('user_id', $merchantId)->orWhereNull('user_id');
                });
            })->where('status', 'active')->count(),
            'global_kb' => AiKnowledgeBase::whereNull('user_id')->count(),
            'total_fr' => AiFastReply::when($merchantId, function($q) use ($merchantId) {
                $q->where(function($query) use ($merchantId) {
                    $query->where('user_id', $merchantId)->orWhereNull('user_id');
                });
            })->count(),
            'active_fr' => AiFastReply::when($merchantId, function($q) use ($merchantId) {
                $q->where(function($query) use ($merchantId) {
                    $query->where('user_id', $merchantId)->orWhereNull('user_id');
                });
            })->where('is_active', true)->count(),
            'global_fr' => AiFastReply::whereNull('user_id')->count(),
            'pending_questions' => UnansweredQuestion::when($merchantId, function($q) use ($merchantId) {
                $q->where('user_id', $merchantId);
            })->where('is_reviewed', false)->count(),
            'urgent_questions' => UnansweredQuestion::when($merchantId, function($q) use ($merchantId) {
                $q->where('user_id', $merchantId);
            })->where('needs_urgent_attention', true)->where('is_reviewed', false)->count(),
        ];

        return view('admin.ai-management', compact(
            'knowledgeBase',
            'fastReplies',
            'unansweredQuestions',
            'merchants',
            'merchantId',
            'stats'
        ));
    }

    /**
     * Update Knowledge Base Entry
     */
    public function updateKnowledgeBase(Request $request, AiKnowledgeBase $knowledgeBase)
    {
        $validated = $request->validate([
            'question' => 'required|string',
            'answer' => 'required|string',
            'category' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'is_verified' => 'boolean',
            'priority' => 'integer|min:0|max:10',
        ]);

        $knowledgeBase->update($validated);

        return back()->with('success', 'تم تحديث المعرفة بنجاح');
    }

    /**
     * Delete Knowledge Base Entry
     */
    public function deleteKnowledgeBase(AiKnowledgeBase $knowledgeBase)
    {
        $knowledgeBase->delete();
        return back()->with('success', 'تم حذف المعرفة بنجاح');
    }

    /**
     * Update Fast Reply
     */
    public function updateFastReply(Request $request, AiFastReply $fastReply)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'reply_text' => 'required|string',
            'trigger_keywords' => 'nullable|array',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0|max:10',
        ]);

        $fastReply->update($validated);

        return back()->with('success', 'تم تحديث الرد السريع بنجاح');
    }

    /**
     * Delete Fast Reply
     */
    public function deleteFastReply(AiFastReply $fastReply)
    {
        $fastReply->delete();
        return back()->with('success', 'تم حذف الرد السريع بنجاح');
    }

    /**
     * Answer Unanswered Question
     */
    public function answerQuestion(Request $request, UnansweredQuestion $question)
    {
        $validated = $request->validate([
            'admin_answer' => 'required|string',
            'add_to_knowledge_base' => 'boolean',
            'category' => 'nullable|string',
        ]);

        $question->update([
            'admin_answer' => $validated['admin_answer'],
            'answered_by' => auth()->id(),
            'answered_at' => now(),
            'is_reviewed' => true,
            'status' => 'answered',
        ]);

        // Add to knowledge base if requested
        if ($request->add_to_knowledge_base) {
            $question->convertToKnowledgeBase([
                'category' => $validated['category'] ?? null,
            ]);
        }

        return back()->with('success', 'تم الرد على السؤال بنجاح');
    }

    /**
     * Bulk Actions for Knowledge Base
     */
    public function bulkKnowledgeBaseAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:activate,deactivate,delete,verify',
            'ids' => 'required|array',
            'ids.*' => 'exists:ai_knowledge_base,id',
        ]);

        $items = AiKnowledgeBase::whereIn('id', $validated['ids']);

        switch ($validated['action']) {
            case 'activate':
                $items->update(['status' => 'active']);
                $message = 'تم تفعيل العناصر المحددة';
                break;
            case 'deactivate':
                $items->update(['status' => 'inactive']);
                $message = 'تم إلغاء تفعيل العناصر المحددة';
                break;
            case 'verify':
                $items->update(['is_verified' => true]);
                $message = 'تم التحقق من العناصر المحددة';
                break;
            case 'delete':
                $items->delete();
                $message = 'تم حذف العناصر المحددة';
                break;
        }

        return back()->with('success', $message);
    }

    /**
     * Create Knowledge Base Entry from Admin
     */
    public function createKnowledgeBase(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'question' => 'required|string',
            'answer' => 'required|string',
            'category' => 'nullable|string',
            'keywords' => 'nullable|array',
            'priority' => 'integer|min:0|max:10',
        ]);

        $validated['status'] = 'active';
        $validated['is_verified'] = true;
        $validated['keywords'] = $validated['keywords'] ?? AiKnowledgeBase::extractKeywords($validated['question']);

        AiKnowledgeBase::create($validated);

        return back()->with('success', 'تم إضافة المعرفة بنجاح');
    }

    /**
     * Create Fast Reply from Admin
     */
    public function createFastReply(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'name' => 'required|string',
            'reply_text' => 'required|string',
            'trigger_keywords' => 'nullable|array',
            'trigger_type' => 'required|in:keyword,greeting,order,product,price,shipping,other',
            'priority' => 'integer|min:0|max:10',
        ]);

        $validated['is_active'] = true;

        AiFastReply::create($validated);

        return back()->with('success', 'تم إضافة الرد السريع بنجاح');
    }
}
