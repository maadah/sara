<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class StoreController extends Controller
{
    /**
     * Get store information by ID
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $store = User::where('role', 'customer')
                ->where('status', 'approved')
                ->with(['aiSetting:user_id,store_description,store_policies,greeting_message'])
                ->find($id);

            if (!$store) {
                return response()->json([
                    'success' => false,
                    'message' => 'Store not found or not approved',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'email' => $store->email,
                    'phone' => $store->phone,
                    'whatsapp' => $store->whatsapp,
                    'facebook_link' => $store->facebook_link,
                    'instagram_link' => $store->instagram_link,
                    'store_address' => $store->store_address,
                    'description' => $store->aiSetting->store_description ?? null,
                    'policies' => $store->aiSetting->store_policies ?? null,
                    'greeting_message' => $store->aiSetting->greeting_message ?? null,
                    'subscription' => [
                        'id' => $store->subscription_id,
                        'expires_at' => $store->subscription_expires_at?->format('Y-m-d H:i:s'),
                        'is_active' => $store->hasActiveSubscription(),
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch store information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all approved stores (paginated)
     *
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);

            $stores = User::where('role', 'customer')
                ->where('status', 'approved')
                ->with(['aiSetting:user_id,store_description'])
                ->paginate($perPage);

            $storesData = $stores->map(function ($store) {
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'phone' => $store->phone,
                    'whatsapp' => $store->whatsapp,
                    'store_address' => $store->store_address,
                    'description' => $store->aiSetting->store_description ?? null,
                    'facebook_link' => $store->facebook_link,
                    'instagram_link' => $store->instagram_link,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $storesData,
                'pagination' => [
                    'total' => $stores->total(),
                    'per_page' => $stores->perPage(),
                    'current_page' => $stores->currentPage(),
                    'last_page' => $stores->lastPage(),
                    'from' => $stores->firstItem(),
                    'to' => $stores->lastItem(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stores',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
