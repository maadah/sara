@extends('layouts.customer')

@section('title', 'الملف الشخصي - لوحة التحكم')

@section('content')
<div class="card">
    <div class="card-header">
        <h2 class="card-title">الملف الشخصي</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('customer.profile.update') }}">
            @csrf
            @method('PUT')

            <div class="dashboard-grid">
                <div class="form-group">
                    <label class="form-label">اسم التاجر</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $user->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">البريد الالكتروني</label>
                    <input type="email" class="form-control" value="{{ $user->email }}" disabled>
                    <small style="color: var(--text-muted);">لا يمكن تغيير البريد الالكتروني</small>
                </div>

                <div class="form-group">
                    <label class="form-label">رقم الهاتف</label>
                    <input type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror"
                           value="{{ old('phone', $user->phone) }}" required>
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">رقم الواتساب</label>
                    <input type="tel" name="whatsapp" class="form-control @error('whatsapp') is-invalid @enderror"
                           value="{{ old('whatsapp', $user->whatsapp) }}">
                    @error('whatsapp')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">رابط الفيس بوك</label>
                    <input type="url" name="facebook_link" class="form-control @error('facebook_link') is-invalid @enderror"
                           value="{{ old('facebook_link', $user->facebook_link) }}">
                    @error('facebook_link')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">رابط الانستجرام</label>
                    <input type="url" name="instagram_link" class="form-control @error('instagram_link') is-invalid @enderror"
                           value="{{ old('instagram_link', $user->instagram_link) }}">
                    @error('instagram_link')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">عنوان المخزن</label>
                <input type="text" name="store_address" class="form-control @error('store_address') is-invalid @enderror"
                       value="{{ old('store_address', $user->store_address) }}">
                @error('store_address')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary" style="width: auto;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    حفظ التغييرات
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
