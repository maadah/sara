@extends('layouts.customer')

@section('title', 'الطلبات - لوحة التحكم')

@section('content')
<div class="page-header-bar">
    <h1 class="page-title">الطلبات</h1>
</div>

<div class="orders-section">
    <div class="orders-header">
        <div class="orders-tabs">
            <button class="orders-tab active" data-filter="all">كل الطلبات</button>
            <button class="orders-tab" data-filter="facebook">طلبات الفيس بوك</button>
            <button class="orders-tab" data-filter="instagram">طلبات الأنستجرام</button>
            <button class="orders-tab" data-filter="pending">قيد الانتظار</button>
            <button class="orders-tab" data-filter="shipping">قيد الشحن</button>
            <button class="orders-tab" data-filter="completed">مكتملة</button>
        </div>
    </div>

    <div class="inventory-table-wrapper" style="border: none;">
        <table class="inventory-table">
            <thead>
                <tr>
                    <th>رقم الطلب</th>
                    <th>اسم المنتج</th>
                    <th>اسم العميل</th>
                    <th>العنوان</th>
                    <th>حالة الطلب</th>
                    <th>اسم المنصة</th>
                    <th>الاجراءات</th>
                </tr>
            </thead>
            <tbody id="ordersTableBody">
                <tr>
                    <td colspan="7">
                        <div class="empty-state-new">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                            <h3>لا توجد طلبات حالياً</h3>
                            <p>ستظهر الطلبات هنا عند استلامها من منصات التواصل الاجتماعي</p>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.orders-tab');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            // Add active class to clicked tab
            this.classList.add('active');

            // Get filter type
            const filter = this.getAttribute('data-filter');

            // Here you can add AJAX call to filter orders
            // For now, just showing which filter is selected
            console.log('Filter selected:', filter);

            // You can implement filtering logic here when orders are available
            // filterOrders(filter);
        });
    });
});
</script>
@endsection
