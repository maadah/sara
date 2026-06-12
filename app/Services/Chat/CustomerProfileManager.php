<?php

namespace App\Services\Chat;

use App\Models\ChatSession;
use App\Models\CustomerProfile;
use Illuminate\Support\Facades\Log;

/**
 * CustomerProfileManager — owns all read/write logic for CustomerProfile records.
 *
 * Also handles lead-score adjustments and tag management (cold/warm/hot/vip).
 * Separated from CustomerTool so the Engine can call scoring logic
 * without going through the tool execution path.
 */
class CustomerProfileManager
{
    /**
     * Get or create the profile for a session's store + lead pair.
     */
    public function getOrCreate(ChatSession $session): CustomerProfile
    {
        return CustomerProfile::firstOrCreate(
            ['store_id' => $session->store_id, 'lead_id' => $session->lead_id],
            [
                'lead_score'   => 0,
                'total_orders' => 0,
                'tags'         => [],
                'preferences'  => [],
            ],
        );
    }

    /**
     * Adjust the lead score by a named event and persist.
     *
     * @param string $event  One of the config('chat.scoring') keys.
     */
    public function adjustScoreForEvent(ChatSession $session, string $event): void
    {
        $delta = config("chat.scoring.{$event}", 0);
        if ($delta === 0) {
            return;
        }

        $profile  = $this->getOrCreate($session);
        $oldCat   = $profile->scoreCategory();

        $profile->adjustScore($delta);

        $newCat = $profile->scoreCategory();

        // Update session meta
        $session->setMeta('lead_score', $profile->lead_score);

        // Detect category change
        if ($oldCat !== $newCat) {
            $this->updateScoreTag($profile, $newCat);

            Log::info('Chat: lead score category changed', [
                'session_id' => $session->id,
                'lead_id'    => $session->lead_id,
                'from'       => $oldCat,
                'to'         => $newCat,
                'score'      => $profile->lead_score,
            ]);
        }
    }

    /**
     * Record that an order was placed.
     */
    public function recordOrder(ChatSession $session): void
    {
        $profile = $this->getOrCreate($session);
        $profile->update([
            'total_orders' => $profile->total_orders + 1,
            'last_order_at' => now(),
        ]);
    }

    /**
     * Merge collected customer data into the profile (and session).
     */
    public function mergeData(ChatSession $session, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $profile = $this->getOrCreate($session);

        // Base contact fields (also synced to session customer_data)
        $coreFields = array_filter([
            'name'    => $data['name'] ?? null,
            'phone'   => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'city'    => $data['city'] ?? null,
            'notes'   => $data['notes'] ?? null,
        ]);

        // Demographic fields (profile only, not in session customer_data)
        $demoFields = array_filter([
            'age'             => $data['age'] ?? null,
            'gender'          => $data['gender'] ?? null,
            'budget_min'      => $data['budget_min'] ?? null,
            'budget_max'      => $data['budget_max'] ?? null,
            'occupation'      => $data['occupation'] ?? null,
            'marital_status'  => $data['marital_status'] ?? null,
            'social_platform' => $data['social_platform'] ?? null,
        ], fn ($v) => $v !== null);

        $allUpdates = array_merge($coreFields, $demoFields);

        if ($allUpdates) {
            $profile->update($allUpdates);
        }

        // Keep session in sync (core fields only)
        if ($coreFields) {
            $existingData = $session->customer_data ?? [];
            $session->customer_data = array_merge($existingData, $coreFields);
        }
    }

    /**
     * Track a browsed category in preferences.
     */
    public function trackBrowsedCategory(ChatSession $session, string $category): void
    {
        $profile = $this->getOrCreate($session);
        $prefs   = $profile->preferences ?? [];
        $cats    = $prefs['browsed_categories'] ?? [];

        if (! in_array($category, $cats, true)) {
            $cats[] = $category;
        }

        $prefs['browsed_categories'] = array_slice($cats, -20);
        $profile->update(['preferences' => $prefs]);
    }

    /* ------------------------------------------------------------------ */
    /* Private                                                             */
    /* ------------------------------------------------------------------ */

    private function updateScoreTag(CustomerProfile $profile, string $newCategory): void
    {
        $tags = $profile->tags ?? [];

        // Remove old score tags
        $tags = array_values(array_filter($tags, fn ($t) => ! in_array($t, ['cold', 'warm', 'hot', 'vip'])));

        $tags[] = $newCategory;

        $profile->update(['tags' => $tags]);
    }
}
