@extends('layouts.customer')

@section('title', isset($entry) ? 'تعديل سؤال' : 'إضافة سؤال جديد')

@section('content')
<div class="dashboard-header">
    <div class="header-title">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 28px; height: 28px;">
            @if(isset($entry))
            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
            @else
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            @endif
        </svg>
        {{ isset($entry) ? 'تعديل سؤال' : 'إضافة سؤال جديد' }}
    </div>
    <div class="header-actions">
        <a href="{{ route('customer.ai-helper.knowledge-base.index') }}" class="btn btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 18px; height: 18px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
            </svg>
            رجوع
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ isset($entry) ? route('customer.ai-helper.knowledge-base.update', $entry->id) : route('customer.ai-helper.knowledge-base.store') }}">
            @csrf
            @if(isset($entry))
                @method('PUT')
            @endif

            <div style="display: grid; gap: 20px;">
                <!-- Question -->
                <div class="form-group">
                    <label class="form-label">
                        السؤال <span style="color: var(--danger);">*</span>
                    </label>
                    <textarea
                        name="question"
                        class="form-control @error('question') is-invalid @enderror"
                        rows="3"
                        placeholder="مثال: شنو سعر التوصيل؟"
                        required
                    >{{ old('question', isset($entry) ? $entry->question : '') }}</textarea>
                    @error('question')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small style="color: var(--text-muted); font-size: 0.85rem; margin-top: 5px; display: block;">
                        اكتب السؤال كما يسأله العميل عادةً
                    </small>
                </div>

                <!-- Answer -->
                <div class="form-group">
                    <label class="form-label">
                        الإجابة <span style="color: var(--danger);">*</span>
                    </label>
                    <textarea
                        name="answer"
                        class="form-control @error('answer') is-invalid @enderror"
                        rows="5"
                        placeholder="مثال: سعر التوصيل 5,000 دينار عراقي لجميع أنحاء العراق. التوصيل خلال 1-3 أيام"
                        required
                    >{{ old('answer', isset($entry) ? $entry->answer : '') }}</textarea>
                    @error('answer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small style="color: var(--text-muted); font-size: 0.85rem; margin-top: 5px; display: block;">
                        اكتب إجابة واضحة ومختصرة
                    </small>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                    <!-- Category -->
                    <div class="form-group">
                        <label class="form-label">التصنيف</label>
                        <select name="category" class="form-select">
                            <option value="">بدون تصنيف</option>
                            <option value="delivery" {{ old('category', isset($entry) ? $entry->category : '') == 'delivery' ? 'selected' : '' }}>توصيل</option>
                            <option value="payment" {{ old('category', isset($entry) ? $entry->category : '') == 'payment' ? 'selected' : '' }}>دفع</option>
                            <option value="products" {{ old('category', isset($entry) ? $entry->category : '') == 'products' ? 'selected' : '' }}>منتجات</option>
                            <option value="returns" {{ old('category', isset($entry) ? $entry->category : '') == 'returns' ? 'selected' : '' }}>استرجاع</option>
                            <option value="general" {{ old('category', isset($entry) ? $entry->category : '') == 'general' ? 'selected' : '' }}>عام</option>
                        </select>
                    </div>

                    <!-- Priority -->
                    <div class="form-group">
                        <label class="form-label">الأولوية (0-10)</label>
                        <input
                            type="number"
                            name="priority"
                            class="form-control"
                            min="0"
                            max="10"
                            value="{{ old('priority', isset($entry) ? $entry->priority : 5) }}"
                        >
                        <small style="color: var(--text-muted); font-size: 0.85rem; margin-top: 5px; display: block;">
                            أعلى أولوية = أكثر أهمية
                        </small>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label class="form-label">الحالة</label>
                        <select name="status" class="form-select">
                            <option value="active" {{ old('status', isset($entry) ? $entry->status : 'active') == 'active' ? 'selected' : '' }}>نشط</option>
                            <option value="inactive" {{ old('status', isset($entry) ? $entry->status : '') == 'inactive' ? 'selected' : '' }}>غير نشط</option>
                            <option value="draft" {{ old('status', isset($entry) ? $entry->status : '') == 'draft' ? 'selected' : '' }}>مسودة</option>
                        </select>
                    </div>
                </div>

                <!-- Options -->
                <div style="background: rgba(255, 255, 255, 0.02); border-radius: 8px; padding: 20px; border: 1px solid var(--border-color);">
                    <h4 style="font-size: 0.95rem; font-weight: 600; color: var(--text-light); margin-bottom: 15px;">خيارات إضافية</h4>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div class="form-check">
                            <input
                                type="checkbox"
                                name="use_for_training"
                                id="use_for_training"
                                class="form-check-input"
                                {{ old('use_for_training', isset($entry) ? $entry->use_for_training : true) ? 'checked' : '' }}
                            >
                            <label for="use_for_training" class="form-check-label">
                                استخدام في تدريب AI
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Buttons -->
                <div style="display: flex; gap: 10px; justify-content: flex-start; padding-top: 10px;">
                    <button type="submit" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ isset($entry) ? 'حفظ التعديلات' : 'إضافة السؤال' }}
                    </button>
                    <a href="{{ route('customer.ai-helper.knowledge-base.index') }}" class="btn btn-secondary">
                        إلغاء
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

@if(isset($entry))
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
                <p style="color: var(--primary-green); font-size: 1.5rem; font-weight: 700;">{{ $entry->usage_count }}</p>
            </div>
            <div style="background: rgba(59, 130, 246, 0.05); border-radius: 8px; padding: 15px; border: 1px solid rgba(59, 130, 246, 0.2);">
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 5px;">تاريخ الإضافة</p>
                <p style="color: var(--info); font-size: 1rem; font-weight: 600;">{{ $entry->created_at->format('Y/m/d') }}</p>
            </div>
            <div style="background: rgba(168, 85, 247, 0.05); border-radius: 8px; padding: 15px; border: 1px solid rgba(168, 85, 247, 0.2);">
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 5px;">آخر تحديث</p>
                <p style="color: #a855f7; font-size: 1rem; font-weight: 600;">{{ $entry->updated_at->diffForHumans() }}</p>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
