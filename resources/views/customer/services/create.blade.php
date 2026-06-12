@extends('layouts.customer')

@section('title', 'إضافة خدمة جديدة')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-800">إضافة خدمة جديدة</h2>
        <p class="text-gray-500 mt-1">أضف خدمة جديدة ليتمكن عملائك من طلبها</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
        <form action="{{ route('customer.services.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">اسم الخدمة <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all"
                       placeholder="مثال: تصميم شعار">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">الوصف</label>
                <textarea name="description" rows="4"
                          class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all"
                          placeholder="وصف تفصيلي للخدمة...">{{ old('description') }}</textarea>
                @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">السعر (د.ع) <span class="text-red-500">*</span></label>
                    <input type="number" name="price" value="{{ old('price') }}" required step="0.01" min="0"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all"
                           placeholder="0.00">
                    @error('price') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">مدة التنفيذ (بالدقائق)</label>
                    <input type="number" name="duration_minutes" value="{{ old('duration_minutes') }}" min="0"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all"
                           placeholder="60">
                    @error('duration_minutes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">صورة الخدمة</label>
                <input type="file" name="image" accept="image/*"
                       class="w-full px-4 py-2.5 rounded-lg border border-gray-200 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#00A8E8]/10 file:text-[#00A8E8] hover:file:bg-[#00A8E8]/20">
                @error('image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-end gap-4 pt-6 border-t border-gray-100">
                <a href="{{ route('customer.services.index') }}" class="px-6 py-2.5 text-gray-600 hover:text-gray-800 font-medium transition-colors">إلغاء</a>
                <button type="submit" class="bg-[#00A8E8] text-white px-8 py-3 rounded-xl font-bold hover:bg-[#00658B] transition-all shadow-lg">
                    حفظ الخدمة
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
