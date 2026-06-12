@extends('layouts.customer')

@section('title', 'الرد على سؤال')

@section('content')
<div class="dashboard-header">
    <div class="header-title">
        @if($question->needs_urgent_attention)
            <span style="font-size: 1.5rem;">🔴</span>
        @else
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 28px; height: 28px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
            </svg>
        @endif
        الرد على سؤال
    </div>
    <div class="header-actions">
        <a href="{{ route('customer.ai-helper.unanswered.index') }}" class="btn btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 18px; height: 18px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
            </svg>
            رجوع
        </a>
    </div>
</div>

<!--الأسئلة المشابهة في قاعدة المعرفة -->
@if($similarKb->count() > 0)
<div class="card" style="margin-bottom: 20px; border-color: rgba(37, 211, 102, 0.3);">
    <div class="card-header" style="background: rgba(37, 211, 102, 0.05); border-color: rgba(37, 211, 102, 0.3);">
        <div class="card-title" style="color: var(--primary-green); display: flex; align-items: center; gap: 8px;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
            </svg>
            أسئلة مشابهة في قاعدة المعرفة
        </div>
    </div>
    <div class="card-body">
        <div style="display: flex; flex-direction: column; gap: 12px;">
            @foreach($similarKb as $kb)
            <div style="background: rgba(255, 255, 255, 0.02); border-radius: 6px; padding: 12px; border: 1px solid var(--border-color);">
                <p style="color: var(--text-light); font-size: 0.9rem; margin-bottom: 6px;"><strong>Q: {{ $kb->question }}</strong></p>
                <p style="color: var(--text-muted); font-size: 0.85rem;">A: {{ $kb->answer }}</p>
            </div>
            @endforeach
        </div>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 12px; border-top: 1px solid var(--border-color); padding-top: 12px;">
            ⓘ يمكنك استخدام إحدى هذه الإجابات أو كتابة إجابة جديدة
        </p>
    </div>
</div>
@endif

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <!-- Main Question Card -->
    <div>
        <!-- Question Details -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <div class="card-title" style="display: flex; align-items: center; gap: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                    </svg>
                    السؤال
                </div>
                <div style="display: flex; gap: 8px;">
                    @if($question->occurrence_count > 1)
                    <span class="badge badge-info">سُئل {{ $question->occurrence_count }} مرات</span>
                    @endif
                    @if($question->needs_urgent_attention)
                    <span class="badge badge-danger">عاجل</span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <h3 style="font-size: 1.2rem; font-weight: 600; color: var(--text-light); line-height: 1.6; margin-bottom: 15px;">
                    {{ $question->question }}
                </h3>

                @if($question->context)
                <div style="background: rgba(255, 255, 255, 0.02); border-radius: 8px; padding: 15px; border-right: 3px solid var(--primary-green); margin-top: 15px;">
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 5px; font-weight: 600;">السياق:</p>
                    <p style="color: var(--text-light); font-size: 0.9rem; line-height: 1.6;">{{ $question->context }}</p>
                </div>
                @endif
            </div>
        </div>

        <!-- Answer Form -->
        <div class="card">
            <div class="card-header" style="background: rgba(37, 211, 102, 0.05);">
                <div class="card-title" style="color: var(--primary-green); display: flex; align-items: center; gap: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                    </svg>
                    إجابتك
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('customer.ai-helper.unanswered.answer', $question->id) }}">
                    @csrf

                    <div class="form-group">
                        <label class="form-label">الإجابة <span style="color: var(--danger);">*</span></label>
                        <textarea
                            name="answer"
                            class="form-control @error('answer') is-invalid @enderror"
                            rows="6"
                            placeholder="اكتب إجابة واضحة ومفيدة للعميل..."
                            required
                            style="font-size: 1rem; line-height: 1.6;"
                        >{{ old('answer', $question->admin_answer) }}</textarea>
                        @error('answer')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">التصنيف</label>
                        <select name="category" class="form-select">
                            <option value="">اختر تصنيف</option>
                            <option value="delivery" {{ old('category', $question->category) == 'delivery' ? 'selected' : '' }}>توصيل</option>
                            <option value="payment" {{ old('category', $question->category) == 'payment' ? 'selected' : '' }}>دفع</option>
                            <option value="products" {{ old('category', $question->category) == 'products' ? 'selected' : '' }}>منتجات</option>
                            <option value="returns" {{ old('category', $question->category) == 'returns' ? 'selected' : '' }}>استرجاع</option>
                            <option value="general" {{ old('category', $question->category) == 'general' ? 'selected' : '' }}>عام</option>
                        </select>
                    </div>

                    <div style="background: rgba(37, 211, 102, 0.05); border-radius: 8px; padding: 15px; margin-bottom: 20px; border: 1px solid rgba(37, 211, 102, 0.2);">
                        <h4 style="font-size: 0.9rem; font-weight: 600; color: var(--primary-green); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            خيارات
                        </h4>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    name="add_to_kb"
                                    id="add_to_kb"
                                    class="form-check-input"
                                    {{ old('add_to_kb', true) ? 'checked' : '' }}
                                >
                                <label for="add_to_kb" class="form-check-label">
                                    إضافة إلى قاعدة المعرفة (AI سيتعلم من هذه الإجابة)
                                </label>
                            </div>

                            <div class="form-group" style="margin: 0; margin-right: 30px;" id="priority-group">
                                <label class="form-label" style="font-size: 0.85rem;">الأولوية (0-10)</label>
                                <input
                                    type="number"
                                    name="priority"
                                    class="form-control"
                                    min="0"
                                    max="10"
                                    value="{{ old('priority', 5) }}"
                                    style="max-width: 150px;"
                                >
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 18px; height: 18px;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            حفظ الإجابة
                        </button>

                        <button type="button" class="btn btn-secondary" onclick="if(confirm('هل أنت متأكد من تجاهل هذا السؤال؟')) { document.getElementById('ignore-form').submit(); }">
                            تجاهل
                        </button>
                    </div>
                </form>

                <form id="ignore-form" method="POST" action="{{ route('customer.ai-helper.unanswered.ignore', $question->id) }}" style="display: none;">
                    @csrf
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        <!-- Customer Info -->
        @if($question->lead)
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <div class="card-title" style="display: flex; align-items: center; gap: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    معلومات العميل
                </div>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div>
                        <p style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 3px;">الاسم</p>
                        <p style="color: var(--text-light); font-weight: 600;">{{ $question->lead->name }}</p>
                    </div>
                    @if($question->lead->phone)
                    <div>
                        <p style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 3px;">الهاتف</p>
                        <p style="color: var(--text-light);">{{ $question->lead->phone }}</p>
                    </div>
                    @endif
                    @if($question->conversation)
                    <div>
                        <a href="{{ route('customer.inbox.show', $question->conversation_id) }}" class="btn btn-secondary" style="width: 100%; justify-content: center; font-size: 0.85rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                            </svg>
                            فتح المحادثة
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        <!-- Question Stats -->
        <div class="card">
            <div class="card-header">
                <div class="card-title" style="display: flex; align-items: center; gap: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                    </svg>
                    الإحصائيات
                </div>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div style="background: rgba(37, 211, 102, 0.05); border-radius: 6px; padding: 12px; border: 1px solid rgba(37, 211, 102, 0.2);">
                        <p style="color: var(--text-muted); font-size: 0.75rem; margin-bottom: 4px;">تاريخ السؤال</p>
                        <p style="color: var(--primary-green); font-weight: 600; font-size: 0.9rem;">{{ $question->created_at->format('Y/m/d H:i') }}</p>
                        <p style="color: var(--text-muted); font-size: 0.75rem; margin-top: 4px;">{{ $question->created_at->diffForHumans() }}</p>
                    </div>

                    @if($question->confidence_score)
                    <div style="background: rgba(59, 130, 246, 0.05); border-radius: 6px; padding: 12px; border: 1px solid rgba(59, 130, 246, 0.2);">
                        <p style="color: var(--text-muted); font-size: 0.75rem; margin-bottom: 4px;">ثقة AI</p>
                        <p style="color: var(--info); font-weight: 600; font-size: 0.9rem;">{{ number_format($question->confidence_score * 100, 1) }}%</p>
                    </div>
                    @endif

                    @if($question->detected_intent)
                    <div style="background: rgba(168, 85, 247, 0.05); border-radius: 6px; padding: 12px; border: 1px solid rgba(168, 85, 247, 0.2);">
                        <p style="color: var(--text-muted); font-size: 0.75rem; margin-bottom: 4px;">النية المكتشفة</p>
                        <p style="color: #a855f7; font-weight: 600; font-size: 0.9rem;">{{ $question->detected_intent }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle priority field based on "add_to_kb" checkbox
document.getElementById('add_to_kb').addEventListener('change', function() {
    document.getElementById('priority-group').style.display = this.checked ? 'block' : 'none';
});
</script>
@endsection
