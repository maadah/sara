<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    /**
     * Display sales history
     */
    public function index(Request $request)
    {
        $query = Sale::where('user_id', auth()->id())
            ->with('items')
            ->latest();

        // Filter by date
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Search by invoice number or customer name
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('invoice_number', 'like', '%' . $request->search . '%')
                  ->orWhere('customer_name', 'like', '%' . $request->search . '%');
            });
        }

        $sales = $query->paginate(15);

        // Statistics
        $todaySales = Sale::where('user_id', auth()->id())
            ->whereDate('created_at', today())
            ->where('status', 'completed')
            ->sum('total');

        $monthSales = Sale::where('user_id', auth()->id())
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', 'completed')
            ->sum('total');

        $totalSales = Sale::where('user_id', auth()->id())
            ->where('status', 'completed')
            ->count();

        return view('customer.sales.index', compact('sales', 'todaySales', 'monthSales', 'totalSales'));
    }

    /**
     * Show sale details
     */
    public function show(Sale $sale)
    {
        if ($sale->user_id !== auth()->id()) {
            abort(403);
        }

        $sale->load('items.product');

        return view('customer.sales.show', compact('sale'));
    }

    /**
     * Cancel a sale (refund)
     */
    public function cancel(Sale $sale)
    {
        if ($sale->user_id !== auth()->id()) {
            abort(403);
        }

        if ($sale->status !== 'completed') {
            return back()->with('error', 'لا يمكن إلغاء هذه العملية');
        }

        // Return items to inventory
        foreach ($sale->items as $item) {
            if ($item->product) {
                $item->product->increment('quantity', $item->quantity);
            }
        }

        $sale->update(['status' => 'cancelled']);

        return back()->with('success', 'تم إلغاء عملية البيع بنجاح');
    }
}
