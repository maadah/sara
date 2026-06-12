<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiFastReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FastRepliesController extends Controller
{
    /**
     * Display a listing of fast replies
     */
    public function index()
    {
        $replies = AiFastReply::where('user_id', auth()->id())
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        $categories = AiFastReply::where('user_id', auth()->id())
            ->distinct()
            ->pluck('category')
            ->filter()
            ->toArray();
        
        return view('admin.ai-helper.fast-replies.index', compact('replies', 'categories'));
    }
    
    /**
     * Show create form
     */
    public function create()
    {
        return view('admin.ai-helper.fast-replies.create');
    }
    
    /**
     * Store new fast reply
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string|max:50',
            'reply' => 'required|string|max:2000',
            'trigger_keywords' => 'nullable|array',
            'priority' => 'nullable|integer|min:1|max:100',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        AiFastReply::create([
            'user_id' => auth()->id(),
            'category' => $request->category,
            'reply' => $request->reply,
            'trigger_keywords' => $request->trigger_keywords ?? [],
            'priority' => $request->priority ?? 5,
            'is_active' => true,
            'usage_count' => 0,
        ]);
        
        return redirect()->route('customer.ai-helper.fast-replies.index')
            ->with('success', 'تم إضافة الرد السريع بنجاح!');
    }
    
    /**
     * Show edit form
     */
    public function edit($id)
    {
        $reply = AiFastReply::where('user_id', auth()->id())
            ->findOrFail($id);
        
        return view('admin.ai-helper.fast-replies.edit', compact('reply'));
    }
    
    /**
     * Update fast reply
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string|max:50',
            'reply' => 'required|string|max:2000',
            'trigger_keywords' => 'nullable|array',
            'priority' => 'nullable|integer|min:1|max:100',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        $reply = AiFastReply::where('user_id', auth()->id())
            ->findOrFail($id);
        
        $reply->update([
            'category' => $request->category,
            'reply' => $request->reply,
            'trigger_keywords' => $request->trigger_keywords ?? [],
            'priority' => $request->priority ?? 5,
        ]);
        
        return redirect()->route('customer.ai-helper.fast-replies.index')
            ->with('success', 'تم تحديث الرد السريع بنجاح!');
    }
    
    /**
     * Delete fast reply
     */
    public function destroy($id)
    {
        $reply = AiFastReply::where('user_id', auth()->id())
            ->findOrFail($id);
        
        $reply->delete();
        
        return redirect()->route('customer.ai-helper.fast-replies.index')
            ->with('success', 'تم حذف الرد السريع بنجاح!');
    }
    
    /**
     * Toggle active status
     */
    public function toggleStatus($id)
    {
        $reply = AiFastReply::where('user_id', auth()->id())
            ->findOrFail($id);
        
        $reply->update([
            'is_active' => !$reply->is_active
        ]);
        
        return redirect()->back()
            ->with('success', 'تم تحديث حالة الرد السريع!');
    }
}
