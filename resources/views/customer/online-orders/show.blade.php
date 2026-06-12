@extends('layouts.customer')

@section('title', 'تفاصيل الطلب #' . $order->order_number)

@section('content')
<div class="page-header-bar">
    <div class="header-right">
        <a href="{{ route('customer.online-orders.index') }}" class="back-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
            </svg>
        </a>
        <h1 class="page-title">تفاصيل الطلب #{{ $order->order_number }}</h1>
    </div>
    <div class="header-buttons">
        @if($order->conversation)
            <a href="{{ route('customer.online-orders.conversation', $order) }}" class="btn-secondary-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                </svg>
                المحادثة
            </a>
        @endif
        <a href="{{ route('customer.online-orders.print', $order) }}" class="btn-secondary-sm" target="_blank">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
            </svg>
            طباعة
        </a>
        <a href="{{ route('customer.online-orders.edit', $order) }}" class="btn-add-sm">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
            </svg>
            تعديل
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert-success">{{ session('success') }}</div>
@endif

@if(session('warning'))
    <div class="alert-warning">{{ session('warning') }}</div>
@endif

<!-- Order Info Header -->
<div class="order-info-header">
    <div class="order-main-info">
        <div class="order-number-big">#{{ $order->order_number }}</div>
        <div class="order-date">{{ $order->created_at->format('Y/m/d - H:i') }}</div>
        @if($order->notes && str_contains($order->notes, 'InvenGPT'))
            <div class="ai-badge">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                </svg>
                طلب من الذكاء الاصطناعي
            </div>
        @endif
    </div>
    <div class="order-status-section">
        <span class="status-tag large {{ $order->status }}">{{ $order->status_label }}</span>
        <span class="payment-tag {{ $order->payment_status }}">
            {{ $order->payment_status === 'paid' ? 'مدفوع' : 'غير مدفوع' }}
        </span>
    </div>
</div>

<!-- Status Update Form -->
<div class="status-update-bar">
    <form action="{{ route('customer.online-orders.status', $order) }}" method="POST" class="status-form">
        @csrf
        <label>تحديث الحالة:</label>
        <select name="status" class="status-select">
            <option value="pending" {{ $order->status === 'pending' ? 'selected' : '' }}>قيد الانتظار</option>
            <option value="confirmed" {{ $order->status === 'confirmed' ? 'selected' : '' }}>مؤكد</option>
            <option value="processing" {{ $order->status === 'processing' ? 'selected' : '' }}>قيد التجهيز</option>
            <option value="shipped" {{ $order->status === 'shipped' ? 'selected' : '' }}>تم الشحن</option>
            <option value="delivered" {{ $order->status === 'delivered' ? 'selected' : '' }}>تم التوصيل</option>
            <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>ملغي</option>
        </select>
        <button type="submit" class="btn-update-status">تحديث</button>
    </form>
</div>

<div class="detail-grid">
    <!-- Customer Info -->
    <div class="detail-card">
        <div class="card-section-title">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
            </svg>
            معلومات العميل
        </div>

        <div class="info-list">
            <div class="info-item">
                <span class="info-label">الاسم</span>
                <span class="info-value">{{ $order->customer_name }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">الهاتف</span>
                <span class="info-value">
                    <a href="tel:{{ $order->customer_phone }}" class="phone-link">{{ $order->customer_phone }}</a>
                </span>
            </div>
            @if($order->customer_city)
            <div class="info-item">
                <span class="info-label">المدينة</span>
                <span class="info-value">{{ $order->customer_city }}</span>
            </div>
            @endif
            @if($order->customer_address)
            <div class="info-item">
                <span class="info-label">العنوان</span>
                <span class="info-value">{{ $order->customer_address }}</span>
            </div>
            @endif
            @if($order->customer_area)
            <div class="info-item">
                <span class="info-label">المنطقة</span>
                <span class="info-value">{{ $order->customer_area }}</span>
            </div>
            @endif
        </div>

        @if($order->lead)
        <div class="card-action">
            <a href="{{ route('customer.leads.show', $order->lead) }}" class="btn-view-lead">
                عرض ملف العميل
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>
        </div>
        @endif
    </div>

    <!-- Order Summary -->
    <div class="detail-card">
        <div class="card-section-title">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
            </svg>
            ملخص الطلب
        </div>

        <div class="info-list">
            <div class="info-item">
                <span class="info-label">المصدر</span>
                <span class="source-tag {{ $order->source }}">
                    @if($order->source === 'facebook')
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="12" height="12"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    @elseif($order->source === 'instagram')
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="12" height="12"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069z"/></svg>
                    @elseif($order->source === 'ai_chat')
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="12" height="12"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" /></svg>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="12" height="12"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                    @endif
                    {{ $order->source_label }}
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">تاريخ الطلب</span>
                <span class="info-value">{{ $order->created_at->format('Y/m/d H:i') }}</span>
            </div>
            {{-- Notes are stored in the customer profile (lead page) --}}
        </div>
    </div>

    <!-- Order Items -->
    <div class="detail-card full-width">
        <div class="card-section-title">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
            </svg>
            المنتجات
        </div>

        <div class="order-items-table">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>المنتج</th>
                        <th>السعر</th>
                        <th>الكمية</th>
                        <th>المجموع</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($order->items as $item)
                    <tr>
                        <td>
                            <div class="product-cell">
                                @if($item->product && $item->product->primaryImage)
                                    <img src="{{ Storage::url($item->product->primaryImage->image_path) }}" alt="{{ $item->product_name }}" class="product-thumb">
                                @else
                                    <div class="product-thumb-placeholder">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                        </svg>
                                    </div>
                                @endif
                                <span>{{ $item->product_name }}</span>
                            </div>
                        </td>
                        <td>{{ number_format($item->unit_price) }} د.ع</td>
                        <td>{{ $item->quantity }}</td>
                        <td class="item-total">{{ number_format($item->total) }} د.ع</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="empty-cell">لا توجد منتجات</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Order Totals -->
        <div class="order-totals">
            <div class="total-row">
                <span>المجموع الفرعي</span>
                <span>{{ number_format($order->subtotal) }} د.ع</span>
            </div>
            @if($order->discount > 0)
            <div class="total-row discount">
                <span>الخصم</span>
                <span>-{{ number_format($order->discount) }} د.ع</span>
            </div>
            @endif
            @if($order->shipping_cost > 0)
            <div class="total-row">
                <span>التوصيل</span>
                <span>{{ number_format($order->shipping_cost) }} د.ع</span>
            </div>
            @endif
            <div class="total-row grand-total">
                <span>المجموع الكلي</span>
                <span>{{ number_format($order->total) }} د.ع</span>
            </div>
        </div>
    </div>
</div>

<!-- Delete Order -->
<div class="danger-zone">
    <form action="{{ route('customer.online-orders.destroy', $order) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا الطلب؟')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn-danger-sm">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
            </svg>
            حذف الطلب
        </button>
    </form>
</div>
@endsection
