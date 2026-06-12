<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\StoreAssistantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

class SyncStoreAssistantJob implements ShouldQueue
{
    use Queueable;

    public int $storeId;
    public int $tries = 3;
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(int $storeId)
    {
        $this->storeId = $storeId;
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        // Prevent overlapping syncs for the same store
        return [new WithoutOverlapping($this->storeId)];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $store = User::find($this->storeId);

        if (!$store) {
            Log::warning("Store not found for assistant sync", ['store_id' => $this->storeId]);
            return;
        }

        $manager = new StoreAssistantManager();
        $result = $manager->syncStoreAssistant($store);

        if ($result['success']) {
            Log::info("Store assistant synced successfully", [
                'store_id' => $this->storeId,
                'products_count' => $result['products_count'] ?? 0,
            ]);
        } else {
            Log::error("Failed to sync store assistant", [
                'store_id' => $this->storeId,
                'error' => $result['error'] ?? 'Unknown error',
            ]);
        }
    }
}
