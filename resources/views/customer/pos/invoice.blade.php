<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة رقم {{ $sale->invoice_number }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: #fff;
            color: #333;
            direction: rtl;
        }

        .invoice-container {
            width: 210mm;
            min-height: 297mm;
            max-width: 210mm;
            margin: 0 auto;
            padding: 15mm 20mm;
            background: #fff;
            box-sizing: border-box;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #25D366;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .company-info h1 {
            font-size: 22px;
            color: #25D366;
            margin-bottom: 5px;
        }

        .company-info p {
            color: #666;
            font-size: 11px;
        }

        .invoice-info {
            text-align: left;
        }

        .invoice-info h2 {
            font-size: 24px;
            color: #333;
            margin-bottom: 8px;
        }

        .invoice-info .invoice-number {
            font-size: 13px;
            color: #25D366;
            font-weight: 600;
        }

        .invoice-info .invoice-date {
            font-size: 11px;
            color: #666;
        }

        .parties {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .party-box {
            width: 48%;
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
        }

        .party-box h3 {
            font-size: 13px;
            color: #25D366;
            margin-bottom: 8px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 5px;
        }

        .party-box p {
            font-size: 11px;
            color: #333;
            margin-bottom: 3px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th {
            background: #25D366;
            color: #fff;
            padding: 10px;
            text-align: right;
            font-size: 11px;
            font-weight: 600;
        }

        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 11px;
        }

        .items-table tr:nth-child(even) {
            background: #f8f9fa;
        }

        .items-table .text-center {
            text-align: center;
        }

        .items-table .text-left {
            text-align: left;
        }

        .totals {
            width: 280px;
            margin-right: auto;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 12px;
            border-bottom: 1px solid #e0e0e0;
        }

        .totals-row.total {
            font-size: 14px;
            font-weight: 700;
            color: #25D366;
            border-bottom: 2px solid #25D366;
            border-top: 2px solid #25D366;
            padding: 10px 0;
            margin-top: 5px;
        }

        .notes {
            margin-top: 20px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .notes h4 {
            font-size: 11px;
            color: #25D366;
            margin-bottom: 5px;
        }

        .notes p {
            font-size: 10px;
            color: #666;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            color: #999;
            font-size: 10px;
            border-top: 1px solid #e0e0e0;
            padding-top: 15px;
        }

        .payment-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .payment-cash { background: #d4edda; color: #155724; }
        .payment-card { background: #cce5ff; color: #004085; }
        .payment-transfer { background: #fff3cd; color: #856404; }

        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }

            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .invoice-container {
                width: 100%;
                min-height: auto;
                padding: 5mm;
                margin: 0;
            }

            .no-print {
                display: none !important;
            }
        }

        .print-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 12px 24px;
            background: #25D366;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: 'Cairo', sans-serif;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .print-btn:hover {
            background: #128C7E;
        }

        .print-btn svg {
            width: 20px;
            height: 20px;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
        </svg>
        طباعة الفاتورة
    </button>

    <div class="invoice-container">
        <div class="invoice-header">
            <div class="company-info">
                <h1>{{ $sale->user->name ?? 'متجري' }}</h1>
                <p>{{ $sale->user->store_address ?? '' }}</p>
                <p>{{ $sale->user->phone ?? '' }}</p>
            </div>
            <div class="invoice-info">
                <h2>فاتورة</h2>
                <p class="invoice-number">{{ $sale->invoice_number }}</p>
                <p class="invoice-date">{{ $sale->created_at->format('Y/m/d - H:i') }}</p>
            </div>
        </div>

        <div class="parties">
            <div class="party-box">
                <h3>معلومات العميل</h3>
                <p><strong>الاسم:</strong> {{ $sale->customer_name ?? 'عميل نقدي' }}</p>
                @if($sale->customer_phone)
                    <p><strong>الهاتف:</strong> {{ $sale->customer_phone }}</p>
                @endif
            </div>
            <div class="party-box">
                <h3>معلومات الدفع</h3>
                <p>
                    <strong>طريقة الدفع:</strong>
                    <span class="payment-badge payment-{{ $sale->payment_method }}">
                        @if($sale->payment_method == 'cash') نقداً
                        @elseif($sale->payment_method == 'card') بطاقة
                        @else تحويل
                        @endif
                    </span>
                </p>
                <p><strong>الحالة:</strong> {{ $sale->status == 'completed' ? 'مكتملة' : ($sale->status == 'cancelled' ? 'ملغاة' : 'مسترجعة') }}</p>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>المنتج</th>
                    <th class="text-center" style="width: 80px;">الكمية</th>
                    <th class="text-left" style="width: 120px;">السعر</th>
                    <th class="text-left" style="width: 120px;">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->product_name }}</td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-left">{{ number_format($item->unit_price) }} {{ $sale->currency == 'USD' ? '$' : 'د.ع' }}</td>
                    <td class="text-left">{{ number_format($item->total) }} {{ $sale->currency == 'USD' ? '$' : 'د.ع' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-row">
                <span>المجموع الفرعي:</span>
                <span>{{ number_format($sale->subtotal) }} {{ $sale->currency == 'USD' ? '$' : 'د.ع' }}</span>
            </div>
            @if($sale->discount_amount > 0)
            <div class="totals-row">
                <span>الخصم{{ $sale->discount_percentage > 0 ? ' (' . $sale->discount_percentage . '%)' : '' }}:</span>
                <span>- {{ number_format($sale->discount_amount) }} {{ $sale->currency == 'USD' ? '$' : 'د.ع' }}</span>
            </div>
            @endif
            <div class="totals-row total">
                <span>الإجمالي:</span>
                <span>{{ number_format($sale->total) }} {{ $sale->currency == 'USD' ? '$' : 'د.ع' }}</span>
            </div>
        </div>

        @if($sale->notes)
        <div class="notes">
            <h4>ملاحظات:</h4>
            <p>{{ $sale->notes }}</p>
        </div>
        @endif

        <div class="footer">
            <p>شكراً لتعاملكم معنا</p>
            <p>هذه الفاتورة تم إنشاؤها إلكترونياً</p>
        </div>
    </div>
</body>
</html>
