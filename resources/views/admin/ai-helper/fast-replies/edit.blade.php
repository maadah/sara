@extends('layouts.customer')

@section('title', isset($reply) ? 'تعديل رد سريع' : 'إضافة رد سريع')

@section('content')
<div class="dashboard-header">
    <div class="header-title">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 28px; height: 28px;">
            @if(isset($reply))
            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
            @else
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            @endif
        </svg>
        {{ isset($reply) ? 'تعديل رد سريع' : 'إضافة رد سريع' }}
    </div>
    <div class="header-actions">
        <a href="{{ route('customer.ai-helper.fast-replies.index') }}" class="btn btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 18px; height: 18px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
            </svg>
            رجوع
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ isset($reply) ? route('customer.ai-helper.fast-replies.update', $reply->id) : route('customer.ai-helper.fast-replies.store') }}" id="fast-reply-form">
            @csrf
            @if(isset($reply))
                @method('PUT')
            @endif

            <div style="display: grid; gap: 20px;">
                <!-- Category -->
                <div class="form-group">
                    <label class="form-label">
                        التصنيف <span style="color: var(--danger);">*</span>
                    </label>
                    <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                        <option value="">اختر تصنيف</option>
                        <option value="welcome" {{ old('category', isset($reply) ? $reply->category : '') == 'welcome' ? 'selected' : '' }}>ترحيب</option>
                        <option value="goodbye" {{ old('category', isset($reply) ? $reply->category : '') == 'goodbye' ? 'selected' : '' }}>وداع</option>
                        <option value="thanks" {{ old('category', isset($reply) ? $reply->category : '') == 'thanks' ? 'selected' : '' }}>شكر</option>
                        <option value="delivery" {{ old('category', isset($reply) ? $reply->category : '') == 'delivery' ? 'selected' : '' }}>توصيل</option>
                        <option value="pricing" {{ old('category', isset($reply) ? $reply->category : '') == 'pricing' ? 'selected' : '' }}>أسعار</option>
                        <option value="availability" {{ old('category', isset($reply) ? $reply->category : '') == 'availability' ? 'selected' : '' }}>توفر</option>
                        <option value="payment" {{ old('category', isset($reply) ? $reply->category : '') == 'payment' ? 'selected' : '' }}>طرق الدفع</option>
                        <option value="support" {{ old('category', isset($reply) ? $reply->category : '') == 'support' ? 'selected' : '' }}>دعم فني</option>
                        <option value="orders" {{ old('category', isset($reply) ? $reply->category : '') == 'orders' ? 'selected' : '' }}>الطلبات</option>
                        <option value="custom" {{ old('category', isset($reply) ? $reply->category : '') == 'custom' ? 'selected' : '' }}>مخصص</option>
                    </select>
                    @error('category')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small style="color: var(--text-muted); font-size: 0.85rem; margin-top: 5px; display: block;">
                        اختر التصنيف المناسب للرد السريع
                    </small>
                </div>

                <!-- Reply Text -->
                <div class="form-group">
                    <label class="form-label">
                        نص الرد <span style="color: var(--danger);">*</span>
                    </label>
                    <textarea
                        name="reply"
                        class="form-control @error('reply') is-invalid @enderror"
                        rows="5"
                        placeholder="مثال: هلا وغلا! 👋 أهلاً بيك، شنو اقدر أساعدك به اليوم؟ 😊"
                        required
                    >{{ old('reply', isset($reply) ? $reply->reply : '') }}</textarea>
                    @error('reply')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small style="color: var(--text-muted); font-size: 0.85rem; margin-top: 5px; display: block;">
                        يمكنك استخدام الإيموجي لجعل الرد أكثر ودية 😊
                    </small>
                </div>

                <!-- Trigger Keywords -->
                <div class="form-group">
                    <label class="form-label">
                        الكلمات المفتاحية (اختياري)
                    </label>
                    <div id="keywords-container" style="margin-bottom: 10px;">
                        @if(isset($reply) && is_array($reply->trigger_keywords))
                            @foreach($reply->trigger_keywords as $index => $keyword)
                            <div class="keyword-item" style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <input
                                    type="text"
                                    name="trigger_keywords[]"
                                    class="form-control"
                                    value="{{ $keyword }}"
                                    placeholder="كلمة مفتاحية"
                                >
                                <button type="button" class="btn btn-danger" style="padding: 10px 20px;" onclick="removeKeyword(this)">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            @endforeach
                        @else
                            <div class="keyword-item" style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <input
                                    type="text"
                                    name="trigger_keywords[]"
                                    class="form-control"
                                    placeholder="كلمة مفتاحية"
                                >
                                <button type="button" class="btn btn-danger" style="padding: 10px 20px;" onclick="removeKeyword(this)">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        @endif
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="addKeyword()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        إضافة كلمة مفتاحية
                    </button>
                    <small style="color: var(--text-muted); font-size: 0.85rem; margin-top: 5px; display: block;">
                        مثال: "مرحبا"، "هلا"، "السلام عليكم" - عندما يكتب العميل هذه الكلمات، سيظهر هذا الرد
                    </small>
                </div>

                <!-- Priority -->
                <div class="form-group">
                    <label class="form-label">الأولوية (1-100)</label>
                    <input
                        type="number"
                        name="priority"
                        class="form-control"
                        min="1"
                        max="100"
                        value="{{ old('priority', isset($reply) ? $reply->priority : 5) }}"
                    >
                    <small style="color: var(--text-muted); font-size: 0.85rem; margin-top: 5px; display: block;">
                        أعلى أولوية = يظهر أولاً (الافتراضي: 5)
                    </small>
                </div>

                <!-- Buttons -->
                <div style="display: flex; gap: 10px; justify-content: flex-start; padding-top: 10px;">
                    <button type="submit" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ isset($reply) ? 'حفظ التعديلات' : 'إضافة الرد' }}
                    </button>
                    <a href="{{ route('customer.ai-helper.fast-replies.index') }}" class="btn btn-secondary">
                        إلغاء
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

@if(isset($reply))
<div class="card" style="margin-top: 20px; border-color: rgba(245, 158, 11, 0.3);">
    <div class="card-header" style="border-color: rgba(245, 158, 11, 0.3);">
        <div class="card-title" style="color: var(--warning);">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px; display: inline-block; margin-left: 8px; vertical-align: middle;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
            </svg>
            إحصائيات الاستخدام
        </div>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div style="background: rgba(37, 211, 102, 0.05); border-radius: 8px; padding: 15px; border: 1px solid rgba(37, 211, 102, 0.2);">
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 5px;">عدد مرات الاستخدام</p>
                <p style="color: var(--primary-green); font-size: 1.5rem; font-weight: 700;">{{ $reply->usage_count }}</p>
            </div>
            <div style="background: rgba(59, 130, 246, 0.05); border-radius: 8px; padding: 15px; border: 1px solid rgba(59, 130, 246, 0.2);">
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 5px;">تاريخ الإضافة</p>
                <p style="color: var(--info); font-size: 1rem; font-weight: 600;">{{ $reply->created_at->format('Y/m/d') }}</p>
            </div>
        </div>
    </div>
</div>
@endif

<script>
function addKeyword() {
    const container = document.getElementById('keywords-container');
    const div = document.createElement('div');
    div.className = 'keyword-item';
    div.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px;';
    div.innerHTML = `
        <input
            type="text"
            name="trigger_keywords[]"
            class="form-control"
            placeholder="كلمة مفتاحية"
        >
        <button type="button" class="btn btn-danger" style="padding: 10px 20px;" onclick="removeKeyword(this)">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    `;
    container.appendChild(div);
}

function removeKeyword(button) {
    const container = document.getElementById('keywords-container');
    if (container.children.length > 1) {
        button.parentElement.remove();
    } else {
        alert('يجب أن يكون هناك حقل واحد على الأقل');
    }
}
</script>
@endsection
