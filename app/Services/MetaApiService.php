<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Central gateway for all Facebook/Instagram API operations.
 *
 * Supports two modes configured via META_CONNECTION_MODE in .env:
 *   - "direct" → calls Facebook Graph API directly (needs own Facebook App)
 *   - "proxy"  → calls a proxy server's API (needs META_PROXY_URL + keys)
 */
class MetaApiService
{
    protected string $mode;
    protected string $graphVersion;
    protected ?string $proxyUrl;
    protected ?string $proxyApiKey;
    protected ?string $proxyApiSecret;

    public function __construct()
    {
        $this->mode           = config('services.meta.connection_mode', 'direct');
        $this->graphVersion   = config('services.meta.graph_api_version', 'v21.0');
        $this->proxyUrl       = rtrim(config('services.meta.proxy_url', ''), '/');
        $this->proxyApiKey    = config('services.meta.proxy_api_key');
        $this->proxyApiSecret = config('services.meta.proxy_api_secret');
    }

    /**
     * Is this instance running in proxy-client mode?
     */
    public function isProxy(): bool
    {
        return $this->mode === 'proxy'
            && !empty($this->proxyUrl)
            && !empty($this->proxyApiKey)
            && !empty($this->proxyApiSecret);
    }

    // ─── OAuth ──────────────────────────────────────────────────────────

    /**
     * Get the URL to start the Facebook OAuth flow.
     * In proxy mode, redirects to the proxy server instead of Facebook directly.
     */
    public function getOAuthStartUrl(string $externalUserId, bool $rerequest = false): ?string
    {
        if (!$this->isProxy()) {
            return null; // Direct mode — use Socialite as usual
        }

        $params = [
            'api_key'          => $this->proxyApiKey,
            'external_user_id' => $externalUserId,
            'callback_url'     => rtrim(config('app.url'), '/') . '/auth/proxy/callback',
        ];

        if ($rerequest) {
            $params['rerequest'] = 1;
        }

        return $this->proxyUrl . '/proxy/auth/start?' . http_build_query($params);
    }

    /**
     * Get the URL to start the Instagram Direct (Instagram Login for Business) flow.
     * Only available in proxy mode — returns null otherwise.
     *
     * Lets an Instagram-only account (no linked Facebook page) connect directly.
     */
    public function getInstagramDirectOAuthStartUrl(string $externalUserId): ?string
    {
        if (!$this->isProxy()) {
            return null;
        }

        $params = [
            'api_key'          => $this->proxyApiKey,
            'external_user_id' => $externalUserId,
            'callback_url'     => rtrim(config('app.url'), '/') . '/auth/proxy/callback',
        ];

        return $this->proxyUrl . '/proxy/auth/instagram-direct/start?' . http_build_query($params);
    }

    // ─── Send Message ───────────────────────────────────────────────────

    /**
     * Send a text message to a user.
     *
     * @return array|null Response data on success, null on failure
     */
    public function sendMessage(string $pageId, string $pageAccessToken, string $recipientId, string $text, string $platform = 'facebook'): ?array
    {
        if ($this->isProxy()) {
            return $this->proxySendMessage($pageId, $recipientId, $text, $platform);
        }

        return $this->directSendMessage($pageId, $pageAccessToken, $recipientId, $text, $platform);
    }

    /**
     * Send an image to a user.
     */
    public function sendImage(string $pageId, string $pageAccessToken, string $recipientId, string $imageUrl, string $platform = 'facebook'): ?array
    {
        if ($this->isProxy()) {
            return $this->proxySendImage($pageId, $recipientId, $imageUrl, $platform);
        }

        return $this->directSendImage($pageId, $pageAccessToken, $recipientId, $imageUrl, $platform);
    }

    /**
     * Reply to a comment.
     */
    public function replyComment(string $pageId, string $pageAccessToken, string $commentId, string $message, string $platform = 'facebook'): ?array
    {
        if ($this->isProxy()) {
            return $this->proxyReplyComment($pageId, $commentId, $message, $platform);
        }

        return $this->directReplyComment($pageAccessToken, $commentId, $message, $platform);
    }

    /**
     * Fetch participant info (name, profile pic) from Facebook.
     */
    public function fetchParticipantInfo(string $participantId, string $pageAccessToken): ?array
    {
        if ($this->isProxy()) {
            return $this->proxyFetchParticipantInfo($participantId);
        }

        return $this->directFetchParticipantInfo($participantId, $pageAccessToken);
    }

    /**
     * Fetch conversations for a page.
     */
    public function fetchConversations(string $pageId, string $pageAccessToken, string $platform = 'facebook'): ?array
    {
        if ($this->isProxy()) {
            return $this->proxyFetchConversations($pageId);
        }

        return $this->directFetchConversations($pageId, $pageAccessToken, $platform);
    }

    /**
     * Fetch recent posts/media from a Facebook Page or Instagram Business Account.
     */
    public function fetchPagePosts(string $pageId, string $pageAccessToken, string $platform = 'facebook', int $limit = 20): ?array
    {
        if ($this->isProxy()) {
            // Proxy mode fallback or implementation
            return $this->proxyFetchPagePosts($pageId, $platform, $limit);
        }

        return $this->directFetchPagePosts($pageId, $pageAccessToken, $platform, $limit);
    }


    // ─── Direct Mode (Facebook Graph API) ───────────────────────────────

    protected function directSendMessage(string $pageId, string $pageAccessToken, string $recipientId, string $text, string $platform): ?array
    {
        // WhatsApp Cloud API uses a different format
        if ($platform === 'whatsapp') {
            return $this->directSendWhatsAppMessage($pageId, $pageAccessToken, $recipientId, $text);
        }

        try {
            // Both Facebook pages and Instagram Business Accounts use /{id}/messages
            $endpoint = "https://graph.facebook.com/{$this->graphVersion}/{$pageId}/messages";

            $payload = [
                'recipient'      => ['id' => $recipientId],
                'message'        => ['text' => $text],
                'messaging_type' => 'RESPONSE',
                'access_token'   => $pageAccessToken,
            ];

            $response = Http::post($endpoint, $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("MetaApiService: direct sendMessage failed", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error("MetaApiService: direct sendMessage exception: " . $e->getMessage());
            return null;
        }
    }

    protected function directSendImage(string $pageId, string $pageAccessToken, string $recipientId, string $imageUrl, string $platform): ?array
    {
        // WhatsApp Cloud API uses a different format
        if ($platform === 'whatsapp') {
            return $this->directSendWhatsAppImage($pageId, $pageAccessToken, $recipientId, $imageUrl);
        }

        try {
            // Both Facebook pages and Instagram Business Accounts use /{id}/messages
            $endpoint = "https://graph.facebook.com/{$this->graphVersion}/{$pageId}/messages";

            $payload = [
                'recipient'      => ['id' => $recipientId],
                'message'        => [
                    'attachment' => [
                        'type'    => 'image',
                        'payload' => ['url' => $imageUrl, 'is_reusable' => true],
                    ],
                ],
                'messaging_type' => 'RESPONSE',
                'access_token'   => $pageAccessToken,
            ];

            $response = Http::post($endpoint, $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("MetaApiService: direct sendImage failed", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error("MetaApiService: direct sendImage exception: " . $e->getMessage());
            return null;
        }
    }

    protected function directReplyComment(string $pageAccessToken, string $commentId, string $message, string $platform = 'facebook'): ?array
    {
        try {
            // Instagram uses /replies endpoint; Facebook uses /comments
            $sub = $platform === 'instagram' ? 'replies' : 'comments';
            $endpoint = "https://graph.facebook.com/{$this->graphVersion}/{$commentId}/{$sub}";

            $response = Http::post($endpoint, [
                'message'      => $message,
                'access_token' => $pageAccessToken,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("MetaApiService: direct replyComment failed", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error("MetaApiService: direct replyComment exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Send a text message via WhatsApp Cloud API.
     */
    protected function directSendWhatsAppMessage(string $phoneNumberId, string $accessToken, string $to, string $text): ?array
    {
        try {
            $endpoint = "https://graph.facebook.com/{$this->graphVersion}/{$phoneNumberId}/messages";

            $response = Http::withToken($accessToken)->post($endpoint, [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'text',
                'text'              => ['body' => $text],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'message_id'   => $data['messages'][0]['id'] ?? null,
                    'recipient_id' => $to,
                ];
            }

            Log::error('MetaApiService: WhatsApp sendMessage failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('MetaApiService: WhatsApp sendMessage exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send an image via WhatsApp Cloud API.
     */
    protected function directSendWhatsAppImage(string $phoneNumberId, string $accessToken, string $to, string $imageUrl): ?array
    {
        try {
            $endpoint = "https://graph.facebook.com/{$this->graphVersion}/{$phoneNumberId}/messages";

            $response = Http::withToken($accessToken)->post($endpoint, [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'image',
                'image'             => ['link' => $imageUrl],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'message_id'   => $data['messages'][0]['id'] ?? null,
                    'recipient_id' => $to,
                ];
            }

            Log::error('MetaApiService: WhatsApp sendImage failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('MetaApiService: WhatsApp sendImage exception: ' . $e->getMessage());
            return null;
        }
    }

    protected function directFetchParticipantInfo(string $participantId, string $pageAccessToken): ?array
    {
        try {
            $response = Http::timeout(10)->get("https://graph.facebook.com/{$this->graphVersion}/{$participantId}", [
                'fields'       => 'name,username,profile_pic',
                'access_token' => $pageAccessToken,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("MetaApiService: direct fetchParticipantInfo failed", [
                'participant' => $participantId,
                'status'      => $response->status(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::warning("MetaApiService: direct fetchParticipantInfo exception: " . $e->getMessage());
            return null;
        }
    }

    protected function directFetchConversations(string $pageId, string $pageAccessToken, string $platform): ?array
    {
        try {
            // Both Facebook pages and Instagram Business Accounts use /{id}/conversations
            $endpoint = "https://graph.facebook.com/{$this->graphVersion}/{$pageId}/conversations";

            $params = [
                'fields'       => 'participants,messages{message,from,created_time,attachments},updated_time',
                'access_token' => $pageAccessToken,
            ];

            if ($platform === 'instagram') {
                $params['platform'] = 'instagram';
            }

            $response = Http::timeout(15)->get($endpoint, $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("MetaApiService: direct fetchConversations failed", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error("MetaApiService: direct fetchConversations exception: " . $e->getMessage());
            return null;
        }
    }

    protected function directFetchPagePosts(string $pageId, string $pageAccessToken, string $platform, int $limit): ?array
    {
        try {
            if ($platform === 'instagram') {
                $endpoint = "https://graph.facebook.com/{$this->graphVersion}/{$pageId}/media";
                $fields = 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp';
            } else {
                $endpoint = "https://graph.facebook.com/{$this->graphVersion}/{$pageId}/published_posts";
                $fields = 'id,message,full_picture,permalink_url,created_time,attachments{media_type,media,url}';
            }

            $response = Http::timeout(15)->get($endpoint, [
                'fields'       => $fields,
                'limit'        => $limit,
                'access_token' => $pageAccessToken,
            ]);

            if ($response->successful()) {
                return $response->json('data', []);
            }

            Log::error("MetaApiService: direct fetchPagePosts failed", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error("MetaApiService: direct fetchPagePosts exception: " . $e->getMessage());
            return null;
        }
    }

    // ─── Proxy Mode (calls proxy server API) ────────────────────────────

    protected function proxySendMessage(string $pageId, string $recipientId, string $text, string $platform = 'facebook'): ?array
    {
        return $this->proxyRequest('POST', '/proxy/api/send-message', [
            'page_id'      => $pageId,
            'recipient_id' => $recipientId,
            'message'      => $text,
            'platform'     => $platform,
        ]);
    }

    protected function proxySendImage(string $pageId, string $recipientId, string $imageUrl, string $platform = 'facebook'): ?array
    {
        return $this->proxyRequest('POST', '/proxy/api/send-image', [
            'page_id'      => $pageId,
            'recipient_id' => $recipientId,
            'image_url'    => $imageUrl,
            'platform'     => $platform,
        ]);
    }

    protected function proxyReplyComment(string $pageId, string $commentId, string $message, string $platform = 'facebook'): ?array
    {
        return $this->proxyRequest('POST', '/proxy/api/reply-comment', [
            'page_id'    => $pageId,
            'comment_id' => $commentId,
            'message'    => $message,
            'platform'   => $platform,
        ]);
    }

    protected function proxyFetchParticipantInfo(string $participantId): ?array
    {
        return $this->proxyRequest('POST', '/proxy/api/participant-info', [
            'participant_id' => $participantId,
        ]);
    }

    protected function proxyFetchConversations(string $pageId): ?array
    {
        return $this->proxyRequest('POST', '/proxy/api/conversations', [
            'page_id' => $pageId,
        ]);
    }

    protected function proxyFetchPagePosts(string $pageId, string $platform, int $limit): ?array
    {
        return $this->proxyRequest('POST', '/proxy/api/page-posts', [
            'page_id' => $pageId,
            'platform' => $platform,
            'limit' => $limit,
        ]);
    }

    /**
     * Make an HMAC-signed request to the proxy server.
     */
    protected function proxyRequest(string $method, string $path, array $data = []): ?array
    {
        try {
            $url       = $this->proxyUrl . $path;
            $timestamp = (string) time();
            $body      = $method === 'GET' ? '' : json_encode($data);
            $signature = hash_hmac('sha256', $timestamp . '.' . $body, $this->proxyApiSecret);

            $headers = [
                'X-Api-Key'       => $this->proxyApiKey,
                'X-Api-Timestamp' => $timestamp,
                'X-Api-Signature' => $signature,
                'Accept'          => 'application/json',
            ];

            if ($method === 'GET') {
                $response = Http::withHeaders($headers)->timeout(15)->get($url, $data);
            } else {
                // Use withBody so the raw bytes sent match exactly what was HMAC-signed
                $response = Http::withHeaders($headers)
                    ->timeout(15)
                    ->withBody($body, 'application/json')
                    ->post($url);
            }

            $result = $response->json();

            if ($response->successful() && ($result['success'] ?? false)) {
                Log::info("MetaApiService: proxy request success", ['path' => $path]);
                return $result['data'] ?? $result;
            }

            Log::error("MetaApiService: proxy request failed", [
                'path'   => $path,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error("MetaApiService: proxy request exception: " . $e->getMessage());
            return null;
        }
    }
}
