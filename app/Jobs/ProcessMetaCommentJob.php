<?php

namespace App\Jobs;

use App\Models\SocialAccount;
use App\Services\SocialCommentService;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessMetaCommentJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    protected array $payload;
    protected string $pageId;
    protected string $platform;

    public function __construct(array $payload, string $pageId, string $platform)
    {
        $this->payload = $payload;
        $this->pageId = $pageId;
        $this->platform = $platform;
    }

    public function handle(): void
    {
        Log::info("ProcessMetaCommentJob started", [
            'pageId' => $this->pageId,
            'platform' => $this->platform
        ]);

        $commentId = $this->payload['comment_id'] ?? null;
        if (!$commentId) {
            $commentId = $this->payload['id'] ?? null;
        }

        $commentText = $this->payload['message'] ?? $this->payload['text'] ?? null;
        $commenterId = $this->payload['from']['id'] ?? null;
        $commenterName = $this->payload['from']['name'] ?? null;
        $postId = $this->payload['post_id'] ?? $this->payload['media']['id'] ?? null;
        $permalinkUrl = $this->payload['post']['permalink_url'] ?? null;
        $verb = $this->payload['verb'] ?? 'add';

        if ($verb !== 'add' || !$commentId || !$commenterId || !$postId || !$commentText) {
            Log::info("SocialCommentService: Skipping comment event", [
                'verb' => $verb,
                'has_comment_id' => (bool)$commentId,
                'has_commenter_id' => (bool)$commenterId,
                'has_post_id' => (bool)$postId,
            ]);
            return;
        }

        // We do NOT reply to our own comments
        if ($commenterId === $this->pageId) {
            Log::info("SocialCommentService: Ignoring our own comment on page {$this->pageId}");
            return;
        }

        $socialAccount = SocialAccount::where('provider', $this->platform === 'instagram' ? 'instagram' : 'facebook_page')
            ->where('provider_id', $this->pageId)
            ->first();

        if (!$socialAccount) {
            Log::warning("ProcessMetaCommentJob: Social account not found", [
                'provider_id' => $this->pageId,
                'platform' => $this->platform
            ]);
            return;
        }

        $commentService = new SocialCommentService();
        $handled = $commentService->handleCommentWebhook(
            $commentId,
            $commentText,
            $commenterId,
            $commenterName,
            $postId,
            $this->platform,
            $socialAccount,
            $permalinkUrl
        );

        Log::info("ProcessMetaCommentJob completed", [
            'handled_by_product' => $handled
        ]);
    }
}
