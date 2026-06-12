<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories
     */
    public function index()
    {
        $categories = Category::where('user_id', auth()->id())
            ->withCount('products')
            ->latest()
            ->paginate(12);

        return view('customer.categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new category
     */
    public function create()
    {
        return view('customer.categories.create');
    }

    /**
     * Store a newly created category
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $validated['user_id'] = auth()->id();

        Category::create($validated);

        return redirect()->route('customer.categories.index')
            ->with('success', 'تم إضافة الفئة بنجاح');
    }

    /**
     * Show the form for editing the category
     */
    public function edit(Category $category)
    {
        // Ensure the category belongs to the user
        if ($category->user_id !== auth()->id()) {
            abort(403);
        }

        return view('customer.categories.edit', compact('category'));
    }

    /**
     * Update the category
     */
    public function update(Request $request, Category $category)
    {
        // Ensure the category belongs to the user
        if ($category->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $category->update($validated);

        return redirect()->route('customer.categories.index')
            ->with('success', 'تم تحديث الفئة بنجاح');
    }

    /**
     * Remove the category
     */
    public function destroy(Category $category)
    {
        // Ensure the category belongs to the user
        if ($category->user_id !== auth()->id()) {
            abort(403);
        }

        // Check if category has products
        if ($category->products()->count() > 0) {
            return back()->with('error', 'لا يمكن حذف الفئة لأنها تحتوي على منتجات');
        }

        $category->delete();

        return redirect()->route('customer.categories.index')
            ->with('success', 'تم حذف الفئة بنجاح');
    }
}
