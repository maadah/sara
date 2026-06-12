@extends('layouts.admin')

@section('title', 'تعديل التاجر - لوحة التحكم')

@section('content')
<div class="page-header-actions">
    <a href="{{ route('admin.merchants.show', $user) }}" class="btn btn-secondary">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
        </svg>
        رجوع
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
            </svg>
            تعديل بيانات التاجر
        </h2>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.merchants.update', $user) }}" method="POST" class="merchant-form">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="form-group">
                    <label for="name" class="form-label">الاسم <span class="required">*</span></label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                    @error('name')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">البريد الالكتروني <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                    @error('email')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">رقم الهاتف</label>
                    <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}">
                    @error('phone')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="company_name" class="form-label">اسم الشركة</label>
                    <input type="text" id="company_name" name="company_name" class="form-control @error('company_name') is-invalid @enderror" value="{{ old('company_name', $user->company_name) }}">
                    @error('company_name')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="status" class="form-label">الحالة <span class="required">*</span></label>
                    <select id="status" name="status" class="form-control @error('status') is-invalid @enderror" required>
                        <option value="pending" {{ old('status', $user->status) === 'pending' ? 'selected' : '' }}>قيد الانتظار</option>
                        <option value="approved" {{ old('status', $user->status) === 'approved' ? 'selected' : '' }}>مفعل</option>
                        <option value="rejected" {{ old('status', $user->status) === 'rejected' ? 'selected' : '' }}>مرفوض</option>
                        <option value="suspended" {{ old('status', $user->status) === 'suspended' ? 'selected' : '' }}>معلق</option>
                    </select>
                    @error('status')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="subscription_id" class="form-label">الباقة</label>
                    <select id="subscription_id" name="subscription_id" class="form-control @error('subscription_id') is-invalid @enderror">
                        <option value="">بدون باقة</option>
                        @foreach($subscriptions as $subscription)
                            <option value="{{ $subscription->id }}" {{ old('subscription_id', $user->subscription_id) == $subscription->id ? 'selected' : '' }}>
                                {{ $subscription->name }} - {{ $subscription->price }} د.ع
                            </option>
                        @endforeach
                    </select>
                    @error('subscription_id')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="subscription_expires_at" class="form-label">تاريخ انتهاء الاشتراك</label>
                    <input type="date" id="subscription_expires_at" name="subscription_expires_at" class="form-control @error('subscription_expires_at') is-invalid @enderror" value="{{ old('subscription_expires_at', $user->subscription_expires_at ? $user->subscription_expires_at->format('Y-m-d') : '') }}">
                    @error('subscription_expires_at')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                    <small class="form-text">اترك فارغاً للاشتراك غير المحدود</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    حفظ التعديلات
                </button>
                <a href="{{ route('admin.merchants.show', $user) }}" class="btn btn-secondary">إلغاء</a>
            </div>
        </form>
    </div>
</div>
@endsection
