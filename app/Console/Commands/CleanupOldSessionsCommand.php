<?php

namespace App\Console\Commands;

use App\Models\AiChatSession;
use App\Models\ConversationSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupOldSessionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:cleanup 
                            {--days=30 : Number of days to keep sessions}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old conversation and AI chat sessions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up sessions older than {$days} days (before {$cutoffDate})...");

        if ($dryRun) {
            $this->warn('DRY RUN - No data will be deleted');
        }

        // Clean up AI Chat Sessions
        $aiSessionsCount = AiChatSession::where('updated_at', '<', $cutoffDate)->count();
        $this->line("Found {$aiSessionsCount} AI chat sessions to delete");

        if (!$dryRun && $aiSessionsCount > 0) {
            AiChatSession::where('updated_at', '<', $cutoffDate)->delete();
            Log::info("Cleaned up {$aiSessionsCount} old AI chat sessions");
        }

        // Clean up Conversation Sessions if table exists
        try {
            if (class_exists(ConversationSession::class)) {
                $convSessionsCount = ConversationSession::where('updated_at', '<', $cutoffDate)->count();
                $this->line("Found {$convSessionsCount} conversation sessions to delete");

                if (!$dryRun && $convSessionsCount > 0) {
                    ConversationSession::where('updated_at', '<', $cutoffDate)->delete();
                    Log::info("Cleaned up {$convSessionsCount} old conversation sessions");
                }
            }
        } catch (\Exception $e) {
            $this->warn("ConversationSession table not found, skipping...");
        }

        // Summary
        $this->newLine();
        if ($dryRun) {
            $this->info("DRY RUN complete. Would have deleted {$aiSessionsCount} AI sessions.");
        } else {
            $this->info("Cleanup complete!");
            $this->table(
                ['Type', 'Deleted'],
                [
                    ['AI Chat Sessions', $aiSessionsCount],
                ]
            );
        }

        return Command::SUCCESS;
    }
}
