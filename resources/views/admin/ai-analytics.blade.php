@extends('layouts.admin')

@section('title', 'إحصائيات الذكاء الاصطناعي')

@section('content')
<!-- Page Header -->
<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 28px; height: 28px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
            </svg>
            إحصائيات الذكاء الاصطناعي
        </h2>
        <p style="color: var(--text-muted); font-size: 14px;">
            تحليل شامل لأداء نظام الذكاء الاصطناعي والردود التلقائية لجميع المتاجر
        </p>
    </div>

    <!-- Filter Form -->
    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
        <form method="GET" action="{{ route('admin.ai-analytics') }}" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <!-- Merchant Filter -->
            <select name="merchant_id" style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; min-width: 150px;">
                <option value="">كل المتاجر</option>
                @foreach($merchants as $merchant)
                    <option value="{{ $merchant->id }}" {{ $merchantId == $merchant->id ? 'selected' : '' }}>
                        {{ $merchant->name }}
                    </option>
                @endforeach
            </select>

            <!-- Date Range -->
            <input type="date" name="from_date" value="{{ $fromDate->format('Y-m-d') }}"
                   style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
            <span style="color: var(--text-muted);">إلى</span>
            <input type="date" name="to_date" value="{{ $toDate->format('Y-m-d') }}"
                   style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
            <button type="submit" style="padding: 8px 16px; background: var(--primary-color); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600;">
                تطبيق
            </button>
        </form>

        <!-- AI Management Button -->
        <a href="{{ route('admin.ai-management') }}" style="padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="16" height="16">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            إدارة AI
        </a>
    </div>
</div>

<!-- Main Stats Row -->
<div class="stats-row" style="margin-bottom: 24px;">
    <!-- Total Messages -->
    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">إجمالي الرسائل</div>
            <div class="stat-value">{{ number_format($stats['total_messages']) }}</div>
            <div class="stat-change positive">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                </svg>
                <span>{{ number_format($stats['incoming_messages']) }} رسالة واردة</span>
            </div>
        </div>
        <div class="stat-icon blue">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
            </svg>
        </div>
    </div>

    <!-- AI Generated Messages -->
    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">ردود الذكاء الاصطناعي</div>
            <div class="stat-value">{{ number_format($stats['ai_generated']) }}</div>
            <div class="stat-change {{ $stats['ai_efficiency_rate'] > 50 ? 'positive' : 'neutral' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                </svg>
                <span>{{ $stats['ai_efficiency_rate'] }}% من الردود</span>
            </div>
        </div>
        <div class="stat-icon purple" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
            </svg>
        </div>
    </div>

    <!-- Fast Replies (Cached) -->
    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">ردود محفوظة (كاش)</div>
            <div class="stat-value">{{ number_format($stats['fast_replies_used']) }}</div>
            <div class="stat-change positive">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                </svg>
                <span>{{ $stats['cached_response_rate'] }}% توفير</span>
            </div>
        </div>
        <div class="stat-icon green">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
            </svg>
        </div>
    </div>

    <!-- Manual Messages -->
    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">ردود يدوية</div>
            <div class="stat-value">{{ number_format($stats['manual_messages']) }}</div>
            <div class="stat-change neutral">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
                <span>{{ $stats['ai_efficiency_rate'] > 0 ? round(100 - $stats['ai_efficiency_rate'], 1) : 100 }}% من الردود</span>
            </div>
        </div>
        <div class="stat-icon orange">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
            </svg>
        </div>
    </div>
</div>

<!-- Secondary Stats Row -->
<div class="stats-row" style="margin-bottom: 24px;">
    <!-- Knowledge Base -->
    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">قاعدة المعرفة</div>
            <div class="stat-value">{{ number_format($stats['knowledge_base_hits']) }}</div>
            <div class="stat-change positive">
                <span>{{ $stats['total_knowledge_base'] }} سؤال محفوظ</span>
            </div>
        </div>
        <div class="stat-icon blue">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
            </svg>
        </div>
    </div>

    <!-- AI Sessions -->
    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">جلسات المحادثة</div>
            <div class="stat-value">{{ number_format($stats['total_sessions']) }}</div>
            <div class="stat-change positive">
                <span>{{ $stats['avg_messages_per_session'] }} رسالة/جلسة</span>
            </div>
        </div>
        <div class="stat-icon purple" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
            </svg>
        </div>
    </div>

    <!-- Response Time -->
    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">وقت الرد (AI)</div>
            <div class="stat-value">{{ $stats['avg_ai_response_time'] }}<small>ث</small></div>
            <div class="stat-change positive">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span>{{ $stats['avg_manual_response_time'] }}ث (يدوي)</span>
            </div>
        </div>
        <div class="stat-icon green">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </div>
    </div>

    <!-- Conversations -->
    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">محادثات AI</div>
            <div class="stat-value">{{ number_format($stats['ai_handled_conversations']) }}</div>
            <div class="stat-change positive">
                <span>من {{ number_format($stats['total_conversations']) }} محادثة</span>
            </div>
        </div>
        <div class="stat-icon orange">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 0 1 1.037-.443 48.282 48.282 0 0 0 5.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
            </svg>
        </div>
    </div>
</div>

<!-- Additional Stats Row (New Metrics) -->
<div class="stats-row" style="margin-bottom: 24px;">
    <!-- Unanswered Questions -->
    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">أسئلة غير مجابة</div>
            <div class="stat-value">{{ number_format($stats['unanswered_questions']) }}</div>
            <div class="stat-change {{ $stats['pending_unanswered'] > 0 ? 'negative' : 'neutral' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                </svg>
                <span>{{ number_format($stats['pending_unanswered']) }} قيد المراجعة</span>
            </div>
        </div>
        <div class="stat-icon" style="background: linear-gradient(135deg, #f6ad55 0%, #fc8181 100%);">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
            </svg>
        </div>
    </div>

    <!-- AI Success Rate -->
    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">معدل النجاح</div>
            <div class="stat-value">{{ $stats['ai_success_rate'] }}%</div>
            <div class="stat-change {{ $stats['ai_success_rate'] > 80 ? 'positive' : 'neutral' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span>معدل نجاح AI</span>
            </div>
        </div>
        <div class="stat-icon green">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </div>
    </div>

    <!-- Merchants Using AI -->
    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">متاجر نشطة</div>
            <div class="stat-value">{{ number_format($stats['merchants_with_ai']) }}</div>
            <div class="stat-change positive">
                <span>من {{ number_format($stats['total_merchants']) }} متجر</span>
            </div>
        </div>
        <div class="stat-icon blue">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72L4.318 3.44A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72m-13.5 8.65h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .415.336.75.75.75Z" />
            </svg>
        </div>
    </div>

    <!-- Cost Savings -->
    <div class="stat-card-new">
        <div class="stat-info">
            <div class="stat-label">التوفير المقدر</div>
            <div class="stat-value">${{ number_format($stats['estimated_cost_savings'], 2) }}</div>
            <div class="stat-change positive">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                </svg>
                <span>توفير في التكاليف</span>
            </div>
        </div>
        <div class="stat-icon" style="background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="dashboard-row">
    <!-- Daily Breakdown Chart -->
    <div class="chart-card" style="flex: 2;">
        <div class="chart-header">
            <h3>توزيع الرسائل اليومية</h3>
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="chart-legend">
                    <span class="dot" style="background: #667eea;"></span>
                    <span>ردود AI</span>
                </div>
                <div class="chart-legend">
                    <span class="dot" style="background: #f6ad55;"></span>
                    <span>ردود يدوية</span>
                </div>
            </div>
        </div>
        <div class="chart-container" style="height: 280px; display: flex; align-items: flex-end; gap: 12px; padding: 20px;">
            @php
                $maxValue = collect($dailyBreakdown)->max('total') ?: 1;
            @endphp
            @foreach($dailyBreakdown as $day)
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                    <div style="width: 100%; display: flex; gap: 4px; align-items: flex-end; justify-content: center;">
                        <div style="width: 45%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 4px; height: {{ $maxValue > 0 ? max(20, ($day['ai_messages'] / $maxValue) * 220) : 20 }}px;"
                             title="AI: {{ number_format($day['ai_messages']) }}"></div>
                        <div style="width: 45%; background: linear-gradient(135deg, #f6ad55 0%, #fc8181 100%); border-radius: 4px; height: {{ $maxValue > 0 ? max(20, ($day['manual_messages'] / $maxValue) * 220) : 20 }}px;"
                             title="يدوي: {{ number_format($day['manual_messages']) }}"></div>
                    </div>
                    <span style="font-size: 11px; color: var(--text-muted); text-align: center;">{{ $day['day'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Top Fast Replies -->
    <div class="visitors-card" style="flex: 1;">
        <h3>أكثر الردود استخداماً</h3>
        <p class="subtitle">الردود السريعة الأعلى تكراراً</p>

        <div style="margin-top: 24px; display: flex; flex-direction: column; gap: 12px;">
            @forelse($topFastReplies as $reply)
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--background-light); border-radius: 8px; border-left: 3px solid {{ ['#667eea', '#48bb78', '#ed8936', '#e53e3e', '#9f7aea'][$loop->index % 5] }};">
                    <div style="flex: 1;">
                        <div style="font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">
                            {{ $reply->name }}
                        </div>
                        <div style="font-size: 11px; color: var(--text-muted);">
                            {{ Str::limit($reply->reply_text, 60) }}
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 4px; padding: 4px 8px; background: white; border-radius: 6px; font-size: 13px; font-weight: 700; color: {{ ['#667eea', '#48bb78', '#ed8936', '#e53e3e', '#9f7aea'][$loop->index % 5] }};">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                        </svg>
                        {{ number_format($reply->usage_count) }}
                    </div>
                </div>
            @empty
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="48" height="48" style="margin: 0 auto 12px; opacity: 0.3;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
                    </svg>
                    <p style="font-size: 14px;">لا توجد ردود سريعة مستخدمة</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Top Merchants & Knowledge Base Row -->
<div class="dashboard-row" style="margin-top: 24px;">
    <!-- Top Merchants -->
    <div class="visitors-card" style="flex: 1;">
        <h3>أكثر المتاجر نشاطاً</h3>
        <p class="subtitle">المتاجر الأكثر استخداماً لـ AI</p>

        <div style="margin-top: 24px; display: flex; flex-direction: column; gap: 12px;">
            @forelse($topMerchants as $merchant)
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--background-light); border-radius: 8px; border-left: 3px solid {{ ['#667eea', '#48bb78', '#ed8936', '#e53e3e', '#9f7aea'][$loop->index % 5] }};">
                    <div style="flex: 1;">
                        <div style="font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">
                            {{ $merchant->name }}
                        </div>
                        <div style="font-size: 11px; color: var(--text-muted);">
                            {{ $merchant->email }}
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 4px; padding: 4px 8px; background: white; border-radius: 6px; font-size: 13px; font-weight: 700; color: {{ ['#667eea', '#48bb78', '#ed8936', '#e53e3e', '#9f7aea'][$loop->index % 5] }};">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                        </svg>
                        {{ number_format($merchant->conversations_count) }}
                    </div>
                </div>
            @empty
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <p style="font-size: 14px;">لا توجد بيانات متاحة</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Top Knowledge Base -->
    <div class="visitors-card" style="flex: 1;">
        <h3>أكثر المعارف استخداماً</h3>
        <p class="subtitle">قاعدة المعرفة الأكثر طلباً</p>

        <div style="margin-top: 24px; display: flex; flex-direction: column; gap: 12px;">
            @forelse($topKnowledgeBase as $kb)
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--background-light); border-radius: 8px; border-left: 3px solid {{ ['#667eea', '#48bb78', '#ed8936', '#e53e3e', '#9f7aea'][$loop->index % 5] }};">
                    <div style="flex: 1;">
                        <div style="font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">
                            {{ Str::limit($kb->question, 50) }}
                        </div>
                        <div style="font-size: 11px; color: var(--text-muted);">
                            {{ Str::limit($kb->answer, 60) }}
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 4px; padding: 4px 8px; background: white; border-radius: 6px; font-size: 13px; font-weight: 700; color: {{ ['#667eea', '#48bb78', '#ed8936', '#e53e3e', '#9f7aea'][$loop->index % 5] }};">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                        {{ number_format($kb->usage_count) }}
                    </div>
                </div>
            @empty
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <p style="font-size: 14px;">لا توجد معارف مستخدمة</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Performance Insights -->
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; padding: 24px; margin-top: 24px; color: white;">
    <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
        </svg>
        تحليل الأداء
    </h3>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
        <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 16px;">
            <div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px;">كفاءة الذكاء الاصطناعي</div>
            <div style="font-size: 32px; font-weight: 700;">{{ $stats['ai_efficiency_rate'] }}%</div>
            <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">من الردود تلقائية</div>
        </div>

        <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 16px;">
            <div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px;">توفير التكلفة</div>
            <div style="font-size: 32px; font-weight: 700;">{{ $stats['cached_response_rate'] }}%</div>
            <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">ردود من الكاش</div>
        </div>

        <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 16px;">
            <div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px;">سرعة الاستجابة</div>
            <div style="font-size: 32px; font-weight: 700;">{{ $stats['avg_ai_response_time'] }}ث</div>
            <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">متوسط وقت الرد</div>
        </div>

        <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 16px;">
            <div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px;">إجمالي التوفير</div>
            <div style="font-size: 32px; font-weight: 700;">{{ number_format($stats['ai_generated'] + $stats['fast_replies_used']) }}</div>
            <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">رسالة تلقائية</div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    .chart-container {
        padding: 20px;
    }

    .chart-legend {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: var(--text-secondary);
    }

    .chart-legend .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--primary-color);
    }

    @media (max-width: 768px) {
        .stats-row {
            grid-template-columns: 1fr !important;
        }

        .dashboard-row {
            flex-direction: column !important;
        }

        .chart-card, .visitors-card {
            flex: 1 !important;
        }
    }
</style>
@endpush
