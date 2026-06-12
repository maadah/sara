@extends('layouts.customer')

@section('title', 'اشتراكي - لوحة التحكم')

@section('content')
<div class="subscription-page">
    <!-- Current Subscription Card -->
    <div class="current-subscription-card">
        <div class="subscription-header">
            <div class="plan-badge advanced">الباقة المتقدمة</div>
            <div class="subscription-status active">
                <span class="status-dot"></span>
                نشط
            </div>
        </div>

        <div class="subscription-details">
            <div class="detail-item">
                <div class="detail-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <div class="detail-content">
                    <span class="detail-label">تاريخ الاشتراك</span>
                    <span class="detail-value">{{ auth()->user()->created_at->format('Y/m/d') }}</span>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </div>
                <div class="detail-content">
                    <span class="detail-label">تاريخ التجديد</span>
                    <span class="detail-value">{{ auth()->user()->created_at->addYear()->format('Y/m/d') }}</span>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div class="detail-content">
                    <span class="detail-label">سعر الباقة</span>
                    <span class="detail-value price">$29 <small>/ شهرياً</small></span>
                </div>
            </div>
        </div>

        <div class="plan-features">
            <h4>خصائص الباقة</h4>
            <ul>
                <li class="included">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    عدد غير محدود من المنتجات
                </li>
                <li class="included">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    إدارة المخزون المتقدمة
                </li>
                <li class="included">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    نقطة البيع (POS)
                </li>
                <li class="included">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    تقارير المبيعات
                </li>
                <li class="included">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    دعم فني على مدار الساعة
                </li>
                <li class="not-included">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                    تكامل مع المحاسبة
                </li>
                <li class="not-included">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                    متاجر متعددة
                </li>
            </ul>
        </div>
    </div>

    <!-- Available Plans Section -->
    <div class="plans-section">
        <h2 class="section-title">الباقات المتاحة</h2>
        <p class="section-subtitle">اختر الباقة المناسبة لاحتياجات عملك</p>

        <div class="plans-grid">
            @foreach($plans as $plan)
                <div class="plan-card {{ $plan['is_popular'] ? 'popular' : '' }}">
                    @if($plan['is_popular'])
                        <div class="popular-badge">الأكثر شعبية</div>
                    @endif

                    <div class="plan-header">
                        <h3 class="plan-name">{{ $plan['name'] }}</h3>
                        <div class="plan-price">
                            <span class="amount">${{ $plan['price'] }}</span>
                            <span class="period">/ {{ $plan['period'] }}</span>
                        </div>
                    </div>

                    <ul class="plan-features-list">
                        @foreach($plan['features'] as $feature)
                            <li class="{{ $feature['included'] ? 'included' : 'not-included' }}">
                                @if($feature['included'])
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                @endif
                                {{ $feature['text'] }}
                            </li>
                        @endforeach
                    </ul>

                    <button class="plan-btn {{ $plan['id'] == 'advanced' ? 'current' : '' }}" {{ $plan['id'] == 'advanced' ? 'disabled' : '' }}>
                        @if($plan['id'] == 'advanced')
                            الباقة الحالية
                        @elseif($plan['id'] == 'basic')
                            تقليل الباقة
                        @else
                            ترقية الآن
                        @endif
                    </button>
                </div>
            @endforeach
        </div>
    </div>
</div>

<style>
    .subscription-page {
        max-width: 1200px;
    }

    /* Current Subscription Card */
    .current-subscription-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 30px;
    }

    .subscription-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .plan-badge {
        padding: 8px 20px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
    }

    .plan-badge.advanced {
        background: linear-gradient(135deg, #25D366, #128C7E);
        color: white;
    }

    .subscription-status {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: #22c55e;
        font-weight: 500;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        background: #22c55e;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .subscription-details {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 25px;
        padding-bottom: 25px;
        border-bottom: 1px solid var(--border-color);
    }

    .detail-item {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .detail-icon {
        width: 48px;
        height: 48px;
        background: rgba(37, 211, 102, 0.1);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .detail-icon svg {
        width: 24px;
        height: 24px;
        color: #25D366;
    }

    .detail-content {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .detail-content .detail-label {
        font-size: 13px;
        color: var(--text-muted);
    }

    .detail-content .detail-value {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-light);
    }

    .detail-content .detail-value.price {
        color: #25D366;
    }

    .detail-content .detail-value.price small {
        font-weight: 400;
        color: var(--text-muted);
    }

    .plan-features h4 {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-light);
        margin-bottom: 15px;
    }

    .plan-features ul {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .plan-features li {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
    }

    .plan-features li svg {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
    }

    .plan-features li.included {
        color: var(--text-light);
    }

    .plan-features li.included svg {
        color: #22c55e;
    }

    .plan-features li.not-included {
        color: var(--text-muted);
    }

    .plan-features li.not-included svg {
        color: rgba(156, 163, 175, 0.5);
    }

    /* Plans Section */
    .plans-section {
        margin-top: 30px;
    }

    .section-title {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-light);
        margin-bottom: 8px;
    }

    .section-subtitle {
        font-size: 15px;
        color: var(--text-muted);
        margin-bottom: 25px;
    }

    .plans-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }

    .plan-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 25px;
        position: relative;
        transition: all 0.3s;
    }

    .plan-card:hover {
        transform: translateY(-5px);
        border-color: var(--primary-green);
        box-shadow: 0 10px 25px rgba(37, 211, 102, 0.1);
    }

    .plan-card.popular {
        border-color: #25D366;
        box-shadow: 0 0 0 1px rgba(37, 211, 102, 0.3);
    }

    .popular-badge {
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, #25D366, #128C7E);
        color: white;
        padding: 5px 15px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }

    .plan-header {
        text-align: center;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--border-color);
        margin-bottom: 20px;
    }

    .plan-name {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-light);
        margin-bottom: 15px;
    }

    .plan-price {
        display: flex;
        align-items: baseline;
        justify-content: center;
        gap: 5px;
    }

    .plan-price .amount {
        font-size: 36px;
        font-weight: 700;
        color: var(--text-light);
    }

    .plan-price .period {
        font-size: 14px;
        color: var(--text-muted);
    }

    .plan-features-list {
        list-style: none;
        padding: 0;
        margin: 0 0 20px 0;
    }

    .plan-features-list li {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 0;
        font-size: 14px;
    }

    .plan-features-list li svg {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
    }

    .plan-features-list li.included {
        color: var(--text-light);
    }

    .plan-features-list li.included svg {
        color: #22c55e;
    }

    .plan-features-list li.not-included {
        color: var(--text-muted);
    }

    .plan-features-list li.not-included svg {
        color: rgba(156, 163, 175, 0.5);
    }

    .plan-btn {
        width: 100%;
        padding: 12px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        font-family: 'Cairo', sans-serif;
    }

    .plan-btn:not(.current) {
        background: linear-gradient(135deg, #25D366, #128C7E);
        color: white;
    }

    .plan-btn:not(.current):hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(37, 211, 102, 0.3);
    }

    .plan-btn.current {
        background: var(--bg-darker);
        color: var(--text-muted);
        cursor: not-allowed;
    }

    @media (max-width: 992px) {
        .subscription-details {
            grid-template-columns: 1fr;
        }

        .plan-features ul {
            grid-template-columns: 1fr;
        }

        .plans-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection
