@extends('layouts.customer')

@section('title', 'حملات البث')

@section('content')
<div class="space-y-8">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-bold text-gray-800">حملات البث</h2>
            <p class="text-gray-500 mt-1">إرسال رسائل جماعية لعملائك</p>
        </div>
        <a href="{{ route('customer.broadcasts.create') }}"
           class="bg-[#00A8E8] text-white px-6 py-3 rounded-xl font-bold hover:bg-[#00658B] transition-all shadow-lg flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            حملة جديدة
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ $stats['total'] }}</p>
                    <p class="text-xs text-gray-500">إجمالي الحملات</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ $stats['sent'] }}</p>
                    <p class="text-xs text-gray-500">تم الإرسال</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-yellow-100 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ $stats['draft'] }}</p>
                    <p class="text-xs text-gray-500">مسودات</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['total_reached']) }}</p>
                    <p class="text-xs text-gray-500">إجمالي الوصول</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Broadcasts List --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        @if($broadcasts->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-right px-6 py-4 text-xs font-semibold text-gray-500 uppercase">الحملة</th>
                            <th class="text-right px-6 py-4 text-xs font-semibold text-gray-500 uppercase">الحالة</th>
                            <th class="text-right px-6 py-4 text-xs font-semibold text-gray-500 uppercase">المستلمين</th>
                            <th class="text-right px-6 py-4 text-xs font-semibold text-gray-500 uppercase">التاريخ</th>
                            <th class="text-right px-6 py-4 text-xs font-semibold text-gray-500 uppercase">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($broadcasts as $broadcast)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-semibold text-gray-800">{{ $broadcast->name }}</p>
                                        <p class="text-sm text-gray-500 truncate max-w-xs">{{ Str::limit($broadcast->message, 60) }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $statusColors = [
                                            'draft' => 'bg-gray-100 text-gray-700',
                                            'scheduled' => 'bg-blue-100 text-blue-700',
                                            'sending' => 'bg-yellow-100 text-yellow-700',
                                            'completed' => 'bg-green-100 text-green-700',
                                            'failed' => 'bg-red-100 text-red-700',
                                        ];
                                        $statusLabels = [
                                            'draft' => 'مسودة',
                                            'scheduled' => 'مجدول',
                                            'sending' => 'جاري الإرسال',
                                            'completed' => 'مكتمل',
                                            'failed' => 'فشل',
                                        ];
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusColors[$broadcast->status] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ $statusLabels[$broadcast->status] ?? $broadcast->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    {{ $broadcast->sent_count }}/{{ $broadcast->total_recipients }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $broadcast->created_at->diffForHumans() }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('customer.broadcasts.show', $broadcast) }}" class="text-[#00A8E8] hover:text-[#00658B] text-sm font-medium">عرض</a>
                                        @if($broadcast->status === 'draft')
                                            <form action="{{ route('customer.broadcasts.destroy', $broadcast) }}" method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-red-500 hover:text-red-700 text-sm font-medium">حذف</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-100">{{ $broadcasts->links() }}</div>
        @else
            <div class="text-center py-16">
                <div class="w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">لا توجد حملات بث</h3>
                <p class="text-gray-500 mb-6">ابدأ بإنشاء حملتك الأولى للتواصل مع عملائك</p>
                <a href="{{ route('customer.broadcasts.create') }}" class="bg-[#00A8E8] text-white px-6 py-3 rounded-xl font-bold hover:bg-[#00658B] transition-all">
                    إنشاء حملة جديدة
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
