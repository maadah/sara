@extends('layouts.customer')

@section('title', 'تفاصيل العميل - ' . $lead->display_name)

@section('content')

@php
    $profile        = $lead->customerProfile;
    $score          = $profile?->lead_score ?? $lead->interest_score ?? 0;
    $scoreCategory  = $profile?->scoreCategory() ?? 'cold';
    $scoreCategoryLabel = match($scoreCategory) {
        'vip'  => 'VIP',  'hot'  => 'ساخن',
        'warm' => 'دافئ', default => 'بارد',
    };
    $scoreCategoryColor = match($scoreCategory) {
        'vip'  => '#8b5cf6', 'hot'  => '#ef4444',
        'warm' => '#f97316', default => '#6b7280',
    };

    // Tags & Preferences
    $tags        = is_array($profile?->tags)        ? $profile->tags        : [];
    $preferences = is_array($profile?->preferences) ? $profile->preferences : [];

    // Profile completeness
    $profileCheckFields = [
        'name'           => $lead->name,
        'phone'          => $lead->phone,
        'city'           => $lead->city,
        'address'        => $lead->address,
        'age'            => $profile?->age,
        'gender'         => $profile?->gender,
        'occupation'     => $profile?->occupation,
        'marital_status' => $profile?->marital_status,
        'budget'         => $profile?->budget_max,
    ];
    $filledCount  = collect($profileCheckFields)->filter()->count();
    $completeness = (int) round($filledCount / count($profileCheckFields) * 100);

    // Charts data
    $ordersByStatus    = $lead->orders->groupBy('status')->map->count()->toArray();
    $orderStatusLabels = array_map(fn($s) => match($s) {
        'pending'    => 'معلق',
        'processing' => 'قيد التجهيز',
        'shipped'    => 'تم الشحن',
        'delivered'  => 'تم التوصيل',
        'cancelled'  => 'ملغي',
        default      => $s,
    }, array_keys($ordersByStatus));
    $orderStatusCounts = array_values($ordersByStatus);
    $orderStatusColors = ['#f97316','#3b82f6','#8b5cf6','#22c55e','#ef4444','#6b7280'];

    // Activity last 14 days
    $msgDays = []; $msgCounts = [];
    if ($lead->conversation) {
        $grouped = $lead->conversation->messages
            ->filter(fn($m) => $m->created_at->gt(now()->subDays(14)))
            ->groupBy(fn($m) => $m->created_at->format('m/d'))
            ->map->count();
        for ($i = 13; $i >= 0; $i--) {
            $day         = now()->subDays($i)->format('m/d');
            $msgDays[]   = $day;
            $msgCounts[] = $grouped->get($day, 0);
        }
    }

    // Budget display
    $budgetVal = null;
    if ($profile?->budget_min && $profile?->budget_max)
        $budgetVal = number_format($profile->budget_min) . ' — ' . number_format($profile->budget_max) . ' د.ع';
    elseif ($profile?->budget_max)
        $budgetVal = 'حتى ' . number_format($profile->budget_max) . ' د.ع';
    elseif ($profile?->budget_min)
        $budgetVal = 'من ' . number_format($profile->budget_min) . ' د.ع';
@endphp

{{-- ══════════ PAGE HEADER ══════════ --}}
<div class="page-header-bar">
    <div class="header-right">
        <a href="{{ route('customer.leads.index') }}" class="back-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
            </svg>
        </a>
        <h1 class="page-title">تفاصيل العميل</h1>
    </div>
    <div class="header-buttons">
        @if($lead->conversation)
            <a href="{{ route('customer.leads.conversation', $lead) }}" class="btn-add-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                </svg>
                فتح المحادثة
            </a>
        @endif
        <form action="{{ route('customer.leads.destroy', $lead) }}" method="POST" style="display:inline;">
            @csrf @method('DELETE')
            <button type="submit" class="btn-danger-sm" onclick="return confirm('هل أنت متأكد من حذف هذا العميل؟')">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
                حذف
            </button>
        </form>
    </div>
</div>

@if(session('success'))<div class="alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert-error">{{ session('error') }}</div>@endif

{{-- ══════════ HERO BANNER ══════════ --}}
<div style="background:linear-gradient(135deg,{{ $scoreCategoryColor }}18 0%,var(--bg-darker) 60%);border:1px solid {{ $scoreCategoryColor }}30;border-radius:16px;padding:22px 26px;margin-bottom:20px;display:flex;align-items:center;gap:20px;flex-wrap:wrap;">

    {{-- Avatar with VIP star --}}
    <div style="position:relative;flex-shrink:0;">
        <div class="customer-avatar large {{ $lead->source }}" style="width:70px;height:70px;font-size:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
            {{ mb_substr($lead->display_name, 0, 1) }}
        </div>
        @if($scoreCategory === 'vip')
            <div style="position:absolute;bottom:-4px;right:-4px;background:#8b5cf6;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#fff;border:2px solid #fff;">★</div>
        @endif
    </div>

    {{-- Name + meta --}}
    <div style="flex:1;min-width:160px;">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
            <h2 style="margin:0;font-size:21px;font-weight:700;color:var(--text-light);">{{ $lead->display_name }}</h2>
            <span style="background:{{ $scoreCategoryColor }};color:#fff;font-size:12px;font-weight:600;padding:3px 11px;border-radius:20px;">{{ $scoreCategoryLabel }}</span>
            @if($profile?->isReturning())
                <span style="background:#dcfce7;color:#16a34a;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;">متكرر</span>
            @endif
            <span class="source-tag {{ $lead->source }}" style="font-size:12px;">{{ $lead->source_label }}</span>
        </div>
        <div style="font-size:13px;color:var(--text-muted);display:flex;gap:16px;flex-wrap:wrap;">
            @if($lead->phone)              <span>📞 {{ $lead->phone }}</span>                                @endif
            @if($lead->city)               <span>📍 {{ $lead->city }}</span>                                 @endif
            @if($lead->first_contact_at)   <span>📅 منذ {{ $lead->first_contact_at->diffForHumans() }}</span>  @endif
            @if($lead->last_contact_at)    <span>🕐 آخر تواصل {{ $lead->last_contact_at->diffForHumans() }}</span> @endif
        </div>
    </div>

    {{-- KPI chips --}}
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        @php
            $chips = [
                ['val' => $score,                               'label' => 'نقاط',  'color' => $scoreCategoryColor],
                ['val' => number_format($lead->total_messages), 'label' => 'رسائل', 'color' => '#3b82f6'],
                ['val' => number_format($lead->total_orders),   'label' => 'طلبات', 'color' => '#22c55e'],
                ['val' => $completeness . '%',                  'label' => 'مكتمل', 'color' => '#6b7280'],
            ];
        @endphp
        @foreach($chips as $chip)
            <div style="text-align:center;background:var(--bg-card);border-radius:12px;padding:9px 16px;box-shadow:0 1px 6px rgba(0,0,0,.25);min-width:66px;border:1px solid var(--border-color);">
                <div style="font-size:20px;font-weight:700;color:{{ $chip['color'] }};">{{ $chip['val'] }}</div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $chip['label'] }}</div>
            </div>
        @endforeach
        @if($lead->total_spent > 0)
            <div style="text-align:center;background:var(--bg-card);border-radius:12px;padding:9px 16px;box-shadow:0 1px 6px rgba(0,0,0,.25);min-width:88px;border:1px solid var(--border-color);">
                <div style="font-size:17px;font-weight:700;color:#f97316;">{{ number_format($lead->total_spent) }}</div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">د.ع إجمالي</div>
            </div>
        @endif
    </div>
</div>

{{-- ══════════ CHARTS ROW ══════════ --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-bottom:20px;">

    {{-- Score gauge --}}
    <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:14px;padding:20px;text-align:center;">
        <div style="font-size:13px;font-weight:600;color:var(--text-light);margin-bottom:12px;">درجة الاهتمام</div>
        <div style="position:relative;width:150px;margin:0 auto;">
            <canvas id="scoreChart" width="150" height="100"></canvas>
            <div style="position:absolute;bottom:2px;left:0;right:0;text-align:center;">
                <span style="font-size:26px;font-weight:800;color:{{ $scoreCategoryColor }};">{{ $score }}</span>
                <span style="font-size:11px;color:#9ca3af;"> / 100</span>
            </div>
        </div>
        <span style="display:inline-block;background:{{ $scoreCategoryColor }}18;color:{{ $scoreCategoryColor }};font-weight:700;font-size:12px;padding:4px 14px;border-radius:20px;border:1px solid {{ $scoreCategoryColor }}44;margin-top:8px;">{{ $scoreCategoryLabel }}</span>
        <div style="display:flex;justify-content:space-between;margin-top:10px;font-size:10px;color:#9ca3af;padding:0 4px;">
            <span>بارد 0–9</span><span>دافئ 10–24</span><span>ساخن 25+</span>
        </div>
    </div>

    {{-- Profile completeness --}}
    <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:14px;padding:20px;">
        <div style="font-size:13px;font-weight:600;color:var(--text-light);margin-bottom:12px;text-align:center;">اكتمال الملف</div>
        <div style="position:relative;width:150px;margin:0 auto 12px;">
            <canvas id="completenessChart" width="150" height="100"></canvas>
            <div style="position:absolute;bottom:2px;left:0;right:0;text-align:center;">
                <span style="font-size:24px;font-weight:800;color:#3b82f6;">{{ $completeness }}%</span>
            </div>
        </div>
        @foreach($profileCheckFields as $fKey => $fVal)
            @php $ok = !empty($fVal); @endphp
            <div style="display:flex;justify-content:space-between;align-items:center;padding:3px 0;font-size:12px;color:var(--text-light);border-bottom:1px solid var(--border-color);">
                <span>{{ match($fKey) {
                    'name'           => 'الاسم',
                    'phone'          => 'الهاتف',
                    'city'           => 'المدينة',
                    'address'        => 'العنوان',
                    'age'            => 'العمر',
                    'gender'         => 'الجنس',
                    'occupation'     => 'المهنة',
                    'marital_status' => 'الحالة الاجتماعية',
                    'budget'         => 'الميزانية',
                    default          => $fKey,
                } }}</span>
                <span style="color:{{ $ok ? '#22c55e' : '#d1d5db' }};font-size:14px;">{{ $ok ? '✓' : '○' }}</span>
            </div>
        @endforeach
    </div>

    {{-- Activity bar chart --}}
    @if(!empty($msgDays))
    <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:14px;padding:20px;">
        <div style="font-size:13px;font-weight:600;color:var(--text-light);margin-bottom:12px;text-align:center;">نشاط المحادثة (14 يوم)</div>
        <div style="height:160px;"><canvas id="activityChart"></canvas></div>
    </div>
    @endif

    {{-- Orders by status --}}
    @if($lead->orders->count() > 0)
    <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:14px;padding:20px;text-align:center;">
        <div style="font-size:13px;font-weight:600;color:var(--text-light);margin-bottom:12px;">الطلبات حسب الحالة</div>
        <div style="width:150px;margin:0 auto;"><canvas id="ordersChart" width="150" height="150"></canvas></div>
        <div style="margin-top:10px;text-align:right;display:flex;flex-direction:column;gap:4px;font-size:12px;">
            @foreach(array_keys($ordersByStatus) as $idx => $st)
                <div style="display:flex;align-items:center;gap:6px;">
                    <div style="width:10px;height:10px;border-radius:2px;background:{{ $orderStatusColors[$idx % count($orderStatusColors)] }};flex-shrink:0;"></div>
                    <span style="color:var(--text-light);">{{ $orderStatusLabels[$idx] }}</span>
                    <span style="margin-right:auto;font-weight:600;color:var(--text-light);">{{ $ordersByStatus[$st] }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

</div>

{{-- ══════════ MAIN EDIT GRID ══════════ --}}
<form action="{{ route('customer.leads.update', $lead) }}" method="POST">
    @csrf @method('PUT')
    <div class="detail-grid">

        {{-- ── LEFT: Edit Form ── --}}
        <div class="detail-card">
            <div class="lead-profile-header">
                <div class="customer-avatar large {{ $lead->source }}">{{ mb_substr($lead->display_name, 0, 1) }}</div>
                <div class="lead-profile-info">
                    <h2>{{ $lead->display_name }}</h2>
                    <span class="source-tag {{ $lead->source }}">{{ $lead->source_label }}</span>
                </div>
            </div>

            <div class="card-section-title">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
                معلومات العميل
            </div>

            <div class="form-group-new">
                <label>الاسم</label>
                <input type="text" name="name" class="form-input-new" value="{{ old('name', $lead->name) }}" placeholder="اسم العميل">
            </div>
            <div class="form-row-new">
                <div class="form-group-new">
                    <label>رقم الهاتف</label>
                    <input type="text" name="phone" class="form-input-new" value="{{ old('phone', $lead->phone) }}" placeholder="07XXXXXXXXX">
                </div>
                <div class="form-group-new">
                    <label>المدينة</label>
                    <input type="text" name="city" class="form-input-new" value="{{ old('city', $lead->city) }}" placeholder="المدينة">
                </div>
            </div>
            <div class="form-group-new">
                <label>العنوان</label>
                <input type="text" name="address" class="form-input-new" value="{{ old('address', $lead->address) }}" placeholder="العنوان الكامل">
            </div>

            <div class="form-group-new">
                <label>الحالة</label>
                <div class="status-selector-new">
                    @foreach(['new' => 'جديد', 'contacted' => 'تم التواصل', 'interested' => 'مهتم', 'converted' => 'تحول لعميل', 'lost' => 'خسارة'] as $val => $lbl)
                        <label class="status-option-new {{ old('status', $lead->status) == $val ? 'active' : '' }}">
                            <input type="radio" name="status" value="{{ $val }}" {{ old('status', $lead->status) == $val ? 'checked' : '' }}>
                            <span class="status-dot {{ $val }}"></span> {{ $lbl }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="form-group-new">
                <label>ملاحظات</label>
                <textarea name="notes" class="form-textarea-new" placeholder="ملاحظات إضافية...">{{ old('notes', $lead->notes) }}</textarea>
            </div>

            <button type="submit" class="btn-submit-new">حفظ التغييرات</button>
        </div>

        {{-- ── RIGHT: AI Profile Panel ── --}}
        <div class="detail-card">

            {{-- Demographics --}}
            <div class="card-section-title">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                </svg>
                ملف AI — البيانات الشخصية
            </div>

            @php
                $demoItems = [
                    ['icon' => '🎂', 'label' => 'العمر',              'val' => $profile?->age ? $profile->age . ' سنة' : null],
                    ['icon' => '👤', 'label' => 'الجنس',              'val' => $profile?->gender ? ($profile->gender === 'male' ? 'ذكر' : ($profile->gender === 'female' ? 'أنثى' : $profile->gender)) : null],
                    ['icon' => '💼', 'label' => 'المهنة',             'val' => $profile?->occupation],
                    ['icon' => '💍', 'label' => 'الحالة الاجتماعية',  'val' => $profile?->marital_status ? match($profile->marital_status) {
                            'single'   => 'أعزب',
                            'married'  => 'متزوج',
                            'divorced' => 'مطلق',
                            default    => $profile->marital_status,
                        } : null],
                    ['icon' => '📍', 'label' => 'المدينة',             'val' => $lead->city],
                    ['icon' => '📱', 'label' => 'المنصة',              'val' => $profile?->social_platform ?? $lead->source],
                ];
            @endphp

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:4px;">
                @foreach($demoItems as $d)
                    <div style="padding:9px 12px;background:{{ $d['val'] ? 'var(--bg-darker)' : 'rgba(255,255,255,0.03)' }};border-radius:8px;border:1px solid {{ $d['val'] ? 'var(--border-color)' : 'rgba(255,255,255,0.06)' }};">
                        <div style="font-size:11px;color:#9ca3af;margin-bottom:3px;">{{ $d['icon'] }} {{ $d['label'] }}</div>
                        <div style="font-size:13px;font-weight:{{ $d['val'] ? '600' : '400' }};color:{{ $d['val'] ? 'var(--text-light)' : 'var(--text-muted)' }};">{{ $d['val'] ?? '—' }}</div>
                    </div>
                @endforeach
                @if($budgetVal)
                    <div style="padding:9px 12px;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0;grid-column:span 2;">
                        <div style="font-size:11px;color:#9ca3af;margin-bottom:3px;">💰 الميزانية</div>
                        <div style="font-size:13px;font-weight:600;color:#16a34a;">{{ $budgetVal }}</div>
                    </div>
                @endif
            </div>

            {{-- Tags --}}
            @if(!empty($tags))
                <div class="card-section-title" style="margin-top:18px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                    </svg>
                    تاجات AI
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:4px;">
                    @foreach($tags as $tag)
                        <span style="background:#ede9fe;color:#7c3aed;font-size:12px;font-weight:600;padding:3px 10px;border-radius:20px;border:1px solid #c4b5fd;"># {{ $tag }}</span>
                    @endforeach
                </div>
            @endif



            {{-- Preferences --}}
            @if(!empty($preferences))
                <div class="card-section-title" style="margin-top:18px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    تفضيلات AI
                </div>
                <div style="display:flex;flex-direction:column;gap:5px;">
                    @foreach($preferences as $pk => $pv)
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 10px;background:var(--bg-darker);border-radius:7px;font-size:12px;border:1px solid var(--border-color);">
                            <span style="color:var(--text-muted);">{{ $pk }}</span>
                            <span style="font-weight:600;color:var(--text-light);">{{ is_array($pv) ? implode(', ', $pv) : $pv }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- AI Notes --}}
            @if($profile && $profile->notes)
                <div class="card-section-title" style="margin-top:18px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                    </svg>
                    ملاحظات الذكاء الاصطناعي
                </div>
                @php $noteLines = array_filter(explode("\n", $profile->notes)); @endphp
                <div style="display:flex;flex-direction:column;gap:6px;max-height:230px;overflow-y:auto;">
                    @foreach($noteLines as $noteLine)
                        @php
                            preg_match('/^\[([^\]]+)\](.*)$/', trim($noteLine), $nm);
                            $noteDate = $nm[1] ?? null;
                            $noteText = trim($nm[2] ?? $noteLine);
                        @endphp
                        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:9px 12px;">
                            @if($noteDate)<div style="font-size:10px;color:#92400e;font-weight:600;opacity:.7;margin-bottom:3px;">{{ $noteDate }}</div>@endif
                            <div style="font-size:13px;color:#78350f;line-height:1.6;">{{ $noteText }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── FULL WIDTH: Orders table ── --}}
        @if($lead->orders && $lead->orders->count() > 0)
        <div class="detail-card" style="grid-column:1 / -1;">
            <div class="card-section-title-between">
                <div class="title-left">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                    </svg>
                    الطلبات ({{ $lead->orders->count() }})
                </div>
                <div style="font-size:13px;color:var(--text-muted);">
                    الإجمالي: <strong style="color:var(--text-light);">{{ number_format($lead->orders->sum('total')) }} د.ع</strong>
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--bg-darker);border-bottom:1px solid var(--border-color);">
                            <th style="padding:10px 14px;text-align:right;color:var(--text-muted);font-weight:600;">#</th>
                            <th style="padding:10px 14px;text-align:right;color:var(--text-muted);font-weight:600;">التاريخ</th>
                            <th style="padding:10px 14px;text-align:right;color:var(--text-muted);font-weight:600;">المنتجات</th>
                            <th style="padding:10px 14px;text-align:right;color:var(--text-muted);font-weight:600;">الإجمالي</th>
                            <th style="padding:10px 14px;text-align:right;color:var(--text-muted);font-weight:600;">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lead->orders as $order)
                        <tr style="border-bottom:1px solid var(--border-color);">
                            <td style="padding:10px 14px;">
                                <a href="{{ route('customer.online-orders.show', $order) }}" style="font-weight:600;color:#3b82f6;text-decoration:none;">#{{ $order->order_number }}</a>
                            </td>
                            <td style="padding:10px 14px;color:var(--text-muted);">{{ $order->created_at->format('Y/m/d H:i') }}</td>
                            <td style="padding:10px 14px;color:#374151;">
                                {{ $order->items->pluck('product_name')->take(2)->implode('، ') }}
                                @if($order->items->count() > 2)
                                    <span style="color:#9ca3af;"> +{{ $order->items->count() - 2 }} أخرى</span>
                                @endif
                            </td>
                            <td style="padding:10px 14px;font-weight:600;color:#111;">{{ number_format($order->total) }} د.ع</td>
                            <td style="padding:10px 14px;"><span class="status-tag {{ $order->status }}">{{ $order->status_label }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- ── FULL WIDTH: Messages preview ── --}}
        @if($lead->conversation && $lead->conversation->messages->count() > 0)
        <div class="detail-card" style="grid-column:1 / -1;">
            <div class="card-section-title-between">
                <div class="title-left">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                    </svg>
                    آخر الرسائل
                </div>
                <a href="{{ route('customer.leads.conversation', $lead) }}" class="view-all-link">عرض المحادثة كاملة</a>
            </div>
            <div class="messages-preview-box">
                @foreach($lead->conversation->messages->reverse()->take(12)->reverse() as $message)
                    <div class="message-preview-item {{ $message->direction }}">
                        <div class="message-preview-bubble">{{ $message->content ?? '[مرفق]' }}</div>
                        <span class="message-preview-time">{{ $message->created_at->format('d/m H:i') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>
</form>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const scoreColor = '{{ $scoreCategoryColor }}';

// ── Score gauge ──
(function () {
    const ctx = document.getElementById('scoreChart');
    if (!ctx) return;
    const score = {{ (int) $score }};
    new Chart(ctx, {
        type: 'doughnut',
        data: { datasets: [{ data: [score, 100 - score], backgroundColor: [scoreColor, '#f3f4f6'], borderWidth: 0, borderRadius: 6 }] },
        options: {
            rotation: -90, circumference: 180, cutout: '75%',
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            layout: { padding: 0 },
        }
    });
})();

// ── Completeness gauge ──
(function () {
    const ctx = document.getElementById('completenessChart');
    if (!ctx) return;
    const pct = {{ $completeness }};
    new Chart(ctx, {
        type: 'doughnut',
        data: { datasets: [{ data: [pct, 100 - pct], backgroundColor: ['#3b82f6', '#f3f4f6'], borderWidth: 0, borderRadius: 6 }] },
        options: {
            rotation: -90, circumference: 180, cutout: '75%',
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            layout: { padding: 0 },
        }
    });
})();

// ── Activity bar ──
(function () {
    const ctx = document.getElementById('activityChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: @json($msgDays ?? []),
            datasets: [{
                label: 'رسائل',
                data: @json($msgCounts ?? []),
                backgroundColor: '#3b82f620',
                borderColor: '#3b82f6',
                borderWidth: 2,
                borderRadius: 4,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } } },
                x: { ticks: { font: { size: 10 } } }
            },
            maintainAspectRatio: false,
        }
    });
})();

// ── Orders doughnut ──
(function () {
    const ctx = document.getElementById('ordersChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: @json($orderStatusLabels ?? []),
            datasets: [{
                data: @json($orderStatusCounts ?? []),
                backgroundColor: ['#f97316','#3b82f6','#8b5cf6','#22c55e','#ef4444','#6b7280'],
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            cutout: '60%',
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => c.label + ': ' + c.raw } }
            }
        }
    });
})();


</script>
@endsection
