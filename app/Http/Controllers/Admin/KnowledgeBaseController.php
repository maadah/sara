<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiKnowledgeBase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KnowledgeBaseController extends Controller
{
    /**
     * Display knowledge base entries
     */
    public function index(Request $request)
    {
        $userId = auth()->id();
        
        $query = AiKnowledgeBase::where('user_id', $userId);
        
        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        // Filter by category
        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }
        
        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('question', 'LIKE', "%{$search}%")
                  ->orWhere('answer', 'LIKE', "%{$search}%");
            });
        }
        
        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);
        
        $entries = $query->paginate(20);
        
        // Get categories for filter
        $categories = AiKnowledgeBase::where('user_id', $userId)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');
        
        return view('admin.ai-helper.knowledge-base.index', compact('entries', 'categories'));
    }
    
    /**
     * Show create form
     */
    public function create()
    {
        return view('admin.ai-helper.knowledge-base.create');
    }
    
    /**
     * Store new knowledge base entry
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:1000',
            'answer' => 'required|string|max:2000',
            'category' => 'nullable|string|max:50',
            'priority' => 'nullable|integer|min:0|max:100',
            'status' => 'required|in:active,inactive,draft',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        $kb = AiKnowledgeBase::create([
            'user_id' => auth()->id(),
            'question' => $request->question,
            'answer' => $request->answer,
            'category' => $request->category,
            'keywords' => AiKnowledgeBase::extractKeywords($request->question),
            'status' => $request->status,
            'is_verified' => true,  // Admin created = verified
            'use_for_training' => $request->has('use_for_training'),
            'priority' => $request->priority ?? 0,
        ]);
        
        return redirect()->route('customer.ai-helper.knowledge-base.index')
            ->with('success', 'تم إضافة السؤال والجواب بنجاح!');
    }
    
    /**
     * Show edit form
     */
    public function edit($id)
    {
        $entry = AiKnowledgeBase::where('user_id', auth()->id())
            ->findOrFail($id);
        
        return view('admin.ai-helper.knowledge-base.edit', compact('entry'));
    }
    
    /**
     * Update knowledge base entry
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:1000',
            'answer' => 'required|string|max:2000',
            'category' => 'nullable|string|max:50',
            'priority' => 'nullable|integer|min:0|max:100',
            'status' => 'required|in:active,inactive,draft',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        $entry = AiKnowledgeBase::where('user_id', auth()->id())
            ->findOrFail($id);
        
        $entry->update([
            'question' => $request->question,
            'answer' => $request->answer,
            'category' => $request->category,
            'keywords' => AiKnowledgeBase::extractKeywords($request->question),
            'status' => $request->status,
            'use_for_training' => $request->has('use_for_training'),
            'priority' => $request->priority ?? 0,
        ]);
        
        return redirect()->route('customer.ai-helper.knowledge-base.index')
            ->with('success', 'تم تحديث السؤال والجواب بنجاح!');
    }
    
    /**
     * Delete knowledge base entry
     */
    public function destroy($id)
    {
        $entry = AiKnowledgeBase::where('user_id', auth()->id())
            ->findOrFail($id);
        
        $entry->delete();
        
        return redirect()->route('customer.ai-helper.knowledge-base.index')
            ->with('success', 'تم حذف السؤال والجواب بنجاح!');
    }
    
    /**
     * Toggle entry status (active/inactive)
     */
    public function toggleStatus($id)
    {
        $entry = AiKnowledgeBase::where('user_id', auth()->id())
            ->findOrFail($id);
        
        $entry->status = $entry->status === 'active' ? 'inactive' : 'active';
        $entry->save();
        
        return response()->json([
            'success' => true,
            'new_status' => $entry->status
        ]);
    }
    
    /**
     * Search for similar questions (AJAX)
     */
    public function searchSimilar(Request $request)
    {
        $question = $request->get('question');
        $userId = auth()->id();
        
        if (!$question) {
            return response()->json([]);
        }
        
        $similar = AiKnowledgeBase::findSimilar($question, $userId, 5);
        
        return response()->json($similar);
    }
}
