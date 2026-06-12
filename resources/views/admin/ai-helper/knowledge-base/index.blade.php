@extends('layouts.customer')

@section('title', 'قاعدة المعرفة')

@section('content')
<div class="dashboard-header">
    <div class="header-title">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 28px; height: 28px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
        </svg>
        قاعدة المعرفة
    </div>
    <div class="header-actions">
        <a href="{{ route('customer.ai-helper.knowledge-base.create') }}" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 20px; height: 20px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            إضافة سؤال جديد
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body">
        <form method="GET" action="{{ route('customer.ai-helper.knowledge-base.index') }}">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: end;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">بحث</label>
                    <input type="text" name="search" class="form-control" placeholder="ابحث عن سؤال أو إجابة..." value="{{ request('search') }}">
                </div>

                <div class="form-group" style="margin: 0;">
                    <label class="form-label">التصنيف</label>
                    <select name="category" class="form-select">
                        <option value="all">الكل</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        <option value="all">الكل</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>نشط</option>
                        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>غير نشط</option>
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>مسودة</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 18px; height: 18px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    بحث
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Knowledge Base Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px; display: inline-block; margin-left: 8px; vertical-align: middle;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
            </svg>
            الأسئلة والأجوبة ({{ $entries->total() }})
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        @if($entries->count() > 0)
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40%;">السؤال</th>
                        <th style="width: 30%;">الإجابة</th>
                        <th>التصنيف</th>
                        <th>الحالة</th>
                        <th>الاستخدام</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entries as $entry)
                    <tr>
                        <td>
                            <div style="display: flex; align-items: start; gap: 8px;">
                                @if($entry->priority > 5)
                                    <span style="color: var(--warning); font-size: 1rem;">⭐</span>
                                @endif
                                <span>{{ $entry->question }}</span>
                            </div>
                        </td>
                        <td>
                            <span style="color: var(--text-muted); font-size: 0.85rem;">
                                {{ Str::limit($entry->answer, 60) }}
                            </span>
                        </td>
                        <td>
                            @if($entry->category)
                                <span class="badge" style="background: rgba(168, 85, 247, 0.1); color: #a855f7;">
                                    {{ $entry->category }}
                                </span>
                            @else
                                <span style="color: var(--text-muted);">-</span>
                            @endif
                        </td>
                        <td class="status-cell">
                            @if($entry->status == 'active')
                                <span class="badge badge-success">نشط</span>
                            @elseif($entry->status == 'inactive')
                                <span class="badge" style="background: rgba(107, 114, 128, 0.1); color: #6b7280;">غير نشط</span>
                            @else
                                <span class="badge badge-warning">مسودة</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge" style="background: rgba(37, 211, 102, 0.1); color: var(--primary-green); font-size: 0.75rem;">
                                {{ $entry->usage_count }} مرة
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('customer.ai-helper.knowledge-base.edit', $entry->id) }}" class="action-btn edit" title="تعديل">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                    </svg>
                                </a>
                                <form method="POST" action="{{ route('customer.ai-helper.knowledge-base.toggle-status', $entry->id) }}" style="display: inline;" class="toggle-status-form">
                                    @csrf
                                    <button type="submit" class="action-btn view" title="تفعيل/تعطيل" style="border: none; cursor: pointer;">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="toggle-icon">
                                            @if($entry->status == 'active')
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            @endif
                                        </svg>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('customer.ai-helper.knowledge-base.destroy', $entry->id) }}" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="action-btn delete" title="حذف" style="border: none; cursor: pointer;">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($entries->hasPages())
        <div style="padding: 20px; border-top: 1px solid var(--border-color);">
            {{ $entries->links() }}
        </div>
        @endif
        @else
        <div style="text-align: center; padding: 60px 20px;">
            <div style="width: 80px; height: 80px; background: rgba(107, 114, 128, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 40px; height: 40px; color: #6b7280;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                </svg>
            </div>
            <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--text-light); margin-bottom: 8px;">
                @if(request('search') || request('category') != 'all' || request('status') != 'all')
                    لا توجد نتائج
                @else
                    لا توجد أسئلة في قاعدة المعرفة
                @endif
            </h3>
            <p style="color: var(--text-muted); margin-bottom: 20px;">
                @if(request('search') || request('category') != 'all' || request('status') != 'all')
                    جرب تغيير معايير البحث
                @else
                    ابدأ بإضافة أول سؤال وجواب
                @endif
            </p>
            @if(!request('search') && request('category') == 'all' && request('status') == 'all')
            <a href="{{ route('customer.ai-helper.knowledge-base.create') }}" class="btn btn-primary" style="display: inline-flex; max-width: 250px;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 20px; height: 20px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                إضافة سؤال جديد
            </a>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleForms = document.querySelectorAll('.toggle-status-form');
    toggleForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            btn.style.opacity = '0.5';
            btn.style.pointerEvents = 'none';

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => {
                if (res.headers.get('content-type')?.includes('application/json')) {
                    return res.json();
                }
                throw new Error('Not JSON response');
            })
            .then(data => {
                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';

                if (data && data.success) {
                    const row = form.closest('tr');
                    const statusCell = row.querySelector('.status-cell');
                    const btnIcon = form.querySelector('.toggle-icon');
                    
                    if (data.new_status === 'active') {
                        if(statusCell) statusCell.innerHTML = '<span class="badge badge-success">نشط</span>';
                        if(btnIcon) btnIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />';
                    } else if (data.new_status === 'inactive') {
                        if(statusCell) statusCell.innerHTML = '<span class="badge" style="background: rgba(107, 114, 128, 0.1); color: #6b7280;">غير نشط</span>';
                        if(btnIcon) btnIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />';
                    }
                } else if(data && !data.success && data.message) {
                    alert(data.message);
                }
            })
            .catch(err => {
                console.error(err);
                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';
            });
        });
    });
});
</script>
@endpush
