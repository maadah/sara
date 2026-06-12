<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Competitor;
use Illuminate\Http\Request;

class CompetitorController extends Controller
{
    public function index()
    {
        $competitors = Competitor::where('user_id', auth()->id())
            ->latest()
            ->paginate(12);

        return view('customer.competitors.index', compact('competitors'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'nullable|url|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $competitor = new Competitor($validated);
        $competitor->user_id = auth()->id();
        $competitor->save();

        return redirect()->route('customer.competitors.index')->with('success', 'تمت إضافة المنافس بنجاح');
    }

    public function show(Competitor $competitor)
    {
        if ($competitor->user_id !== auth()->id()) {
            abort(403);
        }

        return view('customer.competitors.show', compact('competitor'));
    }

    public function update(Request $request, Competitor $competitor)
    {
        if ($competitor->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'nullable|url|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $competitor->update($validated);

        return back()->with('success', 'تم تحديث بيانات المنافس بنجاح');
    }

    public function destroy(Competitor $competitor)
    {
        if ($competitor->user_id !== auth()->id()) {
            abort(403);
        }

        $competitor->delete();

        return redirect()->route('customer.competitors.index')->with('success', 'تم حذف المنافس');
    }
}
