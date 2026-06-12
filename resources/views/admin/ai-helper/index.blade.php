@extends('layouts.customer')

@section('title', 'مساعد الذكاء الاصطناعي')

@section('content')
<div class="dashboard-header">
    <div class="header-title">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 28px; height: 28px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
        </svg>
        مساعد الذكاء الاصطناعي
    </div>
    <div class="header-actions">
        <span style="color: var(--text-muted); font-size: 0.9rem;">حسّن أداء AI بسهولة</span>
    </div>
</div>

<!-- Quick Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
            </svg>
        </div>
        <div class="stat-info">
            <h3>{{ $stats['knowledge_base_count'] }}</h3>
            <p>قاعدة المعرفة</p>
            <small style="color: var(--primary-green);">{{ $stats['active_kb_count'] }} نشط</small>
        </div>
    </div>

    <div class="stat-card" style="@if($stats['pending_questions_count'] > 0) border-color: var(--danger); @endif">
        <div class="stat-icon orange">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
            </svg>
        </div>
        <div class="stat-info">
            @if($stats['pending_questions_count'] > 0)
                <h3 style="color: var(--danger);">{{ $stats['pending_questions_count'] }}</h3>
                <p>أسئلة معلقة</p>
                <small style="color: var(--danger);">تحتاج للمراجعة!</small>
            @else
                <h3 style="color: var(--success);">0</h3>
                <p>أسئلة معلقة</p>
                <small style="color: var(--success);">رائع!</small>
            @endif
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon blue">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <div class="stat-info">
            <h3>{{ $stats['answered_questions_count'] }}</h3>
            <p>أسئلة مُجابة</p>
            <small style="color: var(--info);">تم الرد عليها</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(168, 85, 247, 0.1); color: #a855f7;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
            </svg>
        </div>
        <div class="stat-info">
            <h3>{{ $stats['fast_replies_count'] }}</h3>
            <p>ردود سريعة</p>
            <small style="color: #a855f7;">{{ $stats['active_fast_replies_count'] }} نشط</small>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="dashboard-grid" style="grid-template-columns: repeat(3, 1fr);">
    <a href="{{ route('customer.ai-helper.knowledge-base.index') }}" class="card" style="text-decoration: none; cursor: pointer; transition: all 0.3s ease;">
        <div class="card-body" style="text-align: center; padding: 30px;">
            <div style="width: 60px; height: 60px; background: rgba(37, 211, 102, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 30px; height: 30px; color: var(--primary-green);">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                </svg>
            </div>
            <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--text-light); margin-bottom: 8px;">قاعدة المعرفة</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem;">إدارة الأسئلة والأجوبة</p>
        </div>
    </a>

    <a href="{{ route('customer.ai-helper.unanswered.index') }}" class="card" style="text-decoration: none; cursor: pointer; transition: all 0.3s ease;">
        <div class="card-body" style="text-align: center; padding: 30px; position: relative;">
            @if($stats['pending_questions_count'] > 0)
                <span class="badge badge-danger" style="position: absolute; top: 15px; right: 15px;">{{ $stats['pending_questions_count'] }}</span>
            @endif
            <div style="width: 60px; height: 60px; background: rgba(245, 158, 11, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 30px; height: 30px; color: var(--warning);">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                </svg>
            </div>
            <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--text-light); margin-bottom: 8px;">الأسئلة المعلقة</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem;">راجع وأجب على الأسئلة الجديدة</p>
        </div>
    </a>

    <a href="{{ route('customer.ai-helper.fast-replies.index') }}" class="card" style="text-decoration: none; cursor: pointer; transition: all 0.3s ease;">
        <div class="card-body" style="text-align: center; padding: 30px;">
            <div style="width: 60px; height: 60px; background: rgba(168, 85, 247, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 30px; height: 30px; color: #a855f7;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                </svg>
            </div>
            <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--text-light); margin-bottom: 8px;">الردود السريعة</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem;">إدارة الردود التلقائية</p>
        </div>
    </a>
</div>

<!-- Recent Unanswered Questions -->
@if($recentQuestions->count() > 0)
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header">
        <div class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px; display: inline-block; margin-left: 8px; vertical-align: middle;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
            </svg>
            أسئلة حديثة تحتاج مراجعة
        </div>
        <a href="{{ route('customer.ai-helper.unanswered.index') }}" style="color: var(--primary-green); text-decoration: none; font-size: 0.9rem; font-weight: 600;">
            عرض الكل ←
        </a>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>السؤال</th>
                        <th>الحالة</th>
                        <th>التصنيف</th>
                        <th>التاريخ</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentQuestions as $question)
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                @if($question->needs_urgent_attention)
                                    <span style="color: var(--danger); font-size: 1.2rem;">🔴</span>
                                @endif
                                <div>
                                    {{ Str::limit($question->question, 60) }}
                                    @if($question->occurrence_count > 1)
                                        <span class="badge badge-info" style="margin-right: 8px; font-size: 0.7rem;">سُئل {{ $question->occurrence_count }} مرات</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-warning">معلق</span>
                        </td>
                        <td>
                            @if($question->category)
                                <span class="badge" style="background: rgba(168, 85, 247, 0.1); color: #a855f7;">{{ $question->category }}</span>
                            @else
                                <span style="color: var(--text-muted);">-</span>
                            @endif
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.85rem;">
                            {{ $question->created_at->diffForHumans() }}
                        </td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('customer.ai-helper.unanswered.show', $question->id) }}" class="action-btn view">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<!-- Top Knowledge Base -->
@if($topKnowledge->count() > 0)
<div class="card">
    <div class="card-header">
        <div class="card-title">⭐ أكثر الأسئلة استخداماً</div>
        <a href="{{ route('customer.ai-helper.knowledge-base.index') }}" style="color: var(--primary-green); text-decoration: none; font-size: 0.9rem; font-weight: 600;">
            عرض الكل ←
        </a>
    </div>
    <div class="card-body">
        <div style="display: flex; flex-direction: column; gap: 15px;">
            @foreach($topKnowledge as $kb)
            <div style="background: rgba(255, 255, 255, 0.02); border-radius: 8px; padding: 15px; border: 1px solid var(--border-color); transition: all 0.3s ease;" onmouseover="this.style.borderColor='var(--primary-green)'" onmouseout="this.style.borderColor='var(--border-color)'">
                <div style="display: flex; align-items: start; gap: 12px; margin-bottom: 10px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="color: var(--primary-green); width: 1.2rem; height: 1.2rem; flex-shrink: 0; display: inline-block;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                    </svg>
                    <div style="flex: 1;">
                        <strong style="color: var(--text-light); font-size: 0.95rem; display: block; margin-bottom: 8px;">{{ $kb->question }}</strong>
                        <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.5;">{{ Str::limit($kb->answer, 100) }}</p>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 12px; padding-top: 10px; border-top: 1px solid var(--border-color);">
                    <span class="badge" style="background: rgba(37, 211, 102, 0.1); color: var(--primary-green);">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 14px; height: 14px;">
                            <path d="M10 9a3 3 0 100-6 3 3 0 000 6zM6 8a2 2 0 11-4 0 2 2 0 014 0zM1.49 15.326a.78.78 0 01-.358-.442 3 3 0 014.308-3.516 6.484 6.484 0 00-1.905 3.959c-.023.222-.014.442.025.654a4.97 4.97 0 01-2.07-.655zM16.44 15.98a4.97 4.97 0 002.07-.654.78.78 0 00.357-.442 3 3 0 00-4.308-3.517 6.484 6.484 0 011.907 3.96 2.32 2.32 0 01-.026.654zM18 8a2 2 0 11-4 0 2 2 0 014 0zM5.304 16.19a.844.844 0 01-.277-.71 5 5 0 019.947 0 .843.843 0 01-.277.71A6.975 6.975 0 0110 18a6.974 6.974 0 01-4.696-1.81z" />
                        </svg>
                        {{ $kb->usage_count }} استخدام
                    </span>
                    @if($kb->category)
                        <span class="badge" style="background: rgba(168, 85, 247, 0.1); color: #a855f7;">{{ $kb->category }}</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

@if($recentQuestions->count() == 0 && $topKnowledge->count() == 0)
<div class="card" style="text-align: center; padding: 60px 20px;">
    <div style="width: 80px; height: 80px; background: rgba(37, 211, 102, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 40px; height: 40px; color: var(--primary-green);">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
        </svg>
    </div>
    <h3 style="font-size: 1.3rem; font-weight: 600; color: var(--text-light); margin-bottom: 10px;">ابدأ بتحسين AI</h3>
    <p style="color: var(--text-muted); margin-bottom: 25px;">أضف أسئلة وأجوبة لتحسين أداء الذكاء الاصطناعي</p>
    <a href="{{ route('customer.ai-helper.knowledge-base.create') }}" class="btn btn-primary" style="display: inline-flex; max-width: 250px;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 20px; height: 20px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        إضافة سؤال جديد
    </a>
</div>
@endif
@endsection
