<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $period = $request->get('period', '7days');
        $startDate = $this->getStartDate($period);

        // Key metrics
        $metrics = [
            'average_response_time' => $this->getAverageResponseTime($userId),
            'conversion_rate' => $this->getConversionRate($userId, $startDate),
            'hot_leads' => Lead::where('user_id', $userId)->where('status', 'hot')->count(),
            'total_revenue' => Sale::where('user_id', $userId)->where('status', 'completed')->where('created_at', '>=', $startDate)->sum('total'),
            'total_conversations' => Conversation::where('user_id', $userId)->where('created_at', '>=', $startDate)->count(),
        ];

        // Orders chart data (last 7 days)
        $ordersData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $ordersData[$date] = Sale::where('user_id', $userId)
                ->where('status', 'completed')
                ->whereDate('created_at', $date)
                ->count();
        }

        // Conversations chart data
        $conversationsChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $conversationsChart[$date] = Conversation::where('user_id', $userId)
                ->whereDate('created_at', $date)
                ->count();
        }

        // Category distribution
        $categoryDistribution = Product::where('products.user_id', $userId)
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('categories.name', DB::raw('count(*) as count'))
            ->groupBy('categories.name')
            ->pluck('count', 'name')
            ->toArray();

        // Leads status distribution
        $leadsStatus = Lead::where('user_id', $userId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return view('customer.analytics.index', compact(
            'metrics',
            'ordersData',
            'conversationsChart',
            'categoryDistribution',
            'leadsStatus',
            'period'
        ));
    }

    private function getStartDate($period)
    {
        return match ($period) {
            '7days' => Carbon::now()->subDays(7),
            '30days' => Carbon::now()->subDays(30),
            'month' => Carbon::now()->startOfMonth(),
            default => Carbon::now()->subDays(7),
        };
    }

    private function getAverageResponseTime($userId)
    {
        // Simplified: count average minutes between conversation creation and first response
        return 0;
    }

    private function getConversionRate($userId, $startDate)
    {
        $totalLeads = Lead::where('user_id', $userId)->where('created_at', '>=', $startDate)->count();
        $convertedLeads = OnlineOrder::where('user_id', $userId)->where('created_at', '>=', $startDate)->count();

        return $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;
    }
}
