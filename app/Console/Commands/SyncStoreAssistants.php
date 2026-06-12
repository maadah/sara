<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StoreAssistantManager;
use Illuminate\Console\Command;

class SyncStoreAssistants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assistant:sync {store_id? : Specific store ID to sync} {--all : Sync all stores with assistant mode enabled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync store assistants with OpenAI Vector Store';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $manager = new StoreAssistantManager();

        if ($storeId = $this->argument('store_id')) {
            // Sync specific store
            $store = User::find($storeId);

            if (!$store) {
                $this->error("Store not found: {$storeId}");
                return 1;
            }

            $this->info("Syncing assistant for: {$store->name}");
            $result = $manager->syncStoreAssistant($store);

            if ($result['success']) {
                $this->info("✓ Synced successfully!");
                $this->info("  Assistant ID: {$result['assistant_id']}");
                $this->info("  Products: {$result['products_count']}");
            } else {
                $this->error("✗ Failed: {$result['error']}");
                return 1;
            }

        } elseif ($this->option('all')) {
            // Sync all stores with assistant mode enabled
            $stores = User::where('role', 'customer')
                ->whereHas('aiSetting', function ($query) {
                    $query->where('use_assistant_mode', true);
                })
                ->get();

            if ($stores->isEmpty()) {
                $this->warn("No stores with assistant mode enabled.");
                return 0;
            }

            $this->info("Syncing {$stores->count()} stores...\n");

            $success = 0;
            $failed = 0;

            foreach ($stores as $store) {
                $this->info("Syncing: {$store->name}...");
                $result = $manager->syncStoreAssistant($store);

                if ($result['success']) {
                    $this->info("  ✓ {$result['products_count']} products");
                    $success++;
                } else {
                    $this->error("  ✗ {$result['error']}");
                    $failed++;
                }
            }

            $this->newLine();
            $this->info("Done! Success: {$success}, Failed: {$failed}");

        } else {
            // Show available stores
            $this->info("Usage:");
            $this->info("  php artisan assistant:sync {store_id}  - Sync specific store");
            $this->info("  php artisan assistant:sync --all       - Sync all enabled stores");
            $this->newLine();

            $stores = User::where('role', 'customer')
                ->with('aiSetting')
                ->get();

            $this->info("Available stores:");
            foreach ($stores as $store) {
                $assistantMode = $store->aiSetting?->use_assistant_mode ? '✓ Enabled' : '✗ Disabled';
                $hasAssistant = $store->aiSetting?->openai_assistant_id ? '🤖 Has Assistant' : '';
                $this->line("  [{$store->id}] {$store->name} - {$assistantMode} {$hasAssistant}");
            }
        }

        return 0;
    }
}
