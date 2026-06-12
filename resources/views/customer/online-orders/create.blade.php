@extends('layouts.customer')

@section('title', 'إنشاء طلب جديد')

@section('styles')
<style>
    .create-order-container {
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

    /* Products Section */
    .products-section {
        margin-top: 20px;
    }

    .products-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .products-header h4 {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-light);
        margin: 0;
    }

    .btn-add-item {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        background: rgba(37, 211, 102, 0.1);
        border: 1px solid var(--primary-green);
        border-radius: 8px;
        color: var(--primary-green);
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-add-item:hover {
        background: var(--primary-green);
        color: white;
    }

    .btn-add-item svg {
        width: 16px;
        height: 16px;
    }

    .order-items {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .order-item {
        display: grid;
        grid-template-columns: 1fr 120px 40px;
        gap: 12px;
        align-items: center;
        padding: 16px;
        background: var(--bg-darker);
        border-radius: 12px;
        border: 1px solid var(--border-color);
    }

    .order-item .form-select, .order-item .form-input {
        margin-bottom: 0;
    }

    .btn-remove-item {
        width: 40px;
        height: 40px;
        border: none;
        border-radius: 10px;
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .btn-remove-item:hover {
        background: var(--danger);
        color: white;
    }

    .btn-remove-item svg {
        width: 18px;
        height: 18px;
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

    /* Empty State */
    .empty-items {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-muted);
    }

    .empty-items svg {
        width: 48px;
        height: 48px;
        margin-bottom: 12px;
        opacity: 0.5;
    }

    .empty-items p {
        font-size: 14px;
        margin: 0;
    }
</style>
@endsection

@section('content')
<div class="create-order-container">
    <div class="page-header">
        <h1>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            إنشاء طلب جديد
        </h1>
        <a href="{{ route('customer.online-orders.index') }}" class="btn-back">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
            </svg>
            العودة للطلبات
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

    <form action="{{ route('customer.online-orders.store') }}" method="POST" class="order-form" id="orderForm">
        @csrf

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
                    <p>أدخل بيانات العميل للطلب</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">اختيار من العملاء الحاليين</label>
                    <select name="lead_id" id="lead_id" class="form-select" onchange="fillCustomerData(this)">
                        <option value="">-- عميل جديد --</option>
                        @foreach($leads as $lead)
                            <option value="{{ $lead->id }}"
                                    data-name="{{ $lead->name }}"
                                    data-phone="{{ $lead->phone }}"
                                    data-address="{{ $lead->address }}"
                                    data-city="{{ $lead->city }}"
                                    {{ old('lead_id') == $lead->id ? 'selected' : '' }}>
                                {{ $lead->name }} - {{ $lead->phone }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">اسم العميل <span class="required">*</span></label>
                    <input type="text" name="customer_name" id="customer_name" class="form-input"
                           value="{{ old('customer_name') }}" required placeholder="أدخل اسم العميل">
                    @error('customer_name')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">رقم الهاتف <span class="required">*</span></label>
                    <input type="tel" name="customer_phone" id="customer_phone" class="form-input"
                           value="{{ old('customer_phone') }}" required placeholder="07XXXXXXXX">
                    @error('customer_phone')
                        <span class="error-text">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">المدينة</label>
                    <input type="text" name="customer_city" id="customer_city" class="form-input"
                           value="{{ old('customer_city') }}" placeholder="مثال: بغداد">
                </div>

                <div class="form-group full-width">
                    <label class="form-label">العنوان</label>
                    <textarea name="customer_address" id="customer_address" class="form-textarea"
                              placeholder="العنوان التفصيلي للتوصيل">{{ old('customer_address') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="form-card">
            <div class="card-header">
                <div class="icon green">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                    </svg>
                </div>
                <div class="card-header-info">
                    <h3>المنتجات</h3>
                    <p>اختر المنتجات المطلوبة</p>
                </div>
            </div>

            <div class="products-section">
                <div class="products-header">
                    <h4>قائمة المنتجات</h4>
                    <button type="button" class="btn-add-item" onclick="addItem()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        إضافة منتج
                    </button>
                </div>

                <div class="order-items" id="orderItems">
                    <div class="empty-items" id="emptyItems">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                        </svg>
                        <p>لم يتم إضافة أي منتج بعد. اضغط على "إضافة منتج" للبدء.</p>
                    </div>
                </div>

                <div class="summary-section">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">تكلفة الشحن</label>
                            <input type="number" name="shipping_cost" id="shipping_cost" class="form-input"
                                   value="{{ old('shipping_cost', 0) }}" min="0" step="0.01" onchange="calculateTotal()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الخصم</label>
                            <input type="number" name="discount_amount" id="discount_amount" class="form-input"
                                   value="{{ old('discount_amount', 0) }}" min="0" step="0.01" onchange="calculateTotal()">
                        </div>
                    </div>

                    <div class="summary-row">
                        <span class="summary-label">المجموع الفرعي</span>
                        <span class="summary-value" id="subtotal">0 د.ع</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">الشحن</span>
                        <span class="summary-value" id="shippingDisplay">0 د.ع</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">الخصم</span>
                        <span class="summary-value" id="discountDisplay">0 د.ع</span>
                    </div>
                    <div class="summary-row total">
                        <span class="summary-label">الإجمالي</span>
                        <span class="summary-value" id="totalAmount">0 د.ع</span>
                    </div>
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
                <textarea name="notes" class="form-textarea" placeholder="ملاحظات على الطلب (اختياري)">{{ old('notes') }}</textarea>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('customer.online-orders.index') }}" class="btn-cancel">إلغاء</a>
            <button type="submit" class="btn-submit">إنشاء الطلب</button>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script>
    const products = @json($products);
    let itemIndex = 0;

    function fillCustomerData(select) {
        const option = select.options[select.selectedIndex];
        if (option.value) {
            document.getElementById('customer_name').value = option.dataset.name || '';
            document.getElementById('customer_phone').value = option.dataset.phone || '';
            document.getElementById('customer_address').value = option.dataset.address || '';
            document.getElementById('customer_city').value = option.dataset.city || '';
        }
    }

    function addItem() {
        document.getElementById('emptyItems').style.display = 'none';

        const container = document.getElementById('orderItems');
        const item = document.createElement('div');
        item.className = 'order-item';
        item.id = `item-${itemIndex}`;

        let productOptions = '<option value="">اختر منتج</option>';
        products.forEach(p => {
            productOptions += `<option value="${p.id}" data-price="${p.price}" data-currency="${p.currency}">${p.name} - ${Number(p.price).toLocaleString()} ${p.currency === 'USD' ? '$' : 'د.ع'}</option>`;
        });

        item.innerHTML = `
            <select name="items[${itemIndex}][product_id]" class="form-select" required onchange="calculateTotal()">
                ${productOptions}
            </select>
            <input type="number" name="items[${itemIndex}][quantity]" class="form-input" value="1" min="1" required onchange="calculateTotal()">
            <button type="button" class="btn-remove-item" onclick="removeItem(${itemIndex})">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        `;

        container.appendChild(item);
        itemIndex++;
        calculateTotal();
    }

    function removeItem(index) {
        const item = document.getElementById(`item-${index}`);
        if (item) {
            item.remove();
        }

        const items = document.querySelectorAll('.order-item');
        if (items.length === 0) {
            document.getElementById('emptyItems').style.display = 'block';
        }
        calculateTotal();
    }

    function calculateTotal() {
        let subtotal = 0;
        const items = document.querySelectorAll('.order-item');

        items.forEach(item => {
            const select = item.querySelector('select');
            const qtyInput = item.querySelector('input[type="number"]');
            const option = select.options[select.selectedIndex];

            if (option && option.value) {
                const price = parseFloat(option.dataset.price) || 0;
                const qty = parseInt(qtyInput.value) || 0;
                subtotal += price * qty;
            }
        });

        const shipping = parseFloat(document.getElementById('shipping_cost').value) || 0;
        const discount = parseFloat(document.getElementById('discount_amount').value) || 0;
        const total = subtotal + shipping - discount;

        document.getElementById('subtotal').textContent = subtotal.toLocaleString() + ' د.ع';
        document.getElementById('shippingDisplay').textContent = shipping.toLocaleString() + ' د.ع';
        document.getElementById('discountDisplay').textContent = discount.toLocaleString() + ' د.ع';
        document.getElementById('totalAmount').textContent = total.toLocaleString() + ' د.ع';
    }

    // Add first item on load
    document.addEventListener('DOMContentLoaded', function() {
        addItem();
    });
</script>
@endpush
