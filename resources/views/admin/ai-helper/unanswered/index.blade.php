@extends('layouts.customer')

@section('title', 'الأسئلة المعلقة')

@section('content')
<div class="dashboard-header">
    <div class="header-title">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 28px; height: 28px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
        </svg>
        الأسئلة المعلقة
        @if($counts['pending'] > 0)
            <span class="badge badge-danger" style="margin-right: 10px;">{{ $counts['pending'] }}</span>
        @endif
    </div>
    <div class="header-actions">
        <a href="{{ route('customer.ai-helper.index') }}" class="btn btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 18px; height: 18px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
            </svg>
            رجوع
        </a>
    </div>
</div>

<!-- Status Tabs -->
<div style="background: var(--bg-card); border-radius: 12px; padding: 15px; margin-bottom: 20px; border: 1px solid var(--border-color);">
    <div style="display: flex; gap: 10px;">
        <a href="{{ route('customer.ai-helper.unanswered.index', ['status' => 'pending']) }}"
           class="btn {{ $status == 'pending' ? 'btn-primary' : 'btn-secondary' }}"
           style="padding: 10px 20px;">
            معلقة ({{ $counts['pending'] }})
        </a>
        <a href="{{ route('customer.ai-helper.unanswered.index', ['status' => 'answered']) }}"
           class="btn {{ $status == 'answered' ? 'btn-primary' : 'btn-secondary' }}"
           style="padding: 10px 20px;">
            مُجابة ({{ $counts['answered'] }})
        </a>
        @if($counts['urgent'] > 0)
        <a href="{{ route('customer.ai-helper.unanswered.index', ['urgent' => 1]) }}"
           class="btn {{ request('urgent') ? 'btn-primary' : 'btn-secondary' }}"
           style="padding: 10px 20px; background: {{ request('urgent') ? 'var(--danger)' : '' }};">
            🔴 عاجلة ({{ $counts['urgent'] }})
        </a>
        @endif
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body">
        <form method="GET" action="{{ route('customer.ai-helper.unanswered.index') }}">
            <input type="hidden" name="status" value="{{ $status }}">
            @if(request('urgent'))
                <input type="hidden" name="urgent" value="1">
            @endif

            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: end;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">بحث</label>
                    <input type="text" name="search" class="form-control" placeholder="ابحث في الأسئلة..." value="{{ request('search') }}">
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

<!-- Questions List -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
           📝 الأسئلة ({{ $questions->total() }})
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        @if($questions->count() > 0)
        <div style="display: flex; flex-direction: column;">
            @foreach($questions as $question)
            <div style="padding: 20px; border-bottom: 1px solid var(--border-color); transition: background 0.2s;"
                 onmouseover="this.style.background='rgba(255, 255, 255, 0.02)'"
                 onmouseout="this.style.background='transparent'">

                <!-- Question Header -->
                <div style="display: flex; align-items: start; gap: 15px; margin-bottom: 12px;">
                    @if($question->needs_urgent_attention)
                        <span style="font-size: 1.3rem; flex-shrink: 0;">🔴</span>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.3rem; height: 1.3rem; flex-shrink: 0; display: inline-block;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                        </svg>
                    @endif

                    <div style="flex: 1;">
                        <h4 style="font-size: 1rem; font-weight: 600; color: var(--text-light); margin-bottom: 8px;">
                            {{ $question->question }}
                        </h4>

                        @if($question->context)
                        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 10px; line-height: 1.5;">
                            {{ Str::limit($question->context, 120) }}
                        </p>
                        @endif

                        <!-- Meta Info -->
                        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                            @if($question->lead)
                            <span style="color: var(--text-muted); font-size: 0.8rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 14px; height: 14px; display: inline; margin-left: 4px;">
                                    <path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z" />
                                </svg>
                                {{ $question->lead->name }}
                            </span>
                            @endif

                            <span style="color: var(--text-muted); font-size: 0.8rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 14px; height: 14px; display: inline; margin-left: 4px;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd" />
                                </svg>
                                {{ $question->created_at->diffForHumans() }}
                            </span>

                            @if($question->occurrence_count > 1)
                            <span class="badge badge-info" style="font-size: 0.75rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 12px; height: 12px; display: inline; margin-left: 3px;">
                                    <path d="M15.98 1.804a1 1 0 00-1.96 0l-.24 1.192a1 1 0 01-.784.785l-1.192.238a1 1 0 000 1.962l1.192.238a1 1 0 01.785.785l.238 1.192a1 1 0 001.962 0l.238-1.192a1 1 0 01.785-.785l1.192-.238a1 1 0 000-1.962l-1.192-.238a1 1 0 01-.785-.785l-.238-1.192zM6.949 5.684a1 1 0 00-1.898 0l-.683 2.051a1 1 0 01-.633.633l-2.051.683a1 1 0 000 1.898l2.051.684a1 1 0 01.633.632l.683 2.051a1 1 0 001.898 0l.683-2.051a1 1 0 01.633-.633l2.051-.683a1 1 0 000-1.898l-2.051-.683a1 1 0 01-.633-.633L6.95 5.684zM13.949 13.684a1 1 0 00-1.898 0l-.184.551a1 1 0 01-.632.633l-.551.183a1 1 0 000 1.898l.551.183a1 1 0 01.633.633l.183.551a1 1 0 001.898 0l.184-.551a1 1 0 01.632-.633l.551-.183a1 1 0 000-1.898l-.551-.184a1 1 0 01-.633-.632l-.183-.551z" />
                                </svg>
                                سُئل {{ $question->occurrence_count }} مرات
                            </span>
                            @endif

                            @if($question->category)
                            <span class="badge" style="background: rgba(168, 85, 247, 0.1); color: #a855f7; font-size: 0.75rem;">
                                {{ $question->category }}
                            </span>
                            @endif

                            @if($question->status == 'pending')
                            <span class="badge badge-warning" style="font-size: 0.75rem;">معلق</span>
                            @elseif($question->status == 'answered')
                            <span class="badge badge-success" style="font-size: 0.75rem;">تم الرد</span>
                            @endif
                        </div>
                    </div>

                    <div style="display: flex; gap: 8px; flex-shrink: 0;">
                        <a href="{{ route('customer.ai-helper.unanswered.show', $question->id) }}" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.85rem;">
                            @if($question->status == 'pending')
                                الرد الآن
                            @else
                                عرض
                            @endif
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @if($questions->hasPages())
        <div style="padding: 20px; border-top: 1px solid var(--border-color);">
            {{ $questions->links() }}
        </div>
        @endif
        @else
        <div style="text-align: center; padding: 60px 20px;">
            <div style="width: 80px; height: 80px; background: rgba(37, 211, 102, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 40px; height: 40px; color: var(--primary-green);">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h3 style="font-size: 1.3rem; font-weight: 600; color: var(--text-light); margin-bottom: 10px;">
                @if(request('search') || request('category') != 'all')
                    لا توجد نتائج
                @elseif($status == 'pending')
                    رائع! لا توجد أسئلة معلقة
                @else
                    لا توجد أسئلة مُجابة
                @endif
            </h3>
            <p style="color: var(--text-muted);">
                @if(request('search') || request('category') != 'all')
                    جرب تغيير معايير البحث
                @elseif($status == 'pending')
                    جميع الأسئلة تم الرد عليها ✨
                @endif
            </p>
        </div>
        @endif
    </div>
</div>
@endsection
