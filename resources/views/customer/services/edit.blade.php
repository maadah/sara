@extends('layouts.customer')

@section('title', 'تعديل خدمة')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-800">تعديل الخدمة</h2>
        <p class="text-gray-500 mt-1">تعديل بيانات الخدمة: {{ $service->name }}</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
        <form action="{{ route('customer.services.update', $service) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">اسم الخدمة <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $service->name) }}" required
                       class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">الوصف</label>
                <textarea name="description" rows="4"
                          class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all">{{ old('description', $service->description) }}</textarea>
                @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">السعر (د.ع) <span class="text-red-500">*</span></label>
                    <input type="number" name="price" value="{{ old('price', $service->price) }}" required step="0.01" min="0"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all">
                    @error('price') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">مدة التنفيذ (بالدقائق)</label>
                    <input type="number" name="duration_minutes" value="{{ old('duration_minutes', $service->duration_minutes) }}" min="0"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all">
                    @error('duration_minutes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center gap-3">
                <input type="checkbox" name="is_active" id="is_active" value="1" {{ $service->is_active ? 'checked' : '' }}
                       class="rounded border-gray-300 text-[#00A8E8] focus:ring-[#00A8E8]">
                <label for="is_active" class="text-sm font-medium text-gray-700">نشط</label>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">صورة الخدمة</label>
                @if($service->portfolio && count($service->portfolio) > 0)
                    <div class="mb-3">
                        <img src="{{ Storage::url($service->portfolio[0]) }}" class="w-24 h-24 object-cover rounded-lg border">
                    </div>
                @endif
                <input type="file" name="image" accept="image/*"
                       class="w-full px-4 py-2.5 rounded-lg border border-gray-200 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#00A8E8]/10 file:text-[#00A8E8] hover:file:bg-[#00A8E8]/20">
                @error('image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-end gap-4 pt-6 border-t border-gray-100">
                <a href="{{ route('customer.services.index') }}" class="px-6 py-2.5 text-gray-600 hover:text-gray-800 font-medium transition-colors">إلغاء</a>
                <button type="submit" class="bg-[#00A8E8] text-white px-8 py-3 rounded-xl font-bold hover:bg-[#00658B] transition-all shadow-lg">
                    حفظ التغييرات
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
