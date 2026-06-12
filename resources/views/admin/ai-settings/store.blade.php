@extends('layouts.admin')

@section('title', 'إعدادات AI - ' . $store->store_name)

@section('content')
<style>
    .ai-settings-page { padding: 24px; }
    .page-header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
    .page-info h1 { font-size: 24px; font-weight: 700; margin: 0 0 4px 0; color: #fff; }
    .page-info p { font-size: 14px; color: #9ca3af; margin: 0; }
    .btn-back { display: flex; align-items: center; gap: 8px; padding: 10px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff; text-decoration: none; font-size: 14px; transition: all 0.2s; }
    .btn-back:hover { background: rgba(255,255,255,0.1); }
    .alert-success { display: flex; align-items: center; gap: 10px; padding: 14px 18px; background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.3); border-radius: 10px; color: #22c55e; margin-bottom: 24px; }

    /* Store Info Card */
    .store-info-card { background: rgba(30, 35, 40, 0.95); border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); padding: 24px; margin-bottom: 24px; display: flex; gap: 24px; align-items: center; }
    .store-avatar { width: 64px; height: 64px; border-radius: 12px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: 700; }
    .store-details h2 { font-size: 20px; font-weight: 600; margin: 0 0 4px 0; color: #fff; }
    .store-details p { font-size: 14px; color: #9ca3af; margin: 0; }
    .store-badges { display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
    .badge.active { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
    .badge.inactive { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
    .badge.provider { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }

    /* Usage Dashboard */
    .usage-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
    .usage-card { background: rgba(30, 35, 40, 0.95); border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); padding: 20px; }
    .usage-label { font-size: 12px; color: #9ca3af; display: block; }
    .usage-value { font-size: 24px; font-weight: 700; margin: 4px 0; color: #fff; display: block; }
    .usage-detail { font-size: 11px; color: #6b7280; display: block; }
    .usage-value.cost { color: #22c55e; }

    /* Settings Form */
    .settings-card { background: rgba(30, 35, 40, 0.95); border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 24px; }
    .card-header { padding: 18px 24px; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.02); border-radius: 16px 16px 0 0; }
    .card-header h3 { font-size: 15px; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 10px; color: #fff; }
    .card-body { padding: 24px; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; color: #e5e7eb; }
    .form-group select {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: 10px;
        font-size: 14px;
        background: rgba(255,255,255,0.05);
        color: #fff;
        cursor: pointer;
        transition: all 0.2s;
    }
    .form-group select:hover { border-color: rgba(255,255,255,0.25); }
    .form-group select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.2); }
    .form-group select option { background: #1f2937; color: #fff; }
    .form-group small { font-size: 12px; color: #9ca3af; display: block; margin-top: 6px; }

    .toggle-group { margin-bottom: 16px; }
    .toggle-label { display: flex; align-items: center; gap: 12px; cursor: pointer; font-size: 14px; color: #e5e7eb; flex-direction: row-reverse; justify-content: flex-end; }
    .toggle-label input { display: none; }
    .toggle-switch {
        position: relative;
        width: 52px;
        height: 28px;
        background: #374151;
        border-radius: 14px;
        transition: all 0.3s ease;
        flex-shrink: 0;
        border: 2px solid #4b5563;
    }
    .toggle-switch::after {
        content: '';
        position: absolute;
        top: 2px;
        left: 2px;
        width: 20px;
        height: 20px;
        background: #9ca3af;
        border-radius: 50%;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .toggle-label input:checked + .toggle-switch {
        background: #22c55e;
        border-color: #16a34a;
    }
    .toggle-label input:checked + .toggle-switch::after {
        transform: translateX(24px);
        background: #fff;
    }
    /* Status indicator text */
    .toggle-status {
        font-size: 12px;
        padding: 2px 8px;
        border-radius: 4px;
        margin-right: 8px;
    }
    .toggle-status.on { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
    .toggle-status.off { background: rgba(239, 68, 68, 0.2); color: #f87171; }

    .form-actions { display: flex; justify-content: flex-end; gap: 12px; }
    .btn-primary { padding: 12px 24px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; border-radius: 10px; font-size: 14px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59,130,246,0.4); }

    /* Assistant Mode Special Styling */
    .assistant-mode-box {
        margin-top: 10px;
        padding: 16px;
        background: linear-gradient(135deg, rgba(14, 165, 233, 0.1), rgba(59, 130, 246, 0.1));
        border: 1px solid rgba(14, 165, 233, 0.3);
        border-radius: 12px;
    }
    .assistant-mode-box .toggle-label span:last-child { color: #38bdf8; font-weight: 500; }
    .assistant-mode-box small { color: #7dd3fc !important; margin-top: 8px; padding-right: 56px; }

    /* Usage Breakdown */
    .breakdown-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
    .breakdown-item { display: flex; justify-content: space-between; padding: 12px 16px; background: rgba(255,255,255,0.03); border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); }
    .breakdown-type { font-size: 14px; color: #e5e7eb; }
    .breakdown-stats { text-align: left; }
    .breakdown-count { font-size: 14px; font-weight: 500; color: #fff; }
    .breakdown-cost { font-size: 12px; color: #22c55e; }

    @media (max-width: 900px) { .usage-grid { grid-template-columns: repeat(2, 1fr); } .breakdown-grid { grid-template-columns: 1fr; } }
    @media (max-width: 600px) { .usage-grid { grid-template-columns: 1fr; } .store-info-card { flex-direction: column; text-align: center; } }
</style>

<div class="ai-settings-page">
    <div class="page-header">
        <div class="page-info">
            <h1>إعدادات AI للمتجر</h1>
            <p>إدارة إعدادات الذكاء الاصطناعي لمتجر {{ $store->store_name }}</p>
        </div>
        <a href="{{ route('admin.ai-settings.index') }}" class="btn-back">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            العودة
        </a>
    </div>

    @if(session('success'))
        <div class="alert-success">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <!-- Store Info -->
    <div class="store-info-card">
        <div class="store-avatar">{{ mb_substr($store->store_name ?? $store->name, 0, 1) }}</div>
        <div class="store-details">
            <h2>{{ $store->store_name ?? $store->name }}</h2>
            <p>{{ $store->email }}</p>
            <div class="store-badges">
                @if($settings->ai_enabled ?? false)
                    <span class="badge active">AI مفعل</span>
                @else
                    <span class="badge inactive">AI متوقف</span>
                @endif
                <span class="badge provider">{{ ($settings->ai_provider ?? 'openai') === 'openai' ? 'OpenAI' : 'Groq' }}</span>
                <span class="badge provider">{{ $settings->openai_model ?? $settings->groq_model ?? 'gpt-5-nano' }}</span>
            </div>
        </div>
    </div>

    <!-- Usage Stats -->
    <div class="usage-grid">
        <div class="usage-card">
            <span class="usage-label">طلبات اليوم</span>
            <span class="usage-value">{{ number_format($usageStats['today']['total_requests'] ?? 0) }}</span>
            <span class="usage-detail">{{ number_format($usageStats['today']['total_tokens'] ?? 0) }} tokens</span>
        </div>
        <div class="usage-card">
            <span class="usage-label">طلبات الشهر</span>
            <span class="usage-value">{{ number_format($usageStats['month']['total_requests'] ?? 0) }}</span>
            <span class="usage-detail">{{ number_format($usageStats['month']['total_tokens'] ?? 0) }} tokens</span>
        </div>
        <div class="usage-card">
            <span class="usage-label">تكلفة اليوم</span>
            <span class="usage-value cost">${{ number_format($usageStats['today']['total_cost'] ?? 0, 4) }}</span>
        </div>
        <div class="usage-card">
            <span class="usage-label">تكلفة الشهر</span>
            <span class="usage-value cost">${{ number_format($usageStats['month']['total_cost'] ?? 0, 2) }}</span>
        </div>
    </div>

    <!-- Usage Breakdown -->
    <div class="settings-card">
        <div class="card-header">
            <h3>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                توزيع الاستخدام حسب النوع
            </h3>
        </div>
        <div class="card-body">
            <div class="breakdown-grid">
                @forelse($usageStats['by_type'] ?? [] as $type)
                    <div class="breakdown-item">
                        <span class="breakdown-type">
                            @switch($type['request_type'])
                                @case('chat') 💬 محادثة @break
                                @case('order') 🛒 طلب @break
                                @case('welcome') 👋 ترحيب @break
                                @case('confirm') ✅ تأكيد @break
                                @case('inquiry') ❓ استفسار @break
                                @default {{ $type['request_type'] }}
                            @endswitch
                        </span>
                        <div class="breakdown-stats">
                            <div class="breakdown-count">{{ number_format($type['requests']) }} طلب</div>
                            <div class="breakdown-cost">${{ number_format($type['cost'], 4) }}</div>
                        </div>
                    </div>
                @empty
                    <div style="grid-column: 1/-1; text-align: center; padding: 20px; color: var(--text-muted);">
                        لا توجد بيانات استخدام بعد
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Settings Form -->
    <form action="{{ route('admin.ai-settings.store.update', $store->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="settings-card">
            <div class="card-header">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/></svg>
                    إعدادات AI للمتجر
                </h3>
            </div>
            <div class="card-body">
                <div class="toggle-group">
                    <label class="toggle-label" onclick="updateToggleStatus(this)">
                        <input type="checkbox" name="ai_enabled" value="1" {{ old('ai_enabled', $settings->ai_enabled ?? false) ? 'checked' : '' }}>
                        <span class="toggle-switch"></span>
                        <span>تفعيل الذكاء الاصطناعي</span>
                        <span class="toggle-status {{ old('ai_enabled', $settings->ai_enabled ?? false) ? 'on' : 'off' }}">{{ old('ai_enabled', $settings->ai_enabled ?? false) ? 'مفعّل' : 'متوقف' }}</span>
                    </label>
                </div>

                <div class="toggle-group">
                    <label class="toggle-label" onclick="updateToggleStatus(this)">
                        <input type="checkbox" name="auto_reply_enabled" value="1" {{ old('auto_reply_enabled', $settings->auto_reply_enabled ?? false) ? 'checked' : '' }}>
                        <span class="toggle-switch"></span>
                        <span>تفعيل الرد التلقائي</span>
                        <span class="toggle-status {{ old('auto_reply_enabled', $settings->auto_reply_enabled ?? false) ? 'on' : 'off' }}">{{ old('auto_reply_enabled', $settings->auto_reply_enabled ?? false) ? 'مفعّل' : 'متوقف' }}</span>
                    </label>
                </div>

                <div class="form-group">
                    <label>مزود AI</label>
                    <select name="ai_provider" id="ai_provider" onchange="toggleAssistantMode()">
                        <option value="openai" {{ ($settings->ai_provider ?? 'openai') === 'openai' ? 'selected' : '' }}>OpenAI / ChatGPT</option>
                        <option value="groq" {{ ($settings->ai_provider ?? 'openai') === 'groq' ? 'selected' : '' }}>Groq</option>
                    </select>
                </div>

                <div class="form-group" id="assistant-mode-group" style="{{ ($settings->ai_provider ?? 'openai') !== 'openai' ? 'display:none;' : '' }}">
                    <div class="assistant-mode-box">
                        <label class="toggle-label" onclick="updateToggleStatus(this)">
                            <input type="checkbox" name="use_assistant_mode" value="1" {{ old('use_assistant_mode', $settings->use_assistant_mode ?? false) ? 'checked' : '' }}>
                            <span class="toggle-switch"></span>
                            <span>⚡ وضع المساعد الذكي (Assistant Mode)</span>
                            <span class="toggle-status {{ old('use_assistant_mode', $settings->use_assistant_mode ?? false) ? 'on' : 'off' }}">{{ old('use_assistant_mode', $settings->use_assistant_mode ?? false) ? 'مفعّل' : 'متوقف' }}</span>
                        </label>
                        <small>
                            يوفر ~40% من التوكنز مع ذاكرة محادثة تلقائية - يعمل فقط مع OpenAI
                        </small>
                    </div>
                </div>

                <div class="form-group">
                    <label>موديل OpenAI</label>
                    <select name="openai_model">
                        @foreach($allModels['openai'] ?? [] as $model)
                            <option value="{{ $model['id'] }}" {{ ($settings->openai_model ?? 'gpt-5-nano') === $model['id'] ? 'selected' : '' }}>
                                {{ $model['name'] }} @if($model['recommended'] ?? false) ⭐ @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>موديل Groq</label>
                    <select name="groq_model">
                        @foreach($allModels['groq'] ?? [] as $model)
                            <option value="{{ $model['id'] }}" {{ ($settings->groq_model ?? 'llama-3.3-70b-versatile') === $model['id'] ? 'selected' : '' }}>
                                {{ $model['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle; margin-left: 6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                حفظ التغييرات
            </button>
        </div>
    </form>
</div>

<script>
function toggleAssistantMode() {
    const provider = document.getElementById('ai_provider').value;
    const assistantGroup = document.getElementById('assistant-mode-group');
    if (provider === 'openai') {
        assistantGroup.style.display = 'block';
    } else {
        assistantGroup.style.display = 'none';
        // Uncheck when switching to Groq
        const checkbox = assistantGroup.querySelector('input[name="use_assistant_mode"]');
        if (checkbox) {
            checkbox.checked = false;
            updateToggleStatus(checkbox.closest('.toggle-label'));
        }
    }
}

function updateToggleStatus(label) {
    setTimeout(() => {
        const checkbox = label.querySelector('input[type="checkbox"]');
        const statusSpan = label.querySelector('.toggle-status');
        if (checkbox && statusSpan) {
            if (checkbox.checked) {
                statusSpan.textContent = 'مفعّل';
                statusSpan.className = 'toggle-status on';
            } else {
                statusSpan.textContent = 'متوقف';
                statusSpan.className = 'toggle-status off';
            }
        }
    }, 10);
}

// Initialize all toggles on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.toggle-label').forEach(label => {
        const checkbox = label.querySelector('input[type="checkbox"]');
        const statusSpan = label.querySelector('.toggle-status');
        if (checkbox && statusSpan) {
            if (checkbox.checked) {
                statusSpan.textContent = 'مفعّل';
                statusSpan.className = 'toggle-status on';
            } else {
                statusSpan.textContent = 'متوقف';
                statusSpan.className = 'toggle-status off';
            }
        }
    });
});
</script>
@endsection
