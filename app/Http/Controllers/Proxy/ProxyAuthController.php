<?php

namespace App\Http\Controllers\Proxy;

use App\Http\Controllers\Controller;
use App\Models\ProxyPlatform;
use App\Models\ProxySocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProxyAuthController extends Controller
{
    /**
     * External platform redirects users here to start OAuth.
     *
     * GET /proxy/auth/start?api_key=XXX&external_user_id=123&state=xyz&callback_url=...
     */
    public function start(Request $request)
    {
        $request->validate([
            'api_key'          => 'required|string',
            'external_user_id' => 'required|string',
            'state'            => 'nullable|string',
            'callback_url'     => 'nullable|url',
        ]);

        $platform = ProxyPlatform::where('api_key', $request->api_key)
            ->where('is_active', true)
            ->first();

        if (!$platform) {
            Log::warning('Proxy auth: invalid/inactive API key', ['api_key' => substr($request->api_key, 0, 8) . '...']);
            abort(403, 'Invalid or inactive platform.');
        }

        // Encode all context into Facebook's state parameter (fully stateless — no session)
        $statePayload = [
            'pid' => $platform->id,
            'uid' => $request->external_user_id,
            'st'  => $request->state ?? '',
            'cb'  => $request->callback_url ?? ($platform->oauth_callback_url ?: ''),
        ];
        $statePayload['sig'] = hash_hmac('sha256', json_encode([
            $statePayload['pid'],
            $statePayload['uid'],
            $statePayload['st'],
            $statePayload['cb'],
        ]), $platform->api_secret);

        $encodedState = base64_encode(json_encode($statePayload));

        // Build Facebook OAuth URL manually — no Socialite, no session
        $redirectUri = url('/proxy/oauth-return');
        $scopes = $this->getScopes();

        $queryParams = [
            'client_id'     => config('services.facebook.client_id'),
            'redirect_uri'  => $redirectUri,
            'scope'         => implode(',', $scopes),
            'state'         => $encodedState,
            'response_type' => 'code',
            // IMPORTANT: Do NOT set display, auth_type, or auth_nonce by default.
            // Setting auth_type=rerequest forces the "Edit Previous Settings" screen.
            // We only set it when the user explicitly clicks "Fix Permissions".
        ];

        // Only force rerequest if explicitly requested (e.g. "Fix Permissions" button)
        if ($request->boolean('force_rerequest') || $request->has('rerequest')) {
            $queryParams['auth_type'] = 'rerequest';
            Log::info('Proxy auth: rerequest mode enabled (user explicitly asked to fix permissions)');
        }

        $fbUrl = 'https://www.facebook.com/' . config('services.meta.graph_api_version', 'v21.0') . '/dialog/oauth?' . http_build_query($queryParams);

        Log::info('Proxy auth: redirecting to Facebook OAuth', [
            'has_rerequest' => isset($queryParams['auth_type']),
            'scopes_count'  => count($scopes),
        ]);

        return redirect()->away($fbUrl);
    }

    /**
     * NEW standalone Facebook OAuth return handler.
     * Facebook redirects here after user authorizes.
     * Fully stateless — no session, no Socialite, no middleware.
     *
     * GET /proxy/oauth-return?code=XXX&state=YYY
     */
    public function oauthReturn(Request $request)
    {
        Log::info('Proxy oauthReturn hit', ['query' => $request->query()]);

        // ── 1. Decode state ──────────────────────────────────────────
        $stateRaw = $request->input('state');
        if (!$stateRaw) {
            Log::warning('Proxy oauthReturn: no state parameter');
            return response('Invalid request. Please start the linking process again.', 400);
        }

        $stateData = json_decode(base64_decode($stateRaw), true);
        if (!$stateData || !isset($stateData['pid'], $stateData['uid'], $stateData['sig'])) {
            Log::warning('Proxy oauthReturn: invalid state payload');
            return response('Invalid state. Please start the linking process again.', 400);
        }

        $platform = ProxyPlatform::find($stateData['pid']);
        if (!$platform || !$platform->is_active) {
            return response('Platform not found or inactive.', 403);
        }

        // ── 2. Verify HMAC ───────────────────────────────────────────
        $expectedSig = hash_hmac('sha256', json_encode([
            $stateData['pid'],
            $stateData['uid'],
            $stateData['st'] ?? '',
            $stateData['cb'] ?? '',
        ]), $platform->api_secret);

        if (!hash_equals($expectedSig, $stateData['sig'])) {
            Log::warning('Proxy oauthReturn: HMAC mismatch');
            return response('Invalid signature.', 403);
        }

        $externalUserId = $stateData['uid'];
        $externalState  = $stateData['st'] ?? '';
        $callbackUrl    = $stateData['cb'] ?? '';

        // ── 3. Handle user cancellation ──────────────────────────────
        if ($request->has('error') || $request->has('denied')) {
            Log::info('Proxy oauthReturn: user cancelled');
            return $this->redirectToExternal($platform, [
                'status'           => 'cancelled',
                'external_user_id' => $externalUserId,
                'state'            => $externalState,
                '_callback_url'    => $callbackUrl,
            ]);
        }

        // ── 4. Exchange code for access token (manual HTTP, no Socialite) ─
        $code = $request->input('code');
        if (!$code) {
            Log::warning('Proxy oauthReturn: no code from Facebook');
            return $this->redirectToExternal($platform, [
                'status'           => 'error',
                'error'            => 'no_code',
                'external_user_id' => $externalUserId,
                'state'            => $externalState,
                '_callback_url'    => $callbackUrl,
            ]);
        }

        try {
            $graphVersion = config('services.meta.graph_api_version', 'v21.0');
            $redirectUri  = url('/proxy/oauth-return');

            $tokenResponse = Http::timeout(15)->get(
                "https://graph.facebook.com/{$graphVersion}/oauth/access_token",
                [
                    'client_id'     => config('services.facebook.client_id'),
                    'client_secret' => config('services.facebook.client_secret'),
                    'redirect_uri'  => $redirectUri,
                    'code'          => $code,
                ]
            );

            if (!$tokenResponse->successful()) {
                Log::error('Proxy oauthReturn: token exchange failed', [
                    'status' => $tokenResponse->status(),
                    'body'   => $tokenResponse->body(),
                ]);
                return $this->redirectToExternal($platform, [
                    'status'           => 'error',
                    'error'            => 'token_exchange_failed',
                    'external_user_id' => $externalUserId,
                    'state'            => $externalState,
                    '_callback_url'    => $callbackUrl,
                ]);
            }

            $accessToken = $tokenResponse->json('access_token');
            if (!$accessToken) {
                Log::error('Proxy oauthReturn: no access_token in response', ['body' => $tokenResponse->body()]);
                return $this->redirectToExternal($platform, [
                    'status'           => 'error',
                    'error'            => 'no_token',
                    'external_user_id' => $externalUserId,
                    'state'            => $externalState,
                    '_callback_url'    => $callbackUrl,
                ]);
            }

            // ── Exchange short-lived token → long-lived token (60 days) ──
            // This is CRITICAL: Page Access Tokens derived from a long-lived
            // user token are permanent (never expire), which eliminates
            // future re-authorization prompts entirely.
            $isLongLived = false;
            try {
                $longTokenResponse = Http::timeout(15)->get(
                    "https://graph.facebook.com/{$graphVersion}/oauth/access_token",
                    [
                        'grant_type'        => 'fb_exchange_token',
                        'client_id'         => config('services.facebook.client_id'),
                        'client_secret'     => config('services.facebook.client_secret'),
                        'fb_exchange_token' => $accessToken,
                    ]
                );

                if ($longTokenResponse->successful() && $longTokenResponse->json('access_token')) {
                    $accessToken = $longTokenResponse->json('access_token');
                    $isLongLived = true;
                    Log::info('Proxy oauthReturn: ✅ exchanged for long-lived token (60 days). Page tokens derived from this will be permanent.');
                } else {
                    Log::warning('Proxy oauthReturn: ⚠ long-lived token exchange failed, Page tokens will expire in ~2 hours', [
                        'status' => $longTokenResponse->status(),
                        'body'   => $longTokenResponse->body(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Proxy oauthReturn: long-lived token exchange exception: ' . $e->getMessage());
                // Proceed with the short-lived token as fallback
            }
        } catch (\Exception $e) {
            Log::error('Proxy oauthReturn: exception during token exchange', ['error' => $e->getMessage()]);
            return $this->redirectToExternal($platform, [
                'status'           => 'error',
                'error'            => 'oauth_failed',
                'external_user_id' => $externalUserId,
                'state'            => $externalState,
                '_callback_url'    => $callbackUrl,
            ]);
        }

        // ── 5. Fetch pages & save them ───────────────────────────────
        $result = $this->fetchAndSavePages($platform, $externalUserId, $accessToken);

        Log::info('Proxy oauthReturn: completed', [
            'platform'  => $platform->name,
            'ext_user'  => $externalUserId,
            'pages'     => $result['pages_count'],
            'instagram' => $result['instagram_count'],
        ]);

        // ── 6. Redirect back to the client platform (saraa.tech) ────
        return $this->redirectToExternal($platform, [
            'status'           => 'success',
            'external_user_id' => $externalUserId,
            'pages_count'      => $result['pages_count'],
            'instagram_count'  => $result['instagram_count'],
            'pages'            => json_encode($result['pages']),
            'instagram_pages'  => json_encode($result['instagram_pages'] ?? []),
            'state'            => $externalState,
            '_callback_url'    => $callbackUrl,
        ]);
    }

    /**
     * Legacy callback — kept for backward compatibility.
     * Redirects to the new oauthReturn handler.
     */
    public function callback(Request $request)
    {
        return $this->oauthReturn($request);
    }

    /**
     * Fetch Facebook pages and IG accounts, save them as proxy social accounts,
     * and subscribe each page to our webhook.
     */
    protected function fetchAndSavePages(ProxyPlatform $platform, string $externalUserId, string $accessToken): array
    {
        $pagesCount     = 0;
        $instagramCount = 0;
        $pages          = [];
        $instagramPages = [];
        $graphVersion   = config('services.meta.graph_api_version', 'v21.0');

        try {
            // Fetch granted permissions
            $permResponse = Http::timeout(15)->get("https://graph.facebook.com/{$graphVersion}/me/permissions", [
                'access_token' => $accessToken,
            ]);

            $granted = [];
            if ($permResponse->successful()) {
                foreach ($permResponse->json('data', []) as $p) {
                    if (($p['status'] ?? '') === 'granted') {
                        $granted[] = $p['permission'];
                    }
                }
            }

            // Fetch pages
            $pagesResponse = Http::timeout(15)->get("https://graph.facebook.com/{$graphVersion}/me/accounts", [
                'access_token' => $accessToken,
                'fields'       => 'id,name,access_token,picture,category,fan_count,instagram_business_account',
                'limit'        => 100,
            ]);

            if (!$pagesResponse->successful()) {
                Log::error('Proxy auth: /me/accounts failed', ['body' => $pagesResponse->body()]);
                return ['pages_count' => 0, 'instagram_count' => 0, 'pages' => []];
            }

            foreach ($pagesResponse->json('data', []) as $page) {
                $pageId          = $page['id'];
                $pageAccessToken = $page['access_token'];
                $pagePicture     = $page['picture']['data']['url'] ?? null;

                // Save page as proxy social account
                // NOTE: Page Access Tokens from /me/accounts with a long-lived user token
                // are permanent (never expire) — this is the key to eliminating re-auth.
                ProxySocialAccount::updateOrCreate(
                    [
                        'proxy_platform_id' => $platform->id,
                        'provider'          => 'facebook_page',
                        'provider_id'       => $pageId,
                    ],
                    [
                        'external_user_id'    => $externalUserId,
                        'provider_token'      => $pageAccessToken,
                        'name'                => $page['name'] ?? 'Facebook Page',
                        'avatar'              => $pagePicture,
                        'granted_permissions' => $granted,
                        'meta_data'           => [
                            'category'       => $page['category'] ?? null,
                            'fan_count'      => $page['fan_count'] ?? 0,
                            'token_type'     => 'page_permanent',
                            'linked_at'      => now()->toIso8601String(),
                        ],
                    ]
                );

                $pagesCount++;

                $pages[] = [
                    'id'     => $pageId,
                    'name'   => $page['name'] ?? null,
                    'avatar' => $pagePicture,
                ];

                // Subscribe page to our app's webhook
                $this->subscribePageToWebhook($pageId, $pageAccessToken);

                // Instagram business account
                // Fallback: if not in bulk /me/accounts response (Business Manager connections
                // are sometimes missing), query the page directly.
                $igId = $page['instagram_business_account']['id'] ?? null;

                if (!$igId) {
                    try {
                        $pageDetailResp = Http::timeout(10)->get(
                            "https://graph.facebook.com/{$graphVersion}/{$pageId}",
                            [
                                'access_token' => $pageAccessToken,
                                'fields'       => 'instagram_business_account',
                            ]
                        );
                        $igId = $pageDetailResp->json('instagram_business_account.id');
                        if ($igId) {
                            Log::info("Proxy: found Instagram via direct page query (Business Manager path)", [
                                'page_id' => $pageId,
                                'ig_id'   => $igId,
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Proxy: fallback IG fetch failed', ['page_id' => $pageId, 'error' => $e->getMessage()]);
                    }
                }

                if (isset($igId)) {

                    $igResponse = Http::timeout(10)->get("https://graph.facebook.com/{$graphVersion}/{$igId}", [
                        'access_token' => $pageAccessToken,
                        'fields'       => 'id,username,name,profile_picture_url',
                    ]);

                    if ($igResponse->successful()) {
                        $igData = $igResponse->json();

                        ProxySocialAccount::updateOrCreate(
                            [
                                'proxy_platform_id' => $platform->id,
                                'provider'          => 'instagram',
                                'provider_id'       => $igId,
                            ],
                            [
                                'external_user_id'    => $externalUserId,
                                'provider_token'      => $pageAccessToken,
                                'name'                => $igData['username'] ?? $igData['name'] ?? 'Instagram',
                                'avatar'              => $igData['profile_picture_url'] ?? null,
                                'granted_permissions' => $granted,
                                'meta_data'           => [
                                    'facebook_page_id' => $pageId,
                                    'username'          => $igData['username'] ?? null,
                                ],
                            ]
                        );

                        $instagramCount++;

                        $instagramPages[] = [
                            'id'       => $igId,
                            'username' => $igData['username'] ?? null,
                            'name'     => $igData['name'] ?? null,
                            'avatar'   => $igData['profile_picture_url'] ?? null,
                        ];
                    }
                }
            }

            // ─── WhatsApp Business Account discovery (if enabled) ───────────────
            $whatsappCount = 0;
            $whatsappAccounts = [];

            if (config('services.meta.enable_whatsapp', false)) {
                try {
                    $bizResp = Http::timeout(15)->get("https://graph.facebook.com/{$graphVersion}/me/businesses", [
                        'access_token' => $accessToken,
                        'fields'       => 'id,name',
                        'limit'        => 50,
                    ]);

                    if ($bizResp->successful()) {
                        foreach ($bizResp->json('data', []) as $business) {
                            $bizId = $business['id'];

                            $wabaResp = Http::timeout(15)->get("https://graph.facebook.com/{$graphVersion}/{$bizId}/owned_whatsapp_business_accounts", [
                                'access_token' => $accessToken,
                                'fields'       => 'id,name',
                                'limit'        => 50,
                            ]);

                            if (!$wabaResp->successful()) {
                                continue;
                            }

                            foreach ($wabaResp->json('data', []) as $waba) {
                                $wabaId = $waba['id'];

                                $phoneResp = Http::timeout(15)->get("https://graph.facebook.com/{$graphVersion}/{$wabaId}/phone_numbers", [
                                    'access_token' => $accessToken,
                                    'fields'       => 'id,display_phone_number,verified_name,quality_rating',
                                    'limit'        => 50,
                                ]);

                                if (!$phoneResp->successful()) {
                                    continue;
                                }

                                foreach ($phoneResp->json('data', []) as $phone) {
                                    $phoneNumberId = $phone['id'];

                                    ProxySocialAccount::updateOrCreate(
                                        [
                                            'proxy_platform_id' => $platform->id,
                                            'provider'          => 'whatsapp',
                                            'provider_id'       => $phoneNumberId,
                                        ],
                                        [
                                            'external_user_id'    => $externalUserId,
                                            'provider_token'      => $accessToken,
                                            'name'                => $phone['verified_name'] ?? $phone['display_phone_number'] ?? 'WhatsApp',
                                            'avatar'              => null,
                                            'granted_permissions' => $granted ?? [],
                                            'meta_data'           => [
                                                'phone_number_id' => $phoneNumberId,
                                                'display_phone'   => $phone['display_phone_number'] ?? null,
                                                'verified_name'   => $phone['verified_name'] ?? null,
                                                'quality_rating'  => $phone['quality_rating'] ?? null,
                                                'waba_id'         => $wabaId,
                                                'waba_name'       => $waba['name'] ?? null,
                                                'business_id'     => $bizId,
                                                'business_name'   => $business['name'] ?? null,
                                            ],
                                        ]
                                    );

                                    $whatsappCount++;

                                    $whatsappAccounts[] = [
                                        'id'    => $phoneNumberId,
                                        'phone' => $phone['display_phone_number'] ?? null,
                                        'name'  => $phone['verified_name'] ?? null,
                                    ];

                                    // Subscribe WABA to webhook
                                    $this->subscribeWhatsAppToWebhook($wabaId, $accessToken);
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Proxy: WhatsApp discovery failed', ['error' => $e->getMessage()]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Proxy auth: page fetch error', ['error' => $e->getMessage()]);
        }

        return [
            'pages_count'         => $pagesCount,
            'instagram_count'     => $instagramCount,
            'whatsapp_count'      => $whatsappCount ?? 0,
            'pages'               => $pages,
            'instagram_pages'     => $instagramPages,
            'whatsapp_accounts'   => $whatsappAccounts ?? [],
        ];
    }

    protected function subscribePageToWebhook(string $pageId, string $pageAccessToken): void
    {
        try {
            $graphVersion = config('services.meta.graph_api_version', 'v21.0');

            Http::timeout(15)->post("https://graph.facebook.com/{$graphVersion}/{$pageId}/subscribed_apps", [
                'access_token'     => $pageAccessToken,
                'subscribed_fields' => 'messages,messaging_postbacks,messaging_optins,messaging_referrals,message_reads,message_deliveries,feed,mention',
            ]);
        } catch (\Exception $e) {
            Log::warning('Proxy: webhook subscription failed', [
                'page_id' => $pageId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    protected function subscribeWhatsAppToWebhook(string $wabaId, string $accessToken): void
    {
        try {
            $graphVersion = config('services.meta.graph_api_version', 'v21.0');

            Http::timeout(15)->post("https://graph.facebook.com/{$graphVersion}/{$wabaId}/subscribed_apps", [
                'access_token' => $accessToken,
            ]);
        } catch (\Exception $e) {
            Log::warning('Proxy: WhatsApp webhook subscription failed', [
                'waba_id' => $wabaId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the complete list of OAuth scopes.
     *
     * IMPORTANT: ALL scopes must be requested in a SINGLE OAuth call.
     * Requesting scopes incrementally (adding new ones later) forces
     * Meta to show the "Edit Previous Settings" re-authorization screen.
     * By asking for everything upfront, users only see one clean prompt.
     */
    protected function getScopes(): array
    {
        $commentsEnabled = config('services.meta.enable_comments', false);
        $whatsappEnabled = config('services.meta.enable_whatsapp', false);

        return array_values(array_filter([
            // Core identity
            'email',
            'public_profile',
            // Facebook Pages (required for messaging)
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_metadata',
            'pages_messaging',
            'pages_read_user_content',
            // Instagram (always request — even if user has no IG, it's harmless)
            'instagram_basic',
            'instagram_manage_messages',
            // Comments (conditional)
            $commentsEnabled ? 'pages_manage_engagement'   : null,
            $commentsEnabled ? 'instagram_manage_comments' : null,
            // WhatsApp (conditional)
            $whatsappEnabled ? 'whatsapp_business_management' : null,
            $whatsappEnabled ? 'whatsapp_business_messaging'  : null,
        ]));
    }

    /**
     * Build a signed redirect URL back to the external platform.
     * ALWAYS redirects to the client — never to proxy admin.
     */
    protected function redirectToExternal(ProxyPlatform $platform, array $params)
    {
        // _callback_url is an internal routing hint — extract and remove before signing
        $callbackUrl = $params['_callback_url'] ?? null;
        unset($params['_callback_url']);

        // Fall back to the platform's stored oauth_callback_url
        if (empty($callbackUrl)) {
            $callbackUrl = $platform->oauth_callback_url ?: null;
        }

        // Fall back to building URL from the platform's domain
        if (empty($callbackUrl) && !empty($platform->domain)) {
            $domain = rtrim($platform->domain, '/');
            // Add https:// if no scheme
            if (!str_starts_with($domain, 'http://') && !str_starts_with($domain, 'https://')) {
                $domain = 'https://' . $domain;
            }
            $callbackUrl = $domain . '/auth/proxy/callback';
            Log::info('Proxy: built callback URL from platform domain', ['url' => $callbackUrl]);
        }

        if (empty($callbackUrl)) {
            Log::error('Proxy: no callback URL available for platform', ['platform' => $platform->name, 'id' => $platform->id]);
            return response('OAuth completed but no redirect URL configured for this platform. Contact the platform administrator.', 500);
        }

        // Sign the response so the external platform can verify it came from us
        $params['timestamp'] = time();
        ksort($params);
        $params['signature'] = hash_hmac('sha256', http_build_query($params), $platform->api_secret);

        Log::info('Proxy: redirecting to external platform', ['url' => $callbackUrl, 'status' => $params['status'] ?? 'unknown']);

        return redirect()->away($callbackUrl . '?' . http_build_query($params));
    }
}
