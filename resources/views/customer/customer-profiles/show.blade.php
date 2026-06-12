@extends('layouts.customer')

@section('title', 'ملف الزبون: ' . ($customerProfile->name ?? 'غير معروف'))

@section('content')
<div class="page-header-bar">
    <a href="{{ route('customer.customer-profiles.index') }}" style="color:#6b7280;font-size:.875rem;">← الملفات الشخصية</a>
    <h1 class="page-title" style="margin-top:.5rem;">
        ملف الزبون: {{ $customerProfile->name ?? ($customerProfile->lead?->name ?? 'غير معروف') }}
    </h1>
</div>

@if(session('success'))
    <div style="background:#d1fae5;color:#065f46;padding:.75rem 1rem;border-radius:.5rem;margin-bottom:1rem;">
        {{ session('success') }}
    </div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">

    {{-- ── Contact Info ── --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📞 معلومات التواصل</h3>
        </div>
        <div class="card-body">
            <dl style="display:grid;grid-template-columns:auto 1fr;gap:.5rem 1rem;">
                <dt style="color:#6b7280;font-size:.875rem;">الاسم</dt>
                <dd style="font-weight:600;">{{ $customerProfile->name ?? '—' }}</dd>

                <dt style="color:#6b7280;font-size:.875rem;">الهاتف</dt>
                <dd>{{ $customerProfile->phone ?? '—' }}</dd>

                <dt style="color:#6b7280;font-size:.875rem;">المدينة</dt>
                <dd>{{ $customerProfile->city ?? '—' }}</dd>

                <dt style="color:#6b7280;font-size:.875rem;">العنوان</dt>
                <dd>{{ $customerProfile->address ?? '—' }}</dd>

                <dt style="color:#6b7280;font-size:.875rem;">المنصة</dt>
                <dd>{{ $customerProfile->social_platform ?? $customerProfile->lead?->source ?? '—' }}</dd>

                <dt style="color:#6b7280;font-size:.875rem;">آخر طلب</dt>
                <dd>{{ $customerProfile->last_order_at?->diffForHumans() ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    {{-- ── Demographics ── --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">👤 الملف الديموغرافي</h3>
        </div>
        <div class="card-body">
            <dl style="display:grid;grid-template-columns:auto 1fr;gap:.5rem 1rem;">
                <dt style="color:#6b7280;font-size:.875rem;">العمر</dt>
                <dd>{{ $customerProfile->age ? $customerProfile->age . ' سنة' : '—' }}</dd>

                <dt style="color:#6b7280;font-size:.875rem;">الجنس</dt>
                <dd>
                    @if($customerProfile->gender === 'male')   <span style="color:#3b82f6;font-weight:600;">ذكر</span>
                    @elseif($customerProfile->gender === 'female') <span style="color:#ec4899;font-weight:600;">أنثى</span>
                    @else —
                    @endif
                </dd>

                <dt style="color:#6b7280;font-size:.875rem;">الوظيفة</dt>
                <dd>{{ $customerProfile->occupation ?? '—' }}</dd>

                <dt style="color:#6b7280;font-size:.875rem;">الحالة الاجتماعية</dt>
                <dd>@switch($customerProfile->marital_status)
                    @case('single') أعزب @break
                    @case('married') متزوج @break
                    @case('divorced') مطلق @break
                    @default —
                @endswitch</dd>

                <dt style="color:#6b7280;font-size:.875rem;">أقل ميزانية</dt>
                <dd>{{ $customerProfile->budget_min ? number_format($customerProfile->budget_min) . ' د.ع' : '—' }}</dd>

                <dt style="color:#6b7280;font-size:.875rem;">أعلى ميزانية</dt>
                <dd>{{ $customerProfile->budget_max ? number_format($customerProfile->budget_max) . ' د.ع' : '—' }}</dd>


            </dl>
        </div>
    </div>

    {{-- ── Scores ── --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">⭐ النشاط والنقاط</h3>
        </div>
        <div class="card-body">
            <div class="stats-cards" style="grid-template-columns:repeat(3,1fr);gap:1rem;">
                <div class="stat-card-mini">
                    <span class="stat-value" style="color:#f59e0b;">{{ $customerProfile->lead_score }}</span>
                    <span class="stat-label">نقاط</span>
                </div>
                <div class="stat-card-mini">
                    <span class="stat-value" style="color:#22c55e;">{{ $customerProfile->total_orders }}</span>
                    <span class="stat-label">طلبات</span>
                </div>
                <div class="stat-card-mini">
                    <span class="stat-value" style="color:#3b82f6;">{{ $sessions->count() }}</span>
                    <span class="stat-label">جلسات محادثة</span>
                </div>
            </div>

            @if(!empty($customerProfile->tags))
            <div style="margin-top:1rem;">
                @foreach((array)$customerProfile->tags as $tag)
                    <span style="background:#fef3c7;color:#92400e;padding:.2rem .6rem;border-radius:.5rem;font-size:.8rem;font-weight:600;margin:.1rem;display:inline-block;">{{ $tag }}</span>
                @endforeach
            </div>
            @endif

            @if($customerProfile->notes)
            <div style="margin-top:1rem;">
                <div style="font-size:.75rem;font-weight:600;color:#6b7280;margin-bottom:.4rem;">📌 ملاحظات الانتباه</div>
                <div style="background:#fefce8;border:1px solid #fde68a;border-radius:.5rem;padding:.75rem;font-size:.875rem;color:#374151;line-height:1.9;white-space:pre-line;">{!! nl2br(e($customerProfile->notes)) !!}</div>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Recent Sessions ── --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">💬 آخر جلسات المحادثة</h3>
        </div>
        <div class="card-body" style="padding:0;">
            @forelse($sessions as $session)
            <div style="padding:.75rem 1rem;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-size:.875rem;font-weight:500;color:#111827;">{{ $session->state->value }}</div>
                    <div style="font-size:.75rem;color:#9ca3af;">{{ $session->created_at->format('Y/m/d H:i') }}</div>
                </div>
                <div style="font-size:.8rem;color:#6b7280;">
                    @if($session->hasCartItems())
                        <span style="color:#22c55e;">🛒 سلة</span>
                    @endif
                </div>
            </div>
            @empty
            <p style="padding:1rem;color:#9ca3af;font-size:.875rem;">لا توجد جلسات مسجلة.</p>
            @endforelse
        </div>
    </div>

</div>

{{-- ── Manual Edit Form ── --}}
<div class="card" style="margin-top:1.5rem;">
    <div class="card-header">
        <h3 class="card-title">✏️ تعديل الملف يدوياً</h3>
    </div>
    <div class="card-body">
        <form action="{{ route('customer.customer-profiles.update', $customerProfile) }}" method="POST">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;">

                <div class="form-group">
                    <label class="form-label">الاسم</label>
                    <input type="text" name="name" class="form-input" value="{{ $customerProfile->name }}">
                </div>
                <div class="form-group">
                    <label class="form-label">الهاتف</label>
                    <input type="text" name="phone" class="form-input" value="{{ $customerProfile->phone }}">
                </div>
                <div class="form-group">
                    <label class="form-label">المدينة</label>
                    <input type="text" name="city" class="form-input" value="{{ $customerProfile->city }}">
                </div>
                <div class="form-group">
                    <label class="form-label">العمر</label>
                    <input type="number" name="age" class="form-input" min="1" max="120" value="{{ $customerProfile->age }}">
                </div>
                <div class="form-group">
                    <label class="form-label">الجنس</label>
                    <select name="gender" class="form-input">
                        <option value="">—</option>
                        <option value="male"   @selected($customerProfile->gender === 'male')>ذكر</option>
                        <option value="female" @selected($customerProfile->gender === 'female')>أنثى</option>
                        <option value="other"  @selected($customerProfile->gender === 'other')>أخرى</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">الحالة الاجتماعية</label>
                    <select name="marital_status" class="form-input">
                        <option value="">—</option>
                        <option value="single"   @selected($customerProfile->marital_status === 'single')>أعزب</option>
                        <option value="married"  @selected($customerProfile->marital_status === 'married')>متزوج</option>
                        <option value="divorced" @selected($customerProfile->marital_status === 'divorced')>مطلق</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">الوظيفة</label>
                    <input type="text" name="occupation" class="form-input" value="{{ $customerProfile->occupation }}">
                </div>
                <div class="form-group">
                    <label class="form-label">أقل ميزانية (د.ع)</label>
                    <input type="number" name="budget_min" class="form-input" value="{{ $customerProfile->budget_min }}">
                </div>
                <div class="form-group">
                    <label class="form-label">أعلى ميزانية (د.ع)</label>
                    <input type="number" name="budget_max" class="form-input" value="{{ $customerProfile->budget_max }}">
                </div>

                <div class="form-group" style="grid-column:span 3;">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-input" rows="2">{{ $customerProfile->notes }}</textarea>
                </div>
            </div>
            <div style="margin-top:1rem;">
                <button type="submit" class="btn-primary-sm">حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>
@endsection
