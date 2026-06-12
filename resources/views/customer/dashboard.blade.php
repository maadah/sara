@extends('layouts.customer')

@section('title', 'الرئيسية - لوحة التحكم')

@section('content')
{{-- Connection Status Banner --}}
@if(($linkedAccountsCount ?? 0) === 0)
<div class="mb-6 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-2xl p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div class="flex items-start gap-3">
        <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 shrink-0 mt-0.5">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <h4 class="font-bold text-gray-900 text-sm">لم يتم ربط أي حساب بعد!</h4>
            <p class="text-gray-600 text-sm mt-0.5">اربط صفحة فيسبوك أو حساب انستقرام لبدء استقبال الرسائل والرد التلقائي عبر الذكاء الاصطناعي</p>
        </div>
    </div>
    <a href="{{ route('customer.social-accounts.index') }}"
       class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-colors whitespace-nowrap shadow-sm">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
            <path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/>
        </svg>
        ربط حساب الآن
    </a>
</div>
@endif

<!-- Welcome Section -->
<div class="mb-6 sm:mb-8">
    <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 tracking-tight">مرحباً، {{ auth()->user()->name }} 👋</h2>
    <p class="text-gray-500 mt-1 sm:mt-2 text-base sm:text-lg">إليك نظرة سريعة على أداء متجرك اليوم</p>
</div>

<!-- Stats Grid -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6 mb-6 sm:mb-8">
    <!-- Stat Card 1: Active Conversations -->
    <div class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow duration-300 border border-gray-100 group">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-gray-500 font-medium mb-1">المحادثات النشطة</p>
                <h3 class="text-3xl font-bold text-gray-900 group-hover:text-[#00A8E8] transition-colors">{{ $activeConversations }}</h3>
            </div>
            <div class="w-12 h-12 bg-[#00A8E8]/10 rounded-xl flex items-center justify-center text-[#00A8E8]">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4 flex items-center text-sm">
            <span class="text-green-500 font-semibold flex items-center">
                <svg class="w-4 h-4 me-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                {{ $conversationChange }}%
            </span>
            <span class="text-gray-400 ms-2">من الأسبوع الماضي</span>
        </div>
    </div>

    <!-- Stat Card 2: New Customers -->
    <div class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow duration-300 border border-gray-100 group">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-gray-500 font-medium mb-1">العملاء الجدد</p>
                <h3 class="text-3xl font-bold text-gray-900 group-hover:text-[#00A8E8] transition-colors">{{ $newCustomers }}</h3>
            </div>
            <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4 flex items-center text-sm">
            <span class="text-green-500 font-semibold flex items-center">
                <svg class="w-4 h-4 me-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                {{ $customerChange }}%
            </span>
            <span class="text-gray-400 ms-2">من الأسبوع الماضي</span>
        </div>
    </div>

    <!-- Stat Card 3: Pending Orders -->
    <div class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow duration-300 border border-gray-100 group">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-gray-500 font-medium mb-1">الطلبات المعلقة</p>
                <h3 class="text-3xl font-bold text-gray-900 group-hover:text-[#00A8E8] transition-colors">{{ $pendingOrders }}</h3>
            </div>
            <div class="w-12 h-12 bg-yellow-50 rounded-xl flex items-center justify-center text-yellow-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4 flex items-center text-sm">
            <span class="text-gray-400">{{ $totalOrders }} طلب إجمالي</span>
        </div>
    </div>

    <!-- Stat Card 4: Total Revenue -->
    <div class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow duration-300 border border-gray-100 group">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-gray-500 font-medium mb-1">إجمالي الإيرادات</p>
                <h3 class="text-3xl font-bold text-gray-900 group-hover:text-[#00A8E8] transition-colors">{{ number_format($totalRevenue) }} <small class="text-lg">د.ع</small></h3>
            </div>
            <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center text-green-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4 flex items-center text-sm">
            <span class="text-green-500 font-semibold flex items-center">
                <svg class="w-4 h-4 me-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                {{ $revenueChange }}%
            </span>
            <span class="text-gray-400 ms-2">من الأسبوع الماضي</span>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Main Chart Area -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-gray-800">تحليل المبيعات</h3>
            <span class="bg-gray-50 border border-gray-200 text-gray-700 text-sm rounded-lg px-3 py-2">آخر 7 أيام</span>
        </div>

        <div class="h-80 relative w-full">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Right Side: Quick Actions & Recent Activity -->
    <div class="space-y-8">
        <!-- Quick Actions -->
        <div class="bg-gradient-to-br from-[#00A8E8] to-[#00658B] rounded-2xl p-6 text-white shadow-lg">
            <h3 class="text-lg font-bold mb-4">إجراء سريع</h3>
            <div class="space-y-3">
                <a href="{{ route('customer.products.create') }}" class="w-full bg-white/20 hover:bg-white/30 backdrop-blur-sm border border-white/10 rounded-xl p-3 flex items-center transition-colors">
                    <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center me-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                    <span class="font-medium">إضافة منتج</span>
                </a>
                <a href="{{ route('customer.social-accounts.index') }}" class="w-full bg-white/20 hover:bg-white/30 backdrop-blur-sm border border-white/10 rounded-xl p-3 flex items-center transition-colors">
                    <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center me-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                        </svg>
                    </div>
                    <span class="font-medium">ربط المنصات</span>
                </a>
                <a href="{{ route('customer.analytics.index') }}" class="w-full bg-white/20 hover:bg-white/30 backdrop-blur-sm border border-white/10 rounded-xl p-3 flex items-center transition-colors">
                    <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center me-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <span class="font-medium">التحليلات</span>
                </a>
            </div>
        </div>

        <!-- Recent Activity List -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800">نشاط حديث</h3>
            </div>

            <div class="space-y-4">
                @forelse($recentActivities as $activity)
                <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-100">
                    <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 font-bold text-xs border border-gray-200">
                        {{ mb_substr($activity['user'], 0, 1) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate">{{ $activity['user'] }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ $activity['action'] }}</p>
                    </div>
                    <span class="text-xs text-gray-400 whitespace-nowrap">{{ $activity['time'] }}</span>
                </div>
                @empty
                <div class="text-center py-6">
                    <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p class="text-sm text-gray-400">لا يوجد نشاط حديث</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Inventory Overview Section -->
<div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Inventory Analysis -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
            <svg class="w-5 h-5 text-[#00A8E8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>
            تحليل المخزون
        </h3>
        <div class="space-y-4">
            <div class="flex items-center justify-between p-3 rounded-xl bg-green-50/50">
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                    <span class="text-sm text-gray-700">منتجات متوفرة بكمية جيدة</span>
                </div>
                <span class="text-sm font-bold text-gray-900">{{ $visitorStats['in_stock_percentage'] }}%</span>
            </div>
            <div class="flex items-center justify-between p-3 rounded-xl bg-blue-50/50">
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                    <span class="text-sm text-gray-700">منتجات نشطة</span>
                </div>
                <span class="text-sm font-bold text-gray-900">{{ $visitorStats['active_percentage'] }}%</span>
            </div>
            <div class="flex items-center justify-between p-3 rounded-xl bg-amber-50/50">
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 rounded-full bg-amber-500"></div>
                    <span class="text-sm text-gray-700">مخزون منخفض (أقل من 10)</span>
                </div>
                <span class="text-sm font-bold text-gray-900">{{ $visitorStats['low_stock_percentage'] }}%</span>
            </div>
            <div class="flex items-center justify-between p-3 rounded-xl bg-red-50/50">
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 rounded-full bg-red-500"></div>
                    <span class="text-sm text-gray-700">منتجات منتهية الصلاحية</span>
                </div>
                <span class="text-sm font-bold text-gray-900">{{ $visitorStats['expired_percentage'] }}%</span>
            </div>
        </div>
        @if($visitorStats['total_products'] > 0)
            <div class="mt-4 pt-4 border-t border-gray-100 text-center">
                <span class="text-sm text-gray-500">إجمالي المنتجات: <strong class="text-gray-900">{{ $visitorStats['total_products'] }}</strong></span>
            </div>
        @endif
    </div>

    <!-- Products Chart -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
            توزيع المنتجات حسب الفئة
        </h3>
        <div class="h-64 relative flex items-center justify-center">
            @if(empty($categoryDistribution))
                <div class="text-center text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-2 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/></svg>
                    <p>أضف منتجات وفئات لرؤية التوزيع</p>
                </div>
            @else
                <canvas id="categoryChart"></canvas>
            @endif
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Global defaults
        Chart.defaults.font.family = "'Tajawal', 'Cairo', sans-serif";
        Chart.defaults.color = '#6b7280';

        // Sales Chart (Line)
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const gradient = salesCtx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(0, 168, 232, 0.5)');
        gradient.addColorStop(1, 'rgba(0, 168, 232, 0)');

        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: @json($chartData['labels']),
                datasets: [{
                    label: 'المبيعات',
                    data: @json($chartData['data']),
                    borderColor: '#00A8E8',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#00A8E8',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(255, 255, 255, 0.9)',
                        titleColor: '#1f2937',
                        bodyColor: '#4b5563',
                        borderColor: '#e5e7eb',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' د.ع';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { font: { family: "'Tajawal', sans-serif" } }
                    },
                    y: {
                        grid: { color: '#f3f4f6', borderDash: [5, 5], drawBorder: false },
                        beginAtZero: true,
                        ticks: { font: { family: "'Tajawal', sans-serif" } }
                    }
                },
                interaction: { mode: 'nearest', axis: 'x', intersect: false }
            }
        });

        // Category Distribution Chart (Doughnut)
        @if(!empty($categoryDistribution))
        const catCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: @json(array_keys($categoryDistribution)),
                datasets: [{
                    data: @json(array_values($categoryDistribution)),
                    backgroundColor: ['#00A8E8', '#6366f1', '#ec4899', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { usePointStyle: true, padding: 15, font: { family: "'Tajawal', sans-serif" } }
                    }
                }
            }
        });
        @endif
    });
</script>
@endsection
