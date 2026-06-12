<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeadController extends Controller
{
    /**
     * Display list of leads
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Lead::where('user_id', $user->id)
            ->with(['conversation.socialAccount']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by source
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // Search by name or phone
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('platform_user_id', 'like', "%{$search}%");
            });
        }

        // Sort options
        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');
        $query->orderBy($sortField, $sortDir);

        $leads = $query->paginate(20)->withQueryString();

        // Statistics
        $stats = [
            'total' => Lead::where('user_id', $user->id)->count(),
            'new' => Lead::where('user_id', $user->id)->where('status', 'new')->count(),
            'contacted' => Lead::where('user_id', $user->id)->where('status', 'contacted')->count(),
            'converted' => Lead::where('user_id', $user->id)->where('status', 'converted')->count(),
            'lost' => Lead::where('user_id', $user->id)->where('status', 'lost')->count(),
        ];

        return view('customer.leads.index', compact('leads', 'stats'));
    }

    /**
     * Show lead details
     */
    public function show(Lead $lead)
    {
        $this->authorize('view', $lead);

        $lead->load([
            'conversation.socialAccount',
            'conversation.messages' => function ($query) {
                $query->latest()->limit(50);
            },
            'orders.items',
            'customerProfile',
        ]);

        return view('customer.leads.show', compact('lead'));
    }

    /**
     * Update lead information
     */
    public function update(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'status' => 'required|in:new,contacted,converted,lost',
            'notes' => 'nullable|string|max:2000',
        ]);

        $lead->update([
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
            'city' => $request->city,
            'status' => $request->status,
            'notes' => $request->notes,
            'last_contact_at' => now(),
        ]);

        return redirect()->route('customer.leads.show', $lead)
            ->with('success', 'تم تحديث بيانات العميل بنجاح');
    }

    /**
     * Update lead status via AJAX
     */
    public function updateStatus(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        $request->validate([
            'status' => 'required|in:new,contacted,converted,lost',
        ]);

        $lead->update([
            'status' => $request->status,
            'last_contact_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة العميل',
            'status_label' => $lead->status_label,
            'status_color' => $lead->status_color,
        ]);
    }



    /**
     * Delete a lead
     */
    public function destroy(Lead $lead)
    {
        $this->authorize('delete', $lead);

        $lead->delete();

        return redirect()->route('customer.leads.index')
            ->with('success', 'تم حذف العميل بنجاح');
    }

    /**
     * Go to conversation from lead
     */
    public function conversation(Lead $lead)
    {
        $this->authorize('view', $lead);

        if ($lead->conversation) {
            return redirect()->route('customer.inbox.show', $lead->conversation);
        }

        return redirect()->route('customer.leads.show', $lead)
            ->with('error', 'لا توجد محادثة مرتبطة بهذا العميل');
    }

    /**
     * Export leads
     */
    public function export(Request $request)
    {
        $user = Auth::user();

        $query = Lead::where('user_id', $user->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $leads = $query->get();

        $filename = 'leads_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($leads) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM for Excel
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header row
            fputcsv($file, [
                'الاسم',
                'الهاتف',
                'المدينة',
                'العنوان',
                'المصدر',
                'الحالة',
                'عدد الرسائل',
                'عدد الطلبات',
                'آخر تواصل',
                'تاريخ الإنشاء',
            ]);

            foreach ($leads as $lead) {
                fputcsv($file, [
                    $lead->display_name,
                    $lead->phone ?? '-',
                    $lead->city ?? '-',
                    $lead->address ?? '-',
                    $lead->source_label,
                    $lead->status_label,
                    $lead->total_messages,
                    $lead->total_orders,
                    $lead->last_contacted_at?->format('Y-m-d H:i') ?? '-',
                    $lead->created_at->format('Y-m-d H:i'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
