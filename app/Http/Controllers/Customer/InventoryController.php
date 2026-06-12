<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index()
    {
        $userId = auth()->id();

        $allProducts = Product::where('user_id', $userId)->get();

        $totalProducts = $allProducts->count();
        $lowStock = $allProducts->where('quantity', '<=', 5)->where('quantity', '>', 0)->count();
        $outOfStock = $allProducts->where('quantity', '<=', 0)->count();
        $totalValue = $allProducts->sum(fn($p) => $p->price * $p->quantity);

        $products = Product::where('user_id', $userId)
            ->with('category')
            ->latest()
            ->paginate(15);

        $recentMovements = InventoryMovement::whereHas('product', fn($q) => $q->where('user_id', $userId))
            ->with('product')
            ->latest('created_at')
            ->take(10)
            ->get();

        return view('customer.inventory.index', compact(
            'products', 'totalProducts', 'lowStock', 'outOfStock', 'totalValue', 'recentMovements'
        ));
    }

    public function movements(Request $request)
    {
        $userId = auth()->id();

        $query = InventoryMovement::whereHas('product', fn($q) => $q->where('user_id', $userId))
            ->with('product');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $movements = $query->latest('created_at')->paginate(20);
        $products = Product::where('user_id', $userId)->get();

        return view('customer.inventory.movements', compact('movements', 'products'));
    }

    public function adjustStock(Request $request, Product $product)
    {
        if ($product->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'type' => 'required|in:purchase,sale,return,adjustment,damage',
            'notes' => 'nullable|string|max:500',
        ]);

        $quantity = (int) $validated['quantity'];
        if (in_array($validated['type'], ['sale', 'damage'])) {
            $quantity = -$quantity;
        }

        $product->adjustStock($quantity, $validated['type'], $validated['notes'] ?? null);

        return back()->with('success', 'تم تعديل المخزون بنجاح');
    }
}
