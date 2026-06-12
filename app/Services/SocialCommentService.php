<?php

namespace App\Services;

use App\Events\NewCommentReceived;
use App\Models\CommentInteraction;
use App\Models\Product;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Social Comment Reply Service
 *
 * Handles the full comment-reply flow:
 * 1. Someone comments on a linked FB/IG post
 * 2. We auto-reply: "Contact us privately for details"
 * 3. We save the interaction (commenter + product) for 24 hours
 * 4. When they DM us, we detect the cached interaction and auto-send product details
 * 5. After 24 hours the cache expires — normal AI flow resumes
 */
class SocialCommentService
{
    protected string $graphVersion;

    public function __construct()
    {
        $this->graphVersion = config('services.meta.graph_api_version', 'v18.0');
    }

    // ------------------------------------------------------------------
    // 1. Permission check
    // ------------------------------------------------------------------

    /**
     * Check whether the store owner has the required Graph API permissions
     * to read comments and reply on Facebook / Instagram.
     *
     * Required scopes:
     *   Facebook: pages_manage_engagement, pages_read_engagement
     *   Instagram: instagram_manage_comments, instagram_basic
     */
    public function checkPermissions(User $user): array
    {
        $result = [
            'facebook' => ['ok' => false, 'missing' => []],
            'instagram' => ['ok' => false, 'missing' => []],
        ];

        // --- Facebook ---
        $fbAccount = SocialAccount::where('user_id', $user->id)
            ->where('provider', 'facebook_page')
            ->first();

        if ($fbAccount && $fbAccount->provider_token) {
            $perms = $this->fetchPermissions($fbAccount->provider_token);
            $requiredFb = ['pages_manage_engagement', 'pages_read_engagement'];
            $missing = array_diff($requiredFb, $perms);
            $result['facebook'] = [
                'ok' => empty($missing),
                'missing' => $missing,
                'has_account' => true,
            ];
        } else {
            $result['facebook']['has_account'] = false;
        }

        // --- Instagram ---
        // Instagram Business accounts use the Facebook Page token via Graph API,
        // so we check permissions on the facebook_page token OR a dedicated instagram account.
        $igAccount = SocialAccount::where('user_id', $user->id)
            ->where('provider', 'instagram')
            ->first();

        // Use the IG account token if available, otherwise fall back to FB page token
        // because IG Business permissions are granted through the Facebook OAuth flow
        $igToken = null;
        if ($igAccount && $igAccount->provider_token) {
            $igToken = $igAccount->provider_token;
        } elseif ($fbAccount && $fbAccount->provider_token) {
            $igToken = $fbAccount->provider_token;
        }

        if ($igToken) {
            $perms = $this->fetchPermissions($igToken);
            $requiredIg = ['instagram_manage_comments', 'instagram_basic'];
            $missing = array_diff($requiredIg, $perms);
            $result['instagram'] = [
                'ok' => empty($missing),
                'missing' => $missing,
                'has_account' => true,
            ];
        } else {
            $result['instagram']['has_account'] = ($igAccount !== null);
        }

        return $result;
    }

    /**
     * Query the Graph API /me/permissions endpoint.
     */
    protected function fetchPermissions(string $accessToken): array
    {
        try {
            $response = Http::withoutVerifying()->get("https://graph.facebook.com/{$this->graphVersion}/me/permissions", [
                'access_token' => $accessToken,
            ]);

            if ($response->successful()) {
                $data = $response->json('data', []);
                return collect($data)
                    ->where('status', 'granted')
                    ->pluck('permission')
                    ->toArray();
            }
        } catch (\Exception $e) {
            Log::error('SocialCommentService: Failed to fetch permissions', ['error' => $e->getMessage()]);
        }

        return [];
    }

    // ------------------------------------------------------------------
    // 2. Match post -> product
    // ------------------------------------------------------------------

    /**
     * Given a Graph API post/media object ID, find the product it belongs to.
     * We match by stored facebook_post_url or instagram_post_url on the Product.
     */
    public function findProductByPostId(string $postId, User $user, string $platform, ?string $permalinkUrl = null, ?string $accessToken = null): ?Product
    {
        $urlColumn = $platform === 'instagram' ? 'instagram_post_url' : 'facebook_post_url';
        $idColumn = $platform === 'instagram' ? 'instagram_post_id' : 'facebook_post_id';

        // Collect numeric IDs to try: from postId parts and from the permalink URL.
        // Facebook post_id format is "pageId_postId" — postId part may differ from the
        // actual video/photo media ID that appears in the permalink and the stored URL.
        $candidates = $this->extractNumericIds($postId);
        if ($permalinkUrl) {
            $candidates = array_merge($candidates, $this->extractNumericIds($permalinkUrl));
        }
        $candidates = array_unique(array_filter($candidates));

        $product = Product::where('user_id', $user->id)
            ->where(function ($q) use ($urlColumn, $idColumn, $postId, $candidates) {
                // 1. Match against stored page-scoped object ID (most reliable)
                $q->where($idColumn, $postId);
                foreach ($candidates as $id) {
                    $q->orWhere($idColumn, $id);
                }
                // 2. Fall back to LIKE match on stored URL
                $q->orWhere($urlColumn, 'LIKE', "%{$postId}%");
                foreach ($candidates as $id) {
                    $q->orWhere($urlColumn, 'LIKE', "%{$id}%");
                }
            })
            ->first();

        // Last-resort fallback: fetch post details (permalink + photo attachment IDs) from
        // the Graph API. This covers the common case where the user linked a /photo?fbid=…
        // URL but the webhook delivers a page-scoped post_id whose numeric suffix differs
        // from the photo's own fbid.
        if (!$product && $accessToken && $platform === 'facebook') {
            $details = $this->fetchPostDetails($postId, $accessToken);

            // Build a full set of IDs/URLs to search against
            $searchIds = array_unique(array_filter(array_merge(
                $details['media_fbids'],
                $details['permalink'] ? $this->extractNumericIds($details['permalink']) : []
            )));
            $searchUrls = array_filter([
                $details['permalink'] ?? null,
            ]);

            if ($searchIds || $searchUrls) {
                $product = Product::where('user_id', $user->id)
                    ->where(function ($q) use ($urlColumn, $idColumn, $searchIds, $searchUrls) {
                        foreach ($searchIds as $id) {
                            $q->orWhere($idColumn, $id);
                            $q->orWhere($urlColumn, 'LIKE', "%{$id}%");
                        }
                        foreach ($searchUrls as $url) {
                            $q->orWhere($urlColumn, 'LIKE', "%{$url}%");
                        }
                    })
                    ->first();

                if ($product) {
                    Log::info('SocialCommentService: Matched product via Graph API details fallback', [
                        'post_id' => $postId,
                        'permalink' => $details['permalink'],
                        'media_fbids' => $details['media_fbids'],
                        'product_id' => $product->id,
                    ]);
                }
            }
        }

        return $product;
    }

    /**
     * Extract all numeric sequences of 10+ digits from a URL or ID string.
     * Covers Facebook video IDs, photo IDs, and page-scoped post IDs.
     */
    protected function extractNumericIds(string $str): array
    {
        preg_match_all('/\d{10,}/', $str, $matches);
        return $matches[0] ?? [];
    }

    /**
     * Fetch post details from the Graph API: permalink URL + photo attachment fbids.
     *
     * When a user links a /photo?fbid=… URL to a product, the stored fbid belongs to
     * the individual photo object. But the webhook delivers a page-scoped post_id
     * (pageId_postId) whose numeric suffix is different from the photo fbid. Fetching
     * the post's attachments gives us the actual photo media_fbid so we can match it
     * against the stored URL.
     *
     * Returns: ['permalink' => string|null, 'media_fbids' => string[]]
     */
    protected function fetchPostDetails(string $postId, string $accessToken): array
    {
        $result = ['permalink' => null, 'media_fbids' => []];

        try {
            $response = Http::timeout(10)->withoutVerifying()->get("https://graph.facebook.com/{$this->graphVersion}/{$postId}", [
                'fields' => 'permalink_url,attachments{media_fbid,url}',
                'access_token' => $accessToken,
            ]);

            if (!$response->successful()) {
                Log::warning('SocialCommentService: Graph API post details fetch failed', [
                    'post_id' => $postId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return $result;
            }

            $data = $response->json();
            $result['permalink'] = $data['permalink_url'] ?? null;

            // Collect all photo media_fbid values from attachments
            foreach ($data['attachments']['data'] ?? [] as $attachment) {
                if (!empty($attachment['media_fbid'])) {
                    $result['media_fbids'][] = (string) $attachment['media_fbid'];
                }
                // Also extract numeric IDs from the attachment's own URL if present
                if (!empty($attachment['url'])) {
                    foreach ($this->extractNumericIds($attachment['url']) as $id) {
                        $result['media_fbids'][] = $id;
                    }
                }
            }

            $result['media_fbids'] = array_values(array_unique($result['media_fbids']));

            Log::info('SocialCommentService: Fetched post details from Graph API', [
                'post_id' => $postId,
                'permalink' => $result['permalink'],
                'media_fbids' => $result['media_fbids'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('SocialCommentService: Graph API post details exception', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Fetch the permalink_url for a post/object from the Meta Graph API.
     * Returns null if the call fails or the field is not present.
     * @deprecated Use fetchPostDetails() instead.
     */
    protected function fetchPostPermalink(string $postId, string $accessToken): ?string
    {
        return $this->fetchPostDetails($postId, $accessToken)['permalink'];
    }

    /**
     * Alternative lookup: match by the full URL or extracted media/post id.
     */
    public function findProductByPostUrl(string $postUrl, User $user, string $platform): ?Product
    {
        $column = $platform === 'instagram' ? 'instagram_post_url' : 'facebook_post_url';

        return Product::where('user_id', $user->id)
            ->where($column, $postUrl)
            ->first();
    }

    // ------------------------------------------------------------------
    // 3. Auto-reply to the comment
    // ------------------------------------------------------------------

    /**
     * Reply to a comment on Facebook or Instagram telling the user
     * to contact us privately.
     *
     * @return bool  Whether the reply was sent successfully
     */
    public function replyToComment(
        SocialAccount $socialAccount,
        string $commentId,
        string $platform,
        ?string $customMessage = null
    ): bool {
        $accessToken = $socialAccount->provider_token;
        $message = $customMessage ?? 'مرحباً! تواصل معنا على الخاص وسنرسل لك جميع التفاصيل 🙏';

        try {
            $metaApi = app(MetaApiService::class);

            // In proxy mode, use the proxy API for comment replies
            if ($metaApi->isProxy()) {
                $data = $metaApi->replyComment(
                    $socialAccount->provider_id,
                    $accessToken,
                    $commentId,
                    $message,
                    $platform
                );
                if ($data) {
                    Log::info("SocialCommentService: Replied to comment via proxy", [
                        'comment_id' => $commentId,
                        'platform' => $platform,
                    ]);
                    return true;
                }
                return false;
            }

            // Direct mode: call Facebook/Instagram Graph API
            if ($platform === 'instagram') {
                $endpoint = "https://graph.facebook.com/{$this->graphVersion}/{$commentId}/replies";
            } else {
                $endpoint = "https://graph.facebook.com/{$this->graphVersion}/{$commentId}/comments";
            }

            $response = Http::withoutVerifying()->post($endpoint, [
                'message' => $message,
                'access_token' => $accessToken,
            ]);

            if ($response->successful()) {
                Log::info("SocialCommentService: Replied to comment", [
                    'comment_id' => $commentId,
                    'platform' => $platform,
                    'reply_id' => $response->json('id'),
                ]);
                return true;
            }

            Log::error("SocialCommentService: Failed to reply to comment", [
                'comment_id' => $commentId,
                'platform' => $platform,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error("SocialCommentService: Exception replying to comment", [
                'error' => $e->getMessage(),
                'comment_id' => $commentId,
            ]);
        }

        return false;
    }

    // ------------------------------------------------------------------
    // 4. Save the comment interaction (24h cache)
    // ------------------------------------------------------------------

    /**
     * Record that a user commented on a post linked to a product.
     * The interaction expires after 24 hours.
     */
    public function saveInteraction(
        User $user,
        Product $product,
        string $platform,
        string $commenterId,
        ?string $commenterName,
        ?string $commentId,
        ?string $postId,
        ?string $commentText
    ): CommentInteraction {
        // Upsert: if same commenter + user + product already active — extend
        $interaction = CommentInteraction::where('user_id', $user->id)
            ->where('commenter_id', $commenterId)
            ->where('product_id', $product->id)
            ->active()
            ->first();

        if ($interaction) {
            $interaction->update([
                'comment_id' => $commentId ?? $interaction->comment_id,
                'comment_text' => $commentText ?? $interaction->comment_text,
                'expires_at' => now()->addHours(24),
            ]);
            return $interaction;
        }

        return CommentInteraction::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'platform' => $platform,
            'commenter_id' => $commenterId,
            'commenter_name' => $commenterName,
            'comment_id' => $commentId,
            'post_id' => $postId,
            'comment_text' => $commentText,
            'replied' => false,
            'dm_sent' => false,
            'expires_at' => now()->addHours(24),
        ]);
    }

    // ------------------------------------------------------------------
    // 5. Check if an incoming DM sender has an active comment interaction
    // ------------------------------------------------------------------

    /**
     * When a user sends a private message, check if they commented
     * on one of our linked posts in the last 24 hours.
     *
     * Returns the most recent active interaction, or null.
     */
    public function findActiveInteraction(string $senderId, int $storeUserId): ?CommentInteraction
    {
        return CommentInteraction::where('commenter_id', $senderId)
            ->where('user_id', $storeUserId)
            ->where('dm_sent', false)
            ->active()
            ->with('product.images')
            ->latest()
            ->first();
    }

    // ------------------------------------------------------------------
    // 6. Build a product details reply for the DM
    // ------------------------------------------------------------------

    /**
     * Build a rich text message with product details to send in the DM.
     */
    public function buildProductDetailsMessage(Product $product): string
    {
        $price = number_format((int) $product->price, 0, '', ',');
        $currency = $product->currency === 'USD' ? '$' : 'د.ع';

        $lines = [];
        $lines[] = "مرحباً! شكراً لتعليقك 🙏";
        $lines[] = "";
        $lines[] = "تفاصيل المنتج الذي سألت عنه:";
        $lines[] = "📦 {$product->name}";
        $lines[] = "💰 السعر: {$price} {$currency}";

        if ($product->description) {
            $desc = mb_strlen($product->description) > 200
                ? mb_substr($product->description, 0, 200) . '...'
                : $product->description;
            $lines[] = "📝 {$desc}";
        }

        if ($product->quantity > 0) {
            $lines[] = "✅ متوفر في المخزن";
        } else {
            $lines[] = "❌ غير متوفر حالياً";
        }

        $lines[] = "";
        $lines[] = "هل تريد طلب هذا المنتج؟";

        return implode("\n", $lines);
    }

    /**
     * Mark interaction as DM-sent so we don't re-send on the next message.
     */
    public function markDmSent(CommentInteraction $interaction): void
    {
        $interaction->update(['dm_sent' => true]);
    }

    // ------------------------------------------------------------------
    // 7. The full comment webhook handler
    // ------------------------------------------------------------------

    /**
     * Process an incoming comment webhook event.
     *
     * Steps:
     *  1. Find the social account + store owner
     *  2. Match post to product
     *  3. Auto-reply "contact us privately"
     *  4. Save interaction with 24h TTL
     */
    public function handleCommentWebhook(
        string $commentId,
        string $commentText,
        string $commenterId,
        ?string $commenterName,
        string $postId,
        string $platform,
        SocialAccount $socialAccount,
        ?string $permalinkUrl = null
    ): bool {
        // ── Dedup guard: never reply to the same comment twice ───────────────
        $alreadyReplied = CommentInteraction::where('comment_id', $commentId)
            ->where('replied', true)
            ->exists();

        if ($alreadyReplied) {
            Log::debug('SocialCommentService: Already replied to comment — skipping', [
                'comment_id' => $commentId,
            ]);
            return false;
        }
        // ── End dedup guard ───────────────────────────────────────────────────

        $user = $socialAccount->user;

        // Find which product this post belongs to
        $product = $this->findProductByPostId(
            $postId,
            $user,
            $platform,
            $permalinkUrl,
            $socialAccount->provider_token  // passed for Graph API fallback
        );

        if (!$product) {
            Log::info("SocialCommentService: Post {$postId} not linked to any product", [
                'user_id' => $user->id,
                'platform' => $platform,
            ]);

            // [NEW] Fallback Generic Reply Logic
            $aiSetting = \App\Models\AiSetting::where('user_id', $user->id)->first();
            if ($aiSetting && !empty($aiSetting->fallback_comment_reply)) {
                $this->replyToComment($socialAccount, $commentId, $platform, $aiSetting->fallback_comment_reply);
                Log::info("SocialCommentService: Sent fallback comment reply", [
                    'user_id' => $user->id,
                    'comment_id' => $commentId
                ]);
            }

            return false;
        }

        Log::info("SocialCommentService: Comment on product post", [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'commenter_id' => $commenterId,
            'comment' => mb_substr($commentText, 0, 100),
        ]);

        // Auto-reply to the comment
        $replied = $this->replyToComment($socialAccount, $commentId, $platform);

        // Save interaction for 24h
        $interaction = $this->saveInteraction(
            $user,
            $product,
            $platform,
            $commenterId,
            $commenterName,
            $commentId,
            $postId,
            $commentText
        );

        $interaction->update(['replied' => $replied]);

        // Fire broadcast event for real-time audio notification
        try {
            event(new NewCommentReceived(
                $user->id,
                (string) $commenterId,
                $platform,
                $product->name ?? ''
            ));
        } catch (\Throwable $e) {
            Log::warning('SocialCommentService: Failed to broadcast NewCommentReceived', ['error' => $e->getMessage()]);
        }

        return true;
    }

    // ------------------------------------------------------------------
    // 8. Cleanup expired interactions
    // ------------------------------------------------------------------

    /**
     * Delete all expired interactions (older than 24 hours).
     * Should be called via a scheduled command.
     */
    public function cleanupExpired(): int
    {
        return CommentInteraction::expired()->delete();
    }
}
