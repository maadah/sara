<?php

namespace App\Console\Commands;

use App\Models\AiChatSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * FIX #25: Session Cleanup Command
 * Cleans up expired AI chat sessions and old cache data
 */
class CleanupAiSessions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:cleanup-sessions
                            {--hours=2 : Delete sessions expired more than X hours ago}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up expired AI chat sessions and related cache data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');

        $this->info("🧹 AI Session Cleanup Starting...");
        $this->info("   Looking for sessions expired more than {$hours} hours ago");

        if ($dryRun) {
            $this->warn("   DRY RUN MODE - No actual deletions will be made");
        }

        // Find expired sessions
        $expiredSessions = AiChatSession::where('expires_at', '<', now()->subHours($hours))->get();
        $expiredCount = $expiredSessions->count();

        $this->info("   Found {$expiredCount} expired sessions");

        if ($expiredCount === 0) {
            $this->info("✅ No sessions to clean up");
            return Command::SUCCESS;
        }

        // Show details if verbose
        if ($this->getOutput()->isVerbose()) {
            $this->table(
                ['ID', 'User ID', 'Lead ID', 'Expired At', 'Messages Count'],
                $expiredSessions->map(fn($s) => [
                    $s->id,
                    $s->user_id,
                    $s->lead_id,
                    $s->expires_at->toDateTimeString(),
                    count($s->messages ?? []),
                ])->toArray()
            );
        }

        if (!$dryRun) {
            // Delete expired sessions
            $deleted = AiChatSession::where('expires_at', '<', now()->subHours($hours))->delete();

            $this->info("🗑️  Deleted {$deleted} expired sessions");

            // Log for monitoring
            Log::info('AI Session Cleanup', [
                'deleted_count' => $deleted,
                'hours_threshold' => $hours,
            ]);

            // Clean up old rate limit cache keys (older than 2 hours)
            // Note: Laravel's file/redis cache doesn't have a direct way to list keys
            // This is just a placeholder - implement based on your cache driver
            $this->cleanupOldCacheKeys();
        } else {
            $this->info("🔍 Would delete {$expiredCount} sessions (dry run)");
        }

        $this->info("✅ Cleanup complete!");

        return Command::SUCCESS;
    }

    /**
     * Clean up old cache keys related to AI
     */
    protected function cleanupOldCacheKeys(): void
    {
        // Clear old AI stats (older than 7 days)
        $oldDate = now()->subDays(7)->format('Y-m-d');

        // This is a simple approach - for production, you might want to use
        // Redis SCAN or a similar approach for your cache driver
        $this->info("   Clearing old AI stats cache...");

        // The cache keys follow pattern: ai_stats:{user_id}:{date}
        // We can't easily iterate over cache keys in Laravel's cache abstraction
        // So we just log this as a reminder

        $this->comment("   Note: Old cache keys (ai_rate:*, ai_stats:*, ai_cache:*) will auto-expire based on TTL");
    }
}
