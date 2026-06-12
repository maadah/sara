@extends('layouts.customer')

@section('title', 'العملاء المحتملين')

@php
    $safeLeads = is_iterable($leads ?? null) ? $leads : collect();
    $canPaginate = is_object($safeLeads) && method_exists($safeLeads, 'hasPages') && method_exists($safeLeads, 'links');
@endphp

@section('content')
<div class="page-header-bar">
    <h1 class="page-title">العملاء المحتملين</h1>
    <a href="{{ route('customer.leads.export', request()->query()) }}" class="btn-add-sm">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
        </svg>
        تصدير
    </a>
</div>

<!-- Stats Cards -->
<div class="stats-cards">
    <div class="stat-card-mini">
        <span class="stat-value">{{ number_format($stats['total']) }}</span>
        <span class="stat-label">إجمالي العملاء</span>
    </div>
    <div class="stat-card-mini">
        <span class="stat-value" style="color: #3b82f6;">{{ number_format($stats['new']) }}</span>
        <span class="stat-label">جديد</span>
    </div>
    <div class="stat-card-mini">
        <span class="stat-value" style="color: #f59e0b;">{{ number_format($stats['contacted']) }}</span>
        <span class="stat-label">تم التواصل</span>
    </div>
    <div class="stat-card-mini">
        <span class="stat-value" style="color: #22c55e;">{{ number_format($stats['converted']) }}</span>
        <span class="stat-label">تحول لعميل</span>
    </div>
    <div class="stat-card-mini">
        <span class="stat-value" style="color: #ef4444;">{{ number_format($stats['lost']) }}</span>
        <span class="stat-label">خسارة</span>
    </div>
</div>

<!-- Filters -->
<div class="filters-section">
    <form action="{{ route('customer.leads.index') }}" method="GET" class="filters-form">
        <input type="text" name="search" class="filter-input" placeholder="اسم، هاتف..." value="{{ request('search') }}">
        <select name="status" class="filter-select">
            <option value="">الكل</option>
            <option value="new" {{ request('status') == 'new' ? 'selected' : '' }}>جديد</option>
            <option value="contacted" {{ request('status') == 'contacted' ? 'selected' : '' }}>تم التواصل</option>
            <option value="converted" {{ request('status') == 'converted' ? 'selected' : '' }}>تحول لعميل</option>
            <option value="lost" {{ request('status') == 'lost' ? 'selected' : '' }}>خسارة</option>
        </select>
        <select name="source" class="filter-select">
            <option value="">الكل</option>
            <option value="facebook" {{ request('source') == 'facebook' ? 'selected' : '' }}>فيسبوك</option>
            <option value="instagram" {{ request('source') == 'instagram' ? 'selected' : '' }}>انستغرام</option>
            <option value="whatsapp" {{ request('source') == 'whatsapp' ? 'selected' : '' }}>واتساب</option>
        </select>
        <button type="submit" class="btn-filter">بحث</button>
    </form>
</div>

<!-- Leads Table -->
<div class="inventory-table-wrapper">
    <table class="inventory-table">
        <thead>
            <tr>
                <th>العميل</th>
                <th>المصدر</th>
                <th>الحالة</th>
                <th>الهاتف</th>
                <th>المدينة</th>
                <th>الرسائل</th>
                <th>الطلبات</th>
                <th>آخر تواصل</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($safeLeads as $lead)
                <tr>
                    <td>
                        <div class="lead-cell">
                            @if($lead->profile_image)
                                <div class="lead-avatar {{ $lead->source }}" style="padding: 0; overflow: hidden;">
                                    <img src="{{ $lead->profile_image }}" alt="{{ $lead->display_name }}" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                            @else
                                <div class="lead-avatar {{ $lead->source }}">
                                    {{ mb_substr($lead->display_name, 0, 1) }}
                                </div>
                            @endif
                            <div>
                                <div class="lead-name">{{ $lead->display_name }}</div>
                                <div class="lead-date">{{ $lead->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="source-tag {{ $lead->source }}">
                            @if($lead->source === 'facebook')
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="12" height="12"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            @elseif($lead->source === 'instagram')
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="12" height="12"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="12" height="12"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                            @endif
                            {{ $lead->source_label }}
                        </span>
                    </td>
                    <td>
                        <span class="status-tag {{ $lead->status }}">{{ $lead->status_label }}</span>
                    </td>
                    <td>{{ $lead->phone ?? '-' }}</td>
                    <td>{{ $lead->city ?? '-' }}</td>
                    <td>{{ number_format($lead->total_messages) }}</td>
                    <td>{{ number_format($lead->total_orders) }}</td>
                    <td>{{ $lead->last_contact_at?->diffForHumans() ?? '-' }}</td>
                    <td>
                        <div class="action-buttons">
                            <a href="{{ route('customer.leads.show', $lead) }}" class="action-btn" title="عرض">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </a>
                            @if($lead->conversation)
                                <a href="{{ route('customer.leads.conversation', $lead) }}" class="action-btn chat" title="المحادثة">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                                    </svg>
                                </a>
                            @endif
                            <form action="{{ route('customer.leads.destroy', $lead) }}" method="POST" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="action-btn delete" title="حذف" onclick="return confirm('هل أنت متأكد من حذف هذا العميل؟')">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">
                        <div class="empty-state-new">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            <h3>لا يوجد عملاء</h3>
                            <p>سيظهر العملاء هنا عند التواصل معك عبر فيسبوك أو انستغرام</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($canPaginate && $safeLeads->hasPages())
    <div class="pagination-wrapper">
        {{ $safeLeads->links() }}
    </div>
@endif
@endsection
