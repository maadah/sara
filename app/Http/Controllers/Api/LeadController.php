<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LeadController extends Controller
{
    /**
     * Get lead/client information by ID
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $lead = Lead::with([
                'conversation.socialAccount',
                'orders' => function ($query) {
                    $query->latest()->limit(10);
                },
                'user:id,name,phone,store_address'
            ])->find($id);

            if (!$lead) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'phone' => $lead->phone,
                    'whatsapp' => $lead->whatsapp,
                    'email' => $lead->email,
                    'address' => $lead->address,
                    'city' => $lead->city,
                    'area' => $lead->area,
                    'source' => $lead->source,
                    'platform_user_id' => $lead->platform_user_id,
                    'status' => $lead->status,
                    'status_label' => $lead->status_label,
                    'interest_score' => $lead->interest_score,
                    'interests' => $lead->interests,
                    'total_messages' => $lead->total_messages,
                    'total_orders' => $lead->total_orders,
                    'total_spent' => $lead->total_spent,
                    'first_contact_at' => $lead->first_contact_at?->format('Y-m-d H:i:s'),
                    'last_contact_at' => $lead->last_contact_at?->format('Y-m-d H:i:s'),
                    'notes' => $lead->notes,
                    'store' => [
                        'id' => $lead->user->id,
                        'name' => $lead->user->name,
                        'phone' => $lead->user->phone,
                        'address' => $lead->user->store_address,
                    ],
                    'conversation' => $lead->conversation ? [
                        'id' => $lead->conversation->id,
                        'platform' => $lead->conversation->platform,
                        'participant_name' => $lead->conversation->participant_name,
                        'social_account' => $lead->conversation->socialAccount ? [
                            'name' => $lead->conversation->socialAccount->name,
                            'type' => $lead->conversation->socialAccount->type,
                        ] : null,
                    ] : null,
                    'recent_orders' => $lead->orders->map(function ($order) {
                        return [
                            'id' => $order->id,
                            'order_number' => $order->order_number,
                            'total' => $order->total,
                            'status' => $order->status,
                            'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                        ];
                    }),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch client information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all leads for a specific store
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $storeId = $request->get('store_id'); // Required: filter by store/user

            if (!$storeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'store_id is required',
                ], 400);
            }

            $query = Lead::where('user_id', $storeId)
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
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            $leads = $query->latest()->paginate($perPage);

            $leadsData = $leads->map(function ($lead) {
                return [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'phone' => $lead->phone,
                    'city' => $lead->city,
                    'address' => $lead->address,
                    'source' => $lead->source,
                    'status' => $lead->status,
                    'status_label' => $lead->status_label,
                    'interests' => $lead->interests,
                    'total_messages' => $lead->total_messages,
                    'total_orders' => $lead->total_orders,
                    'total_spent' => $lead->total_spent,
                    'last_contact_at' => $lead->last_contact_at?->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $leadsData,
                'pagination' => [
                    'total' => $leads->total(),
                    'per_page' => $leads->perPage(),
                    'current_page' => $leads->currentPage(),
                    'last_page' => $leads->lastPage(),
                    'from' => $leads->firstItem(),
                    'to' => $leads->lastItem(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch clients',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
