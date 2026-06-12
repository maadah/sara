<?php

namespace App\Services\Chat\Tools;

use App\Models\AiSetting;
use App\Models\User;

/**
 * StoreTool — returns store-level information (settings, policies, promotions).
 *
 * The AI calls this tool when it needs delivery cost, delivery time,
 * active promotions, or store policies to reference in its reply.
 */
class StoreTool
{
    /**
     * Get store info for the AI context.
     */
    public function getStoreInfo(int $storeId): array
    {
        $store    = User::find($storeId);
        $settings = AiSetting::where('user_id', $storeId)->first();

        if (! $store) {
            return ['status' => 'not_found'];
        }

        return [
            'status'           => 'found',
            'store_name'       => $store->name,
            'store_type'       => $store->storeType?->display_name ?? 'عام',
            'delivery_cost'    => (int) ($settings?->delivery_cost ?? config('chat.cart.delivery_cost', 5000)),
            'delivery_time'    => $settings?->delivery_time ?? config('chat.cart.delivery_time', '24-48 ساعة'),
            'working_hours'    => $settings?->working_hours ?? '24/7',
            'store_policies'   => $settings?->store_policies ?? '',
            'active_promotion' => $settings?->active_promotion ?? null,
            'wholesale_info'   => $settings?->wholesale_info ?? null,
            'greeting_message' => $settings?->greeting_message ?? __('chat.greeting_default'),
            // Contact info — ALWAYS use this when customer asks for store phone/location,
            // never give the customer's own phone number.
            'contact_phone'    => $settings?->contact_phone ?? null,
            'store_location'   => $settings?->store_location ?? null,
        ];
    }
}
