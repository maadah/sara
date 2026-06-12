<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\StoreAssistantManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        $this->scheduleAssistantSync($product, 'created');
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        $this->scheduleAssistantSync($product, 'updated');
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        $this->scheduleAssistantSync($product, 'deleted');
    }

    /**
     * Handle the Product "restored" event.
     */
    public function restored(Product $product): void
    {
        $this->scheduleAssistantSync($product, 'restored');
    }

    /**
     * Handle the Product "force deleted" event.
     */
    public function forceDeleted(Product $product): void
    {
        $this->scheduleAssistantSync($product, 'force_deleted');
    }

    /**
     * Schedule assistant sync for the store
     * Uses debouncing to batch multiple rapid changes
     */
    private function scheduleAssistantSync(Product $product, string $action): void
    {
        $store = $product->user;

        if (!$store) {
            return;
        }

        $aiSettings = $store->aiSetting;

        // Only sync if assistant mode is enabled and has assistant configured
        if (!$aiSettings ||
            !$aiSettings->use_assistant_mode ||
            !$aiSettings->openai_assistant_id) {
            return;
        }

        // Use cache-based debouncing to avoid too many syncs
        $cacheKey = "assistant_sync_pending_{$store->id}";

        // If already pending, just log and return
        if (Cache::has($cacheKey)) {
            Log::debug("Assistant sync already pending for store", [
                'store_id' => $store->id,
                'product_id' => $product->id,
                'action' => $action,
            ]);
            return;
        }

        // Mark as pending for 60 seconds (debounce period)
        Cache::put($cacheKey, true, 60);

        Log::info("Syncing assistant for store after product {$action}", [
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        // Sync immediately in background (non-blocking)
        try {
            // Use register_shutdown_function to sync after response is sent
            register_shutdown_function(function () use ($store, $cacheKey) {
                try {
                    $manager = new StoreAssistantManager();
                    $result = $manager->syncStoreAssistant($store);

                    if ($result['success']) {
                        Log::info("Auto-sync completed for store", [
                            'store_id' => $store->id,
                            'products_count' => $result['products_count'] ?? 0,
                        ]);
                    } else {
                        Log::error("Auto-sync failed for store", [
                            'store_id' => $store->id,
                            'error' => $result['error'] ?? 'Unknown',
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error("Auto-sync exception", [
                        'store_id' => $store->id,
                        'error' => $e->getMessage(),
                    ]);
                } finally {
                    Cache::forget($cacheKey);
                }
            });
        } catch (\Exception $e) {
            Cache::forget($cacheKey);
            Log::error("Failed to schedule auto-sync", ['error' => $e->getMessage()]);
        }
    }
}
