@extends('layouts.app')

@section('title', 'في انتظار الموافقة - سارة')

@section('content')
<div class="pending-container">
    <div class="pending-card">
        <div class="auth-logo">
            <img src="{{ asset('images/logo.png') }}" alt="سارة">
        </div>

        <div class="pending-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>

        <h1 class="pending-title">في انتظار الموافقة</h1>

        <p class="pending-text">
            شكراً لتسجيلك في منصة سارة!
            <br><br>
            طلبك قيد المراجعة من قبل الإدارة. سيتم إعلامك فور الموافقة على حسابك.
            <br><br>
            إذا كان لديك أي استفسار، يرجى التواصل مع الإدارة.
        </p>

        <div style="display: flex; gap: 15px; flex-wrap: wrap; justify-content: center;">
            <a href="#" class="btn btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                </svg>
                تواصل مع الإدارة
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-danger">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                    </svg>
                    تسجيل الخروج
                </button>
            </form>
        </div>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color);">
            <p style="color: var(--text-muted); font-size: 0.85rem;">
                حالة الحساب:
                @if($user->status === 'pending')
                    <span class="badge badge-warning">قيد الانتظار</span>
                @elseif($user->status === 'rejected')
                    <span class="badge badge-danger">مرفوض</span>
                @elseif($user->status === 'suspended')
                    <span class="badge badge-danger">معلق</span>
                @endif
            </p>
        </div>
    </div>
</div>
@endsection
