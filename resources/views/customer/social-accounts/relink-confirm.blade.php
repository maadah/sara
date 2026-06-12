@extends('layouts.customer')

@section('title', 'تأكيد ربط الصفحة')

@section('content')
<style>
.relink-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 28px 32px;
    max-width: 560px;
    margin: 40px auto;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
}
.relink-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    background: #fff7ed;
    border-radius: 50%;
    margin: 0 auto 20px;
}
.relink-title {
    font-size: 1.25rem;
    font-weight: 700;
    text-align: center;
    color: #111827;
    margin-bottom: 8px;
}
.relink-subtitle {
    text-align: center;
    color: #6b7280;
    font-size: 0.9em;
    margin-bottom: 24px;
}
.page-item {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 12px;
}
.page-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    background: #e5e7eb;
}
.page-avatar-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #1877F2;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #fff;
    font-weight: 700;
    font-size: 1.1em;
}
.page-info { flex: 1; }
.page-name { font-weight: 600; color: #111827; font-size: 0.97em; }
.page-warning { font-size: 0.8em; color: #b45309; margin-top: 3px; }
.relink-warning-box {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    border-radius: 8px;
    padding: 14px 16px;
    margin-bottom: 24px;
    font-size: 0.88em;
    color: #92400e;
    display: flex;
    gap: 10px;
    align-items: flex-start;
}
.btn-confirm {
    display: block;
    width: 100%;
    background: #1877F2;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 12px;
    font-size: 1em;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    margin-bottom: 10px;
    transition: background 0.2s;
}
.btn-confirm:hover { background: #1558b0; }
.btn-cancel {
    display: block;
    width: 100%;
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 11px;
    font-size: 0.95em;
    font-weight: 500;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    transition: background 0.2s;
}
.btn-cancel:hover { background: #e5e7eb; }
</style>

<div class="relink-card">
    {{-- Icon --}}
    <div class="relink-icon">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#d97706" width="32" height="32">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
    </div>

    <h1 class="relink-title">الصفحة مرتبطة بحساب آخر</h1>
    <p class="relink-subtitle">
        الصفحة التالية مرتبطة حالياً بحساب مختلف. هل تريد إلغاء ربطها من ذلك الحساب وربطها بحسابك؟
    </p>

    {{-- Pages list --}}
    @foreach($pendingPages as $page)
    <div class="page-item">
        @if(!empty($page['avatar']))
            <img src="{{ $page['avatar'] }}" alt="" class="page-avatar">
        @else
            <div class="page-avatar-placeholder">{{ mb_substr($page['page_name'], 0, 1) }}</div>
        @endif
        <div class="page-info">
            <div class="page-name">{{ $page['page_name'] }}</div>
            <div class="page-warning">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="13" height="13" style="vertical-align:middle; margin-left:3px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                </svg>
                مرتبطة بحساب آخر
            </div>
        </div>
    </div>
    @endforeach

    {{-- Warning notice --}}
    <div class="relink-warning-box">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18" style="flex-shrink:0; margin-top:1px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
        </svg>
        <div>
            <strong>ملاحظة:</strong> عند التأكيد، ستنتقل إدارة هذه الصفحة إلى حسابك الحالي وسيفقد الحساب السابق صلاحية التحكم بها من خلال هذا النظام.
        </div>
    </div>

    {{-- Confirm form --}}
    <form method="POST" action="{{ route('customer.social-accounts.relink') }}">
        @csrf
        <button type="submit" class="btn-confirm">
            نعم، أريد ربطها بحسابي
        </button>
    </form>

    <form method="POST" action="{{ route('customer.social-accounts.relink-cancel') }}">
        @csrf
        <button type="submit" class="btn-cancel">لا، إلغاء</button>
    </form>
</div>
@endsection
