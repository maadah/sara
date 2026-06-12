@extends('layouts.customer')

@section('title', 'الردود على التعليقات - لوحة التحكم')

@section('content')
<div class="page-header-bar">
    <h1 class="page-title">نظام الردود على التعليقات</h1>
    <div style="display: flex; gap: 10px;">
        <form action="{{ route('customer.comment-replies.cleanup') }}" method="POST" style="display: inline;">
            @csrf
            <button type="submit" class="btn btn-secondary" onclick="return confirm('حذف جميع التفاعلات المنتهية؟')">
                🗑️ تنظيف المنتهية
            </button>
        </form>
    </div>
</div>

{{-- Permission Alerts --}}
@if(!$permissions['facebook']['has_account'] && !$permissions['instagram']['has_account'])
    <div class="alert alert-warning" style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 12px; padding: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#856404" width="24" height="24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
        <div>
            <strong>لا توجد حسابات مرتبطة!</strong>
            <p style="margin: 4px 0 0 0; color: #856404;">يجب ربط حساب فيسبوك أو انستقرام أولاً من صفحة <a href="{{ route('customer.social-accounts.index') }}" style="color: #533f03; text-decoration: underline;">الحسابات المرتبطة</a></p>
        </div>
    </div>
@else
    @if(isset($permissions['facebook']['has_account']) && $permissions['facebook']['has_account'] && !$permissions['facebook']['ok'])
        <div class="alert alert-info" style="background: #d1ecf1; border: 1px solid #17a2b8; border-radius: 12px; padding: 16px; margin-bottom: 12px;">
            <strong>فيسبوك:</strong> صلاحيات ناقصة — {{ implode(', ', $permissions['facebook']['missing']) }}
        </div>
    @endif
    @if(isset($permissions['instagram']['has_account']) && $permissions['instagram']['has_account'] && !$permissions['instagram']['ok'])
        <div class="alert alert-info" style="background: #d1ecf1; border: 1px solid #17a2b8; border-radius: 12px; padding: 16px; margin-bottom: 12px;">
            <strong>انستقرام:</strong> صلاحيات ناقصة — {{ implode(', ', $permissions['instagram']['missing']) }}
        </div>
    @endif
@endif

{{-- Stats Cards --}}
<div class="stats-row">
    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">منتجات مرتبطة بمنشورات</div>
            <div class="stat-value">{{ $stats['linked_products'] }} <small>/ {{ $stats['total_products'] }}</small></div>
            <div class="stat-change positive">
                <span>منتج لديه رابط منشور</span>
            </div>
        </div>
        <div class="stat-icon blue">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
            </svg>
        </div>
    </div>

    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">تفاعلات نشطة</div>
            <div class="stat-value">{{ $stats['active'] }}</div>
            <div class="stat-change positive">
                <span>خلال آخر 24 ساعة</span>
            </div>
        </div>
        <div class="stat-icon green">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
            </svg>
        </div>
    </div>

    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">تم الرد عليها</div>
            <div class="stat-value">{{ $stats['replied'] }}</div>
            <div class="stat-change positive">
                <span>ردود تلقائية على التعليقات</span>
            </div>
        </div>
        <div class="stat-icon orange">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </div>
    </div>

    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">تم إرسال التفاصيل</div>
            <div class="stat-value">{{ $stats['dm_sent'] }}</div>
            <div class="stat-change positive">
                <span>رسائل خاصة بتفاصيل المنتج</span>
            </div>
        </div>
        <div class="stat-icon green">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
            </svg>
        </div>
    </div>
</div>

{{-- Platform Breakdown --}}
<div class="dashboard-row" style="margin-bottom: 20px;">
    <div class="chart-card" style="flex: 1;">
        <div class="chart-header">
            <h3>كيف يعمل النظام</h3>
        </div>
        <div style="padding: 20px; line-height: 2; color: var(--text-secondary);">
            <div style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px;">
                <span style="background: var(--primary-green); color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px;">1</span>
                <span>أضف رابط المنشور (فيسبوك أو انستقرام) في صفحة تعديل المنتج</span>
            </div>
            <div style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px;">
                <span style="background: var(--primary-green); color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px;">2</span>
                <span>عندما يعلّق شخص على المنشور، النظام يرد تلقائياً: "تواصل معنا على الخاص"</span>
            </div>
            <div style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px;">
                <span style="background: var(--primary-green); color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px;">3</span>
                <span>نحفظ تفاعله لمدة 24 ساعة — عندما يراسلنا على الخاص نرسل له تفاصيل المنتج مباشرة</span>
            </div>
            <div style="display: flex; align-items: flex-start; gap: 12px;">
                <span style="background: var(--primary-green); color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px;">4</span>
                <span>بعد 24 ساعة ينتهي التفاعل — إذا راسلنا بعدها يتعامل معه الذكاء الاصطناعي بشكل عادي</span>
            </div>
        </div>
    </div>

    <div class="visitors-card" style="flex: 0 0 320px;">
        <h3>التوزيع حسب المنصة</h3>
        <p class="subtitle">إحصائيات التعليقات</p>

        <div class="visitor-item">
            <span class="visitor-dot blue"></span>
            <div class="visitor-info">
                <span class="label">فيسبوك</span>
            </div>
            <span class="visitor-percent">{{ $stats['facebook'] }}</span>
        </div>

        <div class="visitor-item">
            <span class="visitor-dot orange"></span>
            <div class="visitor-info">
                <span class="label">انستقرام</span>
            </div>
            <span class="visitor-percent">{{ $stats['instagram'] }}</span>
        </div>

        <div class="visitor-item">
            <span class="visitor-dot green"></span>
            <div class="visitor-info">
                <span class="label">إجمالي التفاعلات</span>
            </div>
            <span class="visitor-percent">{{ $stats['total'] }}</span>
        </div>
    </div>
</div>

{{-- Interactions Table --}}
<div class="orders-section">
    <div class="orders-header">
        <div class="orders-tabs">
            <a href="{{ route('customer.comment-replies.index', ['filter' => 'all']) }}" class="orders-tab {{ $filter === 'all' ? 'active' : '' }}">الكل</a>
            <a href="{{ route('customer.comment-replies.index', ['filter' => 'active']) }}" class="orders-tab {{ $filter === 'active' ? 'active' : '' }}">نشطة</a>
            <a href="{{ route('customer.comment-replies.index', ['filter' => 'expired']) }}" class="orders-tab {{ $filter === 'expired' ? 'active' : '' }}">منتهية</a>
            <a href="{{ route('customer.comment-replies.index', ['filter' => 'facebook']) }}" class="orders-tab {{ $filter === 'facebook' ? 'active' : '' }}">فيسبوك</a>
            <a href="{{ route('customer.comment-replies.index', ['filter' => 'instagram']) }}" class="orders-tab {{ $filter === 'instagram' ? 'active' : '' }}">انستقرام</a>
            <a href="{{ route('customer.comment-replies.index', ['filter' => 'dm_sent']) }}" class="orders-tab {{ $filter === 'dm_sent' ? 'active' : '' }}">تم الإرسال</a>
        </div>
    </div>

    <div class="inventory-table-wrapper" style="border: none;">
        <table class="inventory-table">
            <thead>
                <tr>
                    <th>المنتج</th>
                    <th>المعلّق</th>
                    <th>التعليق</th>
                    <th>المنصة</th>
                    <th>الرد التلقائي</th>
                    <th>إرسال التفاصيل</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($interactions as $interaction)
                    <tr>
                        <td>
                            @if($interaction->product)
                                <a href="{{ route('customer.products.show', $interaction->product) }}" style="color: var(--primary-green); text-decoration: none;">
                                    {{ Str::limit($interaction->product->name, 30) }}
                                </a>
                            @else
                                <span style="color: var(--text-secondary);">محذوف</span>
                            @endif
                        </td>
                        <td>{{ $interaction->commenter_name ?? $interaction->commenter_id }}</td>
                        <td>{{ Str::limit($interaction->comment_text, 40) }}</td>
                        <td>
                            @if($interaction->platform === 'instagram')
                                <span style="background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888); color: white; padding: 2px 10px; border-radius: 12px; font-size: 12px;">انستقرام</span>
                            @else
                                <span style="background: #1877f2; color: white; padding: 2px 10px; border-radius: 12px; font-size: 12px;">فيسبوك</span>
                            @endif
                        </td>
                        <td>
                            @if($interaction->replied)
                                <span style="color: var(--primary-green);">✓ تم</span>
                            @else
                                <span style="color: var(--text-secondary);">—</span>
                            @endif
                        </td>
                        <td>
                            @if($interaction->dm_sent)
                                <span style="color: var(--primary-green);">✓ تم</span>
                            @else
                                <span style="color: var(--text-secondary);">بانتظار الرسالة</span>
                            @endif
                        </td>
                        <td>
                            @if($interaction->isActive())
                                <span style="background: #d4edda; color: #155724; padding: 2px 10px; border-radius: 12px; font-size: 12px;">نشط</span>
                            @else
                                <span style="background: #f8d7da; color: #721c24; padding: 2px 10px; border-radius: 12px; font-size: 12px;">منتهي</span>
                            @endif
                        </td>
                        <td style="font-size: 13px; color: var(--text-secondary);">
                            {{ $interaction->created_at->diffForHumans() }}
                        </td>
                        <td>
                            <form action="{{ route('customer.comment-replies.destroy', $interaction) }}" method="POST" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm" style="background: none; color: #dc3545; border: none; cursor: pointer;" onclick="return confirm('حذف؟')" title="حذف">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="18" height="18">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="empty-state-new">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                                </svg>
                                <h3>لا توجد تفاعلات حالياً</h3>
                                <p>ستظهر التفاعلات هنا عندما يعلّق شخص على منشورات المنتجات المرتبطة</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($interactions->hasPages())
        <div style="padding: 16px; display: flex; justify-content: center;">
            {{ $interactions->appends(['filter' => $filter])->links() }}
        </div>
    @endif
</div>
@endsection
