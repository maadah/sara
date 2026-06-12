@extends('layouts.customer')
@section('title', 'إدارة المخزون')
@section('page-title', 'إدارة المخزون')

@section('content')
<div class="space-y-6">

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#00A8E8]/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-[#00A8E8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ $totalProducts }}</p>
                    <p class="text-sm text-gray-500">إجمالي المنتجات</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-amber-600">{{ $lowStock }}</p>
                    <p class="text-sm text-gray-500">مخزون منخفض</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-red-600">{{ $outOfStock }}</p>
                    <p class="text-sm text-gray-500">نفذ من المخزون</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-green-600">{{ number_format($totalValue, 2) }}</p>
                    <p class="text-sm text-gray-500">قيمة المخزون (ر.س)</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-bold text-gray-800">المنتجات والمخزون</h3>
        <a href="{{ route('customer.inventory.movements') }}" class="px-4 py-2 text-sm text-[#00A8E8] bg-[#00A8E8]/10 rounded-xl hover:bg-[#00A8E8]/20 transition font-medium">
            عرض سجل الحركات
        </a>
    </div>

    {{-- Products Table --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">المنتج</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">السعر</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">الكمية</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">الحالة</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">تعديل المخزون</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($products as $product)
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                @if($product->image)
                                    <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" class="w-10 h-10 rounded-lg object-cover">
                                @else
                                    <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    </div>
                                @endif
                                <div>
                                    <p class="font-medium text-gray-800">{{ $product->name }}</p>
                                    @if($product->sku)
                                        <p class="text-xs text-gray-400" dir="ltr">{{ $product->sku }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-center text-sm text-gray-600">{{ number_format($product->price, 2) }} ر.س</td>
                        <td class="px-5 py-4 text-center">
                            <span class="text-lg font-bold {{ $product->stock <= 0 ? 'text-red-600' : ($product->stock <= 5 ? 'text-amber-600' : 'text-gray-800') }}">
                                {{ $product->stock }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-center">
                            @if($product->stock <= 0)
                                <span class="px-2.5 py-1 text-xs font-medium bg-red-50 text-red-700 rounded-lg">نفذ</span>
                            @elseif($product->stock <= 5)
                                <span class="px-2.5 py-1 text-xs font-medium bg-amber-50 text-amber-700 rounded-lg">منخفض</span>
                            @else
                                <span class="px-2.5 py-1 text-xs font-medium bg-green-50 text-green-700 rounded-lg">متوفر</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <form action="{{ route('customer.inventory.adjust', $product) }}" method="POST" class="flex items-center justify-center gap-2">
                                @csrf
                                <select name="type" class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8]">
                                    <option value="purchase">شراء (+)</option>
                                    <option value="sale">بيع (-)</option>
                                    <option value="return">إرجاع (+)</option>
                                    <option value="adjustment">تعديل</option>
                                    <option value="damage">تالف (-)</option>
                                </select>
                                <input type="number" name="quantity" min="1" value="1" class="w-16 text-xs text-center border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8]">
                                <button type="submit" class="px-3 py-1.5 text-xs bg-[#00A8E8] text-white rounded-lg hover:bg-[#007EA7] transition">تنفيذ</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-gray-400">
                            <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </div>
                            <p class="font-medium">لا توجد منتجات</p>
                            <p class="text-sm mt-1">أضف منتجات أولاً من قسم المنتجات</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $products->links() }}

    {{-- Recent Movements --}}
    @if($recentMovements->count())
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-gray-800">آخر الحركات</h3>
            <a href="{{ route('customer.inventory.movements') }}" class="text-sm text-[#00A8E8] hover:underline">عرض الكل</a>
        </div>
        <div class="space-y-3">
            @foreach($recentMovements as $movement)
            <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center {{ in_array($movement->type, ['purchase', 'return']) ? 'bg-green-100 text-green-600' : (in_array($movement->type, ['sale', 'damage']) ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600') }}">
                        @if(in_array($movement->type, ['purchase', 'return']))
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/></svg>
                        @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/></svg>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ $movement->product->name ?? 'منتج محذوف' }}</p>
                        <p class="text-xs text-gray-400">
                            {{ ['sale' => 'بيع', 'purchase' => 'شراء', 'return' => 'إرجاع', 'adjustment' => 'تعديل', 'damage' => 'تالف'][$movement->type] ?? $movement->type }}
                            · {{ $movement->created_at->diffForHumans() }}
                        </p>
                    </div>
                </div>
                <div class="text-left">
                    <span class="text-sm font-bold {{ $movement->quantity > 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}
                    </span>
                    <p class="text-xs text-gray-400">{{ $movement->stock_before }} → {{ $movement->stock_after }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection