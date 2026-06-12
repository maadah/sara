@extends('layouts.customer')

@section('title', 'نقطة البيع - لوحة التحكم')

@section('content')
<div class="pos-page">
    <!-- Products Section -->
    <div class="pos-products-panel">
        <div class="pos-products-header">
            <div class="pos-title-row">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                    </svg>
                    نقطة البيع
                </h2>
                <span class="products-count">{{ $products->count() }} منتج</span>
            </div>
            <div class="pos-filters">
                <div class="search-box">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input type="text" id="productSearch" placeholder="البحث عن منتج..." onkeyup="searchProducts()">
                </div>
                <select id="categoryFilter" class="category-select" onchange="searchProducts()">
                    <option value="">كل الفئات</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="pos-products-list" id="productsGrid">
            @forelse($products as $product)
                <div class="pos-item {{ $product->quantity <= 0 ? 'out-of-stock' : '' }}"
                     onclick="{{ $product->quantity > 0 ? "addToCart({$product->id}, '{$product->name}', {$product->price}, '{$product->currency}', {$product->quantity})" : '' }}">
                    <div class="pos-item-image">
                        @if($product->images->count() > 0)
                            <img src="{{ asset('storage/' . $product->images->first()->image_path) }}" alt="{{ $product->name }}">
                        @else
                            <div class="no-image-placeholder">
                                <svg xmlns="http://www.w3.org/2000/svg" width="35" height="35" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                            </div>
                        @endif
                        @if($product->quantity <= 0)
                            <div class="out-of-stock-badge">نفذ</div>
                        @elseif($product->quantity <= 5)
                            <div class="low-stock-badge">{{ $product->quantity }} متبقي</div>
                        @endif
                    </div>
                    <div class="pos-item-details">
                        <h4>{{ $product->name }}</h4>
                        <div class="pos-item-meta">
                            <span class="price">{{ number_format($product->price) }} {{ $product->currency == 'USD' ? '$' : 'د.ع' }}</span>
                            <span class="stock">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                </svg>
                                {{ $product->quantity }}
                            </span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="no-products-found">
                    <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                    </svg>
                    <p>لا توجد منتجات</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Cart Section -->
    <div class="pos-cart-panel">
        <div class="cart-header">
            <div class="cart-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                </svg>
                <h3>سلة المشتريات</h3>
                <span class="cart-count" id="cartCount">0</span>
            </div>
            <button type="button" class="clear-btn" onclick="clearCart()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
                مسح
            </button>
        </div>

        <!-- Customer Info -->
        <div class="customer-fields">
            <div class="field-group">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
                <input type="text" id="customerName" placeholder="اسم العميل (اختياري)">
            </div>
            <div class="field-group">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                </svg>
                <input type="text" id="customerPhone" placeholder="رقم الهاتف (اختياري)">
            </div>
        </div>

        <!-- Cart Items -->
        <div class="cart-items-wrapper">
            <div class="cart-items-list" id="cartItems">
                <div class="empty-cart-state">
                    <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                    </svg>
                    <p>السلة فارغة</p>
                    <span>أضف منتجات للبدء</span>
                </div>
            </div>
        </div>

        <!-- Cart Footer -->
        <div class="cart-footer">
            <!-- Currency Selection -->
            <div class="currency-row">
                <label>العملة:</label>
                <select id="saleCurrency" onchange="updateCartTotals()">
                    <option value="IQD">د.ع (دينار عراقي)</option>
                    <option value="USD">$ (دولار)</option>
                </select>
            </div>

            <!-- Summary -->
            <div class="cart-summary">
                <div class="summary-line">
                    <span>المجموع الفرعي:</span>
                    <span id="subtotal">0 د.ع</span>
                </div>
                <div class="summary-line discount-line">
                    <span>الخصم:</span>
                    <div class="discount-controls">
                        <input type="number" id="discountPercent" placeholder="%" min="0" max="100" onchange="calculateDiscount('percent')">
                        <span class="or-text">أو</span>
                        <input type="number" id="discountAmount" placeholder="مبلغ" min="0" onchange="calculateDiscount('amount')">
                    </div>
                </div>
                <div class="summary-line discount-value-line">
                    <span>قيمة الخصم:</span>
                    <span id="discountValue" class="discount-amount">- 0 د.ع</span>
                </div>
                <div class="summary-line total-line">
                    <span>الإجمالي:</span>
                    <span id="totalAmount">0 د.ع</span>
                </div>
            </div>

            <!-- Payment Method -->
            <div class="payment-section">
                <label>طريقة الدفع:</label>
                <div class="payment-buttons">
                    <label class="payment-btn active">
                        <input type="radio" name="paymentMethod" value="cash" checked>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                        </svg>
                        <span>نقداً</span>
                    </label>
                    <label class="payment-btn">
                        <input type="radio" name="paymentMethod" value="card">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                        </svg>
                        <span>بطاقة</span>
                    </label>
                    <label class="payment-btn">
                        <input type="radio" name="paymentMethod" value="transfer">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                        <span>تحويل</span>
                    </label>
                </div>
            </div>

            <!-- Notes -->
            <div class="notes-section">
                <textarea id="saleNotes" placeholder="ملاحظات (اختياري)"></textarea>
            </div>

            <!-- Complete Sale Button -->
            <button type="button" class="checkout-btn" onclick="completeSale()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                إتمام البيع
            </button>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="pos-modal-overlay" id="successModal">
    <div class="pos-modal">
        <div class="modal-success-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
        </div>
        <h3>تم إتمام عملية البيع بنجاح!</h3>
        <p class="invoice-text">رقم الفاتورة: <strong id="invoiceNumber"></strong></p>
        <div class="modal-buttons">
            <button onclick="printInvoice()" class="modal-btn print-btn">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                </svg>
                طباعة الفاتورة
            </button>
            <button onclick="closeModal()" class="modal-btn new-sale-btn">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                بيع جديد
            </button>
        </div>
    </div>
</div>

<style>
/* POS Page Styles */
.pos-page {
    display: grid;
    grid-template-columns: 1fr 420px;
    gap: 20px;
    height: calc(100vh - 140px);
    min-height: 600px;
}

/* Products Panel */
.pos-products-panel {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.pos-products-header {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
}

.pos-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
}

.pos-title-row h2 {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-light);
    margin: 0;
}

.pos-title-row h2 svg {
    width: 24px;
    height: 24px;
    color: var(--primary-green);
}

.products-count {
    background: rgba(37, 211, 102, 0.1);
    color: var(--primary-green);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.pos-filters {
    display: flex;
    gap: 15px;
}

.search-box {
    flex: 1;
    position: relative;
}

.search-box svg {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    color: var(--text-muted);
}

.search-box input {
    width: 100%;
    padding: 12px 45px 12px 15px;
    background: var(--bg-darker);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    color: var(--text-light);
    font-size: 0.95rem;
    transition: all 0.3s;
}

.search-box input:focus {
    outline: none;
    border-color: var(--primary-green);
}

.category-select {
    min-width: 160px;
    padding: 12px 15px;
    background: var(--bg-darker);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    color: var(--text-light);
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s;
}

.category-select:focus {
    outline: none;
    border-color: var(--primary-green);
}

/* Products List */
.pos-products-list {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
    padding: 20px;
    overflow-y: auto;
    align-content: start;
}

.pos-item {
    background: var(--bg-darker);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 12px;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
}

.pos-item:hover:not(.out-of-stock) {
    border-color: var(--primary-green);
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(37, 211, 102, 0.15);
}

.pos-item.out-of-stock {
    opacity: 0.5;
    cursor: not-allowed;
}

.pos-item-image {
    width: 90px;
    height: 90px;
    margin: 0 auto 10px;
    border-radius: 10px;
    overflow: hidden;
    background: var(--bg-card);
    position: relative;
}

.pos-item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
}

.no-image-placeholder svg {
    width: 35px;
    height: 35px;
    opacity: 0.5;
}

.out-of-stock-badge,
.low-stock-badge {
    position: absolute;
    top: 5px;
    right: 5px;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
}

.out-of-stock-badge {
    background: var(--danger);
    color: white;
}

.low-stock-badge {
    background: var(--warning);
    color: white;
}

.pos-item-details h4 {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-light);
    margin: 0 0 8px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pos-item-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pos-item-meta .price {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--primary-green);
}

.pos-item-meta .stock {
    display: flex;
    align-items: center;
    gap: 3px;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.pos-item-meta .stock svg {
    width: 14px;
    height: 14px;
}

.no-products-found {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

.no-products-found svg {
    width: 60px;
    height: 60px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.no-products-found p {
    margin: 0;
    font-size: 1rem;
}

/* Cart Panel */
.pos-cart-panel {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.cart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-darker);
}

.cart-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.cart-title svg {
    width: 22px;
    height: 22px;
    color: var(--primary-green);
}

.cart-title h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-light);
    margin: 0;
}

.cart-count {
    background: var(--primary-green);
    color: white;
    min-width: 24px;
    height: 24px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
}

.clear-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
    border: none;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
}

.clear-btn:hover {
    background: rgba(239, 68, 68, 0.2);
}

.clear-btn svg {
    width: 16px;
    height: 16px;
}

/* Customer Fields */
.customer-fields {
    padding: 15px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    border-bottom: 1px solid var(--border-color);
}

.field-group {
    position: relative;
}

.field-group svg {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    color: var(--text-muted);
}

.field-group input {
    width: 100%;
    padding: 10px 40px 10px 12px;
    background: var(--bg-darker);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-light);
    font-size: 0.9rem;
    transition: all 0.3s;
}

.field-group input:focus {
    outline: none;
    border-color: var(--primary-green);
}

/* Cart Items */
.cart-items-wrapper {
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.cart-items-list {
    flex: 1;
    overflow-y: auto;
    padding: 15px 20px;
    min-height: 200px;
    max-height: 280px;
}

.empty-cart-state {
    text-align: center;
    padding: 50px 20px;
    color: var(--text-muted);
}

.empty-cart-state svg {
    width: 60px;
    height: 60px;
    margin-bottom: 15px;
    opacity: 0.4;
}

.empty-cart-state p {
    margin: 0 0 8px 0;
    font-size: 1.1rem;
    color: var(--text-muted);
}

.empty-cart-state span {
    font-size: 0.85rem;
    color: var(--text-muted);
    opacity: 0.7;
}

.cart-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    background: var(--bg-darker);
    border-radius: 10px;
    margin-bottom: 10px;
}

.cart-item-info {
    flex: 1;
    min-width: 0;
}

.cart-item-info h5 {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--text-light);
    margin: 0 0 4px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.cart-item-price {
    font-size: 0.8rem;
    color: var(--primary-green);
}

.cart-item-qty {
    display: flex;
    align-items: center;
    gap: 6px;
}

.cart-item-qty button {
    width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    color: var(--text-light);
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.2s;
}

.cart-item-qty button:hover {
    border-color: var(--primary-green);
    color: var(--primary-green);
}

.cart-item-qty span {
    width: 28px;
    text-align: center;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-light);
}

.cart-item-total {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-light);
    min-width: 70px;
    text-align: left;
}

.cart-item-remove {
    width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(239, 68, 68, 0.1);
    border: none;
    border-radius: 6px;
    color: var(--danger);
    cursor: pointer;
    transition: all 0.2s;
}

.cart-item-remove:hover {
    background: rgba(239, 68, 68, 0.2);
}

.cart-item-remove svg {
    width: 14px;
    height: 14px;
}

/* Cart Footer */
.cart-footer {
    border-top: 1px solid var(--border-color);
    background: var(--bg-darker);
    padding: 15px 20px;
}

.currency-row {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.currency-row label {
    font-size: 0.85rem;
    color: var(--text-muted);
}

.currency-row select {
    flex: 1;
    padding: 8px 12px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-light);
    font-size: 0.85rem;
    cursor: pointer;
}

.currency-row select:focus {
    outline: none;
    border-color: var(--primary-green);
}

/* Cart Summary */
.cart-summary {
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.summary-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    font-size: 0.85rem;
}

.summary-line span:first-child {
    color: var(--text-muted);
}

.summary-line span:last-child {
    color: var(--text-light);
}

.discount-line {
    flex-wrap: wrap;
    gap: 8px;
}

.discount-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.discount-controls input {
    width: 65px;
    padding: 6px 8px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    color: var(--text-light);
    font-size: 0.8rem;
    text-align: center;
}

.discount-controls input:focus {
    outline: none;
    border-color: var(--primary-green);
}

.or-text {
    color: var(--text-muted);
    font-size: 0.75rem;
}

.discount-value-line .discount-amount {
    color: var(--danger) !important;
}

.total-line {
    padding-top: 12px;
    margin-top: 8px;
    border-top: 1px dashed var(--border-color);
    font-size: 1rem;
    font-weight: 600;
}

.total-line span:last-child {
    color: var(--primary-green) !important;
    font-size: 1.1rem;
}

/* Payment Section */
.payment-section {
    margin-bottom: 15px;
}

.payment-section > label {
    display: block;
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 10px;
}

.payment-buttons {
    display: flex;
    gap: 8px;
}

.payment-btn {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    padding: 10px 8px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.payment-btn:hover {
    border-color: var(--primary-green);
}

.payment-btn.active {
    background: rgba(37, 211, 102, 0.1);
    border-color: var(--primary-green);
}

.payment-btn input {
    display: none;
}

.payment-btn svg {
    width: 20px;
    height: 20px;
    color: var(--text-muted);
}

.payment-btn.active svg {
    color: var(--primary-green);
}

.payment-btn span {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.payment-btn.active span {
    color: var(--primary-green);
}

/* Notes Section */
.notes-section {
    margin-bottom: 15px;
}

.notes-section textarea {
    width: 100%;
    padding: 10px 12px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-light);
    font-size: 0.85rem;
    resize: none;
    height: 50px;
    transition: all 0.3s;
}

.notes-section textarea:focus {
    outline: none;
    border-color: var(--primary-green);
}

/* Checkout Button */
.checkout-btn {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, var(--primary-green) 0%, var(--secondary-green) 100%);
    border: none;
    border-radius: 10px;
    color: white;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.checkout-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(37, 211, 102, 0.3);
}

.checkout-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.checkout-btn svg {
    width: 20px;
    height: 20px;
}

/* Modal */
.pos-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.pos-modal-overlay.active {
    display: flex;
}

.pos-modal {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 40px;
    text-align: center;
    max-width: 400px;
    width: 90%;
}

.modal-success-icon {
    width: 80px;
    height: 80px;
    background: rgba(37, 211, 102, 0.15);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    color: var(--primary-green);
}

.modal-success-icon svg {
    width: 40px;
    height: 40px;
}

.pos-modal h3 {
    font-size: 1.25rem;
    color: var(--text-light);
    margin: 0 0 15px 0;
}

.invoice-text {
    color: var(--text-muted);
    margin: 0 0 25px 0;
}

.invoice-text strong {
    color: var(--primary-green);
    font-family: monospace;
}

.modal-buttons {
    display: flex;
    gap: 12px;
}

.modal-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    border: none;
}

.modal-btn svg {
    width: 18px;
    height: 18px;
}

.print-btn {
    background: var(--primary-green);
    color: white;
}

.print-btn:hover {
    background: var(--secondary-green);
}

.new-sale-btn {
    background: var(--bg-darker);
    color: var(--text-light);
    border: 1px solid var(--border-color);
}

.new-sale-btn:hover {
    border-color: var(--primary-green);
}

/* Responsive */
@media (max-width: 1200px) {
    .pos-page {
        grid-template-columns: 1fr 380px;
    }
}

@media (max-width: 992px) {
    .pos-page {
        grid-template-columns: 1fr;
        height: auto;
    }

    .pos-cart-panel {
        order: -1;
    }

    .cart-items-list {
        max-height: 180px;
    }
}

@media (max-width: 768px) {
    .pos-filters {
        flex-direction: column;
    }

    .category-select {
        width: 100%;
    }

    .pos-products-list {
        grid-template-columns: repeat(2, 1fr);
    }

    .payment-buttons {
        flex-direction: column;
    }

    .modal-buttons {
        flex-direction: column;
    }
}
</style>

<script>
let cart = [];
let currentSaleId = null;

function addToCart(productId, name, price, currency, availableQty) {
    const existingItem = cart.find(item => item.productId === productId);

    if (existingItem) {
        if (existingItem.quantity >= availableQty) {
            showToast('الكمية المطلوبة غير متوفرة', 'error');
            return;
        }
        existingItem.quantity++;
        existingItem.total = existingItem.quantity * existingItem.price;
    } else {
        cart.push({
            productId: productId,
            name: name,
            price: price,
            currency: currency,
            quantity: 1,
            total: price,
            availableQty: availableQty
        });
    }

    renderCart();
    updateCartCount();
}

function removeFromCart(productId) {
    cart = cart.filter(item => item.productId !== productId);
    renderCart();
    updateCartCount();
}

function updateQuantity(productId, change) {
    const item = cart.find(item => item.productId === productId);
    if (item) {
        const newQty = item.quantity + change;
        if (newQty <= 0) {
            removeFromCart(productId);
            return;
        }
        if (newQty > item.availableQty) {
            showToast('الكمية المطلوبة غير متوفرة', 'error');
            return;
        }
        item.quantity = newQty;
        item.total = item.quantity * item.price;
        renderCart();
    }
}

function updateCartCount() {
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    document.getElementById('cartCount').textContent = totalItems;
}

function renderCart() {
    const cartContainer = document.getElementById('cartItems');
    const currency = document.getElementById('saleCurrency').value;
    const currencySymbol = currency === 'USD' ? '$' : 'د.ع';

    if (cart.length === 0) {
        cartContainer.innerHTML = `
            <div class="empty-cart-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                </svg>
                <p>السلة فارغة</p>
                <span>أضف منتجات للبدء</span>
            </div>
        `;
    } else {
        cartContainer.innerHTML = cart.map(item => `
            <div class="cart-item">
                <div class="cart-item-info">
                    <h5>${item.name}</h5>
                    <span class="cart-item-price">${numberFormat(item.price)} ${currencySymbol}</span>
                </div>
                <div class="cart-item-qty">
                    <button type="button" onclick="updateQuantity(${item.productId}, -1)">-</button>
                    <span>${item.quantity}</span>
                    <button type="button" onclick="updateQuantity(${item.productId}, 1)">+</button>
                </div>
                <div class="cart-item-total">
                    ${numberFormat(item.total)} ${currencySymbol}
                </div>
                <button type="button" class="cart-item-remove" onclick="removeFromCart(${item.productId})">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        `).join('');
    }

    updateCartTotals();
}

function updateCartTotals() {
    const currency = document.getElementById('saleCurrency').value;
    const currencySymbol = currency === 'USD' ? '$' : 'د.ع';

    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    document.getElementById('subtotal').textContent = numberFormat(subtotal) + ' ' + currencySymbol;

    calculateDiscount();
}

function calculateDiscount(type) {
    const currency = document.getElementById('saleCurrency').value;
    const currencySymbol = currency === 'USD' ? '$' : 'د.ع';

    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    let discountValue = 0;

    const percentInput = document.getElementById('discountPercent');
    const amountInput = document.getElementById('discountAmount');

    if (type === 'percent') {
        amountInput.value = '';
        const percent = parseFloat(percentInput.value) || 0;
        discountValue = subtotal * (percent / 100);
    } else if (type === 'amount') {
        percentInput.value = '';
        discountValue = parseFloat(amountInput.value) || 0;
    } else {
        const percent = parseFloat(percentInput.value) || 0;
        const amount = parseFloat(amountInput.value) || 0;
        if (percent > 0) {
            discountValue = subtotal * (percent / 100);
        } else {
            discountValue = amount;
        }
    }

    const total = subtotal - discountValue;

    document.getElementById('discountValue').textContent = '- ' + numberFormat(discountValue) + ' ' + currencySymbol;
    document.getElementById('totalAmount').textContent = numberFormat(Math.max(0, total)) + ' ' + currencySymbol;
}

function clearCart() {
    cart = [];
    document.getElementById('discountPercent').value = '';
    document.getElementById('discountAmount').value = '';
    renderCart();
    updateCartCount();
}

function searchProducts() {
    const search = document.getElementById('productSearch').value;
    const categoryId = document.getElementById('categoryFilter').value;

    fetch('{{ route("customer.pos.search") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ search, category_id: categoryId })
    })
    .then(response => response.json())
    .then(products => {
        const grid = document.getElementById('productsGrid');

        if (products.length === 0) {
            grid.innerHTML = `
                <div class="no-products-found">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                    </svg>
                    <p>لا توجد منتجات</p>
                </div>
            `;
            return;
        }

        grid.innerHTML = products.map(product => `
            <div class="pos-item ${product.quantity <= 0 ? 'out-of-stock' : ''}"
                 onclick="${product.quantity > 0 ? `addToCart(${product.id}, '${product.name}', ${product.price}, '${product.currency}', ${product.quantity})` : ''}">
                <div class="pos-item-image">
                    ${product.images && product.images.length > 0
                        ? `<img src="/storage/${product.images[0].image_path}" alt="${product.name}">`
                        : `<div class="no-image-placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                        </div>`
                    }
                    ${product.quantity <= 0 ? '<div class="out-of-stock-badge">نفذ</div>' :
                      product.quantity <= 5 ? `<div class="low-stock-badge">${product.quantity} متبقي</div>` : ''}
                </div>
                <div class="pos-item-details">
                    <h4>${product.name}</h4>
                    <div class="pos-item-meta">
                        <span class="price">${numberFormat(product.price)} ${product.currency === 'USD' ? '$' : 'د.ع'}</span>
                        <span class="stock">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                            </svg>
                            ${product.quantity}
                        </span>
                    </div>
                </div>
            </div>
        `).join('');
    });
}

function completeSale() {
    if (cart.length === 0) {
        showToast('السلة فارغة', 'error');
        return;
    }

    const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
    const currency = document.getElementById('saleCurrency').value;

    const data = {
        items: cart.map(item => ({
            product_id: item.productId,
            quantity: item.quantity
        })),
        customer_name: document.getElementById('customerName').value,
        customer_phone: document.getElementById('customerPhone').value,
        discount_percentage: parseFloat(document.getElementById('discountPercent').value) || 0,
        discount_amount: parseFloat(document.getElementById('discountAmount').value) || 0,
        payment_method: paymentMethod,
        currency: currency,
        notes: document.getElementById('saleNotes').value
    };

    fetch('{{ route("customer.pos.complete") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            currentSaleId = result.sale_id;
            document.getElementById('invoiceNumber').textContent = result.invoice_number;
            document.getElementById('successModal').classList.add('active');
        } else {
            showToast(result.error || 'حدث خطأ', 'error');
        }
    })
    .catch(error => {
        showToast('حدث خطأ في الاتصال', 'error');
    });
}

function printInvoice() {
    if (currentSaleId) {
        window.open('{{ url("customer/pos/invoice") }}/' + currentSaleId, '_blank');
    }
}

function closeModal() {
    document.getElementById('successModal').classList.remove('active');
    clearCart();
    document.getElementById('customerName').value = '';
    document.getElementById('customerPhone').value = '';
    document.getElementById('saleNotes').value = '';
    currentSaleId = null;
    location.reload();
}

function numberFormat(num) {
    return new Intl.NumberFormat('en-US').format(num);
}

function showToast(message, type = 'info') {
    alert(message);
}

// Payment method toggle
document.querySelectorAll('.payment-btn input').forEach(input => {
    input.addEventListener('change', function() {
        document.querySelectorAll('.payment-btn').forEach(btn => btn.classList.remove('active'));
        this.parentElement.classList.add('active');
    });
});

// Initialize cart count
updateCartCount();
</script>
@endsection
