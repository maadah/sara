@extends('layouts.customer')

@section('title', 'تعديل الطلب #' . $order->id)

@section('styles')
<style>
    .edit-order-container {
        max-width: 900px;
        margin: 0 auto;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .page-header h1 {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-light);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .page-header h1 svg {
        width: 28px;
        height: 28px;
        color: var(--primary-green);
    }

    .page-header h1 .order-number {
        color: var(--text-muted);
        font-size: 18px;
        font-weight: 500;
    }

    .btn-back {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        color: var(--text-muted);
        font-size: 14px;
        text-decoration: none;
        transition: all 0.2s;
    }

    .btn-back:hover {
        background: var(--bg-card-hover);
        color: var(--text-light);
    }

    .btn-back svg {
        width: 18px;
        height: 18px;
    }

    .order-form {
        display: grid;
        gap: 24px;
    }

    .form-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px;
        border: 1px solid var(--border-color);
    }

    .card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border-color);
    }

    .card-header .icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .card-header .icon.blue {
        background: rgba(59, 130, 246, 0.1);
        color: var(--info);
    }

    .card-header .icon.green {
        background: rgba(37, 211, 102, 0.1);
        color: var(--primary-green);
    }

    .card-header .icon.orange {
        background: rgba(249, 115, 22, 0.1);
        color: var(--warning);
    }

    .card-header .icon svg {
        width: 24px;
        height: 24px;
    }

    .card-header-info h3 {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-light);
        margin: 0 0 4px 0;
    }

    .card-header-info p {
        font-size: 13px;
        color: var(--text-muted);
        margin: 0;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
    }

    .form-group {
        margin-bottom: 16px;
    }

    .form-group.full-width {
        grid-column: span 2;
    }

    @media (max-width: 768px) {
        .form-group.full-width {
            grid-column: span 1;
        }
    }

    .form-group:last-child {
        margin-bottom: 0;
    }

    .form-label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        color: var(--text-light);
        margin-bottom: 8px;
    }

    .form-label .required {
        color: var(--danger);
    }

    .form-input, .form-select, .form-textarea {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        font-size: 14px;
        background: var(--bg-darker);
        color: var(--text-light);
        transition: all 0.2s;
    }

    .form-input:focus, .form-select:focus, .form-textarea:focus {
        outline: none;
        border-color: var(--primary-green);
        box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.1);
    }

    .form-input::placeholder, .form-textarea::placeholder {
        color: var(--text-muted);
    }

    .form-select option {
        background: var(--bg-darker);
        color: var(--text-light);
    }

    .form-textarea {
        min-height: 100px;
        resize: vertical;
        font-family: inherit;
    }

    /* Summary Section */
    .summary-section {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--border-color);
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
    }

    .summary-row.total {
        padding-top: 12px;
        margin-top: 8px;
        border-top: 1px solid var(--border-color);
    }

    .summary-label {
        font-size: 14px;
        color: var(--text-muted);
    }

    .summary-row.total .summary-label {
        font-weight: 600;
        color: var(--text-light);
    }

    .summary-value {
        font-size: 14px;
        font-weight: 500;
        color: var(--text-light);
    }

    .summary-row.total .summary-value {
        font-size: 18px;
        font-weight: 700;
        color: var(--primary-green);
    }

    /* Form Actions */
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding-top: 24px;
    }

    .btn-cancel {
        padding: 12px 24px;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        color: var(--text-muted);
        font-size: 14px;
        text-decoration: none;
        transition: all 0.2s;
    }

    .btn-cancel:hover {
        background: var(--bg-card-hover);
        color: var(--text-light);
    }

    .btn-submit {
        padding: 12px 32px;
        background: var(--primary-green);
        border: none;
        border-radius: 10px;
        color: white;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-submit:hover {
        background: var(--secondary-green);
        transform: translateY(-1px);
    }

    /* Alert */
    .alert {
        padding: 16px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .alert.error {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .alert.success {
        background: rgba(37, 211, 102, 0.1);
        color: var(--primary-green);
        border: 1px solid rgba(37, 211, 102, 0.2);
    }

    .alert svg {
        width: 24px;
        height: 24px;
        flex-shrink: 0;
    }

    .error-text {
        font-size: 12px;
        color: var(--danger);
        margin-top: 4px;
    }

    .info-box {
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid rgba(59, 130, 246, 0.2);
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .info-box h4 {
        font-size: 14px;
        font-weight: 600;
        color: var(--info);
        margin: 0 0 8px 0;
    }

    .info-box p {
        font-size: 13px;
        color: var(--text-muted);
        margin: 0;
    }
</style>
@endsection

@section('content')
<div class="edit-order-container">
    <div class="page-header">
        <h1>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
            </svg>
            تعديل الطلب <span class="order-number">#{{ $order->id }}</span>
        </h1>
        <a href="{{ route('customer.online-orders.show', $order) }}" class="btn-back">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
            </svg>
            العودة للطلب
        </a>
    </div>

    @if(session('error'))
        <div class="alert error">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            {{ session('error') }}
        </div>
    @endif

    @if(session('success'))
        <div class="alert success">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert error">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            <div>
                <strong>يرجى تصحيح الأخطاء التالية:</strong>
                <ul style="margin: 8px 0 0 0; padding-right: 20px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="info-box">
        <h4>ملاحظة مهمة</h4>
        <p>يمكنك تعديل معلومات العميل والتكاليف فقط. لتغيير المنتجات، يرجى إلغاء هذا الطلب وإنشاء طلب جديد.</p>
    </div>

    <form action="{{ route('customer.online-orders.update', $order) }}" method="POST" class="order-form">
        @csrf
        @method('PUT')

        <!-- Customer Information -->
        <div class="form-card">
            <div class="card-header">
                <div class="icon blue">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                </div>
                <div class="card-header-info">
                    <h3>معلومات العميل</h3>
                    <p>تحديث بيانات العميل</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">اسم العميل <span class="required">*</span></label>
                    <input type="text" name="customer_name" class="form-input"
                           value="{{ old('customer_name', $order->customer_name) }}" required placeholder="أدخل اسم العميل">
                    @error('customer_name')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">رقم الهاتف <span class="required">*</span></label>
                    <input type="tel" name="customer_phone" class="form-input"
                           value="{{ old('customer_phone', $order->customer_phone) }}" required placeholder="07XXXXXXXX">
                    @error('customer_phone')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">المدينة</label>
                    <input type="text" name="customer_city" class="form-input"
                           value="{{ old('customer_city', $order->customer_city) }}" placeholder="مثال: بغداد">
                </div>

                <div class="form-group full-width">
                    <label class="form-label">العنوان</label>
                    <textarea name="customer_address" class="form-textarea"
                              placeholder="العنوان التفصيلي للتوصيل">{{ old('customer_address', $order->customer_address) }}</textarea>
                </div>
            </div>
        </div>

        <!-- Order Costs -->
        <div class="form-card">
            <div class="card-header">
                <div class="icon green">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                    </svg>
                </div>
                <div class="card-header-info">
                    <h3>التكاليف والخصومات</h3>
                    <p>تحديث الأسعار</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">تكلفة الشحن</label>
                    <input type="number" name="shipping_cost" id="shipping_cost" class="form-input"
                           value="{{ old('shipping_cost', $order->shipping_cost) }}" min="0" step="0.01" onchange="calculateTotal()">
                </div>
                <div class="form-group">
                    <label class="form-label">الخصم</label>
                    <input type="number" name="discount_amount" id="discount_amount" class="form-input"
                           value="{{ old('discount_amount', $order->discount_amount) }}" min="0" step="0.01" onchange="calculateTotal()">
                </div>
            </div>

            <div class="summary-section">
                <div class="summary-row">
                    <span class="summary-label">المجموع الفرعي</span>
                    <span class="summary-value" id="subtotal">{{ number_format($order->subtotal, 0) }} د.ع</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">الشحن</span>
                    <span class="summary-value" id="shippingDisplay">{{ number_format($order->shipping_cost, 0) }} د.ع</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">الخصم</span>
                    <span class="summary-value" id="discountDisplay">{{ number_format($order->discount_amount, 0) }} د.ع</span>
                </div>
                <div class="summary-row total">
                    <span class="summary-label">الإجمالي</span>
                    <span class="summary-value" id="totalAmount">{{ number_format($order->total_amount, 0) }} د.ع</span>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="form-card">
            <div class="card-header">
                <div class="icon orange">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                    </svg>
                </div>
                <div class="card-header-info">
                    <h3>ملاحظات</h3>
                    <p>أي ملاحظات إضافية على الطلب</p>
                </div>
            </div>

            <div class="form-group">
                <textarea name="notes" class="form-textarea" placeholder="ملاحظات على الطلب (اختياري)">{{ old('notes', $order->notes) }}</textarea>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('customer.online-orders.show', $order) }}" class="btn-cancel">إلغاء</a>
            <button type="submit" class="btn-submit">حفظ التغييرات</button>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script>
    const subtotal = {{ $order->subtotal }};

    function calculateTotal() {
        const shipping = parseFloat(document.getElementById('shipping_cost').value) || 0;
        const discount = parseFloat(document.getElementById('discount_amount').value) || 0;
        const total = subtotal + shipping - discount;

        document.getElementById('shippingDisplay').textContent = shipping.toLocaleString() + ' د.ع';
        document.getElementById('discountDisplay').textContent = discount.toLocaleString() + ' د.ع';
        document.getElementById('totalAmount').textContent = total.toLocaleString() + ' د.ع';
    }

    // Recalculate on load if values were changed
    document.addEventListener('DOMContentLoaded', function() {
        calculateTotal();
    });
</script>
@endpush
