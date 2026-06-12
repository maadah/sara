@extends('layouts.customer')

@section('title', 'تفاصيل المنتج - لوحة التحكم')

@section('content')
<div class="page-header-bar">
    <h1 class="page-title">{{ $product->name }}</h1>
    <div style="display: flex; gap: 10px;">
        <a href="{{ route('customer.products.edit', $product) }}" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18">
                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
            </svg>
            تعديل
        </a>
        <a href="{{ route('customer.products.index') }}" class="btn btn-secondary">
            رجوع
        </a>
    </div>
</div>

<div class="profile-details-grid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">معلومات المنتج</h3>
        </div>
        <div class="card-body">
            <div class="detail-item">
                <span class="detail-label">اسم المنتج</span>
                <span class="detail-value">{{ $product->name }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">الفئة</span>
                <span class="detail-value">{{ $product->category->name }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">السعر</span>
                <span class="detail-value">{{ number_format($product->price) }} {{ $product->currency == 'USD' ? '$' : 'د.ع' }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">الكمية الكلية</span>
                <span class="detail-value">{{ $product->quantity }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">الكمية المحجوزة</span>
                <span class="detail-value">{{ $product->reserved_quantity }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">الكمية المتاحة</span>
                <span class="detail-value">{{ $product->available_quantity }}</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">معلومات إضافية</h3>
        </div>
        <div class="card-body">
            <div class="detail-item">
                <span class="detail-label">وحدة القياس</span>
                <span class="detail-value">{{ $product->unit ?: '-' }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">وحدة البيع</span>
                <span class="detail-value">{{ $product->sell_unit ?: '-' }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">معامل التحويل</span>
                <span class="detail-value">{{ $product->conversion_factor ?: '-' }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">تاريخ الصلاحية</span>
                <span class="detail-value">{{ $product->expiry_date ? $product->expiry_date->format('Y/m/d') : 'لا ينطبق' }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">تاريخ الإضافة</span>
                <span class="detail-value">{{ $product->created_at->format('Y/m/d') }}</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">الوصف</h3>
        </div>
        <div class="card-body">
            <p style="color: var(--text-muted); line-height: 1.8;">
                {{ $product->description ?: 'لا يوجد وصف' }}
            </p>
        </div>
    </div>
</div>

@if($product->images->count() > 0)
    <div class="card" style="margin-top: 20px;">
        <div class="card-header">
            <h3 class="card-title">صور المنتج</h3>
        </div>
        <div class="card-body">
            <div class="uploaded-images" style="gap: 20px;">
                @foreach($product->images as $image)
                    <div class="uploaded-image" style="width: 150px; height: 150px;">
                        <img src="{{ asset('storage/' . $image->image_path) }}" alt="Product Image">
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
@endsection
