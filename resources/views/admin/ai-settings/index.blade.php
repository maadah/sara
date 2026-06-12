@extends('layouts.admin')

@section('title', 'إعدادات الذكاء الاصطناعي - المطور')

@section('content')
<style>
    .ai-settings-page { padding: 24px; }
    .page-header { margin-bottom: 24px; }
    .page-title { font-size: 24px; font-weight: 700; color: #fff; margin: 0 0 4px 0; }
    .page-subtitle { font-size: 14px; color: #9ca3af; margin: 0; }
    .alert-success { display: flex; align-items: center; gap: 10px; padding: 14px 18px; background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.3); border-radius: 10px; color: #22c55e; margin-bottom: 24px; }

    /* Usage Dashboard */
    .usage-dashboard { background: rgba(30, 35, 40, 0.95); border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 24px; }
    .usage-header { padding: 18px 24px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; }
    .usage-header h2 { font-size: 16px; font-weight: 600; color: #fff; margin: 0; display: flex; align-items: center; gap: 10px; }
    .usage-header h2 svg { color: #3b82f6; }
    .usage-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; padding: 24px; }
    .usage-card { background: rgba(255,255,255,0.03); border-radius: 12px; padding: 20px; border: 1px solid rgba(255,255,255,0.05); }
    .usage-card.wide { grid-column: span 2; }
    .usage-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
    .usage-icon.today { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
    .usage-icon.month { background: rgba(139, 92, 246, 0.15); color: #a78bfa; }
    .usage-icon.cost { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
    .usage-icon.tokens { background: rgba(249, 115, 22, 0.15); color: #fb923c; }
    .usage-label { font-size: 13px; color: #9ca3af; display: block; }
    .usage-value { font-size: 28px; font-weight: 700; color: #fff; display: block; margin: 4px 0; }
    .usage-detail { font-size: 12px; color: #6b7280; }
    .cost-highlight { color: #4ade80; font-weight: 600; }

    /* Stores Table */
    .stores-section { background: rgba(30, 35, 40, 0.95); border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 24px; }
    .stores-header { padding: 18px 24px; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .stores-header h2 { font-size: 16px; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 10px; color: #fff; }
    .stores-table { width: 100%; border-collapse: collapse; }
    .stores-table th, .stores-table td { padding: 14px 20px; text-align: right; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .stores-table th { font-size: 13px; font-weight: 600; color: #9ca3af; background: rgba(255,255,255,0.02); }
    .stores-table td { font-size: 14px; color: #e5e7eb; }
    .stores-table tr:hover { background: rgba(255,255,255,0.02); }
    .store-name { font-weight: 500; color: #fff; }
    .store-info { font-size: 12px; color: #6b7280; }
    .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
    .status-badge.active { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
    .status-badge.inactive { background: rgba(239, 68, 68, 0.15); color: #f87171; }
    .provider-badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }
    .provider-badge.openai { background: rgba(255, 255, 255, 0.1); color: #fff; }
    .provider-badge.groq { background: rgba(255, 107, 0, 0.15); color: #fb923c; }
    .usage-cell { text-align: center; }
    .usage-tokens { font-weight: 500; color: #e5e7eb; }
    .usage-cost { color: #4ade80; font-weight: 600; }
    .btn-view { padding: 6px 12px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer; text-decoration: none; transition: all 0.2s; }
    .btn-view:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59,130,246,0.3); }

    /* Settings Cards */
    .settings-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 24px; }
    .settings-card { background: rgba(30, 35, 40, 0.95); border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); overflow: hidden; }
    .settings-card.full-width { grid-column: 1 / -1; }
    .card-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 24px; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.02); }
    .card-header h2 { font-size: 15px; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 10px; color: #fff; }
    .card-header h2 svg { color: #60a5fa; }
    .card-body { padding: 24px; }

    /* Provider Selection */
    .provider-selection { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .provider-option { cursor: pointer; }
    .provider-option input { display: none; }
    .provider-card { display: flex; align-items: center; gap: 16px; padding: 20px; background: rgba(255,255,255,0.03); border: 2px solid rgba(255,255,255,0.1); border-radius: 12px; transition: all 0.2s; }
    .provider-option.selected .provider-card { border-color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
    .provider-logo { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
    .provider-logo.openai { background: rgba(255, 255, 255, 0.1); color: #fff; }
    .provider-logo.groq { background: rgba(255, 107, 0, 0.15); color: #fb923c; }
    .provider-info { display: flex; flex-direction: column; gap: 4px; }
    .provider-name { font-size: 16px; font-weight: 600; color: #fff; }
    .provider-desc { font-size: 12px; color: #9ca3af; }
    .provider-rec { display: inline-block; padding: 4px 8px; font-size: 10px; font-weight: 600; border-radius: 4px; background: rgba(34, 197, 94, 0.15); color: #4ade80; width: fit-content; }

    /* Form Elements */
    .form-group { margin-bottom: 20px; }
    .form-group:last-child { margin-bottom: 0; }
    .form-group label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; color: #e5e7eb; }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: 10px;
        font-size: 14px;
        background: rgba(255,255,255,0.05);
        color: #fff;
        transition: all 0.2s;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.2); }
    .form-group input::placeholder { color: #6b7280; }
    .form-group select option { background: #1f2937; color: #fff; }
    .form-group small { display: block; font-size: 12px; color: #9ca3af; margin-top: 6px; }
    .form-group small a { color: #60a5fa; }
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
    .input-with-button { display: flex; gap: 10px; }
    .input-with-button input { flex: 1; }
    .btn-test { display: flex; align-items: center; gap: 6px; padding: 12px 16px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; border-radius: 10px; font-size: 14px; cursor: pointer; white-space: nowrap; transition: all 0.2s; }
    .btn-test:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59,130,246,0.3); }
    .connection-status { margin-top: 8px; font-size: 13px; }
    .connection-status .success { color: #4ade80; }
    .connection-status .error { color: #f87171; }

    /* Toggle */
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
    .toggle-status {
        font-size: 12px;
        padding: 2px 8px;
        border-radius: 4px;
        margin-right: 8px;
    }
    .toggle-status.on { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
    .toggle-status.off { background: rgba(239, 68, 68, 0.2); color: #f87171; }

    /* Buttons */
    .form-actions { display: flex; justify-content: flex-end; gap: 12px; }
    .btn-primary { display: flex; align-items: center; gap: 8px; padding: 14px 28px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; border-radius: 12px; font-size: 15px; font-weight: 500; cursor: pointer; transition: all 0.2s; }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59,130,246,0.4); }
    .btn-secondary { display: flex; align-items: center; gap: 6px; padding: 8px 14px; background: transparent; color: #9ca3af; border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; font-size: 13px; cursor: pointer; transition: all 0.2s; }
    .btn-secondary:hover { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.25); }

    @media (max-width: 1200px) { .usage-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 900px) { .settings-grid, .provider-selection { grid-template-columns: 1fr; } .usage-grid { grid-template-columns: 1fr; } .usage-card.wide { grid-column: span 1; } }
</style>

<div class="ai-settings-page">
    <div class="page-header">
        <h1 class="page-title">إعدادات الذكاء الاصطناعي</h1>
        <p class="page-subtitle">إدارة إعدادات AI لجميع المتاجر ومراقبة الاستخدام والتكلفة</p>
    </div>

    @if(session('success'))
        <div class="alert-success">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <!-- Global Usage Dashboard -->
    <div class="usage-dashboard">
        <div class="usage-header">
            <h2>
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                إجمالي الاستخدام (جميع المتاجر)
            </h2>
        </div>
        <div class="usage-grid">
            <div class="usage-card">
                <div class="usage-icon today">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                </div>
                <span class="usage-label">طلبات اليوم</span>
                <span class="usage-value">{{ number_format($usageStats['today']['total_requests'] ?? 0) }}</span>
                <span class="usage-detail">{{ number_format($usageStats['today']['total_tokens'] ?? 0) }} tokens</span>
            </div>

            <div class="usage-card">
                <div class="usage-icon month">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
                </div>
                <span class="usage-label">طلبات الشهر</span>
                <span class="usage-value">{{ number_format($usageStats['month']['total_requests'] ?? 0) }}</span>
                <span class="usage-detail">{{ number_format($usageStats['month']['total_tokens'] ?? 0) }} tokens</span>
            </div>

            <div class="usage-card">
                <div class="usage-icon cost">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                <span class="usage-label">تكلفة اليوم</span>
                <span class="usage-value cost-highlight">${{ number_format($usageStats['today']['total_cost'] ?? 0, 4) }}</span>
            </div>

            <div class="usage-card">
                <div class="usage-icon tokens">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                <span class="usage-label">تكلفة الشهر</span>
                <span class="usage-value cost-highlight">${{ number_format($usageStats['month']['total_cost'] ?? 0, 2) }}</span>
            </div>
        </div>
    </div>

    <!-- Stores Usage Table -->
    <div class="stores-section">
        <div class="stores-header">
            <h2>
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/></svg>
                استخدام المتاجر
            </h2>
        </div>
        <table class="stores-table">
            <thead>
                <tr>
                    <th>المتجر</th>
                    <th>حالة AI</th>
                    <th>المزود</th>
                    <th>الموديل</th>
                    <th>الطلبات (شهري)</th>
                    <th>التوكنز</th>
                    <th>التكلفة</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stores as $store)
                    <tr>
                        <td>
                            <div class="store-name">{{ $store['store_name'] }}</div>
                            <div class="store-info">{{ $store['name'] }}</div>
                        </td>
                        <td>
                            @if($store['ai_enabled'])
                                <span class="status-badge active">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="6"/></svg>
                                    مفعل
                                </span>
                            @else
                                <span class="status-badge inactive">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="6"/></svg>
                                    متوقف
                                </span>
                            @endif
                        </td>
                        <td>
                            <span class="provider-badge {{ $store['ai_provider'] }}">
                                {{ $store['ai_provider'] === 'openai' ? 'OpenAI' : 'Groq' }}
                            </span>
                        </td>
                        <td>{{ $store['ai_model'] }}</td>
                        <td class="usage-cell">{{ number_format($store['requests']) }}</td>
                        <td class="usage-cell usage-tokens">{{ number_format($store['tokens']) }}</td>
                        <td class="usage-cell usage-cost">${{ number_format($store['cost'], 4) }}</td>
                        <td>
                            <a href="{{ route('admin.ai-settings.store', $store['id']) }}" class="btn-view">عرض</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                            لا توجد متاجر مسجلة
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Global AI Settings Form -->
    <form action="{{ route('admin.ai-settings.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="settings-grid">
            <!-- Provider Selection -->
            <div class="settings-card full-width">
                <div class="card-header">
                    <h2>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
                        مزود AI الافتراضي (للمتاجر الجديدة)
                    </h2>
                </div>
                <div class="card-body">
                    <div class="provider-selection">
                        <label class="provider-option {{ ($settings->ai_provider ?? 'openai') === 'openai' ? 'selected' : '' }}">
                            <input type="radio" name="ai_provider" value="openai" {{ ($settings->ai_provider ?? 'openai') === 'openai' ? 'checked' : '' }} onchange="switchProvider('openai')">
                            <div class="provider-card">
                                <div class="provider-logo openai">
                                    <svg viewBox="0 0 24 24" fill="currentColor" width="32" height="32"><path d="M22.2819 9.8211a5.9847 5.9847 0 0 0-.5157-4.9108 6.0462 6.0462 0 0 0-6.5098-2.9A6.0651 6.0651 0 0 0 4.9807 4.1818a5.9847 5.9847 0 0 0-3.9977 2.9 6.0462 6.0462 0 0 0 .7427 7.0966 5.98 5.98 0 0 0 .511 4.9107 6.051 6.051 0 0 0 6.5146 2.9001A5.9847 5.9847 0 0 0 13.2599 24a6.0557 6.0557 0 0 0 5.7718-4.2058 5.9894 5.9894 0 0 0 3.9977-2.9001 6.0557 6.0557 0 0 0-.7475-7.0729z"/></svg>
                                </div>
                                <div class="provider-info">
                                    <span class="provider-name">OpenAI / ChatGPT</span>
                                    <span class="provider-desc">GPT-5 Nano, GPT-4o, GPT-4o-mini</span>
                                    <span class="provider-rec">موصى به</span>
                                </div>
                            </div>
                        </label>

                        <label class="provider-option {{ ($settings->ai_provider ?? 'openai') === 'groq' ? 'selected' : '' }}">
                            <input type="radio" name="ai_provider" value="groq" {{ ($settings->ai_provider ?? 'openai') === 'groq' ? 'checked' : '' }} onchange="switchProvider('groq')">
                            <div class="provider-card">
                                <div class="provider-logo groq">
                                    <svg viewBox="0 0 24 24" fill="currentColor" width="32" height="32"><circle cx="12" cy="12" r="10"/></svg>
                                </div>
                                <div class="provider-info">
                                    <span class="provider-name">Groq</span>
                                    <span class="provider-desc">Llama 3.3, Mixtral, Gemma</span>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- OpenAI Settings -->
            <div class="settings-card" id="openai-settings" style="{{ ($settings->ai_provider ?? 'openai') !== 'openai' ? 'display:none;' : '' }}">
                <div class="card-header">
                    <h2>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        إعدادات OpenAI
                    </h2>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="openai_api_key">مفتاح OpenAI API</label>
                        <div class="input-with-button">
                            <input type="password" id="openai_api_key" name="openai_api_key" value="{{ old('openai_api_key', $settings->openai_api_key) }}" placeholder="sk-proj-xxxxxxxxxxxx">
                            <button type="button" class="btn-test" onclick="testConnection()">اختبار</button>
                        </div>
                        <div id="connection-status" class="connection-status"></div>
                        <small>احصل على مفتاح API من <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a></small>
                    </div>

                    <div class="form-group">
                        <label for="openai_model">موديل OpenAI</label>
                        <select id="openai_model" name="openai_model">
                            @foreach($allModels['openai'] ?? [] as $model)
                                <option value="{{ $model['id'] }}" {{ old('openai_model', $settings->openai_model ?? 'gpt-5-nano') == $model['id'] ? 'selected' : '' }}>
                                    {{ $model['name'] }} - {{ $model['description'] }}
                                    @if($model['recommended'] ?? false) ⭐ @endif
                                </option>
                            @endforeach
                        </select>
                        <small>GPT-5 Nano موصى به - أسرع وأرخص نموذج</small>
                    </div>
                </div>
            </div>

            <!-- Groq Settings -->
            <div class="settings-card" id="groq-settings" style="{{ ($settings->ai_provider ?? 'openai') !== 'groq' ? 'display:none;' : '' }}">
                <div class="card-header">
                    <h2>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        إعدادات Groq
                    </h2>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="groq_api_key">مفتاح Groq API</label>
                        <div class="input-with-button">
                            <input type="password" id="groq_api_key" name="groq_api_key" value="{{ old('groq_api_key', $settings->groq_api_key) }}" placeholder="gsk_xxxxxxxxxxxx">
                            <button type="button" class="btn-test" onclick="testConnection()">اختبار</button>
                        </div>
                        <div id="groq-connection-status" class="connection-status"></div>
                        <small>احصل على مفتاح API من <a href="https://console.groq.com/keys" target="_blank">console.groq.com</a></small>
                    </div>

                    <div class="form-group">
                        <label for="groq_model">موديل Groq</label>
                        <select id="groq_model" name="groq_model">
                            @foreach($allModels['groq'] ?? [] as $model)
                                <option value="{{ $model['id'] }}" {{ old('groq_model', $settings->groq_model ?? 'llama-3.3-70b-versatile') == $model['id'] ? 'selected' : '' }}>
                                    {{ $model['name'] }} - {{ $model['description'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Model Parameters -->
            <div class="settings-card full-width">
                <div class="card-header">
                    <h2>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/></svg>
                        معاملات الموديل الافتراضية
                    </h2>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="temperature">Temperature</label>
                            <input type="number" id="temperature" name="temperature" value="{{ old('temperature', $settings->temperature ?? 0.3) }}" min="0" max="2" step="0.1">
                            <small>0 = دقيق، 2 = إبداعي</small>
                        </div>
                        <div class="form-group">
                            <label for="top_p">Top P</label>
                            <input type="number" id="top_p" name="top_p" value="{{ old('top_p', $settings->top_p ?? 0.95) }}" min="0" max="1" step="0.05">
                        </div>
                        <div class="form-group">
                            <label for="top_k">Top K</label>
                            <input type="number" id="top_k" name="top_k" value="{{ old('top_k', $settings->top_k ?? 40) }}" min="1" max="100">
                        </div>
                        <div class="form-group">
                            <label for="max_output_tokens">Max Tokens</label>
                            <input type="number" id="max_output_tokens" name="max_output_tokens" value="{{ old('max_output_tokens', $settings->max_output_tokens ?? 500) }}" min="100" max="8192">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Default Features -->
            <div class="settings-card full-width">
                <div class="card-header">
                    <h2>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4"/><path d="m6.8 15-3.5 2"/><path d="m20.7 17-3.5-2"/><path d="M6.8 9 3.3 7"/><path d="m20.7 7-3.5 2"/><path d="m9 22 3-8 3 8"/><circle cx="12" cy="10" r="4"/></svg>
                        الميزات الافتراضية
                    </h2>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="toggle-group">
                            <label class="toggle-label">
                                <input type="checkbox" name="ai_enabled" value="1" {{ old('ai_enabled', $settings->ai_enabled ?? true) ? 'checked' : '' }}>
                                <span class="toggle-switch"></span>
                                <span>تفعيل AI افتراضياً</span>
                            </label>
                        </div>
                        <div class="toggle-group">
                            <label class="toggle-label">
                                <input type="checkbox" name="auto_reply_enabled" value="1" {{ old('auto_reply_enabled', $settings->auto_reply_enabled ?? true) ? 'checked' : '' }}>
                                <span class="toggle-switch"></span>
                                <span>الرد التلقائي</span>
                            </label>
                        </div>
                        <div class="toggle-group">
                            <label class="toggle-label">
                                <input type="checkbox" name="collect_customer_info" value="1" {{ old('collect_customer_info', $settings->collect_customer_info ?? true) ? 'checked' : '' }}>
                                <span class="toggle-switch"></span>
                                <span>جمع معلومات الزبائن</span>
                            </label>
                        </div>
                        <div class="toggle-group">
                            <label class="toggle-label">
                                <input type="checkbox" name="can_create_orders" value="1" {{ old('can_create_orders', $settings->can_create_orders ?? true) ? 'checked' : '' }}>
                                <span class="toggle-switch"></span>
                                <span>إنشاء الطلبات تلقائياً</span>
                            </label>
                        </div>
                        <div class="toggle-group">
                            <label class="toggle-label">
                                <input type="checkbox" name="strict_store_scope" value="1" {{ old('strict_store_scope', $settings->strict_store_scope ?? true) ? 'checked' : '' }}>
                                <span class="toggle-switch"></span>
                                <span>تقييد الردود بالمتجر فقط</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn-secondary" onclick="resetToDefault()">استعادة الافتراضي</button>
            <button type="submit" class="btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                حفظ الإعدادات
            </button>
        </div>
    </form>
</div>

<script>
function switchProvider(provider) {
    document.getElementById('openai-settings').style.display = provider === 'openai' ? '' : 'none';
    document.getElementById('groq-settings').style.display = provider === 'groq' ? '' : 'none';

    document.querySelectorAll('.provider-option').forEach(opt => opt.classList.remove('selected'));
    document.querySelector(`input[value="${provider}"]`).closest('.provider-option').classList.add('selected');
}

function testConnection() {
    const statusDiv = document.getElementById('connection-status');
    statusDiv.innerHTML = '<span style="color: #6b7280;">جاري الاختبار...</span>';

    fetch('{{ route("admin.ai-settings.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.innerHTML = '<span class="success">✓ ' + data.message + '</span>';
        } else {
            statusDiv.innerHTML = '<span class="error">✗ ' + data.message + '</span>';
        }
    })
    .catch(error => {
        statusDiv.innerHTML = '<span class="error">✗ خطأ في الاتصال</span>';
    });
}

function resetToDefault() {
    if (confirm('هل تريد استعادة الإعدادات الافتراضية؟')) {
        fetch('{{ route("admin.ai-settings.reset") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        }).then(() => location.reload());
    }
}
</script>
@endsection
