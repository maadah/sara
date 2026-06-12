<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    /**
     * Get products for a specific store (public, scoped by store)
     *
     * @param Request $request
     * @param int $storeId
     * @return JsonResponse
     */
    public function indexForStore(Request $request, int $storeId): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $categoryId = $request->get('category_id');

            $query = Product::with(['category', 'images', 'attributes'])
                ->where('user_id', $storeId)
                ->where('is_active', true);

            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            $products = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $products->items(),
                'pagination' => [
                    'total' => $products->total(),
                    'per_page' => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single product for a specific store (public, scoped)
     *
     * @param int $storeId
     * @param int $id
     * @return JsonResponse
     */
    public function showForStore(int $storeId, int $id): JsonResponse
    {
        try {
            $product = Product::with(['category', 'images', 'attributes'])
                ->where('user_id', $storeId)
                ->where('is_active', true)
                ->find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatProduct($product),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get categories for a specific store (public)
     *
     * @param int $storeId
     * @return JsonResponse
     */
    public function categoriesForStore(int $storeId): JsonResponse
    {
        try {
            $categories = Category::where('user_id', $storeId)
                ->where('is_active', true)
                ->withCount(['products' => fn($q) => $q->where('is_active', true)])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get authenticated user's own products
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function myProducts(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $perPage = $request->get('per_page', 15);

            $products = Product::with(['category', 'images', 'attributes'])
                ->where('user_id', $user->id)
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $products->items(),
                'pagination' => [
                    'total' => $products->total(),
                    'per_page' => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single product belonging to authenticated user
     *
     * @param int $id
     * @return JsonResponse
     */
    public function myProduct(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $product = Product::with(['category', 'images', 'attributes'])
                ->where('user_id', $user->id)
                ->find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatProduct($product),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get authenticated user's own categories
     *
     * @return JsonResponse
     */
    public function myCategories(): JsonResponse
    {
        try {
            $user = Auth::user();

            $categories = Category::where('user_id', $user->id)
                ->withCount(['products' => fn($q) => $q->where('is_active', true)])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format product for API response
     *
     * @param Product $product
     * @return array
     */
    private function formatProduct(Product $product): array
    {
        $data = [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => (float) $product->price,
            'currency' => $product->currency ?? 'IQD',
            'quantity' => $product->quantity,
            'is_active' => (bool) $product->is_active,
            'created_at' => $product->created_at?->toDateTimeString(),
            'updated_at' => $product->updated_at?->toDateTimeString(),
        ];

        if ($product->category) {
            $data['category'] = [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ];
        }

        if ($product->images && $product->images->count() > 0) {
            $data['images'] = $product->images->map(fn($img) => [
                'id' => $img->id,
                'url' => url('storage/' . $img->image_path),
                'is_primary' => (bool) $img->is_primary,
            ])->toArray();
        }

        // Include product attributes (size, color, etc.)
        if ($product->attributes && $product->attributes->count() > 0) {
            $data['attributes'] = $product->attributes->groupBy('attribute_key')
                ->map(fn($group) => $group->pluck('attribute_value')->toArray())
                ->toArray();
        }

        return $data;
    }
}
