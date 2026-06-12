@extends('layouts.customer')

@section('title', 'الحسابات المرتبطة')

@section('content')
{{-- ═══ STYLES ═══ --}}
<style>
.perm-badge {
    display: inline-block;
    font-size: 0.75em;
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 600;
    margin-right: 6px;
    vertical-align: middle;
}
.perm-badge.healthy  { background: #d1fae5; color: #065f46; }
.perm-badge.missing  { background: #fee2e2; color: #991b1b; }
.perm-badge.expired  { background: #fef3c7; color: #92400e; }

.perm-warning-box {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    border-radius: 8px;
    padding: 10px 14px;
    margin-top: 10px;
    font-size: 0.88em;
}
.perm-warning-box strong { color: #9a3412; }
.perm-ok-box  { margin-top: 6px; font-size: 0.82em; color: #6b7280; }
.perm-info-box {
    font-size: 0.85em;
    margin-top: 6px;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 10px;
}
.perm-list { list-style: none; padding: 0; margin: 4px 0 0; }
.perm-list li { margin: 2px 0; }
.perm-list.missing li { color: #b91c1c; }
.perm-list.granted li { color: #065f46; }

.btn-relink {
    display: inline-block;
    margin-top: 8px;
    background: #1877F2;
    color: #fff;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 0.85em;
    font-weight: 600;
    text-decoration: none;
}
.btn-relink:hover { background: #1558b0; }
.btn-recheck {
    background: none;
    border: 1px solid #9ca3af;
    color: #374151;
    padding: 3px 10px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.82em;
}
.btn-recheck:hover { background: #f3f4f6; }
.btn-icon.secondary { background: #f3f4f6; border: 1px solid #e5e7eb; color: #374151; }
.btn-icon.secondary:hover { background: #e5e7eb; }
</style>

{{-- ═══ CONTENT ═══ --}}
<div class="page-header-simple">
    <h1 class="page-title">الحسابات المرتبطة</h1>
    <p class="page-subtitle">قم بربط حساباتك على منصات التواصل الاجتماعي لتسهيل إدارة أعمالك</p>
</div>

@if(session('success'))
    <div class="alert alert-success">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if(session('warning'))
    <div class="alert alert-warning">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
        <div style="flex:1;">
            <span>{{ session('warning') }}</span>
            <div style="margin-top: 8px;">
                <a href="{{ route('social.redirect', ['provider' => 'facebook', 'action' => 'link']) }}" style="display:inline-block; padding:6px 16px; background:#2563eb; color:#fff; border-radius:6px; text-decoration:none; font-size:.85rem;">🔄 إعادة المحاولة</a>
            </div>
        </div>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
        </svg>
        <span>{{ session('error') }}</span>
    </div>
@endif

{{-- ═══════ AI AUTO-REPLY STATUS BANNER ═══════ --}}
@if(!($aiEnabled ?? true) || !($autoReplyEnabled ?? true))
<div class="alert alert-warning" style="display:flex; align-items:flex-start; gap:12px; margin-bottom:20px;">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="22" height="22" style="flex-shrink:0; margin-top:2px;">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
    </svg>
    <div>
        <strong>الرد التلقائي بالذكاء الاصطناعي معطّل</strong><br>
        <span style="font-size:0.9em;">
            @if(!($aiEnabled ?? true))
                الذكاء الاصطناعي غير مفعّل.
            @else
                الرد التلقائي غير مفعّل في إعداداتك.
            @endif
            حتى لو كانت صفحاتك مربوطة، لن يتم الرد على الرسائل تلقائياً.
            <a href="{{ route('customer.ai-settings.index') }}" style="font-weight:600; text-decoration:underline;">اذهب إلى إعدادات الذكاء الاصطناعي ←</a>
        </span>
    </div>
</div>
@endif

@php
    $facebookPages   = $socialAccounts->where('provider', 'facebook_page');
    $instagramAccounts = $socialAccounts->where('provider', 'instagram');
    $whatsappAccounts = $socialAccounts->where('provider', 'whatsapp');
    $hasAccounts     = $facebookPages->count() > 0 || $instagramAccounts->count() > 0 || $whatsappAccounts->count() > 0;
    $permissionLabels = [
        'pages_messaging'           => 'إرسال الرسائل',
        'pages_read_engagement'     => 'قراءة التفاعلات',
        'pages_manage_metadata'     => 'إدارة بيانات الصفحة',
        'pages_manage_engagement'   => 'الرد على التعليقات (فيسبوك)',
        'instagram_manage_messages' => 'رسائل انستقرام',
        'instagram_manage_comments' => 'الرد على التعليقات (انستقرام)',
        'instagram_basic'           => 'بيانات انستقرام',
        'pages_show_list'           => 'عرض قائمة الصفحات',
        'whatsapp_business_management' => 'إدارة واتساب الأعمال',
        'whatsapp_business_messaging'  => 'رسائل واتساب',
    ];
@endphp

<!-- Connected Facebook Pages -->
@if($facebookPages->count() > 0)
<div class="connected-accounts-section">
    <div class="section-header">
        <h2>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#1877F2" width="24" height="24" style="vertical-align: middle; margin-left: 8px;">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
            </svg>
            صفحات فيسبوك ({{ $facebookPages->count() }})
        </h2>
    </div>
    <div class="connected-accounts-list">
        @foreach($facebookPages as $page)
            @php $perm = $permissionStatus[$page->id] ?? []; @endphp
            <div class="connected-account-item facebook-page" id="account-row-{{ $page->id }}">
                <div class="account-avatar">
                    @if($page->avatar)
                        <img src="{{ $page->avatar }}" alt="{{ $page->name }}">
                    @else
                        <div class="avatar-placeholder facebook_page">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
                                <path d="M3 5a2 2 0 012-2h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5zm4.5 0a.5.5 0 00-.5.5v1a.5.5 0 00.5.5h1a.5.5 0 00.5-.5v-1a.5.5 0 00-.5-.5h-1z"/>
                            </svg>
                        </div>
                    @endif
                </div>
                <div class="account-info" style="flex:1;">
                    <div class="account-name">{{ $page->name }}</div>
                    <div class="account-provider">
                        <span class="provider-badge facebook_page">صفحة فيسبوك</span>
                        @if($page->meta_data['category'] ?? null)
                            <span class="account-category">{{ $page->meta_data['category'] }}</span>
                        @endif
                        {{-- Health badge — only show for definitive states --}}
                        @if(!empty($perm))
                            @if($perm['expired'] ?? false)
                                <span class="perm-badge expired">⚠ التوكن منتهي</span>
                            @elseif($perm['healthy'] ?? false)
                                <span class="perm-badge healthy">✓ الصلاحيات مكتملة</span>
                            @elseif(!empty($perm['missing']))
                                <span class="perm-badge missing">✗ صلاحيات ناقصة</span>
                            @endif
                        @endif
                    </div>
                    <div class="account-meta">
                        @if($page->meta_data['fan_count'] ?? 0)
                            <span>{{ number_format($page->meta_data['fan_count']) }} متابع</span>
                            <span>•</span>
                        @endif
                        <span>تم الربط: {{ $page->created_at->format('Y/m/d') }}</span>
                    </div>

                    {{-- ═══ PERMISSION DETAILS ═══ --}}
                    @if(!empty($perm))
                        @if($perm['expired'] ?? false)
                        <div class="perm-warning-box">
                            <strong>⚠ انتهت صلاحية التوكن</strong>
                            <p>لن يتم الرد على رسائل هذه الصفحة. يرجى إعادة الربط.</p>
                            <a href="{{ route('social.redirect', ['provider' => 'facebook', 'action' => 'link']) }}" class="btn-relink">إعادة الربط الآن</a>
                        </div>
                        @elseif(!empty($perm['missing']))
                        <div class="perm-warning-box">
                            <strong>⚠ صلاحيات مفقودة — لن يعمل الرد التلقائي</strong>
                            <ul class="perm-list missing">
                                @foreach($perm['missing'] as $mp)
                                    <li>✗ {{ $permissionLabels[$mp] ?? $mp }}</li>
                                @endforeach
                            </ul>
                            <p style="margin-top:8px; font-size:0.85em;">أعد ربط الحساب ووافق على <strong>جميع</strong> الصلاحيات المطلوبة.</p>
                            <a href="{{ route('social.redirect', ['provider' => 'facebook', 'action' => 'link', 'rerequest' => 1]) }}" class="btn-relink" style="background:#dc2626;">إصلاح الصلاحيات (rerequest)</a>
                        </div>
                        @elseif($perm['healthy'] ?? false)
                        <div class="perm-ok-box">
                            <ul class="perm-list granted">
                                @foreach($perm['granted'] ?? [] as $gp)
                                    @if(isset($permissionLabels[$gp]))
                                        <li>✓ {{ $permissionLabels[$gp] }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                        @endif
                    @endif
                </div>
                <div class="account-actions">
                    <button class="btn-icon secondary" title="فحص الصلاحيات" onclick="recheckPermissions({{ $page->id }}, this)" style="margin-left:6px;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                    </button>
                    <button class="btn-icon secondary" title="تشخيص التوكن" onclick="runDiagnose({{ $page->id }}, this)" style="margin-left:6px;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                    </button>
                    <form action="{{ route('customer.social-accounts.unlink', 'facebook_page') }}" method="POST" class="inline-form" onsubmit="return confirm('هل أنت متأكد من إلغاء ربط هذه الصفحة؟');">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="account_id" value="{{ $page->id }}">
                        <button type="submit" class="btn-icon danger" title="إلغاء الربط">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif

<!-- Connected Instagram Accounts -->
@if($instagramAccounts->count() > 0)
<div class="connected-accounts-section">
    <div class="section-header">
        <h2>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="url(#instagram-gradient)" width="24" height="24" style="vertical-align: middle; margin-left: 8px;">
                <defs>
                    <linearGradient id="instagram-gradient" x1="0%" y1="100%" x2="100%" y2="0%">
                        <stop offset="0%" style="stop-color:#f09433"/>
                        <stop offset="25%" style="stop-color:#e6683c"/>
                        <stop offset="50%" style="stop-color:#dc2743"/>
                        <stop offset="75%" style="stop-color:#cc2366"/>
                        <stop offset="100%" style="stop-color:#bc1888"/>
                    </linearGradient>
                </defs>
                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/>
            </svg>
            حسابات انستقرام ({{ $instagramAccounts->count() }})
        </h2>
    </div>
    <div class="connected-accounts-list">
        @foreach($instagramAccounts as $account)
            @php $perm = $permissionStatus[$account->id] ?? []; @endphp
            <div class="connected-account-item instagram-account" id="account-row-{{ $account->id }}">
                <div class="account-avatar">
                    @if($account->avatar)
                        <img src="{{ $account->avatar }}" alt="{{ $account->name }}">
                    @else
                        <div class="avatar-placeholder instagram">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/>
                            </svg>
                        </div>
                    @endif
                </div>
                <div class="account-info" style="flex:1;">
                    <div class="account-name">
                        @if($account->meta_data['username'] ?? null)
                            <span dir="ltr">{{ '@' . $account->meta_data['username'] }}</span>
                        @else
                            {{ $account->name }}
                        @endif
                    </div>
                    <div class="account-provider">
                        <span class="provider-badge instagram">انستقرام</span>
                        @if($account->meta_data['facebook_page_name'] ?? null)
                            <span class="linked-page">مرتبط بـ: {{ $account->meta_data['facebook_page_name'] }}</span>
                        @endif
                        @if(!empty($perm))
                            @if($perm['expired'] ?? false)
                                <span class="perm-badge expired">⚠ التوكن منتهي</span>
                            @elseif($perm['healthy'] ?? false)
                                <span class="perm-badge healthy">✓ الصلاحيات مكتملة</span>
                            @elseif(!empty($perm['missing']))
                                <span class="perm-badge missing">✗ صلاحيات ناقصة</span>
                            @endif
                        @endif
                    </div>
                    <div class="account-meta">
                        @if($account->meta_data['followers_count'] ?? 0)
                            <span>{{ number_format($account->meta_data['followers_count']) }} متابع</span>
                            <span>•</span>
                        @endif
                        @if($account->meta_data['media_count'] ?? 0)
                            <span>{{ number_format($account->meta_data['media_count']) }} منشور</span>
                            <span>•</span>
                        @endif
                        <span>تم الربط: {{ $account->created_at->format('Y/m/d') }}</span>
                    </div>
                    @if(!empty($perm) && !empty($perm['missing']))
                    <div class="perm-warning-box">
                        <strong>⚠ صلاحيات مفقودة</strong>
                        <ul class="perm-list missing">
                            @foreach($perm['missing'] as $mp)
                                <li>✗ {{ $permissionLabels[$mp] ?? $mp }}</li>
                            @endforeach
                        <p style="margin-top:8px; font-size:0.85em;">أعد ربط الحساب ووافق على <strong>جميع</strong> الصلاحيات المطلوبة.</p>
                        <a href="{{ route('social.redirect', ['provider' => 'facebook', 'action' => 'link', 'rerequest' => 1]) }}" class="btn-relink" style="background:#dc2626;">إصلاح الصلاحيات (rerequest)</a>
                    </div>
                    @endif
                </div>
                <div class="account-actions">
                    <button class="btn-icon secondary" title="فحص الصلاحيات" onclick="recheckPermissions({{ $account->id }}, this)" style="margin-left:6px;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                    </button>
                    <form action="{{ route('customer.social-accounts.unlink', 'instagram') }}" method="POST" class="inline-form" onsubmit="return confirm('هل أنت متأكد من إلغاء ربط هذا الحساب؟');">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="account_id" value="{{ $account->id }}">
                        <button type="submit" class="btn-icon danger" title="إلغاء الربط">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif

<!-- Connected WhatsApp Accounts -->
@if($whatsappAccounts->count() > 0)
<div class="connected-accounts-section">
    <div class="section-header">
        <h2>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#25D366" width="24" height="24" style="vertical-align: middle; margin-left: 8px;">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            حسابات واتساب ({{ $whatsappAccounts->count() }})
        </h2>
    </div>
    <div class="connected-accounts-list">
        @foreach($whatsappAccounts as $account)
            @php $perm = $permissionStatus[$account->id] ?? []; @endphp
            <div class="connected-account-item whatsapp-account" id="account-row-{{ $account->id }}">
                <div class="account-avatar">
                    <div class="avatar-placeholder" style="background:#25D366;color:#fff;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                    </div>
                </div>
                <div class="account-info" style="flex:1;">
                    <div class="account-name" dir="ltr">{{ $account->meta_data['display_phone_number'] ?? $account->name }}</div>
                    <div class="account-provider">
                        <span class="provider-badge" style="background:#25D366;color:#fff;">واتساب</span>
                        @if($account->meta_data['quality_rating'] ?? null)
                            <span class="account-category">جودة: {{ $account->meta_data['quality_rating'] }}</span>
                        @endif
                        @if($account->meta_data['waba_name'] ?? null)
                            <span class="linked-page">WABA: {{ $account->meta_data['waba_name'] }}</span>
                        @endif
                        @if($account->meta_data['business_name'] ?? null)
                            <span class="linked-page">النشاط: {{ $account->meta_data['business_name'] }}</span>
                        @endif
                        @if(!empty($perm))
                            @if($perm['expired'] ?? false)
                                <span class="perm-badge expired">⚠ التوكن منتهي</span>
                            @elseif($perm['healthy'] ?? false)
                                <span class="perm-badge healthy">✓ الصلاحيات مكتملة</span>
                            @elseif(!empty($perm['missing']))
                                <span class="perm-badge missing">✗ صلاحيات ناقصة</span>
                            @endif
                        @endif
                    </div>
                    <div class="account-meta">
                        <span>تم الربط: {{ $account->created_at->format('Y/m/d') }}</span>
                    </div>
                    @if(!empty($perm) && !empty($perm['missing']))
                    <div class="perm-warning-box">
                        <strong>⚠ صلاحيات مفقودة</strong>
                        <ul class="perm-list missing">
                            @foreach($perm['missing'] as $mp)
                                <li>✗ {{ $permissionLabels[$mp] ?? $mp }}</li>
                            @endforeach
                        <p style="margin-top:8px; font-size:0.85em;">أعد ربط الحساب ووافق على <strong>جميع</strong> الصلاحيات المطلوبة.</p>
                        <a href="{{ route('social.redirect', ['provider' => 'facebook', 'action' => 'link', 'rerequest' => 1]) }}" class="btn-relink" style="background:#dc2626;">إصلاح الصلاحيات (rerequest)</a>
                    </div>
                    @endif
                </div>
                <div class="account-actions">
                    <form action="{{ route('customer.social-accounts.unlink', 'whatsapp') }}" method="POST" class="inline-form" onsubmit="return confirm('هل أنت متأكد من إلغاء ربط هذا الحساب؟');">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="account_id" value="{{ $account->id }}">
                        <button type="submit" class="btn-icon danger" title="إلغاء الربط">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif

<!-- Meta Connection Guide (The Golden Tip) -->
<div class="alert alert-info" style="border-right: 4px solid #1877F2; background: #f0f7ff; margin-bottom: 25px;">
    <div style="display:flex; align-items:flex-start; gap:15px;">
        <div style="background:#1877F2; color:#fff; border-radius:50%; width:32px; height:32px; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:2px;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="20" height="20">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-3m0-4.5h.008v.008H12V10.5Zm0 10.5a9 9 0 1 1 0-18 9 9 0 0 1 0 18Z" />
            </svg>
        </div>
        <div>
            <h4 style="margin:0 0 8px; color:#1e40af; font-size:1.05rem;">💡 النصيحة الذهبية لربط ناجح وسريع:</h4>
            <p style="margin:0; font-size:0.92rem; color:#1e3a8a; line-height:1.6;">
                عند الضغط على "ربط الحسابات" وظهور نافذة Meta، اتبع هذه القاعدة البسيطة:
                <br>
                1. إذا ظهر لك اسمك وزر <strong>"متابعة" (Continue as...)</strong>، اضغط عليه فوراً. هذا هو الخيار الأسرع والآمن.
                <br>
                2. ⚠️ <strong>لا تضغط</strong> على "تعديل الإعدادات السابقة" إلا إذا كنت تريد إضافة صفحة جديدة لم تكن تظهر مسبقاً.
                <br>
                3. في حال دخلت على تعديل الإعدادات، تأكد من إبقاء <strong>جميع الصفحات القديمة مختارة</strong> مع الجديدة، وإلا سينقطع الاتصال عن صفحاتك الحالية.
            </p>
        </div>
    </div>
</div>

<!-- Add New Account Section -->
<div class="add-account-section">
    <div class="section-header">
        <h2>{{ $hasAccounts ? 'إضافة حسابات أخرى' : 'ربط الحسابات' }}</h2>
    </div>
    <div class="social-connect-card">
        <div class="connect-card-content">
            <div class="connect-icons">
                <div class="connect-icon facebook">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="32" height="32">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                </div>
                <div class="connect-plus">+</div>
                <div class="connect-icon instagram">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="32" height="32">
                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/>
                    </svg>
                </div>
            </div>
            <div class="connect-text">
                <h3>ربط صفحات فيسبوك وانستقرام وواتساب</h3>
                <p>سيتم جلب جميع صفحات فيسبوك وحسابات انستقرام وأرقام واتساب المرتبطة بها تلقائياً</p>
            </div>
            <a href="{{ route('social.redirect', ['provider' => 'facebook', 'action' => 'link']) }}" class="btn-connect-main">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                </svg>
                {{ $hasAccounts ? 'ربط حسابات إضافية' : 'ربط الحسابات الآن' }}
            </a>
            <a href="{{ route('social.instagram-direct.redirect', ['action' => 'link']) }}" class="btn-connect-main" style="background:linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888); margin-top:10px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/>
                </svg>
                تسجيل الدخول عبر إنستغرام مباشرة (بدون فيسبوك)
            </a>
        </div>
    </div>
</div>

<!-- Help Section -->
<div class="help-section">
    <div class="help-card">
        <div class="help-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
            </svg>
        </div>
        <div class="help-content">
            <h4>ملاحظات هامة</h4>
            <ul>
                <li><strong>يجب أن تكون مدير (Admin)</strong> لصفحة فيسبوك واحدة على الأقل للربط</li>
                <li>الصفحات التي أنت مشرف (Moderator) أو محرر (Editor) عليها <strong>لن تظهر</strong></li>
                <li>تأكد من اختيار <strong>جميع الصلاحيات</strong> المطلوبة وتحديد <strong>جميع الصفحات</strong> التي ترغب بربطها</li>
                <li>إذا كنت قد ربطت حسابك مسبقاً، يفضل الضغط على <strong>"متابعة"</strong> مباشرة دون الدخول في تفاصيل الإعدادات</li>
                <li>يمكنك ربط عدة صفحات وحسابات في نفس الوقت دفعة واحدة</li>
            </ul>

            <h5 style="margin-top: 15px; color: var(--text-primary);">كيفية ربط انستقرام بصفحة فيسبوك:</h5>
            <ol style="padding-right: 20px; margin-top: 8px;">
                <li>افتح تطبيق انستقرام وانتقل إلى الإعدادات</li>
                <li>اضغط على "حساب" ثم "التبديل إلى حساب احترافي"</li>
                <li>اختر "أعمال" أو "صانع محتوى"</li>
                <li>اربط الحساب بصفحة فيسبوك التي أنت مدير عليها</li>
            </ol>

            <h5 style="margin-top:15px; color:var(--text-primary);">الصلاحيات المطلوبة للرد التلقائي:</h5>
            <ul style="padding-right:20px; margin-top:8px; font-size:0.88em;">
                <li><code>pages_messaging</code> — إرسال الرسائل</li>
                <li><code>pages_read_engagement</code> — قراءة التفاعلات</li>
                <li><code>pages_manage_metadata</code> — إدارة بيانات الصفحة</li>
                <li><code>instagram_manage_messages</code> — رسائل انستقرام المباشرة</li>
            </ul>
            <p style="color:var(--danger-color,#b91c1c); font-size:0.85em; margin-top:6px;">إذا رفضت أيًا من هذه الصلاحيات، أعد ربط الحساب وامنح الجميع.</p>
        </div>
    </div>
</div>

{{-- ═══════ SCRIPTS ═══════ --}}
<script>
const _checkPermsUrl = '{{ route('customer.social-accounts.check-permissions') }}';
const _diagnoseUrl   = '{{ route('customer.social-accounts.diagnose') }}';
const _csrfToken     = '{{ csrf_token() }}';
const _relinkUrl     = '{{ route('social.redirect', ['provider' => 'facebook', 'action' => 'link']) }}';
const _rerequestUrl   = '{{ route('social.redirect', ['provider' => 'facebook', 'action' => 'link', 'rerequest' => 1]) }}';

const permLabels = {
    'pages_messaging':           'إرسال الرسائل',
    'pages_read_engagement':     'قراءة التفاعلات',
    'pages_manage_metadata':     'إدارة بيانات الصفحة',
    'pages_manage_engagement':   'الرد على التعليقات (فيسبوك)',
    'instagram_manage_messages': 'رسائل انستقرام',
    'instagram_manage_comments': 'الرد على التعليقات (انستقرام)',
    'instagram_basic':           'بيانات انستقرام',
    'pages_show_list':           'عرض قائمة الصفحات',
    'whatsapp_business_management': 'إدارة واتساب الأعمال',
    'whatsapp_business_messaging':  'رسائل واتساب',
};

function recheckPermissions(accountId, btn) {
    btn.disabled = true;
    const row = document.getElementById('account-row-' + accountId);
    if (row) {
        row.querySelectorAll('.perm-warning-box, .perm-ok-box, .perm-info-box').forEach(b => b.remove());
        const badge = row.querySelector('.perm-badge');
        if (badge) badge.textContent = '⏳ جاري الفحص...';
    }
    fetch(_checkPermsUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ account_id: accountId }),
    })
    .then(r => r.json())
    .then(data => {
        if (!row) return;
        const accountInfo = row.querySelector('.account-info');
        const badge = row.querySelector('.perm-badge');
        if (data.expired || data.error === 'token_expired') {
            if (badge) { badge.className = 'perm-badge expired'; badge.textContent = '⚠ التوكن منتهي'; }
            insertBox(accountInfo, 'warning', '<strong>⚠ انتهت صلاحية التوكن</strong><p>يرجى إعادة ربط الحساب.</p><a href="' + _relinkUrl + '" class="btn-relink">إعادة الربط</a>');
        } else if (data.missing && data.missing.length > 0) {
            if (badge) { badge.className = 'perm-badge missing'; badge.textContent = '✗ صلاحيات ناقصة'; }
            const list = data.missing.map(p => '<li>✗ ' + (permLabels[p] || p) + '</li>').join('');
            insertBox(accountInfo, 'warning', '<strong>⚠ صلاحيات مفقودة</strong><ul class="perm-list missing">' + list + '</ul><p style="margin-top:8px; font-size:0.85em;">يجب الموافقة على جميع الصلاحيات في الخطوات القادمة.</p><a href="' + _rerequestUrl + '" class="btn-relink" style="background:#dc2626;">إصلاح الصلاحيات الآن</a>');
        } else if (data.healthy) {
            if (badge) { badge.className = 'perm-badge healthy'; badge.textContent = '✓ الصلاحيات مكتملة'; }
            const list = (data.granted || []).filter(p => permLabels[p]).map(p => '<li>✓ ' + permLabels[p] + '</li>').join('');
            insertBox(accountInfo, 'ok', '<ul class="perm-list granted">' + list + '</ul>');
        } else {
            if (badge) badge.textContent = '— لا يمكن الفحص';
        }
    })
    .catch(() => { const b = row && row.querySelector('.perm-badge'); if (b) b.textContent = '— فشل الفحص'; })
    .finally(() => { btn.disabled = false; });
}

function insertBox(container, type, html) {
    const div = document.createElement('div');
    div.className = type === 'ok' ? 'perm-ok-box' : 'perm-warning-box';
    div.innerHTML = html;
    container.appendChild(div);
}

function runDiagnose(accountId, btn) {
    btn.disabled = true;
    fetch(_diagnoseUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ account_id: accountId }),
    })
    .then(r => r.json())
    .then(data => {
        let html = '<div style="font-family:monospace;font-size:0.82em;direction:ltr;text-align:left;">';

        // ── Missing required permissions (most important — show first) ──
        if (data.missing_required && data.missing_required.length > 0) {
            html += '<p style="background:#fee2e2;color:#991b1b;padding:8px;border-radius:6px;margin-bottom:8px;">'
                  + '⛔ Missing required permissions: <b>' + data.missing_required.join(', ') + '</b><br>'
                  + 'Re-link the account and grant ALL permissions.</p>';
        }

        // ── Token ──
        const td = data.token_debug?.data ?? data.token_debug ?? {};
        const tokenOk = td.is_valid === true;
        html += '<p><b>Token:</b> ' + (tokenOk ? '✅ Valid' : '❌ ' + (td.error?.message ?? 'Invalid / not checked')) + '</p>';
        if (td.expires_at) html += '<p><b>Expires:</b> ' + new Date(td.expires_at * 1000).toLocaleString() + '</p>';
        if (td.scopes?.length) html += '<p><b>Scopes:</b> ' + td.scopes.join(', ') + '</p>';

        // ── Page info ──
        const pi = data.page_info ?? {};
        if (pi.name) html += '<p><b>Page:</b> ' + pi.name + ' (ID: ' + pi.id + ')</p>';
        if (pi.error) html += '<p style="color:orange;"><b>Page lookup:</b> ' + pi.error.message + '</p>';

        // ── Permissions list ──
        if (Array.isArray(data.permissions) && data.permissions.length > 0) {
            const granted  = data.permissions.filter(p => p.status === 'granted').map(p => p.permission);
            const declined = data.permissions.filter(p => p.status !== 'granted').map(p => p.permission + ' [' + p.status + ']');
            html += '<p><b>✅ Granted:</b> ' + (granted.join(', ') || 'none') + '</p>';
            if (declined.length) html += '<p><b>❌ Declined:</b> ' + declined.join(', ') + '</p>';
        } else if (data.permissions_error) {
            html += '<p style="color:orange;"><b>Permissions:</b> ' + data.permissions_error + '</p>';
        }

        // ── Conversations endpoint (read test) ──
        const me = data.messages_endpoint;
        if (me) {
            const ok = me.status >= 200 && me.status < 300;
            html += '<p><b>Conversations endpoint:</b> ' + (ok ? '✅ OK (HTTP ' + me.status + ')' : '❌ HTTP ' + me.status) + '</p>';
            if (!ok && me.body?.error?.message) html += '<p style="color:red;font-size:0.9em;">' + me.body.error.message + '</p>';
        }

        if (data.error) html += '<p style="color:red;"><b>Error:</b> ' + data.error + '</p>';
        html += '</div>';
        document.getElementById('diagnose-modal-body').innerHTML = html;
        document.getElementById('diagnose-modal').style.display = 'flex';
    })
    .catch(err => alert('فشل التشخيص: ' + err.message))
    .finally(() => { btn.disabled = false; });
}
</script>

<!-- Diagnose Modal -->
<div id="diagnose-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:24px;max-width:580px;width:90%;max-height:80vh;overflow:auto;position:relative;">
        <button onclick="document.getElementById('diagnose-modal').style.display='none'" style="position:absolute;top:12px;left:12px;background:none;border:none;font-size:1.4em;cursor:pointer;color:#6b7280;">✕</button>
        <h3 style="margin:0 0 16px;font-size:1em;color:#111827;">🔍 نتيجة تشخيص التوكن</h3>
        <div id="diagnose-modal-body"></div>
        <div style="margin-top:16px;text-align:left;">
            <button onclick="document.getElementById('diagnose-modal').style.display='none'" style="background:#1877F2;color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;">إغلاق</button>
        </div>
    </div>
</div>
@endsection
