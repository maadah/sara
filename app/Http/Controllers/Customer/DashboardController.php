<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Sale;
use App\Models\Lead;
use App\Models\Conversation;
use App\Models\OnlineOrder;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Show customer dashboard
     */
    public function index()
    {
        $user = Auth::user();

        // Check if user is approved
        if (!$user->isApproved()) {
            return redirect()->route('customer.pending');
        }

        $userId = $user->id;

        // Active conversations
        $activeConversations = Conversation::where('user_id', $userId)
            ->where('status', '!=', 'closed')
            ->count();

        // Conversation change (this week vs last week)
        $thisWeekConvos = Conversation::where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();
        $lastWeekConvos = Conversation::where('user_id', $userId)
            ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->startOfWeek()])
            ->count();
        $conversationChange = $lastWeekConvos > 0 ? round((($thisWeekConvos - $lastWeekConvos) / $lastWeekConvos) * 100) : 0;

        // New customers (leads created this week)
        $newCustomers = Lead::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $lastWeekCustomers = Lead::where('user_id', $userId)
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->count();
        $customerChange = $lastWeekCustomers > 0 ? round((($newCustomers - $lastWeekCustomers) / $lastWeekCustomers) * 100) : 0;

        // Pending orders
        $pendingOrders = OnlineOrder::where('user_id', $userId)->where('status', 'pending')->count();
        $totalOrders = OnlineOrder::where('user_id', $userId)->count();

        // Total revenue
        $totalRevenue = Sale::where('user_id', $userId)->where('status', 'completed')->sum('total');
        $thisWeekRevenue = Sale::where('user_id', $userId)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->startOfWeek())
            ->sum('total');
        $lastWeekRevenue = Sale::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->startOfWeek()])
            ->sum('total');
        $revenueChange = $lastWeekRevenue > 0 ? round((($thisWeekRevenue - $lastWeekRevenue) / $lastWeekRevenue) * 100) : 0;

        // Linked accounts count
        $linkedAccountsCount = SocialAccount::where('user_id', $userId)->count();

        // Chart data (sales last 7 days)
        $chartData = $this->getSalesChartData($userId);

        // Recent activities
        $recentActivities = $this->getRecentActivities($userId);

        // Inventory stats (kept from before)
        $visitorStats = $this->getVisitorStats($userId);

        // Category distribution for doughnut chart
        $categoryDistribution = $this->getCategoryDistribution($userId);

        return view('customer.dashboard', compact(
            'user',
            'activeConversations',
            'conversationChange',
            'newCustomers',
            'customerChange',
            'pendingOrders',
            'totalOrders',
            'totalRevenue',
            'revenueChange',
            'linkedAccountsCount',
            'chartData',
            'recentActivities',
            'visitorStats',
            'categoryDistribution'
        ));
    }

    /**
     * Get sales chart data for last 7 days
     */
    private function getSalesChartData($userId)
    {
        $labels = [];
        $data = [];
        $arabicDays = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $arabicDays[$date->dayOfWeek];

            $dailySales = Sale::where('user_id', $userId)
                ->where('status', 'completed')
                ->whereDate('created_at', $date)
                ->sum('total');

            $data[] = $dailySales;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Get recent activities
     */
    private function getRecentActivities($userId)
    {
        $activities = [];

        // Recent sales
        $recentSales = Sale::where('user_id', $userId)
            ->latest()
            ->take(3)
            ->get();

        foreach ($recentSales as $sale) {
            $activities[] = [
                'user' => 'عملية بيع #' . $sale->id,
                'action' => 'بقيمة ' . number_format($sale->total) . ' د.ع',
                'time' => $sale->created_at->diffForHumans(),
                'date' => $sale->created_at,
            ];
        }

        // Recent leads
        $recentLeads = Lead::where('user_id', $userId)
            ->latest()
            ->take(3)
            ->get();

        foreach ($recentLeads as $lead) {
            $activities[] = [
                'user' => $lead->name ?? 'عميل جديد',
                'action' => 'عميل جديد تم إضافته',
                'time' => $lead->created_at->diffForHumans(),
                'date' => $lead->created_at,
            ];
        }

        // Recent products
        $recentProducts = Product::where('user_id', $userId)
            ->latest()
            ->take(2)
            ->get();

        foreach ($recentProducts as $product) {
            $activities[] = [
                'user' => $product->name,
                'action' => 'منتج جديد تم إضافته',
                'time' => $product->created_at->diffForHumans(),
                'date' => $product->created_at,
            ];
        }

        // Sort by date and take latest 5
        usort($activities, fn($a, $b) => $b['date'] <=> $a['date']);
        return array_slice($activities, 0, 5);
    }

    /**
     * Get visitor statistics (inventory analysis)
     */
    private function getVisitorStats($userId)
    {
        // Get products count by category for distribution
        $totalProducts = Product::where('user_id', $userId)->count();

        if ($totalProducts == 0) {
            return [
                'total_products' => 0,
                'active_products' => 0,
                'low_stock_products' => 0,
                'expired_products' => 0,
                'active_percentage' => 0,
                'low_stock_percentage' => 0,
                'expired_percentage' => 0,
                'in_stock_percentage' => 0,
            ];
        }

        // Active products (quantity > 0 and is_active)
        $activeProducts = Product::where('user_id', $userId)
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->count();

        // Low stock products (quantity < 10)
        $lowStockProducts = Product::where('user_id', $userId)
            ->where('quantity', '>', 0)
            ->where('quantity', '<', 10)
            ->count();

        // Expired products
        $expiredProducts = Product::where('user_id', $userId)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', Carbon::now())
            ->count();

        // In stock products (quantity >= 10)
        $inStockProducts = Product::where('user_id', $userId)
            ->where('quantity', '>=', 10)
            ->count();

        return [
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'low_stock_products' => $lowStockProducts,
            'expired_products' => $expiredProducts,
            'in_stock_products' => $inStockProducts,
            'active_percentage' => round(($activeProducts / $totalProducts) * 100),
            'low_stock_percentage' => round(($lowStockProducts / $totalProducts) * 100),
            'expired_percentage' => round(($expiredProducts / $totalProducts) * 100),
            'in_stock_percentage' => round(($inStockProducts / $totalProducts) * 100),
        ];
    }

    /**
     * Get category distribution for chart
     */
    private function getCategoryDistribution($userId)
    {
        return Product::where('products.user_id', $userId)
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('categories.name', DB::raw('count(*) as count'))
            ->groupBy('categories.name')
            ->pluck('count', 'name')
            ->toArray();
    }

    /**
     * Show pending approval page
     */
    public function pending()
    {
        $user = Auth::user();

        // If approved, redirect to dashboard
        if ($user->isApproved()) {
            return redirect()->route('customer.dashboard');
        }

        return view('customer.pending', compact('user'));
    }

    /**
     * Show subscription expired page
     */
    public function expired()
    {
        $user = Auth::user();

        // If subscription is not expired, redirect to dashboard
        if (!$user->subscription_expires_at || $user->subscription_expires_at->isFuture()) {
            return redirect()->route('customer.dashboard');
        }

        return view('customer.expired', compact('user'));
    }

    /**
     * Show profile page
     */
    public function profile()
    {
        $user = Auth::user();
        return view('customer.profile', compact('user'));
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'facebook_link' => 'nullable|url|max:255',
            'instagram_link' => 'nullable|url|max:255',
            'store_address' => 'nullable|string|max:500',
        ]);

        $user->update($request->only([
            'name',
            'phone',
            'whatsapp',
            'facebook_link',
            'instagram_link',
            'store_address',
        ]));

        return back()->with('success', 'تم تحديث الملف الشخصي بنجاح');
    }
}
