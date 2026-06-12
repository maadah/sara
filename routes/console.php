<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Schedule tasks
 */

// Clean up old sessions daily at 3 AM
Schedule::command('sessions:cleanup --days=30')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/session-cleanup.log'));

// Clean up old sessions (90 days) weekly
Schedule::command('sessions:cleanup --days=90')
    ->weekly()
    ->sundays()
    ->at('04:00')
    ->withoutOverlapping();

/**
 * FIX #25: AI Chat Sessions Cleanup
 * Clean up expired AI chat sessions hourly
 */
Schedule::command('ai:cleanup-sessions --hours=2')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ai-session-cleanup.log'));

// Deep cleanup of AI sessions daily (older than 24 hours)
Schedule::command('ai:cleanup-sessions --hours=24')
    ->dailyAt('04:00')
    ->withoutOverlapping();

/**
 * Comment Interactions Cleanup
 * Remove expired comment interactions (older than 24 hours) every hour
 */
Schedule::command('comments:cleanup')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/comment-interactions-cleanup.log'));
