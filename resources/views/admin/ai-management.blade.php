@extends('layouts.admin')

@section('title', 'إدارة الذكاء الاصطناعي')

@section('content')
<div class="ai-container">
    <div class="page-header">
        <h1>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 32px; height: 32px; display: inline-block; vertical-align: middle; margin-left: 8px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            إدارة الذكاء الاصطناعي
        </h1>
        <p>التحكم الكامل في قاعدة المعرفة والردود السريعة - إضافة بيانات عامة لكل المتاجر أو خاصة بمتجر محدد</p>

        <div class="controls-bar">
            <form method="GET" action="{{ route('admin.ai-management') }}">
                <select name="merchant_id" onchange="this.form.submit()" class="filter-select">
                    <option value="">� جميع المتاجر + البيانات العامة</option>
                    @foreach($merchants as $merchant)
                        <option value="{{ $merchant->id }}" {{ $merchantId == $merchant->id ? 'selected' : '' }}>
                            � {{ $merchant->name }}
                        </option>
                    @endforeach
                </select>
            </form>

            <a href="{{ route('admin.ai-analytics') }}" class="btn-analytics">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px; display: inline-block; vertical-align: middle; margin-left: 6px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </svg>
                عرض الإحصائيات
            </a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-label">قاعدة المعرفة</div>
                    <div class="stat-value">{{ number_format($stats['total_kb']) }}</div>
                    <div class="stat-detail">
                        <span class="highlight">{{ number_format($stats['active_kb']) }}</span> نشط |
                        <span class="highlight">{{ number_format($stats['global_kb']) }}</span> عامة
                    </div>
                </div>
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 32px; height: 32px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="stat-card green">
            <div class="stat-header">
                <div>
                    <div class="stat-label">ردود سريعة</div>
                    <div class="stat-value">{{ number_format($stats['total_fr']) }}</div>
                    <div class="stat-detail">
                        <span class="highlight">{{ number_format($stats['active_fr']) }}</span> نشط |
                        <span class="highlight">{{ number_format($stats['global_fr']) }}</span> عامة
                    </div>
                </div>
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 32px; height: 32px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="stat-card orange">
            <div class="stat-header">
                <div>
                    <div class="stat-label">أسئلة معلقة</div>
                    <div class="stat-value">{{ number_format($stats['pending_questions']) }}</div>
                    <div class="stat-detail">
                        <span class="highlight">{{ number_format($stats['urgent_questions']) }}</span> عاجل
                    </div>
                </div>
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 32px; height: 32px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <div class="tabs-container">
        <div class="tabs-nav">
            <button class="tab-btn active" onclick="switchTab('knowledge-base')">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px; display: inline-block; vertical-align: middle; margin-left: 6px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                </svg>
                قاعدة المعرفة
            </button>
            <button class="tab-btn" onclick="switchTab('fast-replies')">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px; display: inline-block; vertical-align: middle; margin-left: 6px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                </svg>
                ردود سريعة
            </button>
            <button class="tab-btn" onclick="switchTab('unanswered')">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px; display: inline-block; vertical-align: middle; margin-left: 6px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                </svg>
                أسئلة غير مجابة
            </button>
        </div>

        <!-- Knowledge Base Tab -->
        <div id="tab-knowledge-base" class="tab-content active">
            <div class="section-header">
                <h3 class="section-title">قاعدة المعرفة</h3>
                <button onclick="openCreateKBModal()" class="btn-primary">
                    + إضافة معرفة جديدة
                </button>
            </div>

            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>السؤال</th>
                            <th>الإجابة</th>
                            <th style="text-align: center;">النطاق</th>
                            <th style="text-align: center;">الاستخدام</th>
                            <th style="text-align: center;">الحالة</th>
                            <th style="text-align: center;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($knowledgeBase as $kb)
                            <tr>
                                <td style="font-weight: 600; max-width: 300px;">{{ Str::limit($kb->question, 70) }}</td>
                                <td style="color: #6b7280; font-size: 13px; max-width: 350px;">{{ Str::limit($kb->answer, 90) }}</td>
                                <td style="text-align: center;">
                                    @if($kb->user_id)
                                        <span class="badge store">{{ $kb->user->name ?? 'متجر محدد' }}</span>
                                    @else
                                        <span class="badge global">� عامة</span>
                                    @endif
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge usage">{{ number_format($kb->usage_count) }}</span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge {{ $kb->status === 'active' ? 'active' : 'inactive' }}">
                                        {{ $kb->status === 'active' ? '✓ نشط' : '✗ غير نشط' }}
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                        <button onclick="editKB({{ $kb->id }}, {{ json_encode($kb->question) }}, {{ json_encode($kb->answer) }}, '{{ $kb->status }}', {{ $kb->priority }}, {{ $kb->user_id ?? 'null' }})" class="btn-edit">
                                            تعديل
                                        </button>
                                        <form method="POST" action="{{ route('admin.knowledge-base.delete', $kb) }}" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-delete">حذف</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 48px; height: 48px; opacity: 0.5;">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                                            </svg>
                                        </div>
                                        <div>لا توجد بيانات في قاعدة المعرفة</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 20px;">
                {{ $knowledgeBase->appends(['merchant_id' => $merchantId])->links() }}
            </div>
        </div>

        <!-- Fast Replies Tab -->
        <div id="tab-fast-replies" class="tab-content">
            <div class="section-header">
                <h3 class="section-title">الردود السريعة</h3>
                <button onclick="openCreateFRModal()" class="btn-primary">
                    + إضافة رد سريع
                </button>
            </div>

            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>نص الرد</th>
                            <th style="text-align: center;">النطاق</th>
                            <th style="text-align: center;">الاستخدام</th>
                            <th style="text-align: center;">الحالة</th>
                            <th style="text-align: center;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fastReplies as $fr)
                            <tr>
                                <td style="font-weight: 600;">{{ $fr->name }}</td>
                                <td style="color: #6b7280; font-size: 13px; max-width: 400px;">{{ Str::limit($fr->reply_text, 100) }}</td>
                                <td style="text-align: center;">
                                    @if($fr->user_id)
                                        <span class="badge store">{{ $fr->user->name ?? 'متجر محدد' }}</span>
                                    @else
                                        <span class="badge global">� عامة</span>
                                    @endif
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge usage">{{ number_format($fr->usage_count) }}</span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge {{ $fr->is_active ? 'active' : 'inactive' }}">
                                        {{ $fr->is_active ? '✓ نشط' : '✗ غير نشط' }}
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                        <button onclick="editFR({{ $fr->id }}, {{ json_encode($fr->name) }}, {{ json_encode($fr->reply_text) }}, {{ $fr->is_active ? 'true' : 'false' }}, {{ $fr->priority }}, {{ $fr->user_id ?? 'null' }})" class="btn-edit">
                                            تعديل
                                        </button>
                                        <form method="POST" action="{{ route('admin.fast-reply.delete', $fr) }}" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-delete">حذف</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 48px; height: 48px; opacity: 0.5;">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                                            </svg>
                                        </div>
                                        <div>لا توجد ردود سريعة</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 20px;">
                {{ $fastReplies->appends(['merchant_id' => $merchantId])->links() }}
            </div>
        </div>

        <!-- Unanswered Questions Tab -->
        <div id="tab-unanswered" class="tab-content">
            <div class="section-header">
                <h3 class="section-title">أسئلة بحاجة إلى إجابة</h3>
            </div>

            @forelse($unansweredQuestions as $question)
                <div class="question-card {{ $question->needs_urgent_attention ? 'urgent' : '' }}">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                <div class="question-title">{{ $question->question }}</div>
                                @if($question->needs_urgent_attention)
                                    <span class="badge inactive">عاجل</span>
                                @endif
                            </div>
                            <div class="question-meta">
                                <span>� {{ $question->user->name ?? 'غير محدد' }}</span>
                                <span>⟳ تكرر {{ $question->occurrence_count }} مرة</span>
                                <span>🕐 {{ $question->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                        <button onclick="answerQuestion({{ $question->id }}, {{ json_encode($question->question) }})" class="btn-answer">
                            الرد على السؤال
                        </button>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 48px; height: 48px; color: #10b981;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>رائع! لا توجد أسئلة غير مجابة</div>
                </div>
            @endforelse

            <div style="margin-top: 20px;">
                {{ $unansweredQuestions->appends(['merchant_id' => $merchantId])->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching functionality
    window.switchTab = function(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });

        // Remove active class from all buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Show selected tab
        const selectedTab = document.getElementById('tab-' + tabName);
        if (selectedTab) {
            selectedTab.classList.add('active');
        }

        // Activate selected button
        event.target.classList.add('active');
    };

    // Modal functions
    window.editKB = function(id, question, answer, status, priority, userId) {
        const scope = userId ? 'خاص بمتجر محدد' : 'عامة لكل المتاجر';
        alert('تعديل المعرفة #' + id + '\nالنطاق: ' + scope + '\n\nسيتم تطوير نموذج التعديل قريباً');
    };

    window.openCreateKBModal = function() {
        alert('إضافة معرفة جديدة\n\nملاحظة: إذا لم تحدد متجر من القائمة أعلاه، سيتم إضافتها كمعرفة عامة لكل المتاجر');
    };

    window.editFR = function(id, name, reply, isActive, priority, userId) {
        const scope = userId ? 'خاص بمتجر محدد' : 'عامة لكل المتاجر';
        alert('تعديل الرد السريع #' + id + '\nالنطاق: ' + scope + '\n\nسيتم تطوير نموذج التعديل قريباً');
    };

    window.openCreateFRModal = function() {
        alert('إضافة رد سريع جديد\n\nملاحظة: إذا لم تحدد متجر من القائمة أعلاه، سيتم إضافته كرد عام لكل المتاجر');
    };

    window.answerQuestion = function(id, question) {
        alert('الرد على السؤال #' + id + '\n\n' + question + '\n\nسيتم تطوير نموذج الرد قريباً');
    };
});
</script>
