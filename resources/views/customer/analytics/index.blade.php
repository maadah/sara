@extends('layouts.customer')

@section('title', 'التحليلات')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-bold text-gray-800">التحليلات والأداء</h2>
            <p class="text-gray-500 mt-1">نظرة شاملة على أداء متجرك وتفاعلات عملائك</p>
        </div>
        <div class="flex gap-2">
            <form method="GET" action="{{ route('customer.analytics.index') }}">
                <select name="period" onchange="this.form.submit()" class="rounded-lg border-gray-200 text-sm px-3 py-2">
                    <option value="7days" {{ $period == '7days' ? 'selected' : '' }}>آخر 7 أيام</option>
                    <option value="30days" {{ $period == '30days' ? 'selected' : '' }}>آخر 30 يوم</option>
                    <option value="month" {{ $period == 'month' ? 'selected' : '' }}>هذا الشهر</option>
                </select>
            </form>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-500">متوسط وقت الرد</h3>
                <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-gray-900">{{ number_format($metrics['average_response_time'], 1) }} دقيقة</div>
            <div class="mt-2 text-xs text-gray-500">سرعة الاستجابة</div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-500">معدل التحويل</h3>
                <div class="p-2 bg-green-50 rounded-lg text-green-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-gray-900">{{ number_format($metrics['conversion_rate'], 1) }}%</div>
            <div class="mt-2 text-xs text-green-600">محادثات → طلبات</div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-500">عملاء ساخنون 🔥</h3>
                <div class="p-2 bg-red-50 rounded-lg text-red-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-gray-900">{{ $metrics['hot_leads'] }}</div>
            <div class="mt-2 text-xs text-red-600">جاهزون للشراء الآن</div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-500">إجمالي المبيعات</h3>
                <div class="p-2 bg-purple-50 rounded-lg text-purple-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-bold text-gray-900">{{ number_format($metrics['total_revenue']) }} د.ع</div>
            <div class="mt-2 text-xs text-purple-600">{{ $metrics['total_conversations'] }} محادثة</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Sales Activity Chart -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                نشاط المبيعات
            </h3>
            <div class="h-64 relative">
                @if(empty($ordersData) || array_sum($ordersData) == 0)
                    <div class="absolute inset-0 flex items-center justify-center text-gray-400">لا توجد مبيعات كافية في هذه الفترة</div>
                @else
                    <canvas id="salesChart"></canvas>
                @endif
            </div>
        </div>

        <!-- Conversations Volume Chart -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                حجم المحادثات
            </h3>
            <div class="h-64 relative">
                @if(empty(array_filter($conversationsChart)))
                    <div class="absolute inset-0 flex items-center justify-center text-gray-400">لا توجد محادثات في هذه الفترة</div>
                @else
                    <canvas id="conversationsChart"></canvas>
                @endif
            </div>
        </div>

        <!-- Category Distribution Chart -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                    توزيع المنتجات حسب الفئات
            </h3>
            <div class="h-64 relative flex items-center justify-center">
                @if(empty($categoryDistribution))
                    <div class="absolute inset-0 flex items-center justify-center text-gray-400">لم يتم تصنيف أي محادثات بعد</div>
                @else
                    <canvas id="categoryChart"></canvas>
                @endif
            </div>
        </div>

        <!-- Lead Statuses Chart -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                حالة العملاء المحتملين (Leads)
            </h3>
            <div class="h-64 relative flex items-center justify-center">
                @if(empty(array_filter($leadsStatus)))
                    <div class="absolute inset-0 flex items-center justify-center text-gray-400">لا توجد بيانات للعملاء المحتملين</div>
                @else
                    <canvas id="leadsChart"></canvas>
                @endif
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Chart.defaults.font.family = "'Tajawal', 'Cairo', sans-serif";
    Chart.defaults.color = '#6b7280';

    // 1. Sales Activity (Line Chart)
    const salesData = @json($ordersData);
    if(Object.keys(salesData).length > 0 && Object.values(salesData).some(v => v > 0)) {
        const ctxSales = document.getElementById('salesChart').getContext('2d');
        let gradientSales = ctxSales.createLinearGradient(0, 0, 0, 300);
        gradientSales.addColorStop(0, 'rgba(99, 102, 241, 0.5)');
        gradientSales.addColorStop(1, 'rgba(99, 102, 241, 0.05)');

        new Chart(ctxSales, {
            type: 'line',
            data: {
                labels: Object.keys(salesData).map(date => {
                    let d = new Date(date);
                    return d.toLocaleDateString('ar-EG', { month: 'short', day: 'numeric' });
                }),
                datasets: [{
                    label: 'عدد الطلبات',
                    data: Object.values(salesData),
                    borderColor: '#4f46e5',
                    backgroundColor: gradientSales,
                    borderWidth: 2,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#4f46e5',
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
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#1f2937', padding: 12, displayColors: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 45, minRotation: 45 } },
                    y: { grid: { color: '#f3f4f6' }, beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    // 2. Conversation Volume (Bar Chart)
    const convData = @json($conversationsChart);
    if(Object.keys(convData).length > 0 && Object.values(convData).some(v => v > 0)) {
        const ctxConv = document.getElementById('conversationsChart').getContext('2d');
        new Chart(ctxConv, {
            type: 'bar',
            data: {
                labels: Object.keys(convData).map(date => {
                    let d = new Date(date);
                    return d.toLocaleDateString('ar-EG', { month: 'short', day: 'numeric' });
                }),
                datasets: [{
                    label: 'المحادثات',
                    data: Object.values(convData),
                    backgroundColor: '#3b82f6',
                    borderRadius: 6,
                    barPercentage: 0.6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#1f2937', padding: 12, displayColors: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: '#f3f4f6' }, beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    // 3. Category Distribution (Doughnut Chart)
    const catData = @json($categoryDistribution);
    if(Object.keys(catData).length > 0) {
        const ctxCat = document.getElementById('categoryChart').getContext('2d');
        new Chart(ctxCat, {
            type: 'doughnut',
            data: {
                labels: Object.keys(catData),
                datasets: [{
                    data: Object.values(catData),
                    backgroundColor: ['#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#6366f1', '#ef4444'],
                    borderWidth: 0, hoverOffset: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '70%',
                plugins: { legend: { position: 'right', labels: { usePointStyle: true, padding: 20 } } }
            }
        });
    }

    // 4. Leads Status (Polar Area Chart)
    const rawLeads = @json($leadsStatus);
    if(Object.keys(rawLeads).length > 0 && Object.values(rawLeads).some(v => v > 0)) {
        const statusMap = {
            'new': { label: 'جديد', color: '#3b82f6' },
            'contacted': { label: 'تم التواصل', color: '#f59e0b' },
            'qualified': { label: 'مؤهل', color: '#8b5cf6' },
            'hot': { label: 'ساخن', color: '#ef4444' },
            'won': { label: 'تم البيع', color: '#10b981' },
            'lost': { label: 'ضائع', color: '#6b7280' }
        };

        let leadLabels = [], leadValues = [], leadColors = [];
        for (const [key, value] of Object.entries(rawLeads)) {
            if(statusMap[key]) {
                leadLabels.push(statusMap[key].label);
                leadValues.push(value);
                leadColors.push(statusMap[key].color);
            } else {
                leadLabels.push(key);
                leadValues.push(value);
                leadColors.push('#6b7280');
            }
        }

        const ctxLeads = document.getElementById('leadsChart').getContext('2d');
        new Chart(ctxLeads, {
            type: 'polarArea',
            data: {
                labels: leadLabels,
                datasets: [{ data: leadValues, backgroundColor: leadColors.map(c => c + 'CC'), borderWidth: 2, borderColor: '#ffffff' }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { usePointStyle: true, padding: 15 } } },
                scales: { r: { ticks: { display: false }, grid: { color: '#f3f4f6' } } }
            }
        });
    }
});
</script>
@endsection
