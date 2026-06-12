@extends('layouts.customer')

@section('title', 'الطلبات')

@section('content')
<div class="page-header-bar">
    <h1 class="page-title">الطلبات</h1>
    <div class="header-buttons">
        <a href="{{ route('customer.online-orders.export', request()->query()) }}" class="btn-secondary-sm">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            تصدير
        </a>
        <a href="{{ route('customer.online-orders.create') }}" class="btn-add-sm">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            طلب جديد
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="stats-cards">
    <div class="stat-card-mini">
        <span class="stat-value">{{ number_format($stats['total']) }}</span>
        <span class="stat-label">إجمالي الطلبات</span>
        <span class="stat-sub">اليوم: {{ $stats['today_orders'] }}</span>
    </div>
    <div class="stat-card-mini">
        <span class="stat-value" style="color: #22c55e;">{{ number_format($stats['total_revenue']) }}</span>
        <span class="stat-label">إجمالي الإيرادات</span>
        <span class="stat-sub">اليوم: {{ number_format($stats['today_revenue']) }} د.ع</span>
    </div>
    <div class="stat-card-mini">
        <span class="stat-value" style="color: #f59e0b;">{{ number_format($stats['pending']) }}</span>
        <span class="stat-label">قيد الانتظار</span>
    </div>
    <div class="stat-card-mini">
        <span class="stat-value" style="color: #22c55e;">{{ number_format($stats['delivered']) }}</span>
        <span class="stat-label">تم التوصيل</span>
    </div>
</div>

<!-- Status Tabs -->
<div class="orders-section">
    <div class="orders-header">
        <div class="orders-tabs">
            <a href="{{ route('customer.online-orders.index') }}" class="orders-tab {{ !request('status') ? 'active' : '' }}">
                الكل <span class="tab-count">{{ $stats['total'] }}</span>
            </a>
            <a href="{{ route('customer.online-orders.index', ['status' => 'pending']) }}" class="orders-tab {{ request('status') == 'pending' ? 'active' : '' }}">
                قيد الانتظار <span class="tab-count">{{ $stats['pending'] }}</span>
            </a>
            <a href="{{ route('customer.online-orders.index', ['status' => 'confirmed']) }}" class="orders-tab {{ request('status') == 'confirmed' ? 'active' : '' }}">
                مؤكد <span class="tab-count">{{ $stats['confirmed'] }}</span>
            </a>
            <a href="{{ route('customer.online-orders.index', ['status' => 'processing']) }}" class="orders-tab {{ request('status') == 'processing' ? 'active' : '' }}">
                قيد التجهيز <span class="tab-count">{{ $stats['processing'] }}</span>
            </a>
            <a href="{{ route('customer.online-orders.index', ['status' => 'shipped']) }}" class="orders-tab {{ request('status') == 'shipped' ? 'active' : '' }}">
                تم الشحن <span class="tab-count">{{ $stats['shipped'] }}</span>
            </a>
            <a href="{{ route('customer.online-orders.index', ['status' => 'delivered']) }}" class="orders-tab {{ request('status') == 'delivered' ? 'active' : '' }}">
                تم التوصيل <span class="tab-count">{{ $stats['delivered'] }}</span>
            </a>
            <a href="{{ route('customer.online-orders.index', ['status' => 'cancelled']) }}" class="orders-tab {{ request('status') == 'cancelled' ? 'active' : '' }}">
                ملغي <span class="tab-count">{{ $stats['cancelled'] }}</span>
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-inline">
        <form action="{{ route('customer.online-orders.index') }}" method="GET" class="filters-form">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <input type="text" name="search" class="filter-input" placeholder="رقم الطلب، اسم العميل..." value="{{ request('search') }}">
            <select name="source" class="filter-select">
                <option value="">كل المصادر</option>
                <option value="facebook" {{ request('source') == 'facebook' ? 'selected' : '' }}>فيسبوك</option>
                <option value="instagram" {{ request('source') == 'instagram' ? 'selected' : '' }}>انستغرام</option>
                <option value="whatsapp" {{ request('source') == 'whatsapp' ? 'selected' : '' }}>واتساب</option>
                <option value="manual" {{ request('source') == 'manual' ? 'selected' : '' }}>يدوي</option>
            </select>
            <input type="date" name="from_date" class="filter-input" value="{{ request('from_date') }}" placeholder="من تاريخ">
            <input type="date" name="to_date" class="filter-input" value="{{ request('to_date') }}" placeholder="إلى تاريخ">
            <button type="submit" class="btn-filter">بحث</button>
        </form>
    </div>

    <!-- Orders Table -->
    <div class="inventory-table-wrapper" style="border: none; margin-top: 20px;">
        <table class="inventory-table">
            <thead>
                <tr>
                    <th>رقم الطلب</th>
                    <th>العميل</th>
                    <th>المنتجات</th>
                    <th>المجموع</th>
                    <th>المصدر</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td>
                            <span class="order-number">#{{ $order->order_number }}</span>
                        </td>
                        <td>
                            <div class="customer-cell">
                                @if($order->lead && $order->lead->profile_image)
                                    <div class="customer-avatar {{ $order->source }}" style="padding: 0; overflow: hidden;">
                                        <img src="{{ $order->lead->profile_image }}" alt="{{ $order->customer_name }}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                    </div>
                                @else
                                    <div class="customer-avatar {{ $order->source }}">
                                        {{ mb_substr($order->customer_name, 0, 1) }}
                                    </div>
                                @endif
                                <div>
                                    <div class="customer-name">{{ $order->customer_name }}</div>
                                    <div class="customer-phone">{{ $order->customer_phone }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="products-list">
                                @foreach($order->items->take(2) as $item)
                                    <span class="product-item">{{ $item->product_name }} x{{ $item->quantity }}</span>
                                @endforeach
                                @if($order->items->count() > 2)
                                    <span class="more-items">+{{ $order->items->count() - 2 }} منتج آخر</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <span class="order-total">{{ number_format($order->total) }} د.ع</span>
                        </td>
                        <td>
                            <span class="source-tag {{ $order->source }}">
                                @if($order->source === 'facebook')
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="12" height="12"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                @elseif($order->source === 'instagram')
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="12" height="12"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="12" height="12"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                                @endif
                                {{ $order->source_label }}
                            </span>
                        </td>
                        <td>
                            <span class="status-tag {{ $order->status }}">{{ $order->status_label }}</span>
                        </td>
                        <td>
                            <div class="date-cell">
                                <span>{{ $order->created_at->format('Y/m/d') }}</span>
                                <span class="time">{{ $order->created_at->format('H:i') }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="{{ route('customer.online-orders.show', $order) }}" class="action-btn" title="عرض">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </a>
                                @if($order->conversation)
                                    <a href="{{ route('customer.online-orders.conversation', $order) }}" class="action-btn chat" title="المحادثة">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                                        </svg>
                                    </a>
                                @endif
                                <a href="{{ route('customer.online-orders.print', $order) }}" class="action-btn" title="طباعة" target="_blank">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="empty-state-new">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                </svg>
                                <h3>لا توجد طلبات</h3>
                                <p>ستظهر الطلبات هنا عند استلامها من العملاء</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($orders->hasPages())
    <div class="pagination-wrapper">
        {{ $orders->links() }}
    </div>
@endif
@endsection
