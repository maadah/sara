<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CommentInteraction;
use App\Models\Product;
use App\Services\SocialCommentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentReplyController extends Controller
{
    /**
     * Comment Replies dashboard page
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Stats
        $totalInteractions = CommentInteraction::where('user_id', $user->id)->count();
        $activeInteractions = CommentInteraction::where('user_id', $user->id)->active()->count();
        $repliedCount = CommentInteraction::where('user_id', $user->id)->where('replied', true)->count();
        $dmSentCount = CommentInteraction::where('user_id', $user->id)->where('dm_sent', true)->count();

        // Platform breakdown
        $facebookCount = CommentInteraction::where('user_id', $user->id)->where('platform', 'facebook')->count();
        $instagramCount = CommentInteraction::where('user_id', $user->id)->where('platform', 'instagram')->count();

        // Products with linked posts
        $linkedProducts = Product::where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNotNull('facebook_post_url')
                  ->orWhereNotNull('instagram_post_url');
            })
            ->count();

        $totalProducts = Product::where('user_id', $user->id)->count();

        // Recent interactions (paginated)
        $filter = $request->get('filter', 'all');
        $query = CommentInteraction::where('user_id', $user->id)
            ->with('product')
            ->orderByDesc('created_at');

        if ($filter === 'active') {
            $query->active();
        } elseif ($filter === 'expired') {
            $query->expired();
        } elseif ($filter === 'facebook') {
            $query->where('platform', 'facebook');
        } elseif ($filter === 'instagram') {
            $query->where('platform', 'instagram');
        } elseif ($filter === 'dm_sent') {
            $query->where('dm_sent', true);
        }

        $interactions = $query->paginate(20);

        // Permission check
        $commentService = new SocialCommentService();
        $permissions = $commentService->checkPermissions($user);

        $stats = [
            'total' => $totalInteractions,
            'active' => $activeInteractions,
            'replied' => $repliedCount,
            'dm_sent' => $dmSentCount,
            'facebook' => $facebookCount,
            'instagram' => $instagramCount,
            'linked_products' => $linkedProducts,
            'total_products' => $totalProducts,
        ];

        return view('customer.comment-replies.index', compact(
            'stats', 'interactions', 'permissions', 'filter'
        ));
    }

    /**
     * Delete a single interaction
     */
    public function destroy(CommentInteraction $interaction)
    {
        if ($interaction->user_id !== Auth::id()) {
            abort(403);
        }

        $interaction->delete();

        return back()->with('success', 'تم حذف التفاعل بنجاح');
    }

    /**
     * Cleanup all expired interactions manually
     */
    public function cleanup()
    {
        $service = new SocialCommentService();
        $deleted = $service->cleanupExpired();

        return back()->with('success', "تم حذف {$deleted} تفاعل منتهي الصلاحية");
    }
}
