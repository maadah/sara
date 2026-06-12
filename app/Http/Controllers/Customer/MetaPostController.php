<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\MetaApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MetaPostController extends Controller
{
    /**
     * Fetch recent posts for a connected social account.
     */
    public function getAccountPosts(Request $request)
    {
        $platform = $request->input('platform', 'facebook');
        
        $user = Auth::user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('provider', $platform === 'instagram' ? 'instagram' : 'facebook_page')
            ->first();

        if (!$account || empty($account->provider_token)) {
            return response()->json(['error' => 'Account not connected or missing token.'], 400);
        }

        $metaApi = app(MetaApiService::class);
        $posts = $metaApi->fetchPagePosts($account->provider_id, $account->provider_token, $platform, 20);

        if ($posts === null) {
            return response()->json(['error' => 'Failed to fetch posts from Meta API.'], 500);
        }

        return response()->json(['data' => $posts]);
    }

    /**
     * Resolve a pasted URL to a Graph API object, returning a preview.
     */
    public function resolveUrl(Request $request)
    {
        $url = $request->input('url');
        $platform = $request->input('platform', 'facebook');

        if (!$url) {
            return response()->json(['error' => 'URL is required'], 400);
        }

        $user = Auth::user();
        $account = SocialAccount::where('user_id', $user->id)
            ->where('provider', $platform === 'instagram' ? 'instagram' : 'facebook_page')
            ->first();

        if (!$account || empty($account->provider_token)) {
            return response()->json(['error' => 'Account not connected'], 400);
        }

        // Basic ID extraction logic (shared with SocialCommentService)
        preg_match_all('/\d{10,}/', $url, $matches);
        $candidates = $matches[0] ?? [];

        if (empty($candidates)) {
            return response()->json(['error' => 'Could not extract any valid IDs from the URL.'], 400);
        }

        $graphVersion = config('services.meta.graph_api_version', 'v18.0');
        
        foreach ($candidates as $id) {
            try {
                $fields = $platform === 'instagram' 
                    ? 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp'
                    : 'id,message,full_picture,permalink_url,created_time,attachments{media_type,media,url}';

                $response = \Illuminate\Support\Facades\Http::get("https://graph.facebook.com/{$graphVersion}/{$id}", [
                    'fields' => $fields,
                    'access_token' => $account->provider_token,
                ]);

                if ($response->successful() && $response->json('id')) {
                    return response()->json(['data' => $response->json()]);
                }
            } catch (\Exception $e) {
                // Ignore and try next ID
            }
        }

        return response()->json(['error' => 'Could not fetch post details from Meta via the provided URL.'], 404);
    }
}
