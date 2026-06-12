@extends('layouts.customer')
@section('title', $competitor->name . ' - تحليل المنافسين')
@section('page-title', 'تفاصيل المنافس')

@section('content')
<div class="space-y-6">

    {{-- Back Button --}}
    <a href="{{ route('customer.competitors.index') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-[#00A8E8] transition">
        <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        العودة للقائمة
    </a>

    {{-- Competitor Info Card --}}
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-start justify-between mb-6">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#00A8E8] to-[#007EA7] flex items-center justify-center text-white font-bold text-2xl">
                    {{ mb_substr($competitor->name, 0, 1) }}
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">{{ $competitor->name }}</h2>
                    @if($competitor->url)
                        <a href="{{ $competitor->url }}" target="_blank" rel="noopener noreferrer" class="text-sm text-[#00A8E8] hover:underline" dir="ltr">{{ $competitor->url }}</a>
                    @endif
                </div>
            </div>
            <form action="{{ route('customer.competitors.destroy', $competitor) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا المنافس؟')">
                @csrf
                @method('DELETE')
                <button class="px-4 py-2 text-sm text-red-500 hover:bg-red-50 rounded-xl transition">حذف المنافس</button>
            </form>
        </div>

        {{-- Edit Form --}}
        <form action="{{ route('customer.competitors.update', $competitor) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">اسم المنافس *</label>
                    <input type="text" name="name" value="{{ old('name', $competitor->name) }}" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">رابط الموقع</label>
                    <input type="url" name="url" value="{{ old('url', $competitor->url) }}" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition" dir="ltr">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">الوصف</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition">{{ old('description', $competitor->description) }}</textarea>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2.5 bg-gradient-to-l from-[#00A8E8] to-[#007EA7] text-white rounded-xl font-medium hover:shadow-lg transition">
                    تحديث البيانات
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

    {{-- Analysis Results --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        {{-- Strengths --}}
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h3 class="font-bold text-gray-800">نقاط القوة</h3>
            </div>
            @if($competitor->strengths && count($competitor->strengths))
                <ul class="space-y-2">
                    @foreach($competitor->strengths as $strength)
                        <li class="flex items-start gap-2 text-sm text-gray-600">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 mt-1.5 shrink-0"></span>
                            {{ $strength }}
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-400">لم يتم التحليل بعد</p>
            @endif
        </div>

        {{-- Weaknesses --}}
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <h3 class="font-bold text-gray-800">نقاط الضعف</h3>
            </div>
            @if($competitor->weaknesses && count($competitor->weaknesses))
                <ul class="space-y-2">
                    @foreach($competitor->weaknesses as $weakness)
                        <li class="flex items-start gap-2 text-sm text-gray-600">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 mt-1.5 shrink-0"></span>
                            {{ $weakness }}
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-400">لم يتم التحليل بعد</p>
            @endif
        </div>

        {{-- Content Gaps --}}
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                </div>
                <h3 class="font-bold text-gray-800">الفجوات في المحتوى</h3>
            </div>
            @if($competitor->content_gaps && count($competitor->content_gaps))
                <ul class="space-y-2">
                    @foreach($competitor->content_gaps as $gap)
                        <li class="flex items-start gap-2 text-sm text-gray-600">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mt-1.5 shrink-0"></span>
                            {{ $gap }}
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-400">لم يتم التحليل بعد</p>
            @endif
        </div>
    </div>

    {{-- Metadata --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between text-sm text-gray-500">
            <span>تاريخ الإضافة: {{ $competitor->created_at->format('Y/m/d') }}</span>
            @if($competitor->last_analyzed_at)
                <span>آخر تحليل: {{ $competitor->last_analyzed_at->format('Y/m/d H:i') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection