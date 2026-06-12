@extends('layouts.customer')
@section('title', 'تحليل المنافسين')
@section('page-title', 'تحليل المنافسين')

@section('content')
<div class="space-y-6">

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#00A8E8]/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-[#00A8E8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ $competitors->total() }}</p>
                    <p class="text-sm text-gray-500">إجمالي المنافسين</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ $competitors->where('last_analyzed_at', '!=', null)->count() }}</p>
                    <p class="text-sm text-gray-500">تم تحليلهم</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ $competitors->where('last_analyzed_at', null)->count() }}</p>
                    <p class="text-sm text-gray-500">بانتظار التحليل</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Add Competitor Form --}}
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <h3 class="text-lg font-bold text-gray-800 mb-4">إضافة منافس جديد</h3>
        <form action="{{ route('customer.competitors.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">اسم المنافس *</label>
                <input type="text" name="name" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition" placeholder="مثال: متجر المنافس">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">رابط الموقع</label>
                <input type="url" name="url" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition" placeholder="https://example.com" dir="ltr">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-600 mb-1">الوصف</label>
                <textarea name="description" rows="2" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition" placeholder="وصف مختصر عن المنافس..."></textarea>
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="px-6 py-2.5 bg-gradient-to-l from-[#00A8E8] to-[#007EA7] text-white rounded-xl font-medium hover:shadow-lg transition">
                    إضافة منافس
                </button>
            </div>
        </form>
        @if($errors->any())
            <div class="mt-3 p-3 bg-red-50 text-red-700 rounded-xl text-sm">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Competitors Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($competitors as $competitor)
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 hover:shadow-md transition group">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#00A8E8] to-[#007EA7] flex items-center justify-center text-white font-bold text-lg">
                        {{ mb_substr($competitor->name, 0, 1) }}
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800">{{ $competitor->name }}</h4>
                        @if($competitor->url)
                            <a href="{{ $competitor->url }}" target="_blank" rel="noopener noreferrer" class="text-xs text-[#00A8E8] hover:underline" dir="ltr">{{ parse_url($competitor->url, PHP_URL_HOST) }}</a>
                        @endif
                    </div>
                </div>
                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition">
                    <a href="{{ route('customer.competitors.show', $competitor) }}" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-[#00A8E8]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </a>
                    <form action="{{ route('customer.competitors.destroy', $competitor) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا المنافس؟')">
                        @csrf
                        @method('DELETE')
                        <button class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
            </div>

            @if($competitor->description)
                <p class="text-sm text-gray-500 mb-3 line-clamp-2">{{ $competitor->description }}</p>
            @endif

            <div class="flex flex-wrap gap-2 mb-3">
                @if($competitor->strengths && count($competitor->strengths))
                    <span class="text-xs px-2 py-1 bg-green-50 text-green-700 rounded-lg">{{ count($competitor->strengths) }} نقاط قوة</span>
                @endif
                @if($competitor->weaknesses && count($competitor->weaknesses))
                    <span class="text-xs px-2 py-1 bg-red-50 text-red-700 rounded-lg">{{ count($competitor->weaknesses) }} نقاط ضعف</span>
                @endif
                @if($competitor->content_gaps && count($competitor->content_gaps))
                    <span class="text-xs px-2 py-1 bg-amber-50 text-amber-700 rounded-lg">{{ count($competitor->content_gaps) }} فجوات</span>
                @endif
            </div>

            <div class="flex items-center justify-between text-xs text-gray-400">
                <span>{{ $competitor->created_at->diffForHumans() }}</span>
                @if($competitor->last_analyzed_at)
                    <span class="text-green-600">آخر تحليل: {{ $competitor->last_analyzed_at->diffForHumans() }}</span>
                @else
                    <span class="text-amber-500">لم يتم التحليل بعد</span>
                @endif
            </div>
        </div>
        @empty
        <div class="md:col-span-2 lg:col-span-3 bg-white rounded-2xl p-12 shadow-sm border border-gray-100 text-center">
            <div class="w-16 h-16 rounded-2xl bg-[#00A8E8]/10 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-[#00A8E8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800 mb-2">لا يوجد منافسين بعد</h3>
            <p class="text-gray-500">أضف منافسيك لبدء تحليلهم ومعرفة نقاط القوة والضعف</p>
        </div>
        @endforelse
    </div>

    {{ $competitors->links() }}
</div>
@endsection