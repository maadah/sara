<?php

namespace App\Console\Commands;

use App\Services\SocialCommentService;
use Illuminate\Console\Command;

class CleanupExpiredCommentInteractions extends Command
{
    protected $signature = 'comments:cleanup';
    protected $description = 'Delete comment interactions older than 24 hours';

    public function handle(): int
    {
        $service = new SocialCommentService();
        $deleted = $service->cleanupExpired();

        $this->info("Deleted {$deleted} expired comment interactions.");

        return self::SUCCESS;
    }
}
