<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialAccountController extends Controller
{
    /**
     * Core permissions required for auto-reply to work on Facebook.
     * If ANY of these are missing, the system cannot function.
     */
    const REQUIRED_PERMISSIONS = [
        'pages_messaging',
        'pages_read_engagement',
        'pages_manage_metadata',
        'pages_show_list',
    ];

    /** Permissions only needed when FACEBOOK_ENABLE_COMMENTS=true */
    const COMMENT_PERMISSIONS = [
        'pages_manage_engagement',
        'instagram_manage_comments',
    ];

    /** Permissions needed for Instagram DM auto-reply */
    const INSTAGRAM_PERMISSIONS = [
        'instagram_basic',
        'instagram_manage_messages',
    ];

    /** Permissions that are nice-to-have but not blocking */
    const OPTIONAL_PERMISSIONS = [
        'pages_read_user_content',
    ];

    /** Permissions needed for WhatsApp Business messaging */
    const WHATSAPP_PERMISSIONS = [
        'whatsapp_business_management',
        'whatsapp_business_messaging',
    ];

    /**
     * Display the social accounts management page.
     */
    public function index()
    {
        $user = Auth::user();
        $socialAccounts = $user->socialAccounts()->get();
        $connectedProviders = $socialAccounts->pluck('provider')->toArray();

        // Check permissions for every linked account (non-blocking — failures just set empty)
        $permissionStatus = [];
        foreach ($socialAccounts as $account) {
            $permissionStatus[$account->id] = $this->getAccountPermissions($account);
        }

        // Check AI auto-reply settings
        $aiSetting = $user->aiSetting;
        $aiEnabled        = $aiSetting && $aiSetting->ai_enabled;
        $autoReplyEnabled = $aiSetting && $aiSetting->auto_reply_enabled;

        return view('customer.social-accounts.index', [
            'socialAccounts'    => $socialAccounts,
            'connectedProviders'=> $connectedProviders,
            'permissionStatus'  => $permissionStatus,
            'aiEnabled'         => $aiEnabled,
            'autoReplyEnabled'  => $autoReplyEnabled,
        ]);
    }

    /**
     * AJAX endpoint: re-check a single account's permissions.
     */
    public function checkPermissions(Request $request)
    {
        $user    = Auth::user();
        $account = SocialAccount::where('id', $request->input('account_id'))
                                ->where('user_id', $user->id)
                                ->first();

        if (!$account) {
            return response()->json(['error' => 'Account not found'], 404);
        }

        $status = $this->getAccountPermissions($account);
        return response()->json($status);
    }

    /**
     * AJAX endpoint: test the page token against Graph API and return a diagnostic report.
     */
    public function diagnose(Request $request)
    {
        $user    = Auth::user();
        $account = SocialAccount::where('id', $request->input('account_id'))
                                ->where('user_id', $user->id)
                                ->first();

        if (!$account) {
            return response()->json(['error' => 'Account not found'], 404);
        }

        $report  = [];
        $token   = $account->provider_token;
        $graphV  = config('services.meta.graph_api_version', 'v21.0');
        $pageId  = $account->provider_id;

        // 1. Token introspect (debug_token)
        try {
            $appToken = config('services.facebook.client_id') . '|' . config('services.facebook.client_secret');
            $debug    = Http::timeout(8)->get("https://graph.facebook.com/{$graphV}/debug_token", [
                'input_token'  => $token,
                'access_token' => $appToken,
            ]);
            $report['token_debug'] = $debug->json();
        } catch (\Exception $e) {
            $report['token_debug'] = ['error' => $e->getMessage()];
        }

        // 2. Page identity — use only id,name; 'category' needs pages_read_engagement
        try {
            $me = Http::timeout(8)->get("https://graph.facebook.com/{$graphV}/{$pageId}", [
                'fields'       => 'id,name',
                'access_token' => $token,
            ]);
            $report['page_info'] = $me->json();
        } catch (\Exception $e) {
            $report['page_info'] = ['error' => $e->getMessage()];
        }

        // 3. Permissions on this token — extract the 'data' array that FB returns
        try {
            $perms = Http::timeout(8)->get("https://graph.facebook.com/{$graphV}/me/permissions", [
                'access_token' => $token,
            ]);
            // FB returns {"data":[{"permission":"...","status":"granted"},...]} — send only the array
            $permData = $perms->json('data', []);
            $report['permissions'] = $permData;

            // Compute which required permissions are missing — makes it easy to read in the UI
            $granted = collect($permData)->where('status', 'granted')->pluck('permission')->toArray();
            $missing = array_values(array_diff(self::REQUIRED_PERMISSIONS, $granted));
            $report['missing_required'] = $missing;
        } catch (\Exception $e) {
            $report['permissions'] = [];
            $report['permissions_error'] = $e->getMessage();
        }

        // 4. Test reading conversations — requires pages_messaging + pages_read_engagement
        //    (GET /{PAGE_ID}/messages doesn't exist; use /conversations instead)
        try {
            $testResp = Http::timeout(8)->get("https://graph.facebook.com/{$graphV}/{$pageId}/conversations", [
                'fields'       => 'id',
                'limit'        => '1',
                'access_token' => $token,
            ]);
            $report['messages_endpoint'] = [
                'status' => $testResp->status(),
                'body'   => $testResp->json(),
            ];
        } catch (\Exception $e) {
            $report['messages_endpoint'] = ['error' => $e->getMessage()];
        }

        Log::info('Social account diagnostic run', ['account_id' => $account->id, 'report' => $report]);

        return response()->json($report);
    }

    /**
     * Query the Graph API to get granted permissions for this account's token.
     * Returns an array: [ granted => [...], missing => [...], expired => bool, error => ?string ]
     */
    private function getAccountPermissions(SocialAccount $account): array
    {
        $result = [
            'granted'  => [],
            'missing'  => [],
            'expired'  => $account->isTokenExpired(),
            'error'    => null,
            'healthy'  => false,
        ];

        if (empty($account->provider_token)) {
            $result['error'] = 'no_token';
            return $result;
        }

        if ($result['expired']) {
            $result['error'] = 'token_expired';
            return $result;
        }

        // ── Proxy account: token is managed by the proxy server, not Facebook directly.
        // Calling debug_token with 'proxy:...' would always fail. Trust stored permissions.
        if (str_starts_with($account->provider_token, 'proxy:')) {
            $storedGranted = [];
            if (is_array($account->meta_data) && !empty($account->meta_data['granted_permissions'])) {
                $storedGranted = (array) $account->meta_data['granted_permissions'];
            }
            $result['granted'] = $storedGranted;
            // If no permissions stored yet (linked before permissions tracking),
            // default to healthy — the proxy server is the authority on permissions.
            if (empty($storedGranted)) {
                $result['healthy'] = true;
                $result['missing'] = [];
            } else {
                $result['missing'] = array_values(array_diff(self::REQUIRED_PERMISSIONS, $storedGranted));
                $result['healthy'] = empty($result['missing']);
            }
            return $result;
        }

        try {
            $graphVersion = config('services.meta.graph_api_version', 'v21.0');
            $appToken     = config('services.facebook.client_id') . '|' . config('services.facebook.client_secret');

            // Use debug_token — works for BOTH user tokens and page tokens.
            // /me/permissions only works with user tokens and returns empty for page tokens.
            $response = Http::timeout(8)->get("https://graph.facebook.com/{$graphVersion}/debug_token", [
                'input_token'  => $account->provider_token,
                'access_token' => $appToken,
            ]);

            if (!$response->successful()) {
                $result['error'] = $response->json('error.message') ?? 'api_error';
                return $result;
            }

            $data = $response->json('data', []);

            // Token is invalid/expired according to Facebook
            if (($data['is_valid'] ?? false) === false) {
                $result['expired'] = true;
                $result['error']   = 'token_expired';
                return $result;
            }

            // Scopes can appear in several places depending on token type.
            $scopes = $data['scopes'] ?? [];

            $granularScopes = collect($data['granular_scopes'] ?? [])
                ->pluck('scope')
                ->filter()
                ->values()
                ->toArray();

            $storedGranted = [];
            if (is_array($account->meta_data) && isset($account->meta_data['granted_permissions']) && is_array($account->meta_data['granted_permissions'])) {
                $storedGranted = $account->meta_data['granted_permissions'];
            }

            $result['granted'] = array_values(array_unique(array_merge($scopes, $granularScopes, $storedGranted)));

            foreach (self::REQUIRED_PERMISSIONS as $required) {
                if (!in_array($required, $result['granted'])) {
                    $result['missing'][] = $required;
                }
            }

            // Also flag comment permissions as missing when the feature is enabled
            if (config('services.meta.enable_comments', false)) {
                foreach (self::COMMENT_PERMISSIONS as $cp) {
                    if (!in_array($cp, $result['granted']) && !in_array($cp, $result['missing'])) {
                        $result['missing'][] = $cp;
                    }
                }
            }

            $result['healthy'] = empty($result['missing']);
        } catch (\Exception $e) {
            Log::warning("Permission check failed for social account {$account->id}: " . $e->getMessage());
            $result['error'] = 'check_failed';
        }

        return $result;
    }
}
