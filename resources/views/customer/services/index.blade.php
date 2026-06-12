@extends('layouts.customer')

@section('title', 'إدارة الخدمات')

@section('content')
<div class="flex items-center justify-between mb-8">
    <div>
        <h2 class="text-3xl font-bold text-gray-800">الخدمات</h2>
        <p class="text-gray-500 mt-1">إدارة خدماتك وعروضك</p>
    </div>
    <a href="{{ route('customer.services.create') }}" class="bg-[#00A8E8] text-white px-6 py-3 rounded-xl font-bold hover:bg-[#00658B] transition-all shadow-lg hover:shadow-[#00A8E8]/30 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        إضافة خدمة
    </a>
</div>

@if(session('success'))
    <div class="bg-green-50 text-green-700 p-4 rounded-xl mb-6 flex items-center gap-3 border border-green-100">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        {{ session('success') }}
    </div>
@endif

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    @if($services->count() > 0)
        <table class="w-full text-start">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-6 py-4 text-start text-sm font-semibold text-gray-500">الخدمة</th>
                    <th class="px-6 py-4 text-start text-sm font-semibold text-gray-500">مدة التنفيذ</th>
                    <th class="px-6 py-4 text-start text-sm font-semibold text-gray-500">السعر</th>
                    <th class="px-6 py-4 text-start text-sm font-semibold text-gray-500">الحالة</th>
                    <th class="px-6 py-4 text-start text-sm font-semibold text-gray-500">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($services as $service)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-lg bg-gray-100 border border-gray-200 overflow-hidden flex-shrink-0">
                                    @if($service->portfolio && count($service->portfolio) > 0)
                                        <img src="{{ Storage::url($service->portfolio[0]) }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">{{ $service->name }}</div>
                                    <div class="text-xs text-gray-500 truncate max-w-xs">{{ Str::limit($service->description, 50) }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ $service->duration_minutes ? $service->duration_minutes . ' دقيقة' : '--' }}
                        </td>
                        <td class="px-6 py-4 text-sm font-bold text-gray-900">
                            {{ number_format($service->price) }} د.ع
                        </td>
                        <td class="px-6 py-4">
                            @if($service->is_active)
                                <span class="px-2.5 py-0.5 rounded-full bg-green-50 text-green-600 text-xs font-semibold border border-green-100">نشط</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full bg-gray-50 text-gray-600 text-xs font-semibold border border-gray-100">غير نشط</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('customer.services.edit', $service) }}" class="p-2 text-gray-400 hover:text-[#00A8E8] hover:bg-[#00A8E8]/5 rounded-lg transition-colors" title="تعديل">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <form action="{{ route('customer.services.destroy', $service) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذه الخدمة؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="حذف">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if($services->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $services->links() }}
        </div>
        @endif
    @else
        <div class="text-center py-20">
            <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto text-gray-300 mb-4">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-1">لا توجد خدمات بعد</h3>
            <p class="text-gray-500 mb-6">أضف خدماتك ليتمكن العملاء من طلبها</p>
            <a href="{{ route('customer.services.create') }}" class="inline-flex bg-[#00A8E8] text-white px-6 py-2.5 rounded-xl font-bold hover:bg-[#00658B] transition-all shadow-lg items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                إضافة خدمة الآن
            </a>
        </div>
    @endif
</div>
@endsection
