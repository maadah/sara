<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductImage;
use App\Models\Notification;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Display a listing of products
     */
    public function index()
    {
        $products = Product::where('user_id', auth()->id())
            ->with(['category', 'images'])
            ->latest()
            ->paginate(10);

        return view('customer.products.index', compact('products'));
    }

    /**
     * Show the form for creating a new product
     */
    public function create()
    {
        $categories = Category::where('user_id', auth()->id())->get();
        return view('customer.products.create', compact('categories'));
    }

    /**
     * Store a newly created product
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|in:IQD,USD',
            'quantity' => 'required|integer|min:0',
            'manage_stock' => 'nullable|boolean',
            'unit' => 'nullable|string|max:50',
            'sell_unit' => 'nullable|string|max:50',
            'conversion_factor' => 'nullable|numeric|min:0',
            'expiry_date' => 'nullable|date',
            'facebook_post_url' => ['nullable', 'url', 'max:500'],
            'instagram_post_url' => ['nullable', 'url', 'max:500'],
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Checkboxes are omitted from the request when unchecked
        $validated['manage_stock'] = $request->boolean('manage_stock', true);

        // Verify the category belongs to the user
        $category = Category::where('id', $validated['category_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $validated['user_id'] = auth()->id();

        // Auto-resolve the page-scoped Graph object ID from the Facebook URL.
        // Facebook webhooks deliver a page-scoped post_id that often differs from
        // the fbid visible in the public URL (especially for newer 18-digit photo IDs).
        if (!empty($validated['facebook_post_url'])) {
            $validated['facebook_post_id'] = $this->resolveFacebookObjectId(
                $validated['facebook_post_url'],
                auth()->id()
            );
        }

        $product = Product::create($validated);

        // Handle images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ]);
            }
        }

        // Create notification for new product
        Notification::productAdded(auth()->id(), $product);

        return redirect()->route('customer.products.index')
            ->with('success', 'تم إضافة المنتج بنجاح');
    }

    /**
     * Display the specified product
     */
    public function show(Product $product)
    {
        // Ensure the product belongs to the user
        if ($product->user_id !== auth()->id()) {
            abort(403);
        }

        $product->load(['category', 'images']);

        return view('customer.products.show', compact('product'));
    }

    /**
     * Show the form for editing the product
     */
    public function edit(Product $product)
    {
        // Ensure the product belongs to the user
        if ($product->user_id !== auth()->id()) {
            abort(403);
        }

        $categories = Category::where('user_id', auth()->id())->get();
        $product->load('images');

        return view('customer.products.edit', compact('product', 'categories'));
    }

    /**
     * Update the product
     */
    public function update(Request $request, Product $product)
    {
        // Ensure the product belongs to the user
        if ($product->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|in:IQD,USD',
            'quantity' => 'required|integer|min:0',
            'manage_stock' => 'nullable|boolean',
            'unit' => 'nullable|string|max:50',
            'sell_unit' => 'nullable|string|max:50',
            'conversion_factor' => 'nullable|numeric|min:0',
            'expiry_date' => 'nullable|date',
            'facebook_post_url' => ['nullable', 'url', 'max:500'],
            'instagram_post_url' => ['nullable', 'url', 'max:500'],
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Checkboxes are omitted from the request when unchecked
        $validated['manage_stock'] = $request->boolean('manage_stock', true);

        // Verify the category belongs to the user
        $category = Category::where('id', $validated['category_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Auto-resolve the page-scoped Graph object ID from the Facebook URL.
        if (!empty($validated['facebook_post_url'])) {
            $resolved = $this->resolveFacebookObjectId(
                $validated['facebook_post_url'],
                auth()->id()
            );
            if ($resolved) {
                $validated['facebook_post_id'] = $resolved;
            }
        } elseif (array_key_exists('facebook_post_url', $validated)) {
            $validated['facebook_post_id'] = null;
        }

        $product->update($validated);

        // Handle new images
        if ($request->hasFile('images')) {
            $currentMaxOrder = $product->images()->max('sort_order') ?? -1;
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_primary' => $product->images()->count() === 0 && $index === 0,
                    'sort_order' => $currentMaxOrder + $index + 1,
                ]);
            }
        }

        return redirect()->route('customer.products.index')
            ->with('success', 'تم تحديث المنتج بنجاح');
    }

    /**
     * Remove the product
     */
    public function destroy(Product $product)
    {
        // Ensure the product belongs to the user
        if ($product->user_id !== auth()->id()) {
            abort(403);
        }

        // Delete product images from storage
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $product->delete();

        return redirect()->route('customer.products.index')
            ->with('success', 'تم حذف المنتج بنجاح');
    }

    /**
     * Delete a product image
     */
    public function deleteImage(ProductImage $image)
    {
        $product = $image->product;

        // Ensure the product belongs to the user
        if ($product->user_id !== auth()->id()) {
            abort(403);
        }

        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        return back()->with('success', 'تم حذف الصورة بنجاح');
    }

    /**
     * Try to resolve the page-scoped Graph object ID from a Facebook post URL.
     *
     * Facebook webhooks use an internal page-scoped ID (e.g. "122103146919287716")
     * that can differ from the fbid in the public URL ("122103146853287716").
     * By calling GET /{fbid}?fields=id with the page token at save time, we get
     * the real object ID and store it so webhook matching works without extra API calls.
     *
     * Returns the resolved object ID string, or null if not resolvable.
     */
    protected function resolveFacebookObjectId(string $url, int $userId): ?string
    {
        // Extract all 10+ digit candidates from the URL
        preg_match_all('/\d{10,}/', $url, $matches);
        $candidates = $matches[0] ?? [];

        if (empty($candidates)) {
            return null;
        }

        $account = SocialAccount::where('user_id', $userId)
            ->where('provider', 'facebook_page')
            ->whereNotNull('provider_token')
            ->first();

        if (!$account) {
            return null;
        }

        $graphVersion = config('services.meta.graph_api_version', 'v18.0');

        foreach ($candidates as $id) {
            try {
                $response = Http::get("https://graph.facebook.com/{$graphVersion}/{$id}", [
                    'fields' => 'id',
                    'access_token' => $account->provider_token,
                ]);

                if ($response->successful() && $response->json('id')) {
                    $resolvedId = $response->json('id');
                    Log::info('ProductController: Resolved Facebook object ID', [
                        'url' => $url,
                        'input_id' => $id,
                        'resolved_id' => $resolvedId,
                    ]);
                    return $resolvedId;
                }
            } catch (\Throwable $e) {
                Log::warning('ProductController: Facebook object ID resolution failed', [
                    'id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }
}

