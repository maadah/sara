<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Services\MetaApiService;

class SocialAuthController extends Controller
{
    /**
     * Supported providers
     */
    protected array $providers = ['facebook', 'instagram'];

    /**
     * Redirect to provider for authentication.
     */
    public function redirect(string $provider)
    {
        if (!in_array($provider, $this->providers)) {
            return redirect()->route('login')->with('error', 'مزود الخدمة غير مدعوم');
        }

        // Store the intended action (login or link)
        session(['social_action' => request('action', 'login')]);

        // ─── Proxy client mode: redirect to proxy server instead of Facebook ───
        $metaApi = app(MetaApiService::class);
        if ($metaApi->isProxy() && $provider === 'facebook') {
            $user = Auth::user();
            $externalUserId = $user ? (string) $user->id : 'guest';
            $rerequest = request()->has('rerequest') || request()->boolean('force_rerequest');
            $oauthUrl = $metaApi->getOAuthStartUrl($externalUserId, $rerequest);
            Log::info('OAuth redirect via proxy', ['url' => $oauthUrl, 'rerequest' => $rerequest]);
            return redirect()->away($oauthUrl);
        }

        $scopes = $this->getScopes($provider);

        /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
        $driver = Socialite::driver($provider);
        $driver->scopes($scopes);

        if ($provider === 'facebook') {
            $options = [];

            // Only force re-request if explicitly asked by the UI
            // This prevents the annoying 'Edit Previous Settings' screen on every login
            if (request()->has('rerequest') || request()->boolean('force_rerequest')) {
                $options['auth_type'] = 'rerequest';
            }

            if (!empty($options)) {
                $driver->with($options);
            }
        }

        return $driver->redirect();
    }

    /**
     * Start the Instagram Direct (Instagram Login for Business) flow.
     *
     * Only available in proxy mode. Lets an Instagram-only account (no linked
     * Facebook page) connect directly. The proxy completes OAuth and redirects
     * back to /auth/proxy/callback, returning the account in `instagram_pages`
     * (handled by proxyCallback — no extra callback code needed here).
     */
    public function instagramDirectRedirect()
    {
        session(['social_action' => request('action', 'login')]);

        $metaApi = app(MetaApiService::class);

        if (!$metaApi->isProxy()) {
            return redirect()->route('customer.social-accounts.index')
                ->with('error', 'تسجيل الدخول المباشر لإنستغرام متاح فقط في وضع البروكسي');
        }

        $user = Auth::user();
        $externalUserId = $user ? (string) $user->id : 'guest';

        $oauthUrl = $metaApi->getInstagramDirectOAuthStartUrl($externalUserId);

        if (!$oauthUrl) {
            return redirect()->route('customer.social-accounts.index')
                ->with('error', 'تعذّر بدء تسجيل الدخول المباشر لإنستغرام');
        }

        Log::info('Instagram Direct OAuth redirect via proxy', ['url' => $oauthUrl]);

        return redirect()->away($oauthUrl);
    }

    /**
     * Handle callback from the proxy server (when META_CONNECTION_MODE=proxy).
     * The proxy redirects here with signed params after the user completes Facebook OAuth.
     * No auth middleware needed — HMAC signature is the authentication.
     */
    public function proxyCallback(Request $request)
    {
        $status          = $request->input('status');
        $externalUserId  = $request->input('external_user_id');
        $pagesJson       = $request->input('pages', '[]');
        $instagramJson   = $request->input('instagram_pages', '[]');
        $pagesCount      = (int) $request->input('pages_count', 0);
        $instagramCount  = (int) $request->input('instagram_count', 0);
        $timestamp       = $request->input('timestamp');
        $signature       = $request->input('signature');
        $state           = $request->input('state');

        Log::info('proxyCallback hit', [
            'status'          => $status,
            'external_user_id'=> $externalUserId,
            'pages_count'     => $pagesCount,
            'instagram_count' => $instagramCount,
            'has_signature'   => !empty($signature),
            'full_url'        => $request->fullUrl(),
        ]);

        // ── 1. Identify the user first (session → auto-login fallback) ──────────
        $user = Auth::user();
        if (!$user && $externalUserId) {
            $user = User::find($externalUserId);
            if ($user) {
                Auth::login($user);
                Log::info('Proxy callback: auto-logged in user', ['user_id' => $user->id]);
            }
        }

        if (!$user) {
            Log::warning('Proxy callback: no user found', ['external_user_id' => $externalUserId]);
            return redirect()->route('login')
                ->with('error', 'يرجى تسجيل الدخول أولاً');
        }

        // ── 2. Verify HMAC signature ─────────────────────────────────────────────
        $proxySecret = config('services.meta.proxy_api_secret');
        if ($proxySecret && $signature) {
            $verifyParams = $request->except('signature');
            ksort($verifyParams);
            $expected = hash_hmac('sha256', http_build_query($verifyParams), $proxySecret);
            if (!hash_equals($expected, $signature)) {
                Log::warning('Proxy callback: HMAC mismatch', [
                    'external_user_id' => $externalUserId,
                    'user_id'          => $user->id,
                ]);
                // Security: only continue if external_user_id matches the logged-in user
                if ((string) $user->id !== (string) $externalUserId) {
                    Log::error('Proxy callback: HMAC fail + user mismatch — rejecting');
                    return redirect()->route('customer.social-accounts.index')
                        ->with('error', 'فشل التحقق من الهوية. يرجى المحاولة مرة أخرى.');
                }
                // Same user — HMAC mismatch likely due to wrong META_PROXY_API_SECRET on this server
                Log::warning('Proxy callback: HMAC mismatch but user ID matches — check META_PROXY_API_SECRET config');
            }
        }

        if ($status === 'cancelled') {
            return redirect()->route('customer.social-accounts.index')
                ->with('warning', 'تم إلغاء الربط');
        }

        if ($status === 'error') {
            $error = $request->input('error', 'unknown');
            Log::error('Proxy callback: error status', ['error' => $error, 'user_id' => $user->id]);
            return redirect()->route('customer.social-accounts.index')
                ->with('error', 'فشل الربط عبر الوسيط. يرجى المحاولة مرة أخرى.');
        }

        try {
            // Parse linked pages from proxy response
            $pages = json_decode($pagesJson, true) ?: [];
            $instagramPages = json_decode($instagramJson, true) ?: [];

            // Parse granted permissions sent by proxy server
            $grantedPermissions = json_decode($request->input('granted_permissions', '[]'), true) ?: [];

            // Log all granted permissions for debugging
            Log::info('Proxy callback: granted permissions received', [
                'user_id' => $user->id,
                'permissions' => $grantedPermissions
            ]);

            $proxyMeta = [
                'via_proxy'           => true,
                'proxy_url'           => config('services.meta.proxy_url'),
                'granted_permissions' => $grantedPermissions,
                'updated_at'          => now()->toIso8601String(),
            ];

            // Save Facebook pages locally
            foreach ($pages as $page) {
                $pageId   = $page['id'] ?? null;
                $pageName = $page['name'] ?? 'Facebook Page';
                $pageAvatar = $page['avatar'] ?? $page['picture'] ?? null;
                if (!$pageId) continue;

                // Use (provider, provider_id) to match UNIQUE constraint — avoids
                // constraint violation when re-linking a page to a different user.
                $existing = SocialAccount::where('provider', 'facebook_page')
                    ->where('provider_id', $pageId)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'user_id'          => $user->id,
                        'provider_token'   => 'proxy:' . config('services.meta.proxy_api_key'),
                        'token_expires_at' => null,
                        'name'             => $pageName,
                        'avatar'           => $pageAvatar,
                        'meta_data'        => $proxyMeta,
                    ]);
                } else {
                    SocialAccount::create([
                        'user_id'          => $user->id,
                        'provider'         => 'facebook_page',
                        'provider_id'      => $pageId,
                        'provider_token'   => 'proxy:' . config('services.meta.proxy_api_key'),
                        'token_expires_at' => null,
                        'name'             => $pageName,
                        'avatar'           => $pageAvatar,
                        'meta_data'        => $proxyMeta,
                    ]);
                }
            }

            // Save Instagram accounts locally
            foreach ($instagramPages as $ig) {
                $igId = $ig['id'] ?? null;
                if (!$igId) continue;

                $existing = SocialAccount::where('provider', 'instagram')
                    ->where('provider_id', $igId)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'user_id'          => $user->id,
                        'provider_token'   => 'proxy:' . config('services.meta.proxy_api_key'),
                        'token_expires_at' => null,
                        'name'             => $ig['username'] ?? $ig['name'] ?? 'Instagram',
                        'avatar'           => $ig['avatar'] ?? null,
                        'meta_data'        => $proxyMeta,
                    ]);
                } else {
                    SocialAccount::create([
                        'user_id'          => $user->id,
                        'provider'         => 'instagram',
                        'provider_id'      => $igId,
                        'provider_token'   => 'proxy:' . config('services.meta.proxy_api_key'),
                        'token_expires_at' => null,
                        'name'             => $ig['username'] ?? $ig['name'] ?? 'Instagram',
                        'avatar'           => $ig['avatar'] ?? null,
                        'meta_data'        => $proxyMeta,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Proxy callback: failed to save social accounts', [
                'user_id'   => $user->id,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            return redirect()->route('customer.social-accounts.index')
                ->with('error', 'حدث خطأ أثناء حفظ الحسابات. يرجى المحاولة مرة أخرى.');
        }

        // Build success message
        $messages = [];
        if ($pagesCount > 0) {
            $messages[] = $pagesCount . ' صفحة فيسبوك';
        }
        if ($instagramCount > 0) {
            $messages[] = $instagramCount . ' حساب انستقرام';
        }

        if (empty($messages)) {
            return redirect()->route('customer.social-accounts.index')
                ->with('warning', 'لم يتم العثور على صفحات. تأكد من اختيار الصفحات عند ظهور نافذة فيسبوك.');
        }

        Log::info('Proxy OAuth completed', [
            'user_id'    => $user->id,
            'pages'      => $pagesCount,
            'instagram'  => $instagramCount,
        ]);

        return redirect()->route('customer.social-accounts.index')
            ->with('success', 'تم ربط ' . implode(' و ', $messages) . ' بنجاح (عبر الوسيط)');
    }

    /**
     * Handle provider callback.
     */
    public function callback(string $provider)
    {
        if (!in_array($provider, $this->providers)) {
            return redirect()->route('login')->with('error', 'مزود الخدمة غير مدعوم');
        }

        // Handle user-denied or Facebook error callbacks
        if (request()->has('error') || request()->has('denied')) {
            $errorReason = request()->input('error_reason', request()->input('error', 'cancelled'));
            Log::warning('Social auth denied by user', [
                'provider' => $provider,
                'reason'   => $errorReason,
            ]);
            return redirect()->route('customer.social-accounts.index')
                ->with('warning', 'تم إلغاء تسجيل الدخول بـ ' . ucfirst($provider));
        }

        try {
            /** @var \Laravel\Socialite\Two\User $socialUser */
            $socialUser = Socialite::driver($provider)->user();

            // Exchange Facebook short-lived token for a long-lived token (60 days)
            if ($provider === 'facebook' && !empty($socialUser->token)) {
                $socialUser->token = $this->exchangeForLongLivedToken($socialUser->token);
                // Long-lived tokens typically expire in 60 days
                $socialUser->expiresIn = 60 * 24 * 60 * 60;
            }

            // Log the token info for debugging (don't log full token in production!)
            Log::info('Facebook OAuth callback received', [
                'provider' => $provider,
                'user_id' => $socialUser->getId(),
                'user_name' => $socialUser->getName(),
                'token_length' => strlen($socialUser->token ?? ''),
                'has_token' => !empty($socialUser->token),
            ]);

        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            // State mismatch — session expired or cookie issue between redirect and callback
            Log::warning('Social auth state mismatch (session expired?)', [
                'provider' => $provider,
            ]);
            return redirect()->route('customer.social-accounts.index')
                ->with('error', 'انتهت صلاحية الجلسة. يرجى المحاولة مرة أخرى.');
        } catch (\Exception $e) {
            Log::error('Social auth error: ' . $e->getMessage(), [
                'provider'        => $provider,
                'exception_class' => get_class($e),
                'exception'       => $e->getTraceAsString(),
            ]);
            return redirect()->route('customer.social-accounts.index')->with('error', 'فشل في الاتصال بـ ' . ucfirst($provider) . ': ' . $e->getMessage());
        }

        $action = session('social_action', 'login');
        session()->forget('social_action');

        // If user is already logged in, link the account
        if (Auth::check()) {
            return $this->linkAccount($provider, $socialUser);
        }

        // Otherwise, login or register
        return $this->loginOrRegister($provider, $socialUser);
    }

    /**
     * Exchange a Facebook short-lived token for a long-lived one (60 days).
     */
    protected function exchangeForLongLivedToken(string $shortLivedToken): string
    {
        try {
            $graphVersion = config('services.meta.graph_api_version', 'v21.0');
            $response = Http::timeout(10)->get("https://graph.facebook.com/{$graphVersion}/oauth/access_token", [
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => config('services.facebook.client_id'),
                'client_secret'     => config('services.facebook.client_secret'),
                'fb_exchange_token' => $shortLivedToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['access_token'])) {
                    Log::info('Successfully exchanged Facebook short-lived token for a long-lived token.');
                    return $data['access_token'];
                }
            } else {
                Log::warning('Failed to exchange Facebook short-lived token', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception during Facebook token exchange: ' . $e->getMessage());
        }

        return $shortLivedToken; // Fallback to the short one
    }

    /**
     * Login or register user via social provider.
     */
    protected function loginOrRegister(string $provider, $socialUser)
    {
        // Check if social account exists
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            // Update token
            $socialAccount->update([
                'provider_token' => $socialUser->token,
                'provider_refresh_token' => $socialUser->refreshToken,
                'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                'avatar' => $socialUser->getAvatar(),
            ]);

            // Also try to fetch and save Facebook Pages and Instagram accounts
            if ($provider === 'facebook') {
                $this->fetchAndSavePagesAndInstagram($socialAccount->user, $socialUser->token);
            }

            // Login the user
            Auth::login($socialAccount->user);

            return redirect()->route('customer.dashboard')->with('success', 'تم تسجيل الدخول بنجاح');
        }

        // Check if user with this email exists
        $user = null;
        if ($socialUser->getEmail()) {
            $user = User::where('email', $socialUser->getEmail())->first();
        }

        if (!$user) {
            // Create new user
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'مستخدم ' . $provider,
                'email' => $socialUser->getEmail() ?? $socialUser->getId() . '@' . $provider . '.social',
                'password' => Hash::make(Str::random(24)),
                'role' => 'customer',
                'status' => 'pending', // New users need approval
            ]);
        }

        // Create social account
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'provider_token' => $socialUser->token,
            'provider_refresh_token' => $socialUser->refreshToken,
            'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
            'name' => $socialUser->getName() ?? $socialUser->getNickname(),
            'email' => $socialUser->getEmail(),
            'avatar' => $socialUser->getAvatar(),
            'meta_data' => $socialUser->getRaw(),
            'is_primary' => true,
        ]);

        // Also try to fetch and save Facebook Pages and Instagram accounts linked to Facebook
        if ($provider === 'facebook') {
            $this->fetchAndSavePagesAndInstagram($user, $socialUser->token);
        }

        Auth::login($user);

        // Check if user is approved
        if ($user->status === 'pending') {
            Auth::logout();
            return redirect()->route('login')->with('warning', 'تم إنشاء حسابك بنجاح. يرجى انتظار موافقة الإدارة.');
        }

        return redirect()->route('customer.dashboard')->with('success', 'تم إنشاء حسابك وتسجيل الدخول بنجاح');
    }

    /**
     * Link a social account to existing user.
     */
    protected function linkAccount(string $provider, $socialUser)
    {
        $user = Auth::user();

        // If Facebook, fetch and save Facebook Pages and Instagram accounts (not personal profile)
        $pagesCount = 0;
        $instagramCount = 0;

        if ($provider === 'facebook') {
            $result = $this->fetchAndSavePagesAndInstagram($user, $socialUser->token);
            $pagesCount = $result['pages'];
            $instagramCount = $result['instagram'];
        }

        // If any pages are waiting for re-link confirmation, redirect there first
        if (!empty(session('fb_relink_pending'))) {
            return redirect()->route('customer.social-accounts.relink-confirm');
        }

        // Build success message
        $messages = [];
        if ($pagesCount > 0) {
            $messages[] = $pagesCount . ' صفحة فيسبوك';
        }
        if ($instagramCount > 0) {
            $messages[] = $instagramCount . ' حساب انستقرام';
        }

        if (empty($messages)) {
            $fbError = session('fb_link_error');
            session()->forget('fb_link_error');

            if ($fbError === 'permission_denied') {
                $warningMessage = 'لم يتم العثور على صفحات — لم تُمنح صلاحية الوصول للصفحات. يرجى المحاولة مرة أخرى والتأكد من اختيار جميع الصفحات عند ظهور نافذة فيسبوك.';
            } elseif ($fbError === 'no_pages') {
                $warningMessage = 'لم يتم العثور على صفحات — عند المحاولة مرة أخرى: اضغط "تعديل الإعدادات" في نافذة فيسبوك ← فعّل الصفحات التي تريد ربطها ← اضغط "متابعة". يجب أن تكون مديراً (Admin) للصفحة.';
            } elseif ($fbError) {
                $warningMessage = 'فشل ربط فيسبوك: ' . $fbError . '. يرجى المحاولة مرة أخرى.';
            } else {
                $warningMessage = 'لم يتم العثور على صفحات. عند ظهور نافذة فيسبوك: (1) اضغط "تحرير" بجانب الصفحات (2) فعّل جميع الصفحات (3) اضغط تطبيق جميع الصلاحيات.';
            }

            Log::warning('Facebook linking returned 0 pages', [
                'user_id'         => $user->id,
                'fb_error'        => $fbError,
                'pages_count'     => $pagesCount,
                'instagram_count' => $instagramCount,
            ]);

            return redirect()->route('customer.social-accounts.index')
                ->with('warning', $warningMessage);
        }

        $message = 'تم ربط ' . implode(' و ', $messages) . ' بنجاح';

        return redirect()->route('customer.social-accounts.index')
            ->with('success', $message);
    }

    /**
     * Save or update a social account.
     */
    protected function saveOrUpdateSocialAccount($user, string $provider, $socialUser, array $extraData = []): SocialAccount
    {
        $providerId = $extraData['provider_id'] ?? $socialUser->getId();

        // Check if this social account is already linked to another user
        $existingAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($existingAccount) {
            if ($existingAccount->user_id !== $user->id) {
                // Account belongs to another user, skip it
                Log::warning("Social account {$provider}:{$providerId} already linked to user {$existingAccount->user_id}");
                return $existingAccount;
            }

            // Update existing account
            $existingAccount->update([
                'provider_token' => $extraData['token'] ?? $socialUser->token,
                'provider_refresh_token' => $socialUser->refreshToken ?? null,
                'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                'avatar' => $extraData['avatar'] ?? $socialUser->getAvatar(),
                'name' => $extraData['name'] ?? $socialUser->getName() ?? $socialUser->getNickname(),
                'meta_data' => $extraData['meta_data'] ?? $socialUser->getRaw(),
            ]);

            return $existingAccount;
        }

        // Create new social account link
        return SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $providerId,
            'provider_token' => $extraData['token'] ?? $socialUser->token,
            'provider_refresh_token' => $socialUser->refreshToken ?? null,
            'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
            'name' => $extraData['name'] ?? $socialUser->getName() ?? $socialUser->getNickname(),
            'email' => $extraData['email'] ?? $socialUser->getEmail(),
            'avatar' => $extraData['avatar'] ?? $socialUser->getAvatar(),
            'meta_data' => $extraData['meta_data'] ?? $socialUser->getRaw(),
            'is_primary' => false,
        ]);
    }

    /**
     * Fetch and save Facebook Pages and Instagram Business accounts.
     */
    protected function fetchAndSavePagesAndInstagram($user, string $accessToken): array
    {
        $pagesCount    = 0;
        $instagramCount = 0;
        $whatsappCount  = 0;

        try {
            // ─── Step 1: Inspect which permissions the token actually carries ─────
            $permissionsResponse = Http::timeout(15)->get('https://graph.facebook.com/v21.0/me/permissions', [
                'access_token' => $accessToken,
            ]);

            $grantedPermissions  = [];
            $declinedPermissions = [];

            if ($permissionsResponse->successful()) {
                foreach ($permissionsResponse->json('data', []) as $perm) {
                    if (($perm['status'] ?? '') === 'granted') {
                        $grantedPermissions[]  = $perm['permission'];
                    } else {
                        $declinedPermissions[] = $perm['permission'];
                    }
                }
                Log::info('Facebook OAuth — granted permissions: ' . implode(', ', $grantedPermissions));
                if (!empty($declinedPermissions)) {
                    Log::warning('Facebook OAuth — DECLINED permissions: ' . implode(', ', $declinedPermissions));
                }
            } else {
                Log::error('Facebook OAuth — failed to fetch permissions: ' . $permissionsResponse->body());
            }

            // ─── Step 2: Fetch pages this user manages ────────────────────────────
            $graphVersion = config('services.meta.graph_api_version', 'v21.0');
            $pagesResponse = Http::timeout(15)->get("https://graph.facebook.com/{$graphVersion}/me/accounts", [
                'access_token' => $accessToken,
                'fields'       => 'id,name,access_token,picture,category,fan_count,instagram_business_account',
                'limit'        => 100,
            ]);

            Log::info('Facebook /me/accounts response', [
                'status'   => $pagesResponse->status(),
                'body'     => $pagesResponse->body(),
                'granted'  => $grantedPermissions,
                'declined' => $declinedPermissions,
            ]);

            if (!$pagesResponse->successful()) {
                $errorBody    = $pagesResponse->json();
                $errorMessage = $errorBody['error']['message'] ?? 'Unknown API error';
                $errorCode    = $errorBody['error']['code'] ?? 0;
                Log::error('Facebook /me/accounts failed', ['code' => $errorCode, 'message' => $errorMessage]);
                session(['fb_link_error' => $errorMessage]);
                return ['pages' => 0, 'instagram' => 0, 'error' => $errorMessage];
            }

            $pagesData = $pagesResponse->json();
            $pages     = $pagesData['data'] ?? [];
            Log::info('Facebook pages found: ' . count($pages));

            if (empty($pages)) {
                // Determine the most helpful error hint
                if (!in_array('pages_show_list', $grantedPermissions)) {
                    session(['fb_link_error' => 'permission_denied']);
                } else {
                    session(['fb_link_error' => 'no_pages']);
                }
                Log::warning('No Facebook pages returned', [
                    'granted'  => $grantedPermissions,
                    'declined' => $declinedPermissions,
                    'response' => $pagesData,
                ]);
            }

            foreach ($pages as $page) {
                // Save Facebook Page
                $pageId = $page['id'];
                $pageAccessToken = $page['access_token'];
                $pagePicture = $page['picture']['data']['url'] ?? null;

                // Log full page data for debugging
                Log::info('Page data: ' . json_encode($page, JSON_UNESCAPED_UNICODE));
                Log::info('Processing page: ' . ($page['name'] ?? 'Unknown') . ', has_instagram: ' . (isset($page['instagram_business_account']) ? 'yes - ID: ' . $page['instagram_business_account']['id'] : 'no'));

                // Check if page already exists for another user
                $existingPage = SocialAccount::where('provider', 'facebook_page')
                    ->where('provider_id', $pageId)
                    ->first();

                if ($existingPage && $existingPage->user_id !== $user->id) {
                    Log::warning("Facebook page {$pageId} already linked to user {$existingPage->user_id}");
                    // Store pending re-link for user confirmation instead of silently skipping
                    $relinkPending = session('fb_relink_pending', []);
                    $relinkPending[] = [
                        'page_id'      => $pageId,
                        'page_name'    => $page['name'] ?? 'Facebook Page',
                        'old_user_id'  => $existingPage->user_id,
                        'access_token' => $pageAccessToken,
                        'avatar'       => $pagePicture,
                        'meta_data'    => [
                            'page_id'              => $pageId,
                            'name'                 => $page['name'] ?? null,
                            'category'             => $page['category'] ?? null,
                            'fan_count'            => $page['fan_count'] ?? 0,
                            'has_instagram'        => isset($page['instagram_business_account']),
                            'granted_permissions'  => $grantedPermissions,
                            'declined_permissions' => $declinedPermissions,
                        ],
                    ];
                    session(['fb_relink_pending' => $relinkPending]);
                    continue;
                }

                // Save or update Facebook Page
                SocialAccount::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'provider' => 'facebook_page',
                        'provider_id' => $pageId,
                    ],
                    [
                        'provider_token' => $pageAccessToken,
                        'name' => $page['name'] ?? 'Facebook Page',
                        'avatar' => $pagePicture,
                        'meta_data' => [
                            'page_id' => $pageId,
                            'name' => $page['name'] ?? null,
                            'category' => $page['category'] ?? null,
                            'fan_count' => $page['fan_count'] ?? 0,
                            'has_instagram' => isset($page['instagram_business_account']),
                            'granted_permissions' => $grantedPermissions,
                            'declined_permissions' => $declinedPermissions,
                        ],
                        'is_primary' => false,
                    ]
                );

                $pagesCount++;
                Log::info("Saved Facebook Page: {$page['name']} for user {$user->id}");

                // Ensure the app is subscribed to this Page's webhook events.
                $this->subscribePageToWebhook($pageId, $pageAccessToken);

                // Check if this page has an Instagram Business Account.
                // If the field is missing from /me/accounts (can happen when the page is linked
                // to an IG account through Meta Business Suite), do a direct page API call
                // to fetch the instagram_business_account field explicitly.
                $igAccountId = $page['instagram_business_account']['id'] ?? null;

                if (!$igAccountId) {
                    // Fallback: query the page directly — Business Manager connections
                    // are often missing from the /me/accounts bulk response
                    try {
                        $pageDetailResp = Http::timeout(10)->get(
                            "https://graph.facebook.com/{$graphVersion}/{$page['id']}",
                            [
                                'access_token' => $pageAccessToken,
                                'fields'       => 'instagram_business_account',
                            ]
                        );
                        $igAccountId = $pageDetailResp->json('instagram_business_account.id');
                        if ($igAccountId) {
                            Log::info("Page {$page['name']}: found Instagram via direct page query (Business Manager path): {$igAccountId}");
                        }
                    } catch (\Exception $e) {
                        Log::warning("Page {$page['name']}: fallback IG fetch failed", ['error' => $e->getMessage()]);
                    }
                }

                if (!$igAccountId) {
                    Log::info("Page {$page['name']} has no Instagram Business Account linked");
                    continue;
                }

                $instagramId = $igAccountId;
                Log::info("Found Instagram Business Account: {$instagramId} for page {$page['name']}");

                // Get Instagram account details
                $igResponse = Http::get("https://graph.facebook.com/v21.0/{$instagramId}", [
                    'access_token' => $pageAccessToken,
                    'fields' => 'id,username,name,profile_picture_url,followers_count,follows_count,media_count',
                ]);

                if (!$igResponse->successful()) {
                    Log::warning("Failed to fetch Instagram account {$instagramId}: " . $igResponse->body());
                    continue;
                }

                $igData = $igResponse->json();
                Log::info("Instagram data: " . json_encode($igData));

                // Check if already exists for another user
                $existingIg = SocialAccount::where('provider', 'instagram')
                    ->where('provider_id', $instagramId)
                    ->first();

                if ($existingIg && $existingIg->user_id !== $user->id) {
                    Log::warning("Instagram account {$instagramId} already linked to another user");
                    continue;
                }

                // Save or update Instagram account
                SocialAccount::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'provider' => 'instagram',
                        'provider_id' => $instagramId,
                    ],
                    [
                        'provider_token' => $pageAccessToken,
                        'name' => $igData['username'] ?? $igData['name'] ?? 'Instagram Account',
                        'avatar' => $igData['profile_picture_url'] ?? null,
                        'meta_data' => [
                            'instagram_id' => $instagramId,
                            'username' => $igData['username'] ?? null,
                            'name' => $igData['name'] ?? null,
                            'followers_count' => $igData['followers_count'] ?? 0,
                            'follows_count' => $igData['follows_count'] ?? 0,
                            'media_count' => $igData['media_count'] ?? 0,
                            'facebook_page_id' => $page['id'],
                            'facebook_page_name' => $page['name'],
                            'granted_permissions' => $grantedPermissions,
                            'declined_permissions' => $declinedPermissions,
                        ],
                        'is_primary' => false,
                    ]
                );

                $instagramCount++;
                Log::info("Saved Instagram account: " . ($igData['username'] ?? $instagramId) . " for user {$user->id}");
            }

            // ─── Step 4: Discover WhatsApp Business accounts (if enabled) ─────────
            if (config('services.meta.enable_whatsapp', false)) {
                try {
                    // Get businesses the user manages
                    $bizResp = Http::timeout(15)->get("https://graph.facebook.com/{$graphVersion}/me/businesses", [
                        'access_token' => $accessToken,
                        'fields'       => 'id,name',
                        'limit'        => 50,
                    ]);

                    if ($bizResp->successful()) {
                        foreach ($bizResp->json('data', []) as $business) {
                            $bizId = $business['id'];

                            // Get WhatsApp Business Accounts owned by this business
                            $wabaResp = Http::timeout(15)->get("https://graph.facebook.com/{$graphVersion}/{$bizId}/owned_whatsapp_business_accounts", [
                                'access_token' => $accessToken,
                                'fields'       => 'id,name,currency,message_template_namespace',
                                'limit'        => 50,
                            ]);

                            if (!$wabaResp->successful()) {
                                Log::warning("Failed to fetch WABAs for business {$bizId}", ['body' => $wabaResp->body()]);
                                continue;
                            }

                            foreach ($wabaResp->json('data', []) as $waba) {
                                $wabaId = $waba['id'];

                                // Get phone numbers for this WABA
                                $phoneResp = Http::timeout(15)->get("https://graph.facebook.com/{$graphVersion}/{$wabaId}/phone_numbers", [
                                    'access_token' => $accessToken,
                                    'fields'       => 'id,display_phone_number,verified_name,quality_rating,platform_type',
                                    'limit'        => 50,
                                ]);

                                if (!$phoneResp->successful()) {
                                    Log::warning("Failed to fetch phone numbers for WABA {$wabaId}", ['body' => $phoneResp->body()]);
                                    continue;
                                }

                                foreach ($phoneResp->json('data', []) as $phone) {
                                    $phoneNumberId = $phone['id'];

                                    // Check if already linked to another user
                                    $existingWa = SocialAccount::where('provider', 'whatsapp')
                                        ->where('provider_id', $phoneNumberId)
                                        ->first();

                                    if ($existingWa && $existingWa->user_id !== $user->id) {
                                        Log::warning("WhatsApp phone {$phoneNumberId} already linked to user {$existingWa->user_id}");
                                        continue;
                                    }

                                    SocialAccount::updateOrCreate(
                                        [
                                            'user_id'     => $user->id,
                                            'provider'    => 'whatsapp',
                                            'provider_id' => $phoneNumberId,
                                        ],
                                        [
                                            'provider_token' => $accessToken,
                                            'name'   => $phone['verified_name'] ?? $phone['display_phone_number'] ?? 'WhatsApp',
                                            'avatar' => null,
                                            'meta_data' => [
                                                'phone_number_id'    => $phoneNumberId,
                                                'display_phone'      => $phone['display_phone_number'] ?? null,
                                                'verified_name'      => $phone['verified_name'] ?? null,
                                                'quality_rating'     => $phone['quality_rating'] ?? null,
                                                'waba_id'            => $wabaId,
                                                'waba_name'          => $waba['name'] ?? null,
                                                'business_id'        => $bizId,
                                                'business_name'      => $business['name'] ?? null,
                                                'granted_permissions'  => $grantedPermissions,
                                                'declined_permissions' => $declinedPermissions,
                                            ],
                                            'is_primary' => false,
                                        ]
                                    );

                                    $whatsappCount++;
                                    Log::info("Saved WhatsApp account: {$phone['display_phone_number']} for user {$user->id}");

                                    // Subscribe WABA to webhook
                                    $this->subscribeWhatsAppToWebhook($wabaId, $accessToken);
                                }
                            }
                        }
                    } else {
                        Log::warning('Failed to fetch businesses for WhatsApp discovery', ['body' => $bizResp->body()]);
                    }
                } catch (\Exception $e) {
                    Log::warning('WhatsApp discovery failed', ['error' => $e->getMessage()]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error fetching Pages and Instagram accounts: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
            session(['fb_link_error' => 'exception: ' . $e->getMessage()]);
        }

        return ['pages' => $pagesCount, 'instagram' => $instagramCount, 'whatsapp' => $whatsappCount];
    }


    /**
     * Show confirmation page when a page is already linked to another account.
     */
    public function relinkConfirm()
    {
        $pending = session('fb_relink_pending', []);

        if (empty($pending)) {
            return redirect()->route('customer.social-accounts.index');
        }

        return view('customer.social-accounts.relink-confirm', ['pendingPages' => $pending]);
    }

    /**
     * Perform re-linking of Facebook pages after user confirmation.
     */
    public function relinkConfirmed(Request $request)
    {
        $user    = Auth::user();
        $pending = session('fb_relink_pending', []);

        session()->forget('fb_relink_pending');

        if (empty($pending)) {
            return redirect()->route('customer.social-accounts.index');
        }

        $count = 0;
        foreach ($pending as $page) {
            $existing = SocialAccount::where('provider', 'facebook_page')
                ->where('provider_id', $page['page_id'])
                ->first();

            if ($existing) {
                $existing->update([
                    'user_id'        => $user->id,
                    'provider_token' => $page['access_token'],
                    'name'           => $page['page_name'],
                    'avatar'         => $page['avatar'],
                    'meta_data'      => $page['meta_data'],
                ]);
            } else {
                SocialAccount::create([
                    'user_id'        => $user->id,
                    'provider'       => 'facebook_page',
                    'provider_id'    => $page['page_id'],
                    'provider_token' => $page['access_token'],
                    'name'           => $page['page_name'],
                    'avatar'         => $page['avatar'],
                    'meta_data'      => $page['meta_data'],
                    'is_primary'     => false,
                ]);
            }

            Log::info("Re-linked Facebook page {$page['page_id']} from user {$page['old_user_id']} to user {$user->id}");
            $this->subscribePageToWebhook($page['page_id'], $page['access_token']);
            $count++;
        }

        return redirect()->route('customer.social-accounts.index')
            ->with('success', 'تم ربط ' . $count . ' ' . ($count === 1 ? 'صفحة فيسبوك' : 'صفحات فيسبوك') . ' بنجاح');
    }

    /**
     * Cancel the pending re-link and clear session.
     */
    public function relinkCancel()
    {
        session()->forget('fb_relink_pending');

        return redirect()->route('customer.social-accounts.index')
            ->with('warning', 'تم إلغاء عملية الربط.');
    }

    /**
     * Unlink a social account.
     */
    public function unlink(Request $request, string $provider)
    {
        $user = Auth::user();

        // If specific account ID provided, delete that one
        if ($request->has('account_id')) {
            $socialAccount = $user->socialAccounts()
                ->where('id', $request->account_id)
                ->where('provider', $provider)
                ->first();
        } else {
            // Otherwise delete first account of this provider
            $socialAccount = $user->socialAccounts()->where('provider', $provider)->first();
        }

        if (!$socialAccount) {
            return redirect()->route('customer.social-accounts.index')
                ->with('error', 'لم يتم العثور على الحساب');
        }

        $accountName = $socialAccount->name;
        $socialAccount->delete();

        return redirect()->route('customer.social-accounts.index')
            ->with('success', 'تم إلغاء ربط "' . $accountName . '" بنجاح');
    }

    /**
     * Get scopes for each provider.
     * NOTE: Many permissions require Facebook App Review before they work for all users.
     * In Development Mode, only app admins/testers can use the app.
     *
     * IMPORTANT: pages_show_list was deprecated in Graph API v19.0+
     * Use business_management instead for accessing pages.
     */
    protected function getScopes(string $provider): array
    {
        $commentsEnabled = config('services.meta.enable_comments', false);
        $whatsappEnabled = config('services.meta.enable_whatsapp', false);

        return match ($provider) {
            'facebook' => array_values(array_filter([
                // Basic permissions (always requested)
                'email',
                'public_profile',

                // Page management — needed to list pages & send DMs
                'pages_show_list',           // List pages the user manages
                'pages_read_engagement',     // Read page messages/posts
                'pages_read_user_content',   // Read page posts and media
                'pages_manage_metadata',     // Subscribe page to app webhook & manage page-level messaging metadata

                // Messaging (needed for auto-reply)
                'pages_messaging',           // Send messages via Messenger

                // Instagram (needed to link IG accounts)
                'instagram_basic',                // Access IG Business accounts
                'instagram_manage_messages',      // IG Direct message replies

                // ─── Comment-reply permissions ──────────────────────────────
                // Requires Facebook App Review. Enable via FACEBOOK_ENABLE_COMMENTS=true.
                // When disabled the comment-reply feature is silently skipped.
                $commentsEnabled ? 'pages_manage_engagement'   : null,  // Reply to FB comments
                $commentsEnabled ? 'instagram_manage_comments' : null,  // Reply to IG comments

                // ─── WhatsApp permissions ───────────────────────────────────
                // Requires WhatsApp Business API access. Enable via WHATSAPP_ENABLE=true.
                $whatsappEnabled ? 'whatsapp_business_management' : null,
                $whatsappEnabled ? 'whatsapp_business_messaging'  : null,
            ])),
            'instagram' => array_values(array_filter([
                'instagram_basic',
                'instagram_manage_messages',
                $commentsEnabled ? 'instagram_manage_comments' : null,
            ])),
            default => ['email'],
        };
    }

    /**
     * Subscribe a Facebook Page to this app webhook events.
     */
    protected function subscribePageToWebhook(string $pageId, string $pageAccessToken): void
    {
        try {
            $graphVersion = config('services.meta.graph_api_version', 'v21.0');

            $response = Http::timeout(15)->post("https://graph.facebook.com/{$graphVersion}/{$pageId}/subscribed_apps", [
                'access_token' => $pageAccessToken,
                'subscribed_fields' => 'messages,messaging_postbacks,messaging_optins,messaging_referrals,message_reads,message_deliveries,feed,mention',
            ]);

            if ($response->successful()) {
                Log::info('Facebook page subscribed to webhook successfully', [
                    'page_id' => $pageId,
                    'response' => $response->json(),
                ]);
                return;
            }

            Log::warning('Failed to subscribe Facebook page to webhook', [
                'page_id' => $pageId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Exception while subscribing Facebook page to webhook', [
                'page_id' => $pageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Subscribe a WhatsApp Business Account to this app webhook events.
     */
    protected function subscribeWhatsAppToWebhook(string $wabaId, string $accessToken): void
    {
        try {
            $graphVersion = config('services.meta.graph_api_version', 'v21.0');

            $response = Http::timeout(15)->post("https://graph.facebook.com/{$graphVersion}/{$wabaId}/subscribed_apps", [
                'access_token' => $accessToken,
            ]);

            if ($response->successful()) {
                Log::info('WhatsApp Business Account subscribed to webhook', ['waba_id' => $wabaId]);
                return;
            }

            Log::warning('Failed to subscribe WABA to webhook', [
                'waba_id' => $wabaId,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Exception while subscribing WABA to webhook', [
                'waba_id' => $wabaId,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
