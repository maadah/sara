@extends('layouts.app')

@section('title', 'انتهى الاشتراك - سارة')

@section('content')
<div class="pending-container">
    <div class="pending-card">
        <div class="auth-logo">
            <img src="{{ asset('images/logo.png') }}" alt="سارة">
        </div>

        <div class="pending-icon expired-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
        </div>

        <h1 class="pending-title">انتهى اشتراكك</h1>

        <p class="pending-text">
            عذراً، لقد انتهت صلاحية اشتراكك في منصة سارة.
            <br><br>
            @if($user->subscription_expires_at)
                انتهى الاشتراك بتاريخ: <strong>{{ $user->subscription_expires_at->format('Y/m/d') }}</strong>
                <br><br>
            @endif
            يرجى تجديد اشتراكك للاستمرار في استخدام المنصة.
            <br><br>
            للتجديد، يرجى التواصل مع الإدارة.
        </p>

        <div style="display: flex; gap: 15px; flex-wrap: wrap; justify-content: center;">
            <a href="{{ route('contact') }}" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                </svg>
                تواصل معنا للتجديد
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
                حالة الاشتراك:
                <span class="badge badge-danger">منتهي</span>
            </p>
            @if($user->subscription)
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 10px;">
                    الباقة السابقة: <strong>{{ $user->subscription->name }}</strong>
                </p>
            @endif
        </div>
    </div>
</div>

<style>
    .expired-icon {
        background: rgba(239, 68, 68, 0.1) !important;
        color: var(--danger) !important;
    }

    .expired-icon svg {
        color: var(--danger) !important;
    }
</style>
@endsection
