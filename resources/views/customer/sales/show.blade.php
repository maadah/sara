@extends('layouts.customer')

@section('title', 'تفاصيل البيع - لوحة التحكم')

@section('content')
<div class="page-header-bar">
    <div class="header-with-back">
        <a href="{{ route('customer.sales.index') }}" class="back-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
            </svg>
        </a>
        <h1 class="page-title">تفاصيل الفاتورة #{{ $sale->invoice_number }}</h1>
    </div>
    <a href="{{ route('customer.pos.invoice', $sale) }}" class="btn-add-sm" target="_blank">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
        </svg>
        طباعة
    </a>
</div>

<div class="sale-details-grid">
    <!-- Sale Info Card -->
    <div class="detail-card">
        <h3 class="detail-card-title">معلومات الفاتورة</h3>
        <div class="detail-card-content">
            <div class="detail-row">
                <span class="detail-label">رقم الفاتورة</span>
                <span class="detail-value invoice-number">{{ $sale->invoice_number }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">التاريخ</span>
                <span class="detail-value">{{ $sale->created_at->format('Y/m/d') }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">الوقت</span>
                <span class="detail-value">{{ $sale->created_at->format('h:i A') }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">الحالة</span>
                <span class="status-badge {{ $sale->status }}">
                    @if($sale->status == 'completed') مكتملة
                    @elseif($sale->status == 'cancelled') ملغاة
                    @else مسترجعة
                    @endif
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">طريقة الدفع</span>
                <span class="payment-badge {{ $sale->payment_method }}">
                    @if($sale->payment_method == 'cash') نقداً
                    @elseif($sale->payment_method == 'card') بطاقة
                    @else تحويل
                    @endif
                </span>
            </div>
        </div>
    </div>

    <!-- Customer Info Card -->
    <div class="detail-card">
        <h3 class="detail-card-title">معلومات العميل</h3>
        <div class="detail-card-content">
            <div class="detail-row">
                <span class="detail-label">اسم العميل</span>
                <span class="detail-value">{{ $sale->customer_name ?? 'عميل نقدي' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">رقم الهاتف</span>
                <span class="detail-value">{{ $sale->customer_phone ?? '-' }}</span>
            </div>
            @if($sale->notes)
                <div class="detail-row">
                    <span class="detail-label">ملاحظات</span>
                    <span class="detail-value">{{ $sale->notes }}</span>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Items Table -->
<div class="detail-card" style="margin-top: 20px;">
    <h3 class="detail-card-title">المنتجات المباعة</h3>
    <table class="sale-items-table">
        <thead>
            <tr>
                <th>#</th>
                <th>المنتج</th>
                <th>سعر الوحدة</th>
                <th>الكمية</th>
                <th>الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ number_format($item->unit_price) }} {{ $sale->currency == 'USD' ? '$' : 'د.ع' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->total) }} {{ $sale->currency == 'USD' ? '$' : 'د.ع' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-left">المجموع الفرعي</td>
                <td>{{ number_format($sale->subtotal) }} {{ $sale->currency == 'USD' ? '$' : 'د.ع' }}</td>
            </tr>
            @if($sale->discount_amount > 0)
                <tr class="discount-row">
                    <td colspan="4" class="text-left">
                        الخصم
                        @if($sale->discount_percentage > 0)
                            ({{ $sale->discount_percentage }}%)
                        @endif
                    </td>
                    <td>-{{ number_format($sale->discount_amount) }} {{ $sale->currency == 'USD' ? '$' : 'د.ع' }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td colspan="4" class="text-left">الإجمالي النهائي</td>
                <td><strong>{{ number_format($sale->total) }} {{ $sale->currency == 'USD' ? '$' : 'د.ع' }}</strong></td>
            </tr>
        </tfoot>
    </table>
</div>

@if($sale->status == 'completed')
    <div class="action-buttons" style="margin-top: 20px;">
        <form action="{{ route('customer.sales.cancel', $sale) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من إلغاء هذه العملية؟ سيتم إرجاع المخزون.')">
            @csrf
            <button type="submit" class="btn-delete">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 18px; height: 18px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
                إلغاء العملية
            </button>
        </form>
    </div>
@endif

<style>
    .sale-details-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .detail-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px;
    }

    .detail-card-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-light);
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-color);
    }

    .detail-card-content {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .detail-label {
        color: var(--text-muted);
        font-size: 14px;
    }

    .detail-value {
        color: var(--text-light);
        font-size: 14px;
        font-weight: 500;
    }

    .sale-items-table {
        width: 100%;
        border-collapse: collapse;
    }

    .sale-items-table th,
    .sale-items-table td {
        padding: 12px;
        text-align: right;
        border-bottom: 1px solid var(--border-color);
    }

    .sale-items-table th {
        background: var(--bg-darker);
        font-weight: 600;
        font-size: 13px;
        color: var(--text-light);
    }

    .sale-items-table td {
        font-size: 14px;
        color: var(--text-light);
    }

    .sale-items-table tfoot td {
        font-size: 14px;
        padding: 10px 12px;
    }

    .sale-items-table tfoot .discount-row td {
        color: var(--danger);
    }

    .sale-items-table tfoot .total-row td {
        font-size: 16px;
        background: var(--bg-darker);
    }

    .text-left {
        text-align: left !important;
    }

    .header-with-back {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .back-btn {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bg-darker);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        color: var(--text-light);
        transition: all 0.2s;
    }

    .back-btn:hover {
        background: var(--bg-card);
        border-color: var(--primary-green);
        color: var(--primary-green);
    }

    .back-btn svg {
        width: 20px;
        height: 20px;
    }

    .action-buttons {
        display: flex;
        justify-content: flex-end;
    }

    .btn-delete {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
        border: 1px solid transparent;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-delete:hover {
        background: rgba(239, 68, 68, 0.2);
        border-color: var(--danger);
    }

    @media (max-width: 768px) {
        .sale-details-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection
