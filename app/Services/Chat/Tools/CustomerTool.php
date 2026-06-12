<?php

namespace App\Services\Chat\Tools;

use App\Models\ChatSession;
use App\Models\CustomerProfile;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;

/**
 * CustomerTool — reads and updates customer profile data.
 *
 * Used by the AI to retrieve saved info (so it doesn't re-ask)
 * and to persist newly collected info during the conversation.
 */
class CustomerTool
{
    /**
     * Get the saved customer profile for this session's lead.
     */
    public function getProfile(ChatSession $session): array
    {
        $profile = CustomerProfile::where('store_id', $session->store_id)
            ->where('lead_id', $session->lead_id)
            ->first();

        if (! $profile) {
            return [
                'status'       => 'no_profile',
                'name'         => null,
                'phone'        => null,
                'address'      => null,
                'city'         => null,
                'total_orders' => 0,
            ];
        }

        return [
            'status'          => 'found',
            'name'            => $profile->name,
            'phone'           => $profile->phone,
            'address'         => $profile->address,
            'city'            => $profile->city,
            'total_orders'    => $profile->total_orders,
            'lead_score'      => $profile->lead_score,
            'tags'            => $profile->tags ?? [],
            // Demographics
            'age'             => $profile->age,
            'gender'          => $profile->gender,
            'budget_min'      => $profile->budget_min,
            'budget_max'      => $profile->budget_max,
            'occupation'      => $profile->occupation,
            'social_platform' => $profile->social_platform,
        ];
    }

    /**
     * Save or update customer information.
     */
    public function saveData(
        ChatSession $session,
        ?string $name = null,
        ?string $phone = null,
        ?string $address = null,
        ?string $city = null,
        ?string $notes = null,
        // Demographics
        ?int    $age = null,
        ?string $gender = null,
        ?int    $budgetMin = null,
        ?int    $budgetMax = null,
        ?string $occupation = null,
        ?string $maritalStatus = null,
        ?string $socialPlatform = null,
    ): array {
        // Validate phone if provided
        if ($phone) {
            $pattern = config('chat.phone.pattern', '/^07[3-9]\d{8}$/');
            if (! preg_match($pattern, $phone)) {
                return ['status' => 'invalid_phone'];
            }
        }

        $profile = CustomerProfile::firstOrCreate(
            ['store_id' => $session->store_id, 'lead_id' => $session->lead_id],
            ['lead_score' => 0, 'total_orders' => 0],
        );

        $updates = array_filter([
            'name'            => $name,
            'phone'           => $phone,
            'address'         => $address,
            'city'            => $city,
            'age'             => $age,
            'gender'          => $gender,
            'budget_min'      => $budgetMin,
            'budget_max'      => $budgetMax,
            'occupation'      => $occupation,
            'marital_status'  => $maritalStatus,
            'social_platform' => $socialPlatform,
        ], fn ($v) => $v !== null);

        // Append attention notes rather than overwrite — builds a timestamped history
        if ($notes !== null) {
            $existing = trim($profile->notes ?? '');
            $date     = now()->format('d/m/Y');
            $updates['notes'] = $existing !== ''
                ? $existing . "\n[{$date}] " . $notes
                : "[{$date}] " . $notes;
        }

        $profile->update($updates);

        // Also update the session customer_data
        $sessionData = $session->customer_data ?? [];
        $session->customer_data = array_merge($sessionData, $updates);
        $session->save();

        // Sync to leads table as well
        $this->syncToLead($session->lead_id, $updates);

        Log::info('Chat Tool: customer data saved', [
            'session_id' => $session->id,
            'fields'     => array_keys($updates),
        ]);

        return ['status' => 'saved', 'fields' => array_keys($updates)];
    }

    /* ------------------------------------------------------------------ */
    /* Private                                                             */
    /* ------------------------------------------------------------------ */

    private function syncToLead(int $leadId, array $data): void
    {
        $lead = Lead::find($leadId);
        if (! $lead) {
            return;
        }

        $leadUpdates = [];
        if (isset($data['name']))    $leadUpdates['name']    = $data['name'];
        if (isset($data['phone']))   $leadUpdates['phone']   = $data['phone'];
        if (isset($data['address'])) $leadUpdates['address'] = $data['address'];
        if (isset($data['city']))    $leadUpdates['city']    = $data['city'];

        if ($leadUpdates) {
            $lead->update($leadUpdates);
        }
    }
}
