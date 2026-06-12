<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طباعة طلب #{{ $order->order_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif;
            background: white;
            color: #1a1a1a;
            padding: 20px;
            font-size: 14px;
            line-height: 1.6;
        }

        .print-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .company-info h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .company-info p {
            font-size: 12px;
            color: #666;
        }

        .order-info {
            text-align: left;
        }

        .order-info h2 {
            font-size: 18px;
            margin-bottom: 8px;
        }

        .order-info p {
            font-size: 12px;
            color: #666;
        }

        .section {
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            background: #f5f5f5;
            padding: 8px 12px;
            margin-bottom: 12px;
            border-right: 4px solid #333;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .info-item {
            padding: 8px 0;
        }

        .info-item .label {
            font-size: 11px;
            color: #666;
            margin-bottom: 4px;
        }

        .info-item .value {
            font-size: 14px;
            font-weight: 500;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th,
        .items-table td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }

        .items-table th {
            background: #f5f5f5;
            font-weight: 600;
            font-size: 12px;
        }

        .items-table td {
            font-size: 13px;
        }

        .totals {
            width: 300px;
            margin-right: auto;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .total-row.grand {
            border-top: 2px solid #1a1a1a;
            border-bottom: none;
            font-size: 16px;
            font-weight: 700;
            padding-top: 12px;
            margin-top: 8px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.confirmed {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-badge.processing {
            background: #ede9fe;
            color: #6b21a8;
        }

        .status-badge.shipped {
            background: #cffafe;
            color: #155e75;
        }

        .status-badge.delivered {
            background: #dcfce7;
            color: #166534;
        }

        .status-badge.cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .notes {
            background: #f9fafb;
            padding: 12px;
            border-radius: 4px;
            font-size: 13px;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            color: #999;
            font-size: 11px;
        }

        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none;
            }
        }

        .print-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 10px 20px;
            background: #333;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .print-btn:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <button onclick="window.print()" class="print-btn no-print">طباعة</button>

    <div class="print-container">
        <div class="print-header">
            <div class="company-info">
                <h1>{{ auth()->user()->store_name ?? 'المتجر' }}</h1>
                <p>{{ auth()->user()->phone }}</p>
                <p>{{ auth()->user()->address }}</p>
            </div>
            <div class="order-info">
                <h2>طلب #{{ $order->order_number }}</h2>
                <p>التاريخ: {{ $order->created_at->format('Y/m/d H:i') }}</p>
                <span class="status-badge {{ $order->status }}">{{ $order->status_label }}</span>
            </div>
        </div>

        <div class="section">
            <div class="section-title">معلومات العميل</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">الاسم</div>
                    <div class="value">{{ $order->customer_name }}</div>
                </div>
                <div class="info-item">
                    <div class="label">رقم الهاتف</div>
                    <div class="value">{{ $order->customer_phone }}</div>
                </div>
                @if($order->customer_city)
                    <div class="info-item">
                        <div class="label">المدينة</div>
                        <div class="value">{{ $order->customer_city }}</div>
                    </div>
                @endif
                @if($order->customer_address)
                    <div class="info-item">
                        <div class="label">العنوان</div>
                        <div class="value">{{ $order->customer_address }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="section">
            <div class="section-title">تفاصيل المنتجات</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المنتج</th>
                        <th>السعر</th>
                        <th>الكمية</th>
                        <th>الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item->product_name }}</td>
                            <td>{{ number_format($item->unit_price) }} د.ع</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ number_format($item->total_price) }} د.ع</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totals">
                <div class="total-row">
                    <span>المجموع الفرعي</span>
                    <span>{{ number_format($order->subtotal) }} د.ع</span>
                </div>
                @if($order->discount_amount > 0)
                    <div class="total-row">
                        <span>الخصم</span>
                        <span>-{{ number_format($order->discount_amount) }} د.ع</span>
                    </div>
                @endif
                @if($order->shipping_cost > 0)
                    <div class="total-row">
                        <span>رسوم التوصيل</span>
                        <span>{{ number_format($order->shipping_cost) }} د.ع</span>
                    </div>
                @endif
                <div class="total-row grand">
                    <span>الإجمالي</span>
                    <span>{{ number_format($order->total) }} د.ع</span>
                </div>
            </div>
        </div>

        @if($order->notes)
            <div class="section">
                <div class="section-title">ملاحظات</div>
                <div class="notes">{{ $order->notes }}</div>
            </div>
        @endif

        <div class="footer">
            <p>تم إنشاء هذا الطلب بتاريخ {{ $order->created_at->format('Y/m/d') }} - {{ $order->source_label }}</p>
        </div>
    </div>

    <script>
        // Auto print when page loads
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
