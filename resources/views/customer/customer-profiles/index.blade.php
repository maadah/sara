@extends('layouts.customer')

@section('title', 'الملفات الشخصية للزبائن')

@section('content')
<div class="page-header-bar">
    <h1 class="page-title">الملفات الشخصية للزبائن</h1>
    <p style="color:#6b7280;font-size:.875rem;margin:0 0 0 auto;">معلومات جمعها الذكاء الاصطناعي تلقائياً خلال المحادثات</p>
</div>

<!-- Stats Cards -->
<div class="stats-cards">
    <div class="stat-card-mini">
        <span class="stat-value">{{ number_format($stats['total']) }}</span>
        <span class="stat-label">إجمالي الملفات</span>
    </div>
    <div class="stat-card-mini">
        <span class="stat-value" style="color:#22c55e;">{{ number_format($stats['with_orders']) }}</span>
        <span class="stat-label">أجروا طلبات</span>
    </div>
    <div class="stat-card-mini">
        <span class="stat-value" style="color:#3b82f6;">{{ number_format($stats['with_phone']) }}</span>
        <span class="stat-label">لديهم هاتف</span>
    </div>
    <div class="stat-card-mini">
        <span class="stat-value" style="color:#8b5cf6;">{{ number_format($stats['with_age']) }}</span>
        <span class="stat-label">معروف العمر</span>
    </div>
    <div class="stat-card-mini">
        <span class="stat-value" style="color:#f59e0b;">{{ number_format($stats['with_budget']) }}</span>
        <span class="stat-label">معروف الميزانية</span>
    </div>
</div>

<!-- Filters -->
<div class="filters-section">
    <form action="{{ route('customer.customer-profiles.index') }}" method="GET" class="filters-form">
        <input type="text" name="search" class="filter-input" placeholder="اسم أو هاتف..." value="{{ request('search') }}">
        <select name="gender" class="filter-select">
            <option value="">الجنس: الكل</option>
            <option value="male"   {{ request('gender') === 'male'   ? 'selected' : '' }}>ذكر</option>
            <option value="female" {{ request('gender') === 'female' ? 'selected' : '' }}>أنثى</option>
        </select>
        <label style="display:flex;align-items:center;gap:.4rem;font-size:.875rem;color:#374151;">
            <input type="checkbox" name="with_orders" value="1" {{ request('with_orders') ? 'checked' : '' }}>
            أجروا طلبات فقط
        </label>
        <button type="submit" class="btn-primary-sm">بحث</button>
        @if(request()->hasAny(['search','gender','with_orders']))
            <a href="{{ route('customer.customer-profiles.index') }}" class="btn-secondary-sm">مسح</a>
        @endif
    </form>
</div>

<!-- Table -->
<div class="data-table-wrapper">
    <table class="data-table">
        <thead>
            <tr>
                <th>الزبون</th>
                <th>الهاتف</th>
                <th>العمر</th>
                <th>الجنس</th>
                <th>الميزانية</th>
                <th>الطلبات</th>
                <th>النقاط</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($profiles as $profile)
            @php $lead = $profile->lead; @endphp
            <tr>
                <td>
                    <div class="lead-cell">
                        @if($lead?->profile_image)
                            <div class="lead-avatar {{ $lead->source ?? '' }}" style="padding:0;overflow:hidden;">
                                <img src="{{ $lead->profile_image }}" alt="{{ $lead->display_name ?? '' }}" style="width:100%;height:100%;object-fit:cover;">
                            </div>
                        @else
                            <div class="lead-avatar {{ $lead->source ?? '' }}">
                                {{ mb_substr($profile->name ?? ($lead?->display_name ?? '?'), 0, 1) }}
                            </div>
                        @endif
                        <div>
                            <div class="lead-name">{{ $profile->name ?? ($lead?->display_name ?? '—') }}</div>
                            <div class="lead-date">{{ $profile->city ?? ($lead?->source_label ?? '') }}</div>
                        </div>
                    </div>
                </td>
                <td>{{ $profile->phone ?? '—' }}</td>
                <td>{{ $profile->age ? $profile->age . ' سنة' : '—' }}</td>
                <td>
                    @if($profile->gender === 'male')   <span style="color:#3b82f6;">ذكر</span>
                    @elseif($profile->gender === 'female') <span style="color:#ec4899;">أنثى</span>
                    @else —
                    @endif
                </td>
                <td>
                    @if($profile->budget_max)
                        {{ number_format($profile->budget_max) }} د.ع
                    @else
                        —
                    @endif
                </td>
                <td>
                    <span style="font-weight:600;color:{{ $profile->total_orders > 0 ? '#22c55e' : '#9ca3af' }};">
                        {{ $profile->total_orders }}
                    </span>
                </td>
                <td>
                    <span style="background:#fef3c7;color:#92400e;padding:.2rem .5rem;border-radius:.5rem;font-weight:600;font-size:.8rem;">
                        {{ $profile->lead_score }}
                    </span>
                </td>
                <td>
                    <a href="{{ route('customer.customer-profiles.show', $profile) }}" class="action-link">عرض</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" style="text-align:center;padding:2rem;color:#6b7280;">
                    لا توجد ملفات شخصية حتى الآن. ستُجمع تلقائياً عبر محادثات الذكاء الاصطناعي.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $profiles->links() }}
@endsection
