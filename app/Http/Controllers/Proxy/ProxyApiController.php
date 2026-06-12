<?php

namespace App\Http\Controllers\Proxy;

use App\Http\Controllers\Controller;
use App\Models\ProxyApiLog;
use App\Models\ProxyPlatform;
use App\Models\ProxySocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProxyApiController extends Controller
{
    /**
     * Send a text message via the Facebook/Instagram Graph API.
     *
     * POST /proxy/api/send-message
     * Headers: X-Api-Key, X-Api-Signature, X-Api-Timestamp
     * Body: { page_id, recipient_id, message, platform }
     */
    public function sendMessage(Request $request)
    {
        $platform = $this->authenticate($request);
        if (!$platform) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'page_id'      => 'required|string',
            'recipient_id' => 'required|string',
            'message'      => 'required|string|max:2000',
            'platform'     => 'required|in:facebook,instagram',
        ]);

        $proxyAccount = ProxySocialAccount::where('proxy_platform_id', $platform->id)
            ->where('provider', 'facebook_page')
            ->where('provider_id', $request->page_id)
            ->first();

        if (!$proxyAccount) {
            return response()->json(['error' => 'Page not found for this platform'], 404);
        }

        $graphVersion = config('services.meta.graph_api_version', 'v21.0');

        if ($request->platform === 'instagram') {
            $endpoint = "https://graph.facebook.com/{$graphVersion}/me/messages";
        } else {
            $endpoint = "https://graph.facebook.com/{$graphVersion}/{$request->page_id}/messages";
        }

        try {
            $response = Http::timeout(15)->post($endpoint, [
                'recipient'        => ['id' => $request->recipient_id],
                'message'          => ['text' => $request->message],
                'messaging_type'   => 'RESPONSE',
                'access_token'     => $proxyAccount->provider_token,
            ]);

            $this->logApiCall($platform, 'send_message', $request->page_id, $response);

            if ($response->successful()) {
                return response()->json([
                    'success'    => true,
                    'message_id' => $response->json('message_id'),
                ]);
            }

            return response()->json([
                'success' => false,
                'error'   => $response->json('error.message', 'Unknown error'),
            ], 422);
        } catch (\Exception $e) {
            $this->logApiCall($platform, 'send_message', $request->page_id, null, $e->getMessage());

            return response()->json([
                'success' => false,
                'error'   => 'Failed to send message',
            ], 500);
        }
    }

    /**
     * Send an image attachment.
     *
     * POST /proxy/api/send-image
     * Body: { page_id, recipient_id, image_url, platform }
     */
    public function sendImage(Request $request)
    {
        $platform = $this->authenticate($request);
        if (!$platform) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'page_id'      => 'required|string',
            'recipient_id' => 'required|string',
            'image_url'    => 'required|url',
            'platform'     => 'required|in:facebook,instagram',
        ]);

        $proxyAccount = ProxySocialAccount::where('proxy_platform_id', $platform->id)
            ->where('provider', 'facebook_page')
            ->where('provider_id', $request->page_id)
            ->first();

        if (!$proxyAccount) {
            return response()->json(['error' => 'Page not found for this platform'], 404);
        }

        $graphVersion = config('services.meta.graph_api_version', 'v21.0');

        if ($request->platform === 'instagram') {
            $endpoint = "https://graph.facebook.com/{$graphVersion}/me/messages";
        } else {
            $endpoint = "https://graph.facebook.com/{$graphVersion}/{$request->page_id}/messages";
        }

        try {
            $response = Http::timeout(15)->post($endpoint, [
                'recipient'      => ['id' => $request->recipient_id],
                'message'        => [
                    'attachment' => [
                        'type'    => 'image',
                        'payload' => ['url' => $request->image_url, 'is_reusable' => true],
                    ],
                ],
                'messaging_type' => 'RESPONSE',
                'access_token'   => $proxyAccount->provider_token,
            ]);

            $this->logApiCall($platform, 'send_image', $request->page_id, $response);

            if ($response->successful()) {
                return response()->json(['success' => true, 'message_id' => $response->json('message_id')]);
            }

            return response()->json([
                'success' => false,
                'error'   => $response->json('error.message', 'Unknown error'),
            ], 422);
        } catch (\Exception $e) {
            $this->logApiCall($platform, 'send_image', $request->page_id, null, $e->getMessage());

            return response()->json(['success' => false, 'error' => 'Failed to send image'], 500);
        }
    }

    /**
     * Reply to a comment.
     *
     * POST /proxy/api/reply-comment
     * Body: { page_id, comment_id, message, platform }
     */
    public function replyComment(Request $request)
    {
        $platform = $this->authenticate($request);
        if (!$platform) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'page_id'    => 'required|string',
            'comment_id' => 'required|string',
            'message'    => 'required|string|max:2000',
            'platform'   => 'required|in:facebook,instagram',
        ]);

        $proxyAccount = ProxySocialAccount::where('proxy_platform_id', $platform->id)
            ->where('provider', 'facebook_page')
            ->where('provider_id', $request->page_id)
            ->first();

        if (!$proxyAccount) {
            return response()->json(['error' => 'Page not found for this platform'], 404);
        }

        $graphVersion = config('services.meta.graph_api_version', 'v21.0');
        $endpoint     = "https://graph.facebook.com/{$graphVersion}/{$request->comment_id}/comments";

        try {
            $response = Http::timeout(15)->post($endpoint, [
                'message'      => $request->message,
                'access_token' => $proxyAccount->provider_token,
            ]);

            $this->logApiCall($platform, 'reply_comment', $request->page_id, $response);

            if ($response->successful()) {
                return response()->json(['success' => true, 'comment_id' => $response->json('id')]);
            }

            return response()->json([
                'success' => false,
                'error'   => $response->json('error.message', 'Unknown error'),
            ], 422);
        } catch (\Exception $e) {
            $this->logApiCall($platform, 'reply_comment', $request->page_id, null, $e->getMessage());

            return response()->json(['success' => false, 'error' => 'Failed to reply'], 500);
        }
    }

    /**
     * List linked pages for this platform + external user.
     *
     * GET /proxy/api/pages?external_user_id=123
     */
    public function listPages(Request $request)
    {
        $platform = $this->authenticate($request);
        if (!$platform) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $query = ProxySocialAccount::where('proxy_platform_id', $platform->id);

        if ($request->has('external_user_id')) {
            $query->where('external_user_id', $request->external_user_id);
        }

        $accounts = $query->get()->map(fn($a) => [
            'provider'         => $a->provider,
            'provider_id'      => $a->provider_id,
            'name'             => $a->name,
            'avatar'           => $a->avatar,
            'external_user_id' => $a->external_user_id,
        ]);

        return response()->json(['success' => true, 'accounts' => $accounts]);
    }

    /**
     * Fetch participant info (name, profile pic).
     *
     * POST /proxy/api/participant-info
     * { "participant_id": "123456" }
     */
    public function participantInfo(Request $request)
    {
        $platform = $this->authenticate($request);
        if (!$platform) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'participant_id' => 'required|string',
        ]);

        // Find any active page token for this platform to make the API call
        $proxyAccount = ProxySocialAccount::where('proxy_platform_id', $platform->id)
            ->whereNotNull('provider_token')
            ->first();

        if (!$proxyAccount) {
            return response()->json(['error' => 'No page token available'], 404);
        }

        $graphVersion = config('services.meta.graph_api_version', 'v21.0');

        try {
            $response = Http::timeout(10)->get(
                "https://graph.facebook.com/{$graphVersion}/{$request->participant_id}",
                [
                    'fields'       => 'name,first_name,last_name,profile_pic',
                    'access_token' => $proxyAccount->provider_token,
                ]
            );

            if ($response->successful()) {
                return response()->json(['success' => true, 'data' => $response->json()]);
            }

            return response()->json([
                'success' => false,
                'error'   => $response->json('error.message', 'Unknown error'),
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Failed to fetch participant info'], 500);
        }
    }

    /**
     * Fetch conversations for a page.
     *
     * POST /proxy/api/conversations
     * { "page_id": "123456" }
     */
    public function conversations(Request $request)
    {
        $platform = $this->authenticate($request);
        if (!$platform) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'page_id' => 'required|string',
        ]);

        $proxyAccount = ProxySocialAccount::where('proxy_platform_id', $platform->id)
            ->where('provider_id', $request->page_id)
            ->first();

        if (!$proxyAccount) {
            return response()->json(['error' => 'Page not found for this platform'], 404);
        }

        $graphVersion = config('services.meta.graph_api_version', 'v21.0');

        $isFacebook = in_array($proxyAccount->provider, ['facebook_page', 'facebook']);
        $endpoint = $isFacebook
            ? "https://graph.facebook.com/{$graphVersion}/{$request->page_id}/conversations"
            : "https://graph.facebook.com/{$graphVersion}/me/conversations";

        try {
            $params = [
                'fields'       => 'participants,messages{message,from,created_time,attachments},updated_time',
                'access_token' => $proxyAccount->provider_token,
            ];
            if (!$isFacebook) {
                $params['platform'] = 'instagram';
            }

            $response = Http::timeout(15)->get($endpoint, $params);

            $this->logApiCall($platform, 'conversations', $request->page_id, $response);

            if ($response->successful()) {
                return response()->json(['success' => true, 'data' => $response->json()]);
            }

            return response()->json([
                'success' => false,
                'error'   => $response->json('error.message', 'Unknown error'),
            ], 422);
        } catch (\Exception $e) {
            $this->logApiCall($platform, 'conversations', $request->page_id, null, $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to fetch conversations'], 500);
        }
    }

    /**
     * Authenticate the external platform via API key + HMAC signature.
     */
    protected function authenticate(Request $request): ?ProxyPlatform
    {
        $apiKey    = $request->header('X-Api-Key');
        $signature = $request->header('X-Api-Signature');
        $timestamp = $request->header('X-Api-Timestamp');

        if (!$apiKey || !$signature || !$timestamp) {
            return null;
        }

        // Reject requests older than 5 minutes (replay protection)
        if (abs(time() - (int) $timestamp) > 300) {
            return null;
        }

        $platform = ProxyPlatform::where('api_key', $apiKey)
            ->where('is_active', true)
            ->first();

        if (!$platform) {
            return null;
        }

        // Verify HMAC: signature = HMAC-SHA256(timestamp.body, api_secret)
        $body           = $request->getContent();
        $expectedSig    = hash_hmac('sha256', $timestamp . '.' . $body, $platform->api_secret);

        if (!hash_equals($expectedSig, $signature)) {
            Log::warning('Proxy API: signature mismatch', ['platform' => $platform->name]);
            return null;
        }

        return $platform;
    }

    protected function logApiCall(ProxyPlatform $platform, string $action, string $providerId, $response = null, ?string $error = null): void
    {
        ProxyApiLog::create([
            'proxy_platform_id' => $platform->id,
            'action'            => $action,
            'provider_id'       => $providerId,
            'status'            => $error ? 'error' : ($response && $response->successful() ? 'success' : 'error'),
            'details'           => $error ?? ($response && !$response->successful() ? $response->body() : null),
        ]);
    }
}
