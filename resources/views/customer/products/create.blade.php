@extends('layouts.customer')

@section('title', 'إضافة منتج - لوحة تغيييير')

@section('content')


<div class="product-form-container">
    @php
        $sellUnits = [
            'قطعة',
            'كرتون',
            'علبة',
            'كيلو',
            'جرام',
            'لتر',
            'متر',
            'باكت',
            'دزينة',
        ];
    @endphp
<button type="button" id="importFacebookBtn">
    📥 استيراد من فيسبوك
</button>

<input type="text" id="facebookUrl" placeholder="رابط صفحة فيسبوك">
    <form action="{{ route('customer.products.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="product-form-grid">
            <div class="form-section">
                <div class="form-group-new">
                    <label for="name">اسم المنتج</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                    @error('name')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group-new">
                    <label for="price">سعر المنتج</label>
                    <div class="price-input-group">
                        <input type="number" id="price" name="price" value="{{ old('price') }}" step="0.01" required>
                        <select name="currency" id="currency" class="currency-select">
                            <option value="IQD" {{ old('currency', 'IQD') == 'IQD' ? 'selected' : '' }}>د.ع</option>
                            <option value="USD" {{ old('currency') == 'USD' ? 'selected' : '' }}>$</option>
                        </select>
                    </div>
                    @error('price')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group-new">
                    <label for="description">وصف المنتج</label>
                    <textarea id="description" name="description">{{ old('description') }}</textarea>
                    @error('description')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group-new">
                    <label for="quantity">الكمية الكلية</label>
                    <input type="number" id="quantity" name="quantity" value="{{ old('quantity', 0) }}" min="0" required>
                    @error('quantity')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group-new">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="manage_stock" value="1" {{ old('manage_stock', '1') ? 'checked' : '' }} id="manage_stock">
                        تتبع المخزون
                    </label>
                    <small style="color:#6b7280;display:block;margin-top:4px">عطّل هذا الخيار للمنتجات والخدمات الرقمية (مثل خدمات الفيديو والتصميم) التي لا تحتاج لتتبع الكمية — سيظهر المنتج دائماً كـ "متاح للطلب"</small>
                </div>

                <div class="form-group-new">
                    <label for="sell_unit">وحدة البيع</label>
                                        <select id="sell_unit" name="sell_unit">
                        <option value="">اختر وحدة البيع</option>
                        @foreach($sellUnits as $unitOption)
                            <option value="{{ $unitOption }}" {{ old('sell_unit') == $unitOption ? 'selected' : '' }}>
                                {{ $unitOption }}
                            </option>
                        @endforeach
                    </select>
                    @error('sell_unit')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group-new">
                    <label for="expiry_date">تاريخ الصلاحية</label>
                    <input type="date" id="expiry_date" name="expiry_date" value="{{ old('expiry_date') }}">
                    @error('expiry_date')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-section">
                <div class="form-group-new">
                    <label for="category_id">الفئة</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">اختر الفئة</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                    @if($categories->isEmpty())
                        <small style="color: var(--warning);">
                            <a href="{{ route('customer.categories.create') }}" style="color: var(--primary-green);">أضف فئة أولاً</a>
                        </small>
                    @endif
                </div>

                <div class="form-group-new">
                    <label for="unit">وحدة القياس</label>
                    <input type="text" id="unit" name="unit" value="{{ old('unit') }}">
                    @error('unit')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group-new">
                    <label>صورة المنتج</label>
                    <div class="image-upload-area" onclick="document.getElementById('images').click()">
                        <svg class="upload-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                        </svg>
                        <p>اضغط لإضافة صور</p>
                    </div>
                    <input type="file" id="images" name="images[]" multiple accept="image/*" style="display: none;" onchange="previewImages(this)">
                    <div id="preview-container" class="uploaded-images"></div>
                    @error('images.*')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group-new">
                    <label for="conversion_factor">معامل التحويل</label>
                    <input type="number" id="conversion_factor" name="conversion_factor" value="{{ old('conversion_factor') }}" step="0.0001">
                    @error('conversion_factor')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group-new">
                    <div class="flex items-center justify-between pointer-events-none w-full">
                        <label for="facebook_post_url" class="pointer-events-auto">رابط منشور فيسبوك</label>
                        <button type="button" onclick="openPostPickerModal('facebook', 'facebook_post_url')" class="pointer-events-auto flex items-center gap-1.5 text-xs font-medium text-[#00A8E8] hover:text-blue-700 bg-blue-50 px-2.5 py-1 rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            إختيار من الصفحة
                        </button>
                    </div>
                    <input type="url" id="facebook_post_url" name="facebook_post_url" value="{{ old('facebook_post_url') }}" placeholder="أدخل الرابط أو اختر من الصفحة...">
                    @error('facebook_post_url')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group-new mt-4">
                    <div class="flex items-center justify-between pointer-events-none w-full">
                        <label for="instagram_post_url" class="pointer-events-auto">رابط منشور انستقرام</label>
                        <button type="button" onclick="openPostPickerModal('instagram', 'instagram_post_url')" class="pointer-events-auto flex items-center gap-1.5 text-xs font-medium text-[#c13584] hover:text-pink-700 bg-pink-50 px-2.5 py-1 rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            إختيار من الحساب
                        </button>
                    </div>
                    <input type="url" id="instagram_post_url" name="instagram_post_url" value="{{ old('instagram_post_url') }}" placeholder="أدخل الرابط أو اختر من החساب...">
                    @error('instagram_post_url')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        </div>

        <div class="form-actions-bar">
            <a href="{{ route('customer.products.index') }}" class="btn btn-secondary">إلغاء</a>
            <button type="submit" class="btn btn-primary">كمممممل</button>
            <div class="page-header-bar">
    <h1 class="page-title">أضافة منتج</h1>
</div>
        </div>
    </form>
</div>

@include('customer.products._post_picker')

@push('scripts')
<script>
function previewImages(input) {
    const container = document.getElementById('preview-container');
    container.innerHTML = '';

    if (input.files) {
        Array.from(input.files).forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'uploaded-image';
                div.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <span class="remove-btn" onclick="removeImage(${index})">×</span>
                `;
                container.appendChild(div);
            }
            reader.readAsDataURL(file);
        });
    }
}

function removeImage(index) {
    // Note: This is a simplified version. Full implementation would need DataTransfer API
    const container = document.getElementById('preview-container');
    container.children[index].remove();
}
</script>
@endpush
@endsection
