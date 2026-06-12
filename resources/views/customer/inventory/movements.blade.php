@extends('layouts.customer')
@section('title', 'سجل حركات المخزون')
@section('page-title', 'سجل حركات المخزون')

@section('content')
<div class="space-y-6">

    {{-- Back + Filters --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('customer.inventory.index') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-[#00A8E8] transition">
            <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            العودة للمخزون
        </a>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <form method="GET" action="{{ route('customer.inventory.movements') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">نوع الحركة</label>
                <select name="type" class="px-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8]">
                    <option value="">الكل</option>
                    <option value="sale" {{ request('type') == 'sale' ? 'selected' : '' }}>بيع</option>
                    <option value="purchase" {{ request('type') == 'purchase' ? 'selected' : '' }}>شراء</option>
                    <option value="return" {{ request('type') == 'return' ? 'selected' : '' }}>إرجاع</option>
                    <option value="adjustment" {{ request('type') == 'adjustment' ? 'selected' : '' }}>تعديل</option>
                    <option value="damage" {{ request('type') == 'damage' ? 'selected' : '' }}>تالف</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">المنتج</label>
                <select name="product_id" class="px-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8]">
                    <option value="">الكل</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-[#00A8E8] text-white rounded-xl text-sm hover:bg-[#007EA7] transition">تصفية</button>
            <a href="{{ route('customer.inventory.movements') }}" class="px-4 py-2 text-gray-500 hover:text-gray-700 text-sm">مسح</a>
        </form>
    </div>

    {{-- Movements Table --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">التاريخ</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">المنتج</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">النوع</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">الكمية</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">قبل</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">بعد</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">ملاحظات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($movements as $movement)
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="px-5 py-3 text-sm text-gray-500">{{ $movement->created_at->format('m/d H:i') }}</td>
                        <td class="px-5 py-3 text-sm font-medium text-gray-800">{{ $movement->product->name ?? 'محذوف' }}</td>
                        <td class="px-5 py-3 text-center">
                            @php
                                $typeLabels = ['sale' => 'بيع', 'purchase' => 'شراء', 'return' => 'إرجاع', 'adjustment' => 'تعديل', 'damage' => 'تالف'];
                                $typeColors = ['sale' => 'bg-blue-50 text-blue-700', 'purchase' => 'bg-green-50 text-green-700', 'return' => 'bg-purple-50 text-purple-700', 'adjustment' => 'bg-gray-50 text-gray-700', 'damage' => 'bg-red-50 text-red-700'];
                            @endphp
                            <span class="px-2.5 py-1 text-xs font-medium rounded-lg {{ $typeColors[$movement->type] ?? 'bg-gray-50 text-gray-700' }}">
                                {{ $typeLabels[$movement->type] ?? $movement->type }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <span class="font-bold text-sm {{ $movement->quantity > 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-center text-sm text-gray-500">{{ $movement->stock_before }}</td>
                        <td class="px-5 py-3 text-center text-sm text-gray-800 font-medium">{{ $movement->stock_after }}</td>
                        <td class="px-5 py-3 text-sm text-gray-500">{{ $movement->notes ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                            <p class="font-medium">لا توجد حركات مسجلة</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $movements->links() }}
</div>
@endsection