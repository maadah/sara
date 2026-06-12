@extends('layouts.admin')

@section('title', 'عرض التاجر - لوحة التحكم')

@section('content')
<div class="page-header-actions">
    <a href="{{ route('admin.merchants') }}" class="btn btn-secondary">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
        </svg>
        رجوع للقائمة
    </a>
    <a href="{{ route('admin.merchants.edit', $user) }}" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
        </svg>
        تعديل
    </a>
</div>

<div class="merchant-profile">
    <div class="profile-header-card">
        <div class="profile-avatar">
            <span>{{ mb_substr($user->name, 0, 1) }}</span>
        </div>
        <div class="profile-info">
            <h2>{{ $user->name }}</h2>
            <p>{{ $user->email }}</p>
            <div class="profile-badges">
                @if($user->status === 'approved')
                    <span class="badge badge-success">مفعل</span>
                @elseif($user->status === 'pending')
                    <span class="badge badge-warning">قيد الانتظار</span>
                @elseif($user->status === 'rejected')
                    <span class="badge badge-danger">مرفوض</span>
                @else
                    <span class="badge badge-danger">معلق</span>
                @endif
                @if($user->subscription)
                    <span class="badge badge-info">{{ $user->subscription->name }}</span>
                @endif
            </div>
        </div>
        <div class="profile-quick-actions">
            @if($user->status === 'pending')
                <form action="{{ route('admin.users.approve', $user) }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-success">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        قبول
                    </button>
                </form>
                <form action="{{ route('admin.users.reject', $user) }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-danger">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        رفض
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="profile-details-grid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                    البيانات الشخصية
                </h3>
            </div>
            <div class="card-body">
                <div class="detail-item">
                    <span class="detail-label">الاسم</span>
                    <span class="detail-value">{{ $user->name }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">البريد الالكتروني</span>
                    <span class="detail-value">{{ $user->email }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">رقم الهاتف</span>
                    <span class="detail-value">{{ $user->phone ?: '-' }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">اسم الشركة</span>
                    <span class="detail-value">{{ $user->company_name ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                    </svg>
                    معلومات الاشتراك
                </h3>
            </div>
            <div class="card-body">
                <div class="detail-item">
                    <span class="detail-label">الباقة</span>
                    <span class="detail-value">{{ $user->subscription?->name ?? 'لا يوجد اشتراك' }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">السعر</span>
                    <span class="detail-value">{{ $user->subscription?->price ? $user->subscription->price . ' د.ع/شهرياً' : '-' }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">تاريخ انتهاء الاشتراك</span>
                    <span class="detail-value">
                        @if($user->subscription_expires_at)
                            {{ $user->subscription_expires_at->format('Y/m/d') }}
                            @if($user->subscription_expires_at->isPast())
                                <span class="badge badge-danger">منتهي</span>
                            @elseif($user->subscription_expires_at->diffInDays(now()) <= 7)
                                <span class="badge badge-warning">ينتهي قريباً</span>
                            @else
                                <span class="badge badge-success">نشط</span>
                            @endif
                        @else
                            <span class="badge badge-info">غير محدد</span>
                        @endif
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">الحالة</span>
                    <span class="detail-value">
                        @if($user->status === 'approved')
                            <span class="badge badge-success">مفعل</span>
                        @elseif($user->status === 'pending')
                            <span class="badge badge-warning">قيد الانتظار</span>
                        @elseif($user->status === 'rejected')
                            <span class="badge badge-danger">مرفوض</span>
                        @else
                            <span class="badge badge-danger">معلق</span>
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    التواريخ
                </h3>
            </div>
            <div class="card-body">
                <div class="detail-item">
                    <span class="detail-label">تاريخ التسجيل</span>
                    <span class="detail-value">{{ $user->created_at->format('Y/m/d') }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">وقت التسجيل</span>
                    <span class="detail-value">{{ $user->created_at->format('H:i') }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">آخر تحديث</span>
                    <span class="detail-value">{{ $user->updated_at->format('Y/m/d H:i') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
