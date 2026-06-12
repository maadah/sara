<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UnansweredQuestion;
use App\Models\AiKnowledgeBase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UnansweredQuestionsController extends Controller
{
    /**
     * Display unanswered questions
     */
    public function index(Request $request)
    {
        $userId = auth()->id();
        
        $query = UnansweredQuestion::where('user_id', $userId)
            ->with(['conversation', 'lead']);
        
        // Filter by status
        $status = $request->get('status', 'pending');
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        
        // Filter by category
        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }
        
        // Filter urgent
        if ($request->has('urgent') && $request->urgent) {
            $query->where('needs_urgent_attention', true);
        }
        
        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('question', 'LIKE', "%{$search}%");
        }
        
        // Sort - urgent first, then by occurrence count, then by date
        $query->orderBy('needs_urgent_attention', 'desc')
            ->orderBy('occurrence_count', 'desc')
            ->orderBy('created_at', 'desc');
        
        $questions = $query->paginate(15);
        
        // Get categories for filter
        $categories = UnansweredQuestion::where('user_id', $userId)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');
        
        // Get counts for tabs
        $counts = [
            'pending' => UnansweredQuestion::where('user_id', $userId)->pending()->count(),
            'answered' => UnansweredQuestion::where('user_id', $userId)->where('status', 'answered')->count(),
            'urgent' => UnansweredQuestion::where('user_id', $userId)->urgent()->pending()->count(),
        ];
        
        return view('admin.ai-helper.unanswered.index', compact('questions', 'categories', 'counts', 'status'));
    }
    
    /**
     * Show answer form for a question
     */
    public function show($id)
    {
        $question = UnansweredQuestion::where('user_id', auth()->id())
            ->with(['conversation.messages', 'lead'])
            ->findOrFail($id);
        
        // Find similar questions in knowledge base
        $similarKb = AiKnowledgeBase::findSimilar($question->question, auth()->id(), 5);
        
        return view('admin.ai-helper.unanswered.show', compact('question', 'similarKb'));
    }
    
    /**
     * Answer a question
     */
    public function answer(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'answer' => 'required|string|max:2000',
            'category' => 'nullable|string|max:50',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        $question = UnansweredQuestion::where('user_id', auth()->id())
            ->findOrFail($id);
        
        // Update question with answer
        $question->admin_answer = $request->answer;
        $question->category = $request->category;
        $question->answered_by = auth()->id();
        $question->answered_at = now();
        $question->status = 'answered';
        $question->is_reviewed = true;
        $question->save();
        
        // If checkbox is checked, add to knowledge base
        if ($request->has('add_to_kb')) {
            $kb = $question->addToKnowledgeBase([
                'priority' => $request->get('priority', 5),
            ]);
            
            return redirect()->route('customer.ai-helper.unanswered.index')
                ->with('success', 'تم حفظ الإجابة وإضافتها إلى قاعدة المعرفة!');
        }
        
        return redirect()->route('customer.ai-helper.unanswered.index')
            ->with('success', 'تم حفظ الإجابة بنجاح!');
    }
    
    /**
     * Ignore a question
     */
    public function ignore($id)
    {
        $question = UnansweredQuestion::where('user_id', auth()->id())
            ->findOrFail($id);
        
        $question->status = 'ignored';
        $question->is_reviewed = true;
        $question->save();
        
        return redirect()->route('customer.ai-helper.unanswered.index')
            ->with('success', 'تم تجاهل السؤال');
    }
    
    /**
     * Mark as urgent
     */
    public function toggleUrgent($id)
    {
        $question = UnansweredQuestion::where('user_id', auth()->id())
            ->findOrFail($id);
        
        $question->needs_urgent_attention = !$question->needs_urgent_attention;
        $question->save();
        
        return response()->json([
            'success' => true,
            'is_urgent' => $question->needs_urgent_attention
        ]);
    }
    
    /**
     * Reply directly to customer (sends message to conversation)
     */
    public function replyToCustomer(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        $question = UnansweredQuestion::where('user_id', auth()->id())
            ->with('conversation')
            ->findOrFail($id);
        
        if (!$question->conversation) {
            return response()->json(['success' => false, 'message' => 'المحادثة غير موجودة'], 404);
        }
        
        // Create message in conversation
        $message = $question->conversation->messages()->create([
            'content' => $request->message,
            'direction' => 'outgoing',
            'status' => 'sent',
            'message_type' => 'text',
        ]);
        
        // Mark question as answered
        $question->admin_answer = $request->message;
        $question->answered_by = auth()->id();
        $question->answered_at = now();
        $question->status = 'answered';
        $question->is_reviewed = true;
        $question->save();
        
        // TODO: Send actual message via WhatsApp/Facebook
        
        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الرد للعميل بنجاح!'
        ]);
    }
    
    /**
     * Get unreviewed count for notification badge
     */
    public function getUnreviewedCount()
    {
        $count = UnansweredQuestion::getUnreviewedCount(auth()->id());
        
        return response()->json(['count' => $count]);
    }
}
