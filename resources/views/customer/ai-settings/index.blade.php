@extends('layouts.customer')

@section('title', 'إعدادات الذكاء الاصطناعي')

@section('content')
<div class="page-container">
    <!-- Header -->
    <div class="page-header-bar">
        <div>
            <h1 class="page-title">إعدادات الذكاء الاصطناعي</h1>
            <p class="page-subtitle">إدارة إعدادات الشات الآلي والردود التلقائية</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert-success">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <!-- Usage Dashboard -->
    <div class="usage-dashboard">
        <div class="usage-header">
            <h2>
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                استخدام الذكاء الاصطناعي والتكلفة
            </h2>
        </div>
        <div class="usage-grid">
            <div class="usage-card">
                <div class="usage-icon today">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                </div>
                <div class="usage-info">
                    <span class="usage-label">اليوم</span>
                    <span class="usage-value">{{ number_format($usageStats['today']['total_requests'] ?? 0) }}</span>
                    <span class="usage-detail">طلب</span>
                </div>
                <div class="usage-cost">
                    <span class="cost-value">${{ number_format($usageStats['today']['total_cost'] ?? 0, 4) }}</span>
                    <span class="tokens-value">{{ number_format($usageStats['today']['total_tokens'] ?? 0) }} tokens</span>
                </div>
            </div>

            <div class="usage-card">
                <div class="usage-icon month">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
                </div>
                <div class="usage-info">
                    <span class="usage-label">هذا الشهر</span>
                    <span class="usage-value">{{ number_format($usageStats['month']['total_requests'] ?? 0) }}</span>
                    <span class="usage-detail">طلب</span>
                </div>
                <div class="usage-cost">
                    <span class="cost-value">${{ number_format($usageStats['month']['total_cost'] ?? 0, 2) }}</span>
                    <span class="tokens-value">{{ number_format($usageStats['month']['total_tokens'] ?? 0) }} tokens</span>
                </div>
            </div>

            <div class="usage-card wide">
                <div class="usage-breakdown">
                    <h4>توزيع الاستخدام حسب النوع</h4>
                    <div class="breakdown-list">
                        @forelse($usageStats['by_type'] ?? [] as $type)
                            <div class="breakdown-item">
                                <span class="type-name">
                                    @switch($type['request_type'])
                                        @case('chat') محادثة @break
                                        @case('order') طلب @break
                                        @case('welcome') ترحيب @break
                                        @case('confirm') تأكيد @break
                                        @case('inquiry') استفسار @break
                                        @default {{ $type['request_type'] }}
                                    @endswitch
                                </span>
                                <span class="type-count">{{ number_format($type['requests']) }} طلب</span>
                                <span class="type-cost">${{ number_format($type['cost'], 4) }}</span>
                            </div>
                        @empty
                            <div class="no-data">لا توجد بيانات استخدام بعد</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form action="{{ route('customer.ai-settings.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="settings-grid">
            <!-- Provider Selection -->
            <div class="settings-card full-width">
                <div class="card-header">
                    <h2>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
                        مزود الذكاء الاصطناعي
                    </h2>
                </div>
                <div class="card-body">
                    <div class="provider-selection">
                        <label class="provider-option {{ ($settings->ai_provider ?? 'openai') === 'openai' ? 'selected' : '' }}">
                            <input type="radio" name="ai_provider" value="openai" {{ ($settings->ai_provider ?? 'openai') === 'openai' ? 'checked' : '' }} onchange="switchProvider('openai')">
                            <div class="provider-card">
                                <div class="provider-logo openai">
                                    <svg viewBox="0 0 24 24" fill="currentColor" width="32" height="32"><path d="M22.2819 9.8211a5.9847 5.9847 0 0 0-.5157-4.9108 6.0462 6.0462 0 0 0-6.5098-2.9A6.0651 6.0651 0 0 0 4.9807 4.1818a5.9847 5.9847 0 0 0-3.9977 2.9 6.0462 6.0462 0 0 0 .7427 7.0966 5.98 5.98 0 0 0 .511 4.9107 6.051 6.051 0 0 0 6.5146 2.9001A5.9847 5.9847 0 0 0 13.2599 24a6.0557 6.0557 0 0 0 5.7718-4.2058 5.9894 5.9894 0 0 0 3.9977-2.9001 6.0557 6.0557 0 0 0-.7475-7.0729zm-9.022 12.6081a4.4755 4.4755 0 0 1-2.8764-1.0408l.1419-.0804 4.7783-2.7582a.7948.7948 0 0 0 .3927-.6813v-6.7369l2.02 1.1686a.071.071 0 0 1 .038.052v5.5826a4.504 4.504 0 0 1-4.4945 4.4944zm-9.6607-4.1254a4.4708 4.4708 0 0 1-.5346-3.0137l.142.0852 4.783 2.7582a.7712.7712 0 0 0 .7806 0l5.8428-3.3685v2.3324a.0804.0804 0 0 1-.0332.0615L9.74 19.9502a4.4992 4.4992 0 0 1-6.1408-1.6464zM2.3408 7.8956a4.485 4.485 0 0 1 2.3655-1.9728V11.6a.7664.7664 0 0 0 .3879.6765l5.8144 3.3543-2.0201 1.1685a.0757.0757 0 0 1-.071 0l-4.8303-2.7865A4.504 4.504 0 0 1 2.3408 7.8956zm16.5963 3.8558L13.1038 8.364l2.0201-1.1638a.0757.0757 0 0 1 .071 0l4.8303 2.7913a4.4944 4.4944 0 0 1-.6765 8.1042v-5.6772a.79.79 0 0 0-.4006-.6814zm2.0107-3.0231l-.142-.0852-4.7735-2.7818a.7759.7759 0 0 0-.7854 0L9.409 9.2297V6.8974a.0662.0662 0 0 1 .0284-.0615l4.8303-2.7866a4.4992 4.4992 0 0 1 6.6802 4.66zM8.3065 12.863l-2.02-1.1638a.0804.0804 0 0 1-.038-.0567V6.0742a4.4992 4.4992 0 0 1 7.3757-3.4537l-.142.0805L8.704 5.459a.7948.7948 0 0 0-.3927.6813zm1.0976-2.3654l2.602-1.4998 2.6069 1.4998v2.9994l-2.5974 1.4997-2.6067-1.4997Z"/></svg>
                                </div>
                                <div class="provider-info">
                                    <span class="provider-name">OpenAI / ChatGPT</span>
                                    <span class="provider-desc">GPT-5 Nano, GPT-4o, GPT-4o-mini</span>
                                    <span class="provider-badge recommended">موصى به</span>
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
                                    <span class="provider-badge">بديل مجاني</span>
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        إعدادات OpenAI
                    </h2>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="openai_api_key">مفتاح OpenAI API</label>
                        <div class="input-with-button">
                            <input type="password" id="openai_api_key" name="openai_api_key" value="{{ old('openai_api_key', $settings->openai_api_key) }}" placeholder="sk-proj-xxxxxxxxxxxx">
                            <button type="button" class="btn-test" onclick="testConnection()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                اختبار
                            </button>
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

                    <div class="assistant-mode-box" style="margin-top: 20px; padding: 16px; background: linear-gradient(135deg, rgba(14, 165, 233, 0.15), rgba(59, 130, 246, 0.1)); border-radius: 12px; border: 1px solid rgba(14, 165, 233, 0.3);">
                        <label class="toggle-label" style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                            <input type="checkbox" name="use_assistant_mode" value="1" {{ old('use_assistant_mode', $settings->use_assistant_mode ?? false) ? 'checked' : '' }} style="display: none;">
                            <span class="toggle-switch" style="position: relative; width: 44px; height: 24px; background: rgba(255,255,255,0.15); border-radius: 12px; flex-shrink: 0;"></span>
                            <span style="font-weight: 600; color: #38bdf8;">⚡ وضع المساعد الذكي (Assistant Mode)</span>
                        </label>
                        <small style="color: #7dd3fc; display: block; margin-top: 8px; padding-right: 56px;">
                            يوفر ~40% من التوكنز مع ذاكرة محادثة تلقائية مدمجة - موصى به للاستخدام المكثف
                        </small>
                    </div>

                    <!-- Assistant Sync Section -->
                    <div class="assistant-sync-box" style="margin-top: 16px; padding: 16px; background: linear-gradient(135deg, rgba(34, 197, 94, 0.15), rgba(16, 185, 129, 0.1)); border-radius: 12px; border: 1px solid rgba(34, 197, 94, 0.3);">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                            <div>
                                <h4 style="margin: 0; color: #4ade80; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                                    مزامنة المساعد الذكي
                                </h4>
                                <p style="margin: 4px 0 0; font-size: 12px; color: #86efac;">
                                    @if($settings->openai_assistant_id)
                                        ✅ المساعد مُفعّل - آخر تحديث: {{ $settings->assistant_synced_at ? \Carbon\Carbon::parse($settings->assistant_synced_at)->diffForHumans() : 'غير معروف' }}
                                    @else
                                        ⚠️ لم يتم إنشاء المساعد بعد - اضغط مزامنة لإنشائه
                                    @endif
                                </p>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" id="syncAssistantBtn" onclick="syncAssistant()" style="padding: 8px 16px; background: linear-gradient(135deg, #22c55e, #16a34a); color: white; border: none; border-radius: 8px; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                                    <span id="syncBtnText">مزامنة المنتجات</span>
                                </button>
                                @if($settings->openai_assistant_id)
                                <button type="button" onclick="deleteAssistant()" style="padding: 8px 12px; background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; font-size: 13px; cursor: pointer;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                                </button>
                                @endif
                            </div>
                        </div>
                        <div id="syncStatus" style="display: none; margin-top: 12px; padding: 10px; border-radius: 8px; font-size: 13px;"></div>
                    </div>
                </div>
            </div>

            <!-- Groq Settings -->
            <div class="settings-card" id="groq-settings" style="{{ ($settings->ai_provider ?? 'openai') !== 'groq' ? 'display:none;' : '' }}">
                <div class="card-header">
                    <h2>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        إعدادات Groq (بديل)
                    </h2>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="groq_api_key">مفتاح Groq API</label>
                        <div class="input-with-button">
                            <input type="password" id="groq_api_key" name="groq_api_key" value="{{ old('groq_api_key', $settings->groq_api_key) }}" placeholder="gsk_xxxxxxxxxxxx">
                            <button type="button" class="btn-test" onclick="testConnection()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                اختبار
                            </button>
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

            <!-- Features Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <h2>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="m6.8 15-3.5 2"/><path d="m20.7 17-3.5-2"/><path d="M6.8 9 3.3 7"/><path d="m20.7 7-3.5 2"/><path d="m9 22 3-8 3 8"/><path d="M12 6a4 4 0 0 0-4 4c0 2.2 1.8 4 4 4s4-1.8 4-4a4 4 0 0 0-4-4Z"/></svg>
                        الميزات
                    </h2>
                </div>
                <div class="card-body">
                    <div class="toggle-group">
                        <label class="toggle-label">
                            <input type="checkbox" name="ai_enabled" value="1" {{ old('ai_enabled', $settings->ai_enabled) ? 'checked' : '' }}>
                            <span class="toggle-switch"></span>
                            <span>تفعيل الذكاء الاصطناعي</span>
                        </label>
                    </div>

                    <div class="toggle-group">
                        <label class="toggle-label">
                            <input type="checkbox" name="auto_reply_enabled" value="1" {{ old('auto_reply_enabled', $settings->auto_reply_enabled) ? 'checked' : '' }}>
                            <span class="toggle-switch"></span>
                            <span>تفعيل الرد التلقائي</span>
                        </label>
                    </div>

                    <div class="toggle-group">
                        <label class="toggle-label">
                            <input type="checkbox" name="collect_customer_info" value="1" {{ old('collect_customer_info', $settings->collect_customer_info ?? true) ? 'checked' : '' }}>
                            <span class="toggle-switch"></span>
                            <span>جمع معلومات الزبائن (الاسم، الهاتف، العنوان)</span>
                        </label>
                    </div>

                    <div class="toggle-group">
                        <label class="toggle-label">
                            <input type="checkbox" name="strict_store_scope" value="1" {{ old('strict_store_scope', $settings->strict_store_scope ?? true) ? 'checked' : '' }}>
                            <span class="toggle-switch"></span>
                            <span>تقييد الردود بمواضيع المتجر فقط</span>
                        </label>
                    </div>

                    <div class="toggle-group">
                        <label class="toggle-label">
                            <input type="checkbox" name="can_create_orders" value="1" {{ old('can_create_orders', $settings->can_create_orders ?? true) ? 'checked' : '' }}>
                            <span class="toggle-switch"></span>
                            <span>السماح بإنشاء الطلبات تلقائياً</span>
                        </label>
                    </div>

                    <div class="toggle-group">
                        <label class="toggle-label">
                            <input type="checkbox" name="send_product_images" value="1" {{ old('send_product_images', $settings->send_product_images ?? true) ? 'checked' : '' }}>
                            <span class="toggle-switch"></span>
                            <span>إرسال صور المنتجات تلقائياً</span>
                        </label>
                        <small class="toggle-hint">عند عرض المنتجات للزبون، سيتم إرسال صورها تلقائياً</small>
                    </div>
                </div>
            </div>

            <!-- Model Parameters -->
            <div class="settings-card">
                <div class="card-header">
                    <h2>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                        معاملات الموديل
                    </h2>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="temperature">Temperature (الإبداعية)</label>
                            <input type="number" id="temperature" name="temperature" value="{{ old('temperature', $settings->temperature ?? 0.3) }}" min="0" max="2" step="0.1" required>
                            <small>0 = دقيق جداً، 2 = إبداعي جداً</small>
                        </div>

                        <div class="form-group">
                            <label for="top_p">Top P</label>
                            <input type="number" id="top_p" name="top_p" value="{{ old('top_p', $settings->top_p ?? 0.95) }}" min="0" max="1" step="0.05" required>
                            <small>تنوع الردود (0.95 موصى به)</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="top_k">Top K</label>
                            <input type="number" id="top_k" name="top_k" value="{{ old('top_k', $settings->top_k ?? 40) }}" min="1" max="100" required>
                            <small>عدد الخيارات (40 موصى به)</small>
                        </div>

                        <div class="form-group">
                            <label for="max_output_tokens">الحد الأقصى للكلمات</label>
                            <input type="number" id="max_output_tokens" name="max_output_tokens" value="{{ old('max_output_tokens', $settings->max_output_tokens ?? 500) }}" min="100" max="8192" required>
                            <small>أقصى عدد للكلمات في الرد</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Store Info -->
            <div class="settings-card full-width">
                <div class="card-header">
                    <h2>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/></svg>
                        معلومات المتجر
                    </h2>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="store_description">وصف المتجر</label>
                            <textarea id="store_description" name="store_description" rows="3" placeholder="وصف مختصر لمتجرك ونوع المنتجات...">{{ old('store_description', $settings->store_description) }}</textarea>
                        </div>

                        <div class="form-group">
                            <label for="store_policies">سياسات المتجر</label>
                            <textarea id="store_policies" name="store_policies" rows="3" placeholder="سياسات التوصيل، الإرجاع، الدفع...">{{ old('store_policies', $settings->store_policies) }}</textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="greeting_message">رسالة الترحيب</label>
                        <textarea id="greeting_message" name="greeting_message" rows="2" placeholder="أهلاً وسهلاً! كيف أقدر أساعدك؟">{{ old('greeting_message', $settings->greeting_message) }}</textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="working_hours">ساعات العمل</label>
                            <input type="text" id="working_hours" name="working_hours" value="{{ old('working_hours', $settings->working_hours ?? '9am - 10pm') }}" placeholder="9am - 10pm">
                        </div>

                        <div class="form-group">
                            <label for="delivery_time">وقت التوصيل</label>
                            <input type="text" id="delivery_time" name="delivery_time" value="{{ old('delivery_time', $settings->delivery_time ?? 'نفس اليوم') }}" placeholder="نفس اليوم">
                        </div>

                        <div class="form-group">
                            <label for="delivery_cost">سعر التوصيل (دينار)</label>
                            <input type="number" id="delivery_cost" name="delivery_cost" value="{{ old('delivery_cost', $settings->delivery_cost ?? 5000) }}" min="0">
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Instruction -->
            <div class="settings-card full-width">
                <div class="card-header">
                    <h2>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                        تعليمات النظام (System Instruction)
                    </h2>
                    <button type="button" class="btn-secondary" onclick="resetSystemInstruction()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                        استعادة الافتراضي
                    </button>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <textarea id="system_instruction" name="system_instruction" rows="10" placeholder="تعليمات للذكاء الاصطناعي...">{{ old('system_instruction', $settings->system_instruction) }}</textarea>
                        <small>هذه التعليمات تحدد شخصية وسلوك الذكاء الاصطناعي</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                حفظ الإعدادات
            </button>
        </div>
    </form>
</div>

<script>
const defaultSystemInstruction = `أنت مساعد مبيعات ذكي لمتجر عراقي. تتحدث بلهجة عراقية ودودة ومختصرة.

مهمتك الأساسية:
- مساعدة الزبائن في اختيار المنتجات
- جمع معلومات الطلب (الاسم، رقم الهاتف، العنوان)
- تأكيد الطلبات

قواعد مهمة:
1. كن مختصراً ولطيفاً
2. استخدم اللهجة العراقية
3. لا تكرر السؤال عن معلومات أعطاك إياها الزبون
4. عند اكتمال المعلومات، أكد الطلب مباشرة`;

function switchProvider(provider) {
    document.getElementById('openai-settings').style.display = provider === 'openai' ? '' : 'none';
    document.getElementById('groq-settings').style.display = provider === 'groq' ? '' : 'none';

    document.querySelectorAll('.provider-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    document.querySelector(`input[value="${provider}"]`).closest('.provider-option').classList.add('selected');
}

function testConnection() {
    const provider = document.querySelector('input[name="ai_provider"]:checked').value;
    const statusDiv = provider === 'openai' ? document.getElementById('connection-status') : document.getElementById('groq-connection-status');

    statusDiv.innerHTML = '<span class="loading">جاري الاختبار...</span>';

    fetch('{{ route("customer.ai-settings.test") }}', {
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

function resetSystemInstruction() {
    if (confirm('هل تريد استعادة التعليمات الافتراضية؟')) {
        document.getElementById('system_instruction').value = defaultSystemInstruction;
    }
}

function syncAssistant() {
    const btn = document.getElementById('syncAssistantBtn');
    const btnText = document.getElementById('syncBtnText');
    const statusDiv = document.getElementById('syncStatus');

    btn.disabled = true;
    btnText.innerHTML = 'جاري المزامنة...';
    statusDiv.style.display = 'block';
    statusDiv.style.background = 'rgba(59, 130, 246, 0.1)';
    statusDiv.style.color = '#60a5fa';
    statusDiv.innerHTML = '⏳ جاري مزامنة المنتجات مع المساعد الذكي...';

    fetch('{{ route("customer.ai-settings.sync-assistant") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btnText.innerHTML = 'مزامنة المنتجات';

        if (data.success) {
            statusDiv.style.background = 'rgba(34, 197, 94, 0.1)';
            statusDiv.style.color = '#4ade80';
            statusDiv.innerHTML = '✅ تمت المزامنة بنجاح! تم تحميل ' + (data.products_count || 0) + ' منتج للمساعد الذكي.';
            // Reload page after 2 seconds to show updated status
            setTimeout(() => location.reload(), 2000);
        } else {
            statusDiv.style.background = 'rgba(239, 68, 68, 0.1)';
            statusDiv.style.color = '#f87171';
            statusDiv.innerHTML = '❌ ' + data.message;
        }
    })
    .catch(error => {
        btn.disabled = false;
        btnText.innerHTML = 'مزامنة المنتجات';
        statusDiv.style.background = 'rgba(239, 68, 68, 0.1)';
        statusDiv.style.color = '#f87171';
        statusDiv.innerHTML = '❌ حدث خطأ في الاتصال';
    });
}

function deleteAssistant() {
    if (!confirm('هل أنت متأكد من حذف المساعد الذكي؟ سيتم حذف جميع بيانات المساعد من OpenAI.')) {
        return;
    }

    const statusDiv = document.getElementById('syncStatus');
    statusDiv.style.display = 'block';
    statusDiv.style.background = 'rgba(239, 68, 68, 0.1)';
    statusDiv.style.color = '#f87171';
    statusDiv.innerHTML = '⏳ جاري حذف المساعد...';

    fetch('{{ route("customer.ai-settings.delete-assistant") }}', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.style.background = 'rgba(34, 197, 94, 0.1)';
            statusDiv.style.color = '#4ade80';
            statusDiv.innerHTML = '✅ تم حذف المساعد بنجاح';
            setTimeout(() => location.reload(), 1500);
        } else {
            statusDiv.innerHTML = '❌ ' + data.message;
        }
    })
    .catch(error => {
        statusDiv.innerHTML = '❌ حدث خطأ في الحذف';
    });
}
</script>
@endsection

@section('styles')
<style>
    .page-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
    .page-header-bar { margin-bottom: 24px; }
    .page-title { font-size: 24px; font-weight: 700; color: var(--text-color); margin: 0 0 4px 0; }
    .page-subtitle { font-size: 14px; color: var(--text-muted); margin: 0; }
    .alert-success { display: flex; align-items: center; gap: 10px; padding: 14px 18px; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3); border-radius: 10px; color: #22c55e; margin-bottom: 24px; }

    .usage-dashboard { background: var(--card-background); border-radius: 16px; border: 1px solid var(--border-color); margin-bottom: 24px; }
    .usage-header { padding: 18px 24px; border-bottom: 1px solid var(--border-color); background: var(--background-color); }
    .usage-header h2 { font-size: 15px; font-weight: 600; color: var(--text-color); margin: 0; display: flex; align-items: center; gap: 10px; }
    .usage-header h2 svg { color: var(--primary-color); }
    .usage-grid { display: grid; grid-template-columns: 1fr 1fr 2fr; gap: 20px; padding: 24px; }
    .usage-card { background: var(--background-color); border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 16px; }
    .usage-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
    .usage-icon.today { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .usage-icon.month { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
    .usage-info { flex: 1; display: flex; flex-direction: column; }
    .usage-label { font-size: 12px; color: var(--text-muted); }
    .usage-value { font-size: 28px; font-weight: 700; color: var(--text-color); }
    .usage-detail { font-size: 12px; color: var(--text-muted); }
    .usage-cost { text-align: left; display: flex; flex-direction: column; }
    .cost-value { font-size: 18px; font-weight: 600; color: #22c55e; }
    .tokens-value { font-size: 11px; color: var(--text-muted); }
    .usage-breakdown { width: 100%; }
    .usage-breakdown h4 { font-size: 13px; font-weight: 600; margin: 0 0 12px 0; }
    .breakdown-list { display: flex; flex-direction: column; gap: 8px; }
    .breakdown-item { display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: var(--card-background); border-radius: 8px; }
    .type-name { font-size: 13px; flex: 1; }
    .type-count { font-size: 12px; color: var(--text-muted); margin: 0 16px; }
    .type-cost { font-size: 12px; font-weight: 600; color: #22c55e; }
    .no-data { font-size: 13px; color: var(--text-muted); text-align: center; padding: 20px; }

    .provider-selection { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .provider-option { cursor: pointer; }
    .provider-option input { display: none; }
    .provider-card { display: flex; align-items: center; gap: 16px; padding: 20px; background: var(--background-color); border: 2px solid var(--border-color); border-radius: 12px; transition: all 0.2s; }
    .provider-option.selected .provider-card { border-color: var(--primary-color); background: rgba(59, 130, 246, 0.05); }
    .provider-logo { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
    .provider-logo.openai { background: rgba(0, 0, 0, 0.1); color: #000; }
    .provider-logo.groq { background: rgba(255, 107, 0, 0.1); color: #ff6b00; }
    .provider-info { display: flex; flex-direction: column; gap: 4px; }
    .provider-name { font-size: 16px; font-weight: 600; }
    .provider-desc { font-size: 12px; color: var(--text-muted); }
    .provider-badge { display: inline-block; padding: 4px 8px; font-size: 10px; font-weight: 600; border-radius: 4px; background: var(--border-color); color: var(--text-muted); width: fit-content; }
    .provider-badge.recommended { background: rgba(34, 197, 94, 0.1); color: #22c55e; }

    .settings-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 24px; }
    .settings-card { background: var(--card-background); border-radius: 16px; border: 1px solid var(--border-color); overflow: hidden; }
    .settings-card.full-width { grid-column: 1 / -1; }
    .card-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 24px; border-bottom: 1px solid var(--border-color); background: var(--background-color); }
    .card-header h2 { font-size: 15px; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 10px; }
    .card-header h2 svg { color: var(--primary-color); }
    .card-body { padding: 24px; }

    .form-group { margin-bottom: 20px; }
    .form-group:last-child { margin-bottom: 0; }
    .form-group label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 14px; border: 1px solid var(--border-color); border-radius: 10px; font-size: 14px; background: var(--background-color); color: var(--text-color); }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary-color); }
    .form-group small { display: block; font-size: 12px; color: var(--text-muted); margin-top: 6px; }
    .form-group small a { color: var(--primary-color); }
    .form-group textarea { resize: vertical; min-height: 80px; }
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }

    .input-with-button { display: flex; gap: 10px; }
    .input-with-button input { flex: 1; }
    .btn-test { display: flex; align-items: center; gap: 6px; padding: 12px 16px; background: var(--primary-color); color: white; border: none; border-radius: 10px; font-size: 14px; cursor: pointer; white-space: nowrap; }
    .btn-test:hover { opacity: 0.9; }
    .connection-status { margin-top: 8px; font-size: 13px; }
    .connection-status .loading { color: var(--text-muted); }
    .connection-status .success { color: #22c55e; }
    .connection-status .error { color: #ef4444; }

    .toggle-group { margin-bottom: 16px; }
    .toggle-group:last-child { margin-bottom: 0; }
    .toggle-label { display: flex; align-items: center; gap: 12px; cursor: pointer; font-size: 14px; }
    .toggle-label input { display: none; }
    .toggle-switch { position: relative; width: 44px; height: 24px; background: var(--border-color); border-radius: 12px; transition: background 0.2s; flex-shrink: 0; }
    .toggle-switch::after { content: ''; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; background: white; border-radius: 50%; transition: transform 0.2s; }
    .toggle-label input:checked + .toggle-switch { background: var(--primary-color); }
    .toggle-label input:checked + .toggle-switch::after { transform: translateX(20px); }

    .btn-secondary { display: flex; align-items: center; gap: 6px; padding: 8px 14px; background: transparent; color: var(--text-muted); border: 1px solid var(--border-color); border-radius: 8px; font-size: 13px; cursor: pointer; }
    .btn-secondary:hover { background: var(--background-color); }
    .form-actions { display: flex; justify-content: flex-end; }
    .btn-primary { display: flex; align-items: center; gap: 8px; padding: 14px 28px; background: var(--primary-color); color: white; border: none; border-radius: 12px; font-size: 15px; font-weight: 500; cursor: pointer; }
    .btn-primary:hover { opacity: 0.9; }

    @media (max-width: 900px) {
        .settings-grid, .usage-grid, .provider-selection { grid-template-columns: 1fr; }
        .form-row { grid-template-columns: 1fr; }
    }
</style>
@endsection
