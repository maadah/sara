@extends('layouts.customer')

@section('title', 'تقرير المنتجات')

@section('content')
<div class="page-container">
    <!-- Header -->
    <div class="page-header-bar">
        <div>
            <h1 class="page-title">تقرير المنتجات</h1>
            <p class="page-subtitle">أداء المنتجات والفئات</p>
        </div>
        <a href="{{ route('customer.reports.index') }}" class="btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            العودة للتقارير
        </a>
    </div>

    <!-- Date Filter -->
    <div class="filter-card">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>من تاريخ</label>
                <input type="date" name="from_date" value="{{ $fromDate->format('Y-m-d') }}">
            </div>
            <div class="filter-group">
                <label>إلى تاريخ</label>
                <input type="date" name="to_date" value="{{ $toDate->format('Y-m-d') }}">
            </div>
            <button type="submit" class="btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                تصفية
            </button>
        </form>
    </div>

    <!-- Top Products -->
    <div class="content-card">
        <div class="card-header">
            <h2>
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                أفضل المنتجات مبيعاً
            </h2>
        </div>
        <div class="card-body">
            @if($topProducts->count() > 0)
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المنتج</th>
                                <th>الفئة</th>
                                <th>الكمية المباعة</th>
                                <th>إجمالي الإيرادات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topProducts as $index => $product)
                                <tr>
                                    <td>
                                        @if($index < 3)
                                            <span class="rank-badge rank-{{ $index + 1 }}">{{ $index + 1 }}</span>
                                        @else
                                            {{ $index + 1 }}
                                        @endif
                                    </td>
                                    <td>
                                        <div class="product-cell">
                                            @if($product['image'])
                                                <img src="{{ asset('storage/' . $product['image']) }}" alt="{{ $product['name'] }}" class="product-thumb">
                                            @else
                                                <div class="product-thumb-placeholder">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                                </div>
                                            @endif
                                            <span>{{ $product['name'] }}</span>
                                        </div>
                                    </td>
                                    <td>{{ $product['category'] ?? 'بدون فئة' }}</td>
                                    <td>
                                        <span class="quantity-badge">{{ number_format($product['total_sold']) }}</span>
                                    </td>
                                    <td>
                                        <span class="revenue-amount">{{ number_format($product['total_revenue'], 2) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                    <p>لا توجد مبيعات في هذه الفترة</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Categories Performance -->
    <div class="content-card">
        <div class="card-header">
            <h2>
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"/></svg>
                أداء الفئات
            </h2>
        </div>
        <div class="card-body">
            @if($categoriesPerformance->count() > 0)
                <div class="categories-grid">
                    @foreach($categoriesPerformance as $category)
                        <div class="category-card">
                            <div class="category-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                            </div>
                            <div class="category-info">
                                <h3>{{ $category['name'] }}</h3>
                                <div class="category-stats">
                                    <div class="stat">
                                        <span class="stat-value">{{ number_format($category['total_sold']) }}</span>
                                        <span class="stat-label">قطعة مباعة</span>
                                    </div>
                                    <div class="stat">
                                        <span class="stat-value">{{ number_format($category['total_revenue'], 2) }}</span>
                                        <span class="stat-label">إيرادات</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                    <p>لا توجد بيانات للفئات</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
    .page-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .page-header-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .page-title {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-color);
        margin: 0 0 4px 0;
    }

    .page-subtitle {
        font-size: 14px;
        color: var(--text-muted);
        margin: 0;
    }

    .btn-secondary {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: var(--card-background);
        color: var(--text-color);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        text-decoration: none;
        font-size: 14px;
        transition: all 0.2s;
    }

    .btn-secondary:hover {
        background: var(--background-color);
    }

    .filter-card {
        background: var(--card-background);
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 24px;
        border: 1px solid var(--border-color);
    }

    .filter-form {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .filter-group {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .filter-group label {
        font-size: 14px;
        color: var(--text-muted);
    }

    .filter-group input[type="date"] {
        padding: 8px 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 14px;
        background: var(--background-color);
        color: var(--text-color);
    }

    .btn-primary {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-primary:hover {
        background: var(--primary-hover);
    }

    .content-card {
        background: var(--card-background);
        border-radius: 16px;
        border: 1px solid var(--border-color);
        margin-bottom: 24px;
        overflow: hidden;
    }

    .card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
    }

    .card-header h2 {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-color);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-header h2 svg {
        color: var(--primary-color);
    }

    .card-body {
        padding: 24px;
    }

    .table-responsive {
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th,
    .data-table td {
        padding: 14px 16px;
        text-align: right;
        border-bottom: 1px solid var(--border-color);
    }

    .data-table th {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-muted);
        background: var(--background-color);
    }

    .data-table td {
        font-size: 14px;
        color: var(--text-color);
    }

    .data-table tbody tr:hover {
        background: var(--background-color);
    }

    .data-table tbody tr:last-child td {
        border-bottom: none;
    }

    .rank-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        font-size: 13px;
        font-weight: 600;
    }

    .rank-badge.rank-1 {
        background: linear-gradient(135deg, #ffd700, #ffb800);
        color: #000;
    }

    .rank-badge.rank-2 {
        background: linear-gradient(135deg, #c0c0c0, #a8a8a8);
        color: #000;
    }

    .rank-badge.rank-3 {
        background: linear-gradient(135deg, #cd7f32, #b87333);
        color: #fff;
    }

    .product-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .product-thumb {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        object-fit: cover;
    }

    .product-thumb-placeholder {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: var(--background-color);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
    }

    .quantity-badge {
        display: inline-block;
        padding: 4px 12px;
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
    }

    .revenue-amount {
        font-weight: 600;
        color: #22c55e;
    }

    .categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 16px;
    }

    .category-card {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 20px;
        background: var(--background-color);
        border-radius: 12px;
        border: 1px solid var(--border-color);
    }

    .category-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: rgba(149, 128, 255, 0.1);
        color: var(--primary-color);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .category-info {
        flex: 1;
    }

    .category-info h3 {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-color);
        margin: 0 0 10px 0;
    }

    .category-stats {
        display: flex;
        gap: 20px;
    }

    .category-stats .stat {
        display: flex;
        flex-direction: column;
    }

    .category-stats .stat-value {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-color);
    }

    .category-stats .stat-label {
        font-size: 12px;
        color: var(--text-muted);
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-muted);
    }

    .empty-state svg {
        margin-bottom: 16px;
        opacity: 0.5;
    }

    .empty-state p {
        margin: 0;
        font-size: 14px;
    }

    @media (max-width: 768px) {
        .page-header-bar {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }

        .filter-form {
            flex-direction: column;
            align-items: stretch;
        }

        .filter-group {
            flex-direction: column;
            align-items: stretch;
        }

        .categories-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection
