<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BroadcastController extends Controller
{
    public function index()
    {
        $broadcasts = Broadcast::where('user_id', Auth::id())
            ->latest()
            ->paginate(10);

        $stats = [
            'total' => Broadcast::where('user_id', Auth::id())->count(),
            'sent' => Broadcast::where('user_id', Auth::id())->where('status', 'completed')->count(),
            'draft' => Broadcast::where('user_id', Auth::id())->where('status', 'draft')->count(),
            'total_reached' => Broadcast::where('user_id', Auth::id())->sum('sent_count'),
        ];

        return view('customer.broadcasts.index', compact('broadcasts', 'stats'));
    }

    public function create()
    {
        return view('customer.broadcasts.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'target_audience' => 'nullable|array',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['status'] = $request->filled('scheduled_at') ? 'scheduled' : 'draft';
        $validated['total_recipients'] = 0;
        $validated['sent_count'] = 0;
        $validated['failed_count'] = 0;

        Broadcast::create($validated);

        return redirect()->route('customer.broadcasts.index')
            ->with('success', 'تم إنشاء حملة البث بنجاح');
    }

    public function show(Broadcast $broadcast)
    {
        $this->authorizeBroadcast($broadcast);

        return view('customer.broadcasts.show', compact('broadcast'));
    }

    public function destroy(Broadcast $broadcast)
    {
        $this->authorizeBroadcast($broadcast);

        $broadcast->delete();

        return redirect()->route('customer.broadcasts.index')
            ->with('success', 'تم حذف حملة البث');
    }

    private function authorizeBroadcast(Broadcast $broadcast)
    {
        if ($broadcast->user_id !== Auth::id()) {
            abort(403);
        }
    }
}
