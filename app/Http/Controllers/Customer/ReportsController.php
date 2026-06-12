<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Message;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    /**
     * Get date format SQL based on database driver
     */
    private function getDateFormatSQL($column, $format)
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // Convert MySQL format to SQLite strftime format
            $sqliteFormat = str_replace(
                ['%Y', '%m', '%d', '%u', '%W'],
                ['%Y', '%m', '%d', '%W', '%W'],
                $format
            );
            return "strftime('{$sqliteFormat}', {$column})";
        }

        // MySQL/MariaDB
        return "DATE_FORMAT({$column}, '{$format}')";
    }

    /**
     * Main reports dashboard
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Date range
        $fromDate = $request->from_date ? Carbon::parse($request->from_date) : Carbon::now()->subDays(30);
        $toDate = $request->to_date ? Carbon::parse($request->to_date) : Carbon::now();

        // Summary stats
        $summary = $this->getSummaryStats($user, $fromDate, $toDate);

        // Sales chart data
        $salesChart = $this->getSalesChartData($user, $fromDate, $toDate);

        // Top products
        $topProducts = $this->getTopProducts($user, $fromDate, $toDate, 10);

        // Lead sources breakdown
        $leadSources = $this->getLeadSourcesData($user, $fromDate, $toDate);

        // Order status distribution
        $orderStatuses = $this->getOrderStatusDistribution($user, $fromDate, $toDate);

        // Recent activity
        $recentActivity = $this->getRecentActivity($user, 10);

        // Recent orders
        $recentOrders = OnlineOrder::where('user_id', $user->id)
            ->with(['lead', 'conversation'])
            ->latest()
            ->limit(5)
            ->get();

        return view('customer.reports.index', compact(
            'summary',
            'salesChart',
            'topProducts',
            'leadSources',
            'orderStatuses',
            'recentActivity',
            'recentOrders',
            'fromDate',
            'toDate'
        ));
    }

    /**
     * Sales report
     */
    public function sales(Request $request)
    {
        $user = Auth::user();

        $fromDate = $request->from_date ? Carbon::parse($request->from_date) : Carbon::now()->subDays(30);
        $toDate = $request->to_date ? Carbon::parse($request->to_date) : Carbon::now();
        $groupBy = $request->get('group_by', 'day'); // day, week, month

        // Sales data
        $salesData = $this->getSalesData($user, $fromDate, $toDate, $groupBy);

        // Product performance
        $productPerformance = $this->getProductPerformance($user, $fromDate, $toDate);

        // Sales by source
        $salesBySource = $this->getSalesBySource($user, $fromDate, $toDate);

        // Average order value over time
        $avgOrderValue = $this->getAverageOrderValueTrend($user, $fromDate, $toDate, $groupBy);

        return view('customer.reports.sales', compact(
            'salesData',
            'productPerformance',
            'salesBySource',
            'avgOrderValue',
            'fromDate',
            'toDate',
            'groupBy'
        ));
    }

    /**
     * Leads report
     */
    public function leads(Request $request)
    {
        $user = Auth::user();

        $fromDate = $request->from_date ? Carbon::parse($request->from_date) : Carbon::now()->subDays(30);
        $toDate = $request->to_date ? Carbon::parse($request->to_date) : Carbon::now();

        // Lead acquisition over time
        $leadAcquisition = $this->getLeadAcquisitionData($user, $fromDate, $toDate);

        // Conversion funnel
        $conversionFunnel = $this->getConversionFunnel($user, $fromDate, $toDate);

        // Lead interests breakdown

        // Lead response time
        $responseTime = $this->getAverageResponseTime($user, $fromDate, $toDate);

        // Top cities
        $topCities = $this->getTopCities($user, $fromDate, $toDate);

        return view('customer.reports.leads', compact(
            'leadAcquisition',
            'conversionFunnel',
            'responseTime',
            'topCities',
            'fromDate',
            'toDate'
        ));
    }

    /**
     * Messages/AI report
     */
    public function messages(Request $request)
    {
        $user = Auth::user();

        $fromDate = $request->from_date ? Carbon::parse($request->from_date) : Carbon::now()->subDays(30);
        $toDate = $request->to_date ? Carbon::parse($request->to_date) : Carbon::now();

        // Message volume over time
        $messageVolume = $this->getMessageVolumeData($user, $fromDate, $toDate);

        // AI vs Manual responses
        $aiVsManual = $this->getAiVsManualResponses($user, $fromDate, $toDate);

        // Response time stats
        $responseStats = $this->getResponseTimeStats($user, $fromDate, $toDate);

        // Peak hours
        $peakHours = $this->getPeakHours($user, $fromDate, $toDate);

        // Conversation outcomes
        $conversationOutcomes = $this->getConversationOutcomes($user, $fromDate, $toDate);

        return view('customer.reports.messages', compact(
            'messageVolume',
            'aiVsManual',
            'responseStats',
            'peakHours',
            'conversationOutcomes',
            'fromDate',
            'toDate'
        ));
    }

    /**
     * Products performance report
     */
    public function products(Request $request)
    {
        $user = Auth::user();

        $fromDate = $request->from_date ? Carbon::parse($request->from_date) : Carbon::now()->subDays(30);
        $toDate = $request->to_date ? Carbon::parse($request->to_date) : Carbon::now();

        // Top products with details
        $topProducts = $this->getTopProducts($user, $fromDate, $toDate, 50);

        // Product categories performance
        $categoriesPerformance = OnlineOrderItem::whereHas('order', function ($q) use ($user, $fromDate, $toDate) {
                $q->where('user_id', $user->id)
                    ->whereBetween('created_at', [$fromDate, $toDate])
                    ->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered']);
            })
            ->whereHas('product.category')
            ->with('product.category')
            ->select(
                DB::raw('SUM(quantity) as total_sold'),
                DB::raw('SUM(total) as total_revenue')
            )
            ->get()
            ->groupBy(function ($item) {
                return $item->product->category->name ?? 'بدون فئة';
            })
            ->map(function ($items, $category) {
                return [
                    'name' => $category,
                    'total_sold' => $items->sum('total_sold'),
                    'total_revenue' => $items->sum('total_revenue'),
                ];
            })
            ->values();

        return view('customer.reports.products', compact(
            'topProducts',
            'categoriesPerformance',
            'fromDate',
            'toDate'
        ));
    }

    /**
     * Export report data
     */
    public function export(Request $request)
    {
        $user = Auth::user();
        $type = $request->get('type', 'summary');

        $fromDate = $request->from_date ? Carbon::parse($request->from_date) : Carbon::now()->subDays(30);
        $toDate = $request->to_date ? Carbon::parse($request->to_date) : Carbon::now();

        switch ($type) {
            case 'sales':
                return $this->exportSalesReport($user, $fromDate, $toDate);
            case 'leads':
                return $this->exportLeadsReport($user, $fromDate, $toDate);
            case 'products':
                return $this->exportProductsReport($user, $fromDate, $toDate);
            default:
                return $this->exportSummaryReport($user, $fromDate, $toDate);
        }
    }

    // ==================== PRIVATE HELPER METHODS ====================

    private function getSummaryStats($user, $fromDate, $toDate)
    {
        $orderQuery = OnlineOrder::where('user_id', $user->id)
            ->whereBetween('created_at', [$fromDate, $toDate]);

        $prevFromDate = $fromDate->copy()->subDays($fromDate->diffInDays($toDate));
        $prevToDate = $fromDate->copy()->subDay();

        $prevOrderQuery = OnlineOrder::where('user_id', $user->id)
            ->whereBetween('created_at', [$prevFromDate, $prevToDate]);

        $totalOrders = $orderQuery->count();
        $prevTotalOrders = $prevOrderQuery->count();

        $totalRevenue = $orderQuery->clone()
            ->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered'])
            ->sum('total');
        $prevTotalRevenue = $prevOrderQuery->clone()
            ->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered'])
            ->sum('total');

        $totalLeads = Lead::where('user_id', $user->id)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->count();
        $prevTotalLeads = Lead::where('user_id', $user->id)
            ->whereBetween('created_at', [$prevFromDate, $prevToDate])
            ->count();

        $totalMessages = Message::whereHas('conversation', function ($q) use ($user) {
                $q->whereHas('socialAccount', function ($sq) use ($user) {
                    $sq->where('user_id', $user->id);
                });
            })
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->count();

        $convertedLeads = Lead::where('user_id', $user->id)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->where('status', 'converted')
            ->count();

        $conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;

        $avgOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 0) : 0;
        $prevAvgOrderValue = $prevTotalOrders > 0 ? round($prevTotalRevenue / $prevTotalOrders, 0) : 0;

        return [
            'total_orders' => $totalOrders,
            'orders_change' => $this->calculatePercentageChange($prevTotalOrders, $totalOrders),
            'total_revenue' => $totalRevenue,
            'revenue_change' => $this->calculatePercentageChange($prevTotalRevenue, $totalRevenue),
            'total_leads' => $totalLeads,
            'leads_change' => $this->calculatePercentageChange($prevTotalLeads, $totalLeads),
            'total_messages' => $totalMessages,
            'conversion_rate' => $conversionRate,
            'avg_order_value' => $avgOrderValue,
            'aov_change' => $this->calculatePercentageChange($prevAvgOrderValue, $avgOrderValue),
        ];
    }

    private function getSalesChartData($user, $fromDate, $toDate)
    {
        $days = $fromDate->diffInDays($toDate);

        if ($days <= 31) {
            $format = 'Y-m-d';
            $labelFormat = 'd M';
        } elseif ($days <= 90) {
            $format = 'Y-W';
            $labelFormat = 'W Y';
        } else {
            $format = 'Y-m';
            $labelFormat = 'M Y';
        }

        $dateSQL = $this->getDateFormatSQL('created_at', '%Y-%m-%d');
        $sales = OnlineOrder::where('user_id', $user->id)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered'])
            ->select(
                DB::raw("{$dateSQL} as date"),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = [];
        $revenueData = [];
        $ordersData = [];

        $current = $fromDate->copy();
        while ($current <= $toDate) {
            $dateKey = $current->format('Y-m-d');
            $labels[] = $current->format('d/m');

            $dayData = $sales->firstWhere('date', $dateKey);
            $revenueData[] = $dayData ? (int) $dayData->revenue : 0;
            $ordersData[] = $dayData ? (int) $dayData->orders : 0;

            $current->addDay();
        }

        return [
            'labels' => $labels,
            'revenue' => $revenueData,
            'orders' => $ordersData,
        ];
    }

    private function getTopProducts($user, $fromDate, $toDate, $limit = 10)
    {
        return OnlineOrderItem::whereHas('order', function ($q) use ($user, $fromDate, $toDate) {
                $q->where('user_id', $user->id)
                  ->whereBetween('created_at', [$fromDate, $toDate])
                  ->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered']);
            })
            ->select(
                'product_name',
                'product_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total) as total_revenue'),
                DB::raw('COUNT(DISTINCT online_order_id) as order_count')
            )
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();
    }

    private function getLeadSourcesData($user, $fromDate, $toDate)
    {
        return Lead::where('user_id', $user->id)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->select('source', DB::raw('COUNT(*) as count'))
            ->groupBy('source')
            ->get()
            ->mapWithKeys(function ($item) {
                $labels = Lead::$sourceLabels ?? ['facebook' => 'فيسبوك', 'instagram' => 'انستغرام', 'whatsapp' => 'واتساب', 'other' => 'آخر'];
                return [$labels[$item->source] ?? $item->source => $item->count];
            });
    }

    private function getOrderStatusDistribution($user, $fromDate, $toDate)
    {
        return OnlineOrder::where('user_id', $user->id)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                $order = new OnlineOrder(['status' => $item->status]);
                return [$order->status_label => $item->count];
            });
    }

    private function getRecentActivity($user, $limit = 10)
    {
        $activities = collect();

        // Recent orders
        $orders = OnlineOrder::where('user_id', $user->id)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($order) {
                return [
                    'type' => 'order',
                    'icon' => 'shopping-cart',
                    'color' => 'blue',
                    'title' => "طلب جديد #{$order->order_number}",
                    'description' => "{$order->customer_name} - " . number_format($order->total) . ' د.ع',
                    'time' => $order->created_at,
                ];
            });

        // Recent leads
        $leads = Lead::where('user_id', $user->id)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($lead) {
                return [
                    'type' => 'lead',
                    'icon' => 'user-plus',
                    'color' => 'green',
                    'title' => 'عميل جديد',
                    'description' => $lead->display_name . ' من ' . $lead->source_label,
                    'time' => $lead->created_at,
                ];
            });

        return $activities
            ->merge($orders)
            ->merge($leads)
            ->sortByDesc('time')
            ->take($limit)
            ->values();
    }

    private function getSalesData($user, $fromDate, $toDate, $groupBy)
    {
        $formatMap = [
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
        ];

        $format = $formatMap[$groupBy] ?? '%Y-%m-%d';

        $periodSQL = $this->getDateFormatSQL('created_at', $format);
        return OnlineOrder::where('user_id', $user->id)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered'])
            ->select(
                DB::raw("{$periodSQL} as period"),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('AVG(total) as avg_order')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();
    }

    private function getProductPerformance($user, $fromDate, $toDate)
    {
        return OnlineOrderItem::whereHas('order', function ($q) use ($user, $fromDate, $toDate) {
                $q->where('user_id', $user->id)
                  ->whereBetween('created_at', [$fromDate, $toDate])
                  ->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered']);
            })
            ->select(
                'product_id',
                'product_name',
                DB::raw('SUM(quantity) as quantity_sold'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('AVG(unit_price) as avg_price')
            )
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('revenue')
            ->get();
    }

    private function getSalesBySource($user, $fromDate, $toDate)
    {
        return OnlineOrder::where('user_id', $user->id)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered'])
            ->select(
                'source',
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(total) as revenue')
            )
            ->groupBy('source')
            ->get();
    }

    private function getAverageOrderValueTrend($user, $fromDate, $toDate, $groupBy)
    {
        $formatMap = [
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
        ];

        $format = $formatMap[$groupBy] ?? '%Y-%m-%d';

        $periodSQL = $this->getDateFormatSQL('created_at', $format);
        return OnlineOrder::where('user_id', $user->id)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered'])
            ->select(
                DB::raw("{$periodSQL} as period"),
                DB::raw('AVG(total) as avg_value')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();
    }

    private function getLeadAcquisitionData($user, $fromDate, $toDate)
    {
        $dateSQL = $this->getDateFormatSQL('created_at', '%Y-%m-%d');
        return Lead::where('user_id', $user->id)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->select(
                DB::raw("{$dateSQL} as date"),
                'source',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date', 'source')
            ->orderBy('date')
            ->get();
    }

    private function getConversionFunnel($user, $fromDate, $toDate)
    {
        $total = Lead::where('user_id', $user->id)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->count();

        $contacted = Lead::where('user_id', $user->id)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->whereIn('status', ['contacted', 'interested', 'converted'])
            ->count();

        $interested = Lead::where('user_id', $user->id)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->whereIn('status', ['converted'])
            ->count();

        $converted = Lead::where('user_id', $user->id)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->where('status', 'converted')
            ->count();

        return [
            ['stage' => 'جديد', 'count' => $total, 'percentage' => 100],
            ['stage' => 'تم التواصل', 'count' => $contacted, 'percentage' => $total > 0 ? round(($contacted / $total) * 100) : 0],
            ['stage' => 'تحول لعميل', 'count' => $converted, 'percentage' => $total > 0 ? round(($converted / $total) * 100) : 0],
        ];
    }


    private function getAverageResponseTime($user, $fromDate, $toDate)
    {
        // This would need actual calculation based on message timestamps
        // For now, return placeholder
        return [
            'average_minutes' => 15,
            'fastest_minutes' => 2,
            'slowest_minutes' => 120,
        ];
    }

    private function getTopCities($user, $fromDate, $toDate)
    {
        return Lead::where('user_id', $user->id)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->whereNotNull('city')
            ->select('city', DB::raw('COUNT(*) as count'))
            ->groupBy('city')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
    }

    private function getMessageVolumeData($user, $fromDate, $toDate)
    {
        $dateSQL = $this->getDateFormatSQL('created_at', '%Y-%m-%d');
        return Message::whereHas('conversation', function ($q) use ($user) {
                $q->whereHas('socialAccount', function ($sq) use ($user) {
                    $sq->where('user_id', $user->id);
                });
            })
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->select(
                DB::raw("{$dateSQL} as date"),
                'is_from_customer',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date', 'is_from_customer')
            ->orderBy('date')
            ->get();
    }

    private function getAiVsManualResponses($user, $fromDate, $toDate)
    {
        $messages = Message::whereHas('conversation', function ($q) use ($user) {
                $q->whereHas('socialAccount', function ($sq) use ($user) {
                    $sq->where('user_id', $user->id);
                });
            })
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->where('is_from_customer', false)
            ->select(
                DB::raw('COALESCE(is_ai_generated, 0) as is_ai'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('is_ai')
            ->get();

        return [
            'ai' => $messages->firstWhere('is_ai', 1)?->count ?? 0,
            'manual' => $messages->firstWhere('is_ai', 0)?->count ?? 0,
        ];
    }

    private function getResponseTimeStats($user, $fromDate, $toDate)
    {
        // Placeholder - would need actual calculation
        return [
            'under_5min' => 45,
            '5_to_15min' => 30,
            '15_to_60min' => 15,
            'over_60min' => 10,
        ];
    }

    private function getPeakHours($user, $fromDate, $toDate)
    {
        return Message::whereHas('conversation', function ($q) use ($user) {
                $q->whereHas('socialAccount', function ($sq) use ($user) {
                    $sq->where('user_id', $user->id);
                });
            })
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->where('is_from_customer', true)
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();
    }

    private function getConversationOutcomes($user, $fromDate, $toDate)
    {
        // Conversations that led to orders
        $withOrders = Conversation::whereHas('socialAccount', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->whereHas('onlineOrders')
            ->count();

        // Total conversations
        $total = Conversation::whereHas('socialAccount', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->count();

        return [
            'with_orders' => $withOrders,
            'without_orders' => $total - $withOrders,
            'total' => $total,
            'conversion_rate' => $total > 0 ? round(($withOrders / $total) * 100, 1) : 0,
        ];
    }

    private function calculatePercentageChange($old, $new)
    {
        if ($old == 0) {
            return $new > 0 ? 100 : 0;
        }
        return round((($new - $old) / $old) * 100, 1);
    }

    private function exportSalesReport($user, $fromDate, $toDate)
    {
        $orders = OnlineOrder::where('user_id', $user->id)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->with('items')
            ->get();

        $filename = 'sales_report_' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ['رقم الطلب', 'التاريخ', 'العميل', 'المصدر', 'الحالة', 'الإجمالي']);

            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->order_number,
                    $order->created_at->format('Y-m-d H:i'),
                    $order->customer_name,
                    $order->source_label,
                    $order->status_label,
                    number_format($order->total, 0),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportLeadsReport($user, $fromDate, $toDate)
    {
        $leads = Lead::where('user_id', $user->id)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->get();

        $filename = 'leads_report_' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($leads) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ['الاسم', 'الهاتف', 'المدينة', 'المصدر', 'الحالة', 'التاريخ']);

            foreach ($leads as $lead) {
                fputcsv($file, [
                    $lead->display_name,
                    $lead->phone ?? '-',
                    $lead->city ?? '-',
                    $lead->source_label,
                    $lead->status_label,
                    $lead->created_at->format('Y-m-d H:i'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportProductsReport($user, $fromDate, $toDate)
    {
        $products = $this->getProductPerformance($user, $fromDate, $toDate);

        $filename = 'products_report_' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ['المنتج', 'الكمية المباعة', 'الإيرادات', 'متوسط السعر']);

            foreach ($products as $product) {
                fputcsv($file, [
                    $product->product_name,
                    $product->quantity_sold,
                    number_format($product->revenue, 0),
                    number_format($product->avg_price, 0),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportSummaryReport($user, $fromDate, $toDate)
    {
        $summary = $this->getSummaryStats($user, $fromDate, $toDate);

        $filename = 'summary_report_' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($summary, $fromDate, $toDate) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ['تقرير ملخص', $fromDate->format('Y-m-d') . ' - ' . $toDate->format('Y-m-d')]);
            fputcsv($file, []);
            fputcsv($file, ['المؤشر', 'القيمة', 'التغيير']);
            fputcsv($file, ['إجمالي الطلبات', $summary['total_orders'], $summary['orders_change'] . '%']);
            fputcsv($file, ['إجمالي الإيرادات', number_format($summary['total_revenue'], 0) . ' د.ع', $summary['revenue_change'] . '%']);
            fputcsv($file, ['إجمالي العملاء', $summary['total_leads'], $summary['leads_change'] . '%']);
            fputcsv($file, ['معدل التحويل', $summary['conversion_rate'] . '%', '']);
            fputcsv($file, ['متوسط قيمة الطلب', number_format($summary['avg_order_value'], 0) . ' د.ع', $summary['aov_change'] . '%']);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
