<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * CustomerProfileController — lets store owners view the rich demographic
 * profiles the AI assembles for each lead.
 *
 * Routes:
 *   GET  /customer/customer-profiles          → index  (paginated list)
 *   GET  /customer/customer-profiles/{profile} → show   (full detail)
 *   PUT  /customer/customer-profiles/{profile} → update (manual edits)
 */
class CustomerProfileController extends Controller
{
    /**
     * Paginated index of all profiles belonging to this store.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = CustomerProfile::where('store_id', $user->id)
            ->with(['lead', 'lead.conversation'])
            ->orderByDesc('lead_score');

        // Filter: only profiles with an order
        if ($request->boolean('with_orders')) {
            $query->where('total_orders', '>', 0);
        }

        // Filter by gender
        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        // Search by name / phone
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            });
        }

        $profiles = $query->paginate(20)->withQueryString();

        // Aggregate stats
        $stats = [
            'total'         => CustomerProfile::where('store_id', $user->id)->count(),
            'with_orders'   => CustomerProfile::where('store_id', $user->id)->where('total_orders', '>', 0)->count(),
            'with_phone'    => CustomerProfile::where('store_id', $user->id)->whereNotNull('phone')->count(),
            'avg_score'     => (int) CustomerProfile::where('store_id', $user->id)->avg('lead_score'),
            'with_age'      => CustomerProfile::where('store_id', $user->id)->whereNotNull('age')->count(),
            'with_gender'   => CustomerProfile::where('store_id', $user->id)->whereNotNull('gender')->count(),
            'with_budget'   => CustomerProfile::where('store_id', $user->id)->whereNotNull('budget_max')->count(),
        ];

        return view('customer.customer-profiles.index', compact('profiles', 'stats'));
    }

    /**
     * Full detail view for a single profile.
     */
    public function show(CustomerProfile $customerProfile)
    {
        // Authorise — profile must belong to the logged-in store
        if ($customerProfile->store_id !== Auth::id()) {
            abort(403);
        }

        $customerProfile->load('lead');

        // Fetch the lead's chat sessions for timeline
        $sessions = \App\Models\ChatSession::where('store_id', Auth::id())
            ->where('lead_id', $customerProfile->lead_id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('customer.customer-profiles.show', compact('customerProfile', 'sessions'));
    }

    /**
     * Allow store owner to manually fill in missing demographics.
     */
    public function update(Request $request, CustomerProfile $customerProfile)
    {
        if ($customerProfile->store_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name'           => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:20',
            'address'        => 'nullable|string|max:500',
            'city'           => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:2000',
            'age'            => 'nullable|integer|min:1|max:120',
            'gender'         => 'nullable|in:male,female,other',
            'budget_min'     => 'nullable|integer|min:0',
            'budget_max'     => 'nullable|integer|min:0',
            'occupation'     => 'nullable|string|max:120',
            'marital_status' => 'nullable|in:single,married,divorced,other',
            'interests'      => 'nullable|string',   // comma-separated from form
        ]);

        $customerProfile->update(array_filter($validated, fn ($v) => $v !== null));

        return back()->with('success', 'تم تحديث الملف الشخصي بنجاح.');
    }
}
