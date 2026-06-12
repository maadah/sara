@extends('layouts.customer')

@section('title', 'التقارير والإحصائيات')


@section('content')
<div class="space-y-8">
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">التقارير والإحصائيات</h1>
            <p class="text-gray-500 mt-1 font-medium">نظرة شاملة على أداء متجرك وتحليل البيانات</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('customer.reports.export') }}?start_date={{ request('start_date') }}&end_date={{ request('end_date') }}" 
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#00A8E8] hover:bg-[#0086BA] text-white text-sm font-semibold rounded-xl transition-all shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                تصدير التقرير
            </a>
        </div>
    </div>

    {{-- Date Filter Card --}}
    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm transition-all hover:shadow-md">
        <form method="GET" class="flex flex-col sm:flex-row flex-wrap items-center gap-4">
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <label class="text-sm font-bold text-gray-700 whitespace-nowrap min-w-[70px]">من تاريخ:</label>
                <input type="date" name="start_date" value="{{ request('start_date', now()->startOfMonth()->format('Y-m-d')) }}"
                       class="w-full sm:w-auto rounded-xl border-gray-200 text-sm font-medium focus:ring-[#00A8E8] focus:border-[#00A8E8] transition-all bg-gray-50/50">
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <label class="text-sm font-bold text-gray-700 whitespace-nowrap min-w-[70px]">إلى تاريخ:</label>
                <input type="date" name="end_date" value="{{ request('end_date', now()->format('Y-m-d')) }}"
                       class="w-full sm:w-auto rounded-xl border-gray-200 text-sm font-medium focus:ring-[#00A8E8] focus:border-[#00A8E8] transition-all bg-gray-50/50">
            </div>
            <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-gray-900 hover:bg-black text-white text-sm font-bold rounded-xl transition-all mr-auto sm:mr-0 shadow-sm hover:translate-y-[-1px] active:translate-y-0">
                عرض البيانات
            </button>
        </form>
    </div>

    {{-- Stats Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        {{-- Revenue --}}
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm group hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 transition-transform group-hover:scale-110">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                @if(($summary['revenue_change'] ?? 0) != 0)
                    <span class="text-xs font-bold {{ ($summary['revenue_change'] ?? 0) > 0 ? 'text-green-600 bg-green-50' : 'text-red-600 bg-red-50' }} px-2 py-1 rounded-lg">
                        {{ ($summary['revenue_change'] ?? 0) > 0 ? '+' : '' }}{{ $summary['revenue_change'] }}%
                    </span>
                @endif
            </div>
            <h3 class="text-2xl font-black text-gray-900 mb-1 tracking-tight">{{ number_format($summary['total_revenue'] ?? 0) }} <span class="text-sm font-medium text-gray-400">د.ع</span></h3>
            <p class="text-sm text-gray-500 font-bold tracking-wide">إجمالي الإيرادات</p>
        </div>

        {{-- Orders --}}
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm group hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center text-green-600 transition-transform group-hover:scale-110">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <span class="text-xs font-bold text-gray-500 bg-gray-50 px-2 py-1 rounded-lg">
                    {{ $summary['conversion_rate'] ?? 0 }}% تحويل
                </span>
            </div>
            <h3 class="text-2xl font-black text-gray-900 mb-1 tracking-tight">{{ $summary['total_orders'] ?? 0 }}</h3>
            <p class="text-sm text-gray-500 font-bold tracking-wide">إجمالي الطلبات</p>
        </div>

        {{-- Leads --}}
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm group hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600 transition-transform group-hover:scale-110">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                @if(($summary['leads_change'] ?? 0) != 0)
                    <span class="text-xs font-bold {{ ($summary['leads_change'] ?? 0) > 0 ? 'text-green-600 bg-green-50' : 'text-red-600 bg-red-50' }} px-2 py-1 rounded-lg">
                        {{ ($summary['leads_change'] ?? 0) > 0 ? '+' : '' }}{{ $summary['leads_change'] }}%
                    </span>
                @endif
            </div>
            <h3 class="text-2xl font-black text-gray-900 mb-1 tracking-tight">{{ $summary['total_leads'] ?? 0 }}</h3>
            <p class="text-sm text-gray-500 font-bold tracking-wide">عملاء محتملين</p>
        </div>

        {{-- Messages --}}
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm group hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center text-amber-600 transition-transform group-hover:scale-110">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                </div>
                <span class="text-xs font-bold text-gray-500 bg-gray-50 px-2 py-1 rounded-lg">
                    الرسائل المستلمة
                </span>
            </div>
            <h3 class="text-2xl font-black text-gray-900 mb-1 tracking-tight">{{ $summary['total_messages'] ?? 0 }}</h3>
            <p class="text-sm text-gray-500 font-bold tracking-wide">إجمالي التواصل</p>
        </div>
    </div>

    {{-- Charts Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Sales Chart --}}
        <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <div class="w-2 h-6 bg-[#00A8E8] rounded-full"></div>
                    المبيعات حسب اليوم
                </h3>
                <div class="flex items-center gap-4 text-xs font-bold text-gray-400">
                    <div class="flex items-center gap-1.5">
                        <div class="w-3 h-3 rounded-full bg-[#00A8E8]"></div>
                        <span>الإيرادات</span>
                    </div>
                </div>
            </div>
            <div class="h-[320px] relative w-full overflow-hidden">
                <canvas id="salesChartCanvas"></canvas>
            </div>
        </div>

        {{-- Source Distribution --}}
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <div class="w-2 h-6 bg-indigo-600 rounded-full"></div>
                    تحليل المصادر
                </h3>
            </div>
            <div class="space-y-6">
                @php
                    $totalOrders = 0;
                    if(isset($leadSources)) {
                        foreach($leadSources as $ls) { $totalOrders += $ls->count ?? 0; }
                    }
                @endphp
                @foreach(['facebook' => ['label' => 'فيسبوك', 'color' => '#1877f2', 'bg' => 'bg-blue-50'], 
                          'instagram' => ['label' => 'انستغرام', 'color' => '#e1306c', 'bg' => 'bg-pink-50'], 
                          'whatsapp' => ['label' => 'واتساب', 'color' => '#25d366', 'bg' => 'bg-green-50'], 
                          'manual' => ['label' => 'يدوي', 'color' => '#00A8E8', 'bg' => 'bg-sky-50']] as $source => $data)
                    @php
                        $sourceData = collect($leadSources ?? [])->firstWhere('source', $source);
                        $count = $sourceData->count ?? 0;
                        $percentage = $totalOrders > 0 ? ($count / $totalOrders) * 100 : 0;
                    @endphp
                    <div class="group">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full transition-transform group-hover:scale-150" style="background-color: {{ $data['color'] }}"></div>
                                <span class="text-sm font-bold text-gray-700">{{ $data['label'] }}</span>
                            </div>
                            <span class="text-sm font-black text-gray-900">{{ $count }}</span>
                        </div>
                        <div class="w-full h-2.5 bg-gray-50 rounded-full overflow-hidden border border-gray-100">
                            <div class="h-full rounded-full transition-all duration-1000 ease-out" style="width: {{ $percentage }}%; background-color: {{ $data['color'] }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Detailed Analysis Cards --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 pb-12">
        {{-- Top Products --}}
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <div class="w-2 h-6 bg-amber-500 rounded-full"></div>
                    الأكثر مبيعاً
                </h3>
                <a href="{{ route('customer.reports.products') }}" class="text-sm font-bold text-[#00A8E8] hover:bg-sky-50 px-3 py-1.5 rounded-lg transition-colors">عرض الكل</a>
            </div>
            <div class="space-y-4">
                @forelse($topProducts ?? [] as $index => $product)
                    <div class="flex items-center justify-between p-4 rounded-xl bg-gray-50 border border-transparent hover:border-gray-200 hover:bg-white hover:shadow-sm transition-all group">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center text-sm font-black text-gray-300 border border-gray-100 transition-colors group-hover:text-amber-500">
                                #{{ $index + 1 }}
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-gray-900">{{ $product->product_name }}</h4>
                                <p class="text-xs font-medium text-gray-500">{{ number_format($product->total_revenue) }} <span class="text-[10px]">د.ع</span></p>
                            </div>
                        </div>
                        <div class="text-left">
                            <span class="text-sm font-black text-gray-900">{{ $product->total_sold }}</span>
                            <span class="text-[10px] font-bold text-gray-400 block uppercase">مباع</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-100">
                        <p class="text-sm font-bold text-gray-400">لا توجد بيانات مبيعات متوفرة</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Recent Activity --}}
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <div class="w-2 h-6 bg-blue-500 rounded-full"></div>
                    الطلبيات الأخيرة
                </h3>
                <a href="{{ route('customer.online-orders.index') }}" class="text-sm font-bold text-[#00A8E8] hover:bg-sky-50 px-3 py-1.5 rounded-lg transition-colors">عرض الكل</a>
            </div>
            <div class="space-y-4">
                @forelse($recentOrders ?? [] as $order)
                    <div class="flex items-center justify-between p-4 rounded-xl bg-gray-50 border border-transparent hover:border-gray-200 hover:bg-white hover:shadow-sm transition-all group">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-[#00A8E8]/10 flex items-center justify-center text-[#00A8E8] font-black text-xs transition-transform group-hover:scale-110">
                                {{ mb_substr($order->customer_name, 0, 1) }}
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-gray-900">{{ $order->customer_name }}</h4>
                                <p class="text-[10px] font-bold text-gray-400 tracking-wider">#{{ $order->order_number }} • {{ $order->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <div class="text-left">
                            <span class="text-sm font-black text-[#00A8E8] block">{{ number_format($order->total) }}</span>
                            <span class="text-[9px] font-black text-gray-400">د.ع</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-100">
                        <p class="text-sm font-bold text-gray-400">لا توجد طلبيات حديثة</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const salesChartData = @json($salesChart ?? ['labels' => [], 'revenue' => []]);
    const salesLabels = salesChartData.labels || [];
    const salesRevenue = salesChartData.revenue || [];

    if (salesLabels.length > 0 && document.getElementById('salesChartCanvas')) {
        const ctx = document.getElementById('salesChartCanvas').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(0, 168, 232, 0.2)');
        gradient.addColorStop(1, 'rgba(0, 168, 232, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'الإيرادات',
                    data: salesRevenue,
                    borderColor: '#00A8E8',
                    borderWidth: 3,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#00A8E8',
                    pointBorderWidth: 3,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#00A8E8',
                    pointHoverBorderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        rtl: true,
                        backgroundColor: '#1f2937',
                        padding: 12,
                        cornerRadius: 12,
                        titleFont: { family: 'Tajawal', size: 14, weight: 'bold' },
                        bodyFont: { family: 'Tajawal', size: 13 },
                        callbacks: {
                            label: function(context) {
                                return ' الإيرادات: ' + context.parsed.y.toLocaleString() + ' د.ع';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Tajawal', size: 11 }, color: '#9ca3af' }
                    },
                    y: {
                        grid: { borderDash: [5, 5], color: '#f3f4f6' },
                        ticks: { 
                            font: { family: 'Tajawal', size: 11 }, 
                            color: '#9ca3af',
                            callback: function(value) { return value.toLocaleString(); }
                        }
                    }
                }
            }
        });
    }
});
</script>
@endsection
