@extends('layouts.customer')

@section('title', 'تعديل فئة - لوحة التحكم')

@section('content')
<div class="page-header-bar">
    <h1 class="page-title">تعديل الفئة</h1>
</div>

<div class="product-form-container" style="max-width: 600px;">
    <form action="{{ route('customer.categories.update', $category) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="form-section">
            <div class="form-group-new">
                <label for="name">اسم الفئة</label>
                <input type="text" id="name" name="name" value="{{ old('name', $category->name) }}" required>
                @error('name')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group-new">
                <label for="description">وصف الفئة (اختياري)</label>
                <textarea id="description" name="description" rows="4">{{ old('description', $category->description) }}</textarea>
                @error('description')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="form-actions-bar">
            <a href="{{ route('customer.categories.index') }}" class="btn btn-secondary">إلغاء</a>
            <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
        </div>
    </form>
</div>
@endsection
