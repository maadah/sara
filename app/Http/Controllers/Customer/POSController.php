<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class POSController extends Controller
{
    /**
     * Show the POS interface
     */
    public function index()
    {
        $categories = Category::where('user_id', auth()->id())
            ->withCount('products')
            ->get();

        $products = Product::where('user_id', auth()->id())
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->with('category', 'images')
            ->get();

        return view('customer.pos.index', compact('categories', 'products'));
    }

    /**
     * Search products via AJAX
     */
    public function searchProducts(Request $request)
    {
        $query = Product::where('user_id', auth()->id())
            ->where('is_active', true)
            ->where('quantity', '>', 0);

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->with('category', 'images')->get();

        return response()->json($products);
    }

    /**
     * Complete a sale
     */
    public function completeSale(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:cash,card,transfer',
            'currency' => 'required|in:IQD,USD',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $userId = auth()->id();
            $subtotal = 0;
            $validatedItems = [];

            // Validate and calculate
            foreach ($request->items as $item) {
                $product = Product::where('id', $item['product_id'])
                    ->where('user_id', $userId)
                    ->where('is_active', true)
                    ->first();

                if (!$product) {
                    return response()->json(['error' => 'منتج غير موجود: ' . $item['product_id']], 400);
                }

                if ($product->quantity < $item['quantity']) {
                    return response()->json(['error' => 'الكمية غير متوفرة للمنتج: ' . $product->name], 400);
                }

                $itemTotal = $product->price * $item['quantity'];
                $subtotal += $itemTotal;

                $validatedItems[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'total' => $itemTotal,
                ];
            }

            // Calculate discount
            $discountPercentage = $request->discount_percentage ?? 0;
            $discountAmount = $request->discount_amount ?? 0;

            if ($discountPercentage > 0) {
                $discountAmount = $subtotal * ($discountPercentage / 100);
            }

            $total = $subtotal - $discountAmount;

            // Create sale
            $sale = Sale::create([
                'user_id' => $userId,
                'invoice_number' => Sale::generateInvoiceNumber($userId),
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'discount_percentage' => $discountPercentage,
                'total' => $total,
                'currency' => $request->currency,
                'notes' => $request->notes,
                'payment_method' => $request->payment_method,
                'status' => 'completed',
            ]);

            // Create sale items and update inventory
            foreach ($validatedItems as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'unit_price' => $item['product']->price,
                    'quantity' => $item['quantity'],
                    'total' => $item['total'],
                    'currency' => $request->currency,
                ]);

                // Decrease product quantity
                $item['product']->decrement('quantity', $item['quantity']);

                // Check for low stock and create notification
                if ($item['product']->quantity <= 5 && $item['product']->quantity > 0) {
                    Notification::lowStock($userId, $item['product']);
                }
            }

            // Create sale completed notification
            Notification::saleCompleted($userId, $sale);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إتمام عملية البيع بنجاح',
                'sale_id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get sale details for invoice
     */
    public function getSale(Sale $sale)
    {
        if ($sale->user_id !== auth()->id()) {
            abort(403);
        }

        $sale->load('items.product');

        return response()->json($sale);
    }

    /**
     * Show invoice for printing
     */
    public function printInvoice(Sale $sale)
    {
        if ($sale->user_id !== auth()->id()) {
            abort(403);
        }

        $sale->load('items.product', 'user');

        return view('customer.pos.invoice', compact('sale'));
    }
}
