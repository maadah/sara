@extends('layouts.customer')

@section('title', 'إنشاء حملة بث')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-800">إنشاء حملة بث جديدة</h2>
        <p class="text-gray-500 mt-1">أرسل رسالة جماعية لعملائك عبر المنصات المرتبطة</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
        <form action="{{ route('customer.broadcasts.store') }}" method="POST" class="space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">اسم الحملة <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       placeholder="مثال: عرض نهاية الأسبوع"
                       class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">نص الرسالة <span class="text-red-500">*</span></label>
                <textarea name="message" rows="6" required maxlength="2000"
                          placeholder="اكتب رسالتك هنا..."
                          class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all">{{ old('message') }}</textarea>
                <p class="text-xs text-gray-400 mt-1">الحد الأقصى 2000 حرف</p>
                @error('message') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">الجمهور المستهدف</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-[#00A8E8] cursor-pointer transition-colors">
                        <input type="checkbox" name="target_audience[]" value="all_customers" checked
                               class="rounded border-gray-300 text-[#00A8E8] focus:ring-[#00A8E8]">
                        <span class="text-sm text-gray-700">جميع العملاء</span>
                    </label>
                    <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-[#00A8E8] cursor-pointer transition-colors">
                        <input type="checkbox" name="target_audience[]" value="active_leads"
                               class="rounded border-gray-300 text-[#00A8E8] focus:ring-[#00A8E8]">
                        <span class="text-sm text-gray-700">العملاء النشطين</span>
                    </label>
                    <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-[#00A8E8] cursor-pointer transition-colors">
                        <input type="checkbox" name="target_audience[]" value="new_leads"
                               class="rounded border-gray-300 text-[#00A8E8] focus:ring-[#00A8E8]">
                        <span class="text-sm text-gray-700">العملاء الجدد</span>
                    </label>
                    <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-[#00A8E8] cursor-pointer transition-colors">
                        <input type="checkbox" name="target_audience[]" value="hot_leads"
                               class="rounded border-gray-300 text-[#00A8E8] focus:ring-[#00A8E8]">
                        <span class="text-sm text-gray-700">العملاء المهمين</span>
                    </label>
                </div>
                @error('target_audience') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">جدولة الإرسال (اختياري)</label>
                <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}"
                       class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all">
                <p class="text-xs text-gray-400 mt-1">اتركه فارغاً لحفظ كمسودة</p>
                @error('scheduled_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-end gap-4 pt-6 border-t border-gray-100">
                <a href="{{ route('customer.broadcasts.index') }}" class="px-6 py-2.5 text-gray-600 hover:text-gray-800 font-medium transition-colors">إلغاء</a>
                <button type="submit" class="bg-[#00A8E8] text-white px-8 py-3 rounded-xl font-bold hover:bg-[#00658B] transition-all shadow-lg">
                    إنشاء الحملة
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
