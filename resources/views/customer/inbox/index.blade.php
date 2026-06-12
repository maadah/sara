@extends('layouts.customer')

@section('title', 'صندوق الرسائل')

@section('styles')
<style>
    /* ============ Page Header ============ */
    .inbox-page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 16px;
    }

    .inbox-page-header h1 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-light);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .inbox-page-header h1 svg {
        width: 28px;
        height: 28px;
        color: var(--primary-green);
    }

    .inbox-page-header p {
        color: var(--text-muted);
        margin: 4px 0 0 40px;
        font-size: 0.9rem;
    }

    /* ============ Main Container ============ */
    .inbox-container {
        display: flex;
        height: calc(100vh - 220px);
        min-height: 500px;
        background: var(--bg-card);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.2);
        border: 1px solid var(--border-color);
    }

    /* ============ Conversations Sidebar ============ */
    .conversations-sidebar {
        width: 380px;
        border-left: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        background: var(--bg-darker);
    }

    .sidebar-header {
        padding: 20px;
        border-bottom: 1px solid var(--border-color);
        background: var(--bg-card);
    }

    .sidebar-header h2 {
        font-size: 1.15rem;
        font-weight: 600;
        margin-bottom: 15px;
        color: var(--text-light);
    }

    /* ============ Filters ============ */
    .inbox-filters {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .filter-btn {
        padding: 8px 14px;
        border-radius: 25px;
        font-size: 0.8rem;
        border: 1px solid var(--border-color);
        background: transparent;
        color: var(--text-muted);
        cursor: pointer;
        transition: all 0.25s ease;
        display: flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
    }

    .filter-btn:hover {
        background: rgba(37, 211, 102, 0.1);
        color: var(--primary-green);
        border-color: var(--primary-green);
    }

    .filter-btn.active {
        background: var(--primary-green);
        color: white;
        border-color: var(--primary-green);
    }

    .filter-btn .badge {
        background: rgba(255,255,255,0.2);
        padding: 2px 7px;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .filter-btn.active .badge {
        background: rgba(255,255,255,0.3);
    }

    /* ============ Search Box ============ */
    .search-box {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
    }

    .search-input {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        background: var(--bg-card);
        color: var(--text-light);
        font-size: 0.9rem;
        transition: all 0.25s ease;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--primary-green);
        box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.15);
    }

    .search-input::placeholder {
        color: var(--text-muted);
    }

    /* ============ Sync Button ============ */
    .sync-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin: 15px 20px;
        padding: 12px 20px;
        background: linear-gradient(135deg, var(--primary-green) 0%, var(--secondary-green) 100%);
        border: none;
        border-radius: 10px;
        color: white;
        font-size: 0.9rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.25s ease;
        box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
    }

    .sync-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4);
    }

    .sync-btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }

    .sync-btn svg {
        width: 18px;
        height: 18px;
    }

    .sync-btn.loading svg {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    /* ============ Conversations List ============ */
    .conversations-list {
        flex: 1;
        overflow-y: auto;
    }

    .conversations-list::-webkit-scrollbar {
        width: 6px;
    }

    .conversations-list::-webkit-scrollbar-track {
        background: transparent;
    }

    .conversations-list::-webkit-scrollbar-thumb {
        background: var(--border-color);
        border-radius: 3px;
    }

    /* ============ Conversation Item ============ */
    .conversation-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px 20px;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s ease;
        border-bottom: 1px solid var(--border-color);
        position: relative;
    }

    .conversation-item:hover {
        background: rgba(37, 211, 102, 0.05);
    }

    .conversation-item.unread {
        background: rgba(37, 211, 102, 0.08);
    }

    .conversation-item.unread::before {
        content: '';
        position: absolute;
        right: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 50%;
        background: var(--primary-green);
        border-radius: 0 4px 4px 0;
    }

    /* Avatar */
    .conversation-avatar {
        position: relative;
        flex-shrink: 0;
    }

    .conversation-avatar img {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--border-color);
    }

    .conversation-avatar .avatar-placeholder {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        font-weight: 600;
        color: white;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .platform-badge {
        position: absolute;
        bottom: -2px;
        left: -2px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
    }

    .platform-badge.facebook {
        background: #1877F2;
    }

    .platform-badge.instagram {
        background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
    }

    .platform-badge.whatsapp {
        background: #25D366;
    }

    .platform-badge svg {
        width: 11px;
        height: 11px;
        color: white;
    }

    /* Content */
    .conversation-content {
        flex: 1;
        min-width: 0;
    }

    .conversation-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 4px;
    }

    .conversation-name {
        font-size: 0.95rem;
        font-weight: 500;
        color: var(--text-light);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .conversation-item.unread .conversation-name {
        font-weight: 700;
        color: white;
    }

    .conversation-time {
        font-size: 0.75rem;
        color: var(--text-muted);
        flex-shrink: 0;
        margin-right: 10px;
    }

    .conversation-preview {
        font-size: 0.85rem;
        color: var(--text-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 4px;
    }

    .conversation-item.unread .conversation-preview {
        color: rgba(255, 255, 255, 0.8);
    }

    .conversation-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .account-name {
        font-size: 0.75rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .unread-badge {
        background: var(--primary-green);
        color: white;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 12px;
        min-width: 20px;
        text-align: center;
    }

    /* ============ Chat Area ============ */
    .chat-area {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: var(--bg-dark);
    }

    .chat-placeholder {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
        text-align: center;
        padding: 40px;
    }

    .chat-placeholder svg {
        width: 100px;
        height: 100px;
        margin-bottom: 20px;
        opacity: 0.3;
        color: var(--primary-green);
    }

    .chat-placeholder h3 {
        font-size: 1.2rem;
        color: var(--text-light);
        margin-bottom: 8px;
        font-weight: 600;
    }

    .chat-placeholder p {
        font-size: 0.9rem;
        color: var(--text-muted);
    }

    /* ============ Empty State ============ */
    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        text-align: center;
    }

    .empty-state svg {
        width: 70px;
        height: 70px;
        margin-bottom: 20px;
        opacity: 0.5;
        color: var(--text-muted);
    }

    .empty-state h3 {
        font-size: 1.1rem;
        color: var(--text-light);
        margin-bottom: 8px;
        font-weight: 600;
    }

    .empty-state p {
        font-size: 0.9rem;
        color: var(--text-muted);
    }

    /* ============ No Accounts State ============ */
    .no-accounts-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: calc(100vh - 220px);
        padding: 60px 40px;
        text-align: center;
        background: var(--bg-card);
        border-radius: 16px;
        border: 1px solid var(--border-color);
    }

    .no-accounts-state .icon-wrapper {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.1) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 28px;
    }

    .no-accounts-state .icon-wrapper svg {
        width: 60px;
        height: 60px;
        color: #667eea;
    }

    .no-accounts-state h2 {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-light);
        margin: 0 0 12px 0;
    }

    .no-accounts-state p {
        font-size: 1rem;
        color: var(--text-muted);
        margin: 0 0 28px 0;
        max-width: 380px;
        line-height: 1.6;
    }

    .no-accounts-state .btn-connect {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 14px 28px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
    }

    .no-accounts-state .btn-connect:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 30px rgba(102, 126, 234, 0.5);
    }

    .no-accounts-state .btn-connect svg {
        width: 20px;
        height: 20px;
    }

    /* ============ Pagination ============ */
    .inbox-pagination {
        padding: 15px 20px;
        border-top: 1px solid var(--border-color);
    }

    /* ============ Responsive ============ */
    @media (max-width: 992px) {
        .conversations-sidebar {
            width: 320px;
        }
    }

    @media (max-width: 768px) {
        .inbox-container {
            flex-direction: column;
            height: auto;
            min-height: calc(100vh - 200px);
        }

        .conversations-sidebar {
            width: 100%;
            border-left: none;
            border-bottom: 1px solid var(--border-color);
        }

        .chat-area {
            display: none;
        }

        .conversation-item {
            padding: 14px 16px;
        }

        .conversation-avatar img,
        .conversation-avatar .avatar-placeholder {
            width: 45px;
            height: 45px;
        }
    }
</style>
@endsection

@section('content')
<div class="inbox-page-header">
    <div>
        <h1>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
            </svg>
            صندوق الرسائل
        </h1>
        <p>إدارة رسائل فيسبوك وانستقرام في مكان واحد</p>
    </div>
</div>

@if($socialAccounts->isEmpty())
    <div class="no-accounts-state">
        <div class="icon-wrapper">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
            </svg>
        </div>
        <h2>لم يتم ربط أي حسابات بعد</h2>
        <p>قم بربط صفحات فيسبوك أو حسابات انستقرام أو واتساب الخاصة بك لبدء استقبال وإدارة الرسائل</p>
        <a href="{{ route('customer.social-accounts.index') }}" class="btn-connect">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            ربط الحسابات
        </a>
    </div>
@else
    <div class="inbox-container" id="inboxContainer">
        <!-- Conversations Sidebar -->
        <div class="conversations-sidebar">
            <div class="sidebar-header">
                <h2>المحادثات</h2>
                <div class="inbox-filters">
                    <a href="{{ route('customer.inbox.index') }}" class="filter-btn {{ !$platform ? 'active' : '' }}">
                        الكل
                        @if($unreadCounts['all'] > 0)
                            <span class="badge">{{ $unreadCounts['all'] }}</span>
                        @endif
                    </a>
                    <a href="{{ route('customer.inbox.index', ['platform' => 'facebook']) }}" class="filter-btn {{ $platform === 'facebook' ? 'active' : '' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                        فيسبوك
                        @if($unreadCounts['facebook'] > 0)
                            <span class="badge">{{ $unreadCounts['facebook'] }}</span>
                        @endif
                    </a>
                    <a href="{{ route('customer.inbox.index', ['platform' => 'instagram']) }}" class="filter-btn {{ $platform === 'instagram' ? 'active' : '' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                        </svg>
                        انستقرام
                        @if($unreadCounts['instagram'] > 0)
                            <span class="badge">{{ $unreadCounts['instagram'] }}</span>
                        @endif
                    </a>
                    <a href="{{ route('customer.inbox.index', ['platform' => 'whatsapp']) }}" class="filter-btn {{ $platform === 'whatsapp' ? 'active' : '' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        واتساب
                        @if($unreadCounts['whatsapp'] > 0)
                            <span class="badge">{{ $unreadCounts['whatsapp'] }}</span>
                        @endif
                    </a>
                </div>
            </div>

            <div class="search-box">
                <form action="{{ route('customer.inbox.index') }}" method="GET">
                    @if($platform)
                        <input type="hidden" name="platform" value="{{ $platform }}">
                    @endif
                    <input type="text" name="search" class="search-input" placeholder="بحث في المحادثات..." value="{{ $search ?? '' }}">
                </form>
            </div>

            <button type="button" class="sync-btn" id="syncBtn" onclick="syncMessages()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                مزامنة الرسائل
            </button>

            <div class="conversations-list">
                @forelse($conversations as $conversation)
                    <a href="{{ route('customer.inbox.show', $conversation) }}" class="conversation-item {{ !$conversation->is_read ? 'unread' : '' }}">
                        <div class="conversation-avatar">
                            @if($conversation->participant_avatar)
                                <img src="{{ $conversation->participant_avatar }}" alt="{{ $conversation->participant_name }}">
                            @else
                                <div class="avatar-placeholder">
                                    {{ mb_substr($conversation->participant_name ?? 'م', 0, 1) }}
                                </div>
                            @endif
                            <div class="platform-badge {{ $conversation->platform }}">
                                @if($conversation->platform === 'instagram')
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                                @elseif($conversation->platform === 'whatsapp')
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                @else
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                @endif
                            </div>
                        </div>
                        <div class="conversation-content">
                            <div class="conversation-header">
                                <span class="conversation-name">{{ $conversation->participant_name ?? 'مستخدم ' . $conversation->platform }}</span>
                                <span class="conversation-time">
                                    @if($conversation->last_message_at)
                                        {{ $conversation->last_message_at->diffForHumans() }}
                                    @endif
                                </span>
                            </div>
                            <div class="conversation-preview">
                                {{ \Str::limit($conversation->last_message, 40) ?? 'لا توجد رسائل' }}
                            </div>
                            <div class="conversation-meta">
                                <span class="account-name">
                                    {{ $conversation->socialAccount->name ?? '' }}
                                </span>
                                @if($conversation->unread_count > 0)
                                    <span class="unread-badge">{{ $conversation->unread_count }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="empty-state">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
                        </svg>
                        <h3>لا توجد محادثات</h3>
                        <p>ستظهر المحادثات هنا عندما يتواصل معك العملاء</p>
                    </div>
                @endforelse
            </div>

            @if($conversations->hasPages())
                <div style="padding: 15px 20px; border-top: 1px solid var(--border-color);">
                    {{ $conversations->appends(request()->query())->links() }}
                </div>
            @endif
        </div>

        <!-- Chat Area Placeholder -->
        <div class="chat-area">
            <div class="chat-placeholder">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                </svg>
                <h3>اختر محادثة للبدء</h3>
                <p>اختر محادثة من القائمة لعرض الرسائل والرد عليها</p>
            </div>
        </div>
    </div>
@endif
@endsection

@section('scripts')
<script>
    function syncMessages() {
        const btn = document.getElementById('syncBtn');
        btn.classList.add('loading');
        btn.disabled = true;

        fetch('{{ route('customer.inbox.sync') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showToast('حدث خطأ أثناء المزامنة', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('حدث خطأ أثناء المزامنة', 'error');
        })
        .finally(() => {
            btn.classList.remove('loading');
            btn.disabled = false;
        });
    }

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        const bgColors = {
            success: 'linear-gradient(135deg, #25D366, #128C7E)',
            error: 'linear-gradient(135deg, #dc3545, #c82333)',
            info: 'linear-gradient(135deg, #0084ff, #0066cc)'
        };
        toast.style.cssText = `
            position: fixed;
            top: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(-20px);
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 500;
            z-index: 10000;
            opacity: 0;
            transition: all 0.3s ease;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            background: ${bgColors[type] || bgColors.info};
            color: white;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);

        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(-50%) translateY(0)';
        });

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(-50%) translateY(-20px)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
</script>
@endsection
