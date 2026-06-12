@extends('layouts.customer')

@section('title', 'المبيعات - لوحة التحكم')

@section('content')
<div class="page-header-bar">
    <h1 class="page-title">المبيعات</h1>
    <a href="{{ route('customer.pos.index') }}" class="btn-add-sm">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        بيع جديد
    </a>
</div>

<!-- Sales Stats -->
<div class="stats-row" style="margin-bottom: 25px;">
    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">مبيعات اليوم</div>
            <div class="stat-value">{{ number_format($todaySales) }} <small>د.ع</small></div>
        </div>
        <div class="stat-icon green">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </div>
    </div>

    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">مبيعات الشهر</div>
            <div class="stat-value">{{ number_format($monthSales) }} <small>د.ع</small></div>
        </div>
        <div class="stat-icon orange">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
            </svg>
        </div>
    </div>

    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">إجمالي العمليات</div>
            <div class="stat-value">{{ number_format($totalSales) }}</div>
        </div>
        <div class="stat-icon blue">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
            </svg>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="filters-bar">
    <form action="{{ route('customer.sales.index') }}" method="GET" class="filters-form">
        <input type="text" name="search" placeholder="بحث برقم الفاتورة أو اسم العميل" value="{{ request('search') }}">
        <input type="date" name="date_from" value="{{ request('date_from') }}" placeholder="من تاريخ">
        <input type="date" name="date_to" value="{{ request('date_to') }}" placeholder="إلى تاريخ">
        <select name="status">
            <option value="">كل الحالات</option>
            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>مكتملة</option>
            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>ملغاة</option>
            <option value="refunded" {{ request('status') == 'refunded' ? 'selected' : '' }}>مسترجعة</option>
        </select>
        <button type="submit" class="btn-filter">بحث</button>
    </form>
</div>

<!-- Sales Table -->
<div class="inventory-table-wrapper">
    <table class="inventory-table">
        <thead>
            <tr>
                <th>رقم الفاتورة</th>
                <th>التاريخ</th>
                <th>العميل</th>
                <th>عدد المنتجات</th>
                <th>الإجمالي</th>
                <th>طريقة الدفع</th>
                <th>الحالة</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sales as $sale)
                <tr>
                    <td>
                        <span class="invoice-number">{{ $sale->invoice_number }}</span>
                    </td>
                    <td>{{ $sale->created_at->format('Y/m/d H:i') }}</td>
                    <td>{{ $sale->customer_name ?? 'عميل نقدي' }}</td>
                    <td>{{ $sale->items->count() }}</td>
                    <td>
                        <strong>{{ number_format($sale->total) }}</strong>
                        {{ $sale->currency == 'USD' ? '$' : 'د.ع' }}
                        @if($sale->discount_amount > 0)
                            <br><small class="text-muted">خصم: {{ number_format($sale->discount_amount) }}</small>
                        @endif
                    </td>
                    <td>
                        <span class="payment-badge {{ $sale->payment_method }}">
                            @if($sale->payment_method == 'cash') نقداً
                            @elseif($sale->payment_method == 'card') بطاقة
                            @else تحويل
                            @endif
                        </span>
                    </td>
                    <td>
                        <span class="status-badge {{ $sale->status }}">
                            @if($sale->status == 'completed') مكتملة
                            @elseif($sale->status == 'cancelled') ملغاة
                            @else مسترجعة
                            @endif
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ route('customer.sales.show', $sale) }}" class="table-action-btn view" title="عرض">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </a>
                            <a href="{{ route('customer.pos.invoice', $sale) }}" class="table-action-btn edit" title="طباعة" target="_blank">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                                </svg>
                            </a>
                            @if($sale->status == 'completed')
                                <form action="{{ route('customer.sales.cancel', $sale) }}" method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من إلغاء هذه العملية؟')">
                                    @csrf
                                    <button type="submit" class="table-action-btn delete" title="إلغاء">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state-new">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                            <h3>لا توجد مبيعات</h3>
                            <p>ابدأ بإجراء عمليات بيع من نقطة البيع</p>
                            <a href="{{ route('customer.pos.index') }}" class="btn-add" style="margin-top: 15px;">
                                بدء البيع
                            </a>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($sales->hasPages())
        <div class="pagination-wrapper">
            {{ $sales->links() }}
        </div>
    @endif
</div>
@endsection
