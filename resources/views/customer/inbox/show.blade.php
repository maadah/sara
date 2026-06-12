@extends('layouts.customer')

@section('title', 'محادثة مع ' . ($conversation->participant_name ?? 'مستخدم'))

@section('styles')
<style>
    /* ============ Chat Container ============ */
    .chat-container {
        display: flex;
        height: calc(100vh - 130px);
        min-height: 500px;
        background: var(--bg-darker);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        border: 1px solid var(--border-color);
    }

    /* ============ Sidebar ============ */
    .chat-sidebar {
        width: 320px;
        border-left: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        background: var(--bg-card);
    }

    .sidebar-header {
        padding: 18px 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--bg-darker);
    }

    .sidebar-header a {
        color: var(--text-light);
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        font-size: 0.95rem;
        transition: color 0.2s;
    }

    .sidebar-header a:hover {
        color: var(--primary-green);
    }

    .sidebar-title {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 15px 20px 10px;
    }

    .sidebar-conversations {
        flex: 1;
        overflow-y: auto;
    }

    .sidebar-conversations::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar-conversations::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.1);
        border-radius: 4px;
    }

    /* AI Context Panel (InvenGPT v6) */
    .ai-context-panel {
        border-top: 1px solid var(--border-color);
        background: rgba(139, 92, 246, 0.03);
        padding: 15px;
        max-height: 300px;
        overflow-y: auto;
    }

    .ai-context-panel::-webkit-scrollbar {
        width: 4px;
    }

    .ai-context-header {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.75rem;
        font-weight: 700;
        color: #8b5cf6;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid rgba(139, 92, 246, 0.2);
    }

    .ai-context-header svg {
        width: 16px;
        height: 16px;
    }

    .ai-section {
        margin-bottom: 12px;
        background: var(--bg-card);
        border-radius: 8px;
        padding: 10px;
        border: 1px solid rgba(139, 92, 246, 0.1);
    }

    .ai-section-title {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-light);
        margin-bottom: 8px;
    }

    .cart-item-mini {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        border-bottom: 1px solid var(--border-color);
    }

    .cart-item-mini:last-child {
        border-bottom: none;
    }

    .cart-item-name {
        font-size: 0.85rem;
        color: var(--text-light);
    }

    .cart-item-meta {
        display: flex;
        gap: 8px;
        font-size: 0.8rem;
        color: var(--text-muted);
    }

    .cart-total {
        margin-top: 8px;
        padding-top: 8px;
        border-top: 2px solid var(--primary-green);
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--primary-green);
        text-align: left;
    }

    .ai-data-item {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        font-size: 0.8rem;
    }

    .ai-data-item .label {
        color: var(--text-muted);
        font-weight: 500;
    }

    .ai-data-item .value {
        color: var(--text-light);
        font-weight: 600;
    }

    .interested-products {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .product-tag {
        display: inline-block;
        padding: 4px 10px;
        background: rgba(37, 211, 102, 0.1);
        border: 1px solid rgba(37, 211, 102, 0.3);
        border-radius: 12px;
        font-size: 0.75rem;
        color: var(--primary-green);
        font-weight: 500;
    }

    .sidebar-conversation-item {
        display: flex;
        align-items: center;
        padding: 14px 20px;
        cursor: pointer;
        transition: all 0.2s;
        gap: 12px;
        text-decoration: none;
        color: inherit;
        position: relative;
    }

    .sidebar-conversation-item:hover {
        background: rgba(255,255,255,0.05);
    }

    .sidebar-conversation-item.active {
        background: rgba(37, 211, 102, 0.15);
    }

    .sidebar-conversation-item.active::before {
        content: '';
        position: absolute;
        right: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 60%;
        background: var(--primary-green);
        border-radius: 3px 0 0 3px;
    }

    .sidebar-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
        position: relative;
    }

    .sidebar-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .sidebar-avatar .placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-weight: 600;
        font-size: 1rem;
    }

    .sidebar-avatar .online-dot {
        position: absolute;
        bottom: 2px;
        left: 2px;
        width: 10px;
        height: 10px;
        background: var(--primary-green);
        border: 2px solid var(--bg-card);
        border-radius: 50%;
    }

    .sidebar-info {
        flex: 1;
        min-width: 0;
    }

    .sidebar-name {
        font-size: 0.95rem;
        font-weight: 500;
        color: var(--text-light);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 3px;
    }

    .sidebar-preview {
        font-size: 0.8rem;
        color: var(--text-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sidebar-time {
        font-size: 0.7rem;
        color: var(--text-muted);
        flex-shrink: 0;
    }

    /* ============ Main Chat Area ============ */
    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: var(--bg-dark);
    }

    /* ============ Chat Header ============ */
    .chat-header {
        padding: 16px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 16px;
        background: var(--bg-card);
    }

    .chat-header .back-btn {
        display: none;
        color: var(--text-light);
        text-decoration: none;
        padding: 8px;
        margin-right: -8px;
        border-radius: 8px;
        transition: background 0.2s;
    }

    .chat-header .back-btn:hover {
        background: rgba(255,255,255,0.1);
    }

    .chat-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        overflow: hidden;
        position: relative;
        flex-shrink: 0;
    }

    .chat-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .chat-avatar .placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-weight: 600;
        font-size: 1.3rem;
    }

    .chat-avatar .platform-indicator {
        position: absolute;
        bottom: -2px;
        left: -2px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }

    .chat-avatar .platform-indicator.facebook {
        background: #1877F2;
        color: white;
    }

    .chat-avatar .platform-indicator.instagram {
        background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
        color: white;
    }

    .chat-avatar .platform-indicator.whatsapp {
        background: #25D366;
        color: white;
    }

    .chat-avatar .platform-indicator svg {
        width: 12px;
        height: 12px;
    }

    .chat-user-info {
        flex: 1;
    }

    .chat-user-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-light);
        margin-bottom: 2px;
    }

    .chat-user-meta {
        font-size: 0.85rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .chat-user-meta .status-dot {
        width: 8px;
        height: 8px;
        background: var(--primary-green);
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .chat-actions {
        display: flex;
        gap: 8px;
    }

    .chat-action-btn {
        padding: 10px;
        border: none;
        background: rgba(255,255,255,0.05);
        border-radius: 10px;
        color: var(--text-muted);
        cursor: pointer;
        transition: all 0.2s;
    }

    .chat-action-btn:hover {
        background: var(--primary-green);
        color: white;
        transform: translateY(-2px);
    }

    .chat-action-btn svg {
        width: 20px;
        height: 20px;
    }

    /* ============ Messages Area ============ */
    .messages-area {
        flex: 1;
        overflow-y: auto;
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        background: var(--bg-dark);
        background-image:
            radial-gradient(circle at 20% 80%, rgba(37, 211, 102, 0.03) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(102, 126, 234, 0.03) 0%, transparent 50%);
    }

    .messages-area::-webkit-scrollbar {
        width: 6px;
    }

    .messages-area::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.1);
        border-radius: 6px;
    }

    .message-wrapper {
        display: flex;
        flex-direction: column;
        max-width: 65%;
        animation: messageIn 0.3s ease-out;
    }

    @keyframes messageIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .message-wrapper.incoming {
        align-self: flex-start;
    }

    .message-wrapper.outgoing {
        align-self: flex-end;
    }

    .message-bubble {
        padding: 12px 18px;
        border-radius: 20px;
        font-size: 0.95rem;
        line-height: 1.6;
        word-wrap: break-word;
        position: relative;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }

    .message-wrapper.incoming .message-bubble {
        background: var(--bg-card);
        color: var(--text-light);
        border-bottom-right-radius: 6px;
    }

    .message-wrapper.outgoing .message-bubble {
        background: linear-gradient(135deg, var(--primary-green) 0%, var(--secondary-green) 100%);
        color: white;
        border-bottom-left-radius: 6px;
    }

    .message-wrapper.outgoing.facebook .message-bubble {
        background: linear-gradient(135deg, #0084ff 0%, #0066cc 100%);
    }

    .message-wrapper.outgoing.instagram .message-bubble {
        background: linear-gradient(135deg, #833ab4 0%, #fd1d1d 50%, #fcb045 100%);
    }

    .message-wrapper.outgoing.whatsapp .message-bubble {
        background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
    }

    .message-time {
        font-size: 0.7rem;
        color: var(--text-muted);
        margin-top: 6px;
        padding: 0 8px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .message-wrapper.outgoing .message-time {
        justify-content: flex-start;
    }

    .message-wrapper.incoming .message-time {
        justify-content: flex-end;
    }

    .message-status {
        display: inline-flex;
        align-items: center;
    }

    .message-status svg {
        width: 16px;
        height: 16px;
    }

    .message-status.read svg {
        color: #53bdeb;
    }

    /* Message attachments */
    .message-attachment {
        margin-top: 10px;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
    }

    .message-attachment img {
        max-width: 100%;
        max-height: 300px;
        display: block;
        border-radius: 12px;
        cursor: pointer;
        transition: transform 0.2s;
    }

    .message-attachment img:hover {
        transform: scale(1.02);
    }

    .message-attachment .product-label {
        position: absolute;
        bottom: 8px;
        right: 8px;
        background: rgba(0, 0, 0, 0.7);
        color: white;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        backdrop-filter: blur(4px);
    }

    .message-attachment video {
        max-width: 100%;
        max-height: 300px;
        display: block;
    }

    /* Date separator */
    .date-separator {
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 24px 0;
        position: relative;
    }

    .date-separator::before {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        top: 50%;
        height: 1px;
        background: var(--border-color);
    }

    .date-separator span {
        background: var(--bg-dark);
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.75rem;
        color: var(--text-muted);
        position: relative;
        z-index: 1;
        border: 1px solid var(--border-color);
    }

    /* ============ Compose Area ============ */
    .compose-area {
        padding: 16px 24px;
        background: var(--bg-card);
        border-top: 1px solid var(--border-color);
    }

    .compose-form {
        display: flex;
        gap: 12px;
        align-items: flex-end;
    }

    .compose-actions {
        display: flex;
        gap: 4px;
    }

    .compose-action-btn {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: rgba(255,255,255,0.05);
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .compose-action-btn:hover {
        background: rgba(255,255,255,0.1);
        color: var(--text-light);
    }

    .compose-action-btn svg {
        width: 20px;
        height: 20px;
    }

    .compose-input-wrapper {
        flex: 1;
        position: relative;
    }

    .compose-input {
        width: 100%;
        padding: 14px 20px;
        border: 1px solid var(--border-color);
        border-radius: 24px;
        background: var(--bg-darker);
        color: var(--text-light);
        font-size: 0.95rem;
        resize: none;
        max-height: 120px;
        min-height: 50px;
        line-height: 1.5;
        transition: all 0.2s;
        font-family: inherit;
    }

    .compose-input:focus {
        outline: none;
        border-color: var(--primary-green);
        background: var(--bg-dark);
        box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.1);
    }

    .compose-input::placeholder {
        color: var(--text-muted);
    }

    .send-btn {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
        color: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        flex-shrink: 0;
        box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
    }

    .send-btn:hover:not(:disabled) {
        transform: scale(1.08);
        box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4);
    }

    .send-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    .send-btn svg {
        width: 22px;
        height: 22px;
        transform: rotate(180deg);
    }

    /* Typing indicator */
    .typing-indicator {
        display: none;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        color: var(--text-muted);
        font-size: 0.85rem;
    }

    .typing-indicator.show {
        display: flex;
    }

    .typing-dots {
        display: flex;
        gap: 4px;
    }

    .typing-dots span {
        width: 8px;
        height: 8px;
        background: var(--text-muted);
        border-radius: 50%;
        animation: typingDot 1.4s infinite;
    }

    .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.4s; }

    @keyframes typingDot {
        0%, 60%, 100% { transform: translateY(0); }
        30% { transform: translateY(-6px); }
    }

    /* Empty state */
    .no-messages {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
        text-align: center;
        padding: 40px;
    }

    .no-messages svg {
        width: 80px;
        height: 80px;
        margin-bottom: 20px;
        opacity: 0.3;
        color: var(--primary-green);
    }

    .no-messages h3 {
        font-size: 1.2rem;
        color: var(--text-light);
        margin-bottom: 8px;
        font-weight: 600;
    }

    .no-messages p {
        color: var(--text-muted);
    }

    /* New message notification */
    .new-message-toast {
        position: fixed;
        bottom: 100px;
        left: 50%;
        transform: translateX(-50%) translateY(100px);
        background: var(--primary-green);
        color: white;
        padding: 12px 24px;
        border-radius: 30px;
        font-size: 0.9rem;
        font-weight: 500;
        box-shadow: 0 4px 20px rgba(37, 211, 102, 0.4);
        opacity: 0;
        transition: all 0.3s ease;
        z-index: 100;
        cursor: pointer;
    }

    .new-message-toast.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }

    /* ============ Responsive ============ */
    @media (max-width: 992px) {
        .chat-sidebar {
            width: 280px;
        }
    }

    @media (max-width: 768px) {
        body {
            overflow: hidden; /* Prevent body scrolling while in chat */
        }

        .chat-container {
            position: fixed;
            top: 72px; /* Height of the sticky layout header */
            left: 0;
            right: 0;
            bottom: 0;
            height: auto;
            width: auto;
            margin: 0;
            border-radius: 0;
            border: none;
            border-top: 1px solid var(--border-color);
            z-index: 20;
        }

        .chat-sidebar {
            display: none;
        }

        .chat-header .back-btn {
            display: flex;
        }

        .message-wrapper {
            max-width: 90%;
        }

        .messages-area {
            padding: 16px;
            padding-bottom: 24px;
        }

        .compose-area {
            padding: 10px 16px;
            padding-bottom: max(10px, env(safe-area-inset-bottom));
        }

        .compose-actions button[title="إيموجي"] {
            display: none;
        }
        
        .chat-header {
            padding: 12px 16px;
        }
        
        .chat-avatar {
            width: 40px;
            height: 40px;
        }
        
        .send-btn {
            width: 42px;
            height: 42px;
        }
        
        .compose-input {
            padding: 10px 16px;
        }
    }
</style>
@endsection

@section('content')
<div class="chat-container">
    <!-- Sidebar with other conversations -->
    <div class="chat-sidebar">
        <div class="sidebar-header">
            <a href="{{ route('customer.inbox.index') }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
                جميع المحادثات
            </a>
        </div>
        <div class="sidebar-title">المحادثات الأخيرة</div>
        <div class="sidebar-conversations">
            <!-- Current Conversation -->
            <a href="{{ route('customer.inbox.show', $conversation) }}" class="sidebar-conversation-item active">
                <div class="sidebar-avatar">
                    @if($conversation->participant_avatar)
                        <img src="{{ $conversation->participant_avatar }}" alt="">
                    @else
                        <div class="placeholder">{{ mb_substr($conversation->participant_name ?? 'م', 0, 1) }}</div>
                    @endif
                </div>
                <div class="sidebar-info">
                    <div class="sidebar-name">{{ $conversation->participant_name ?? 'مستخدم' }}</div>
                    <div class="sidebar-preview">{{ \Str::limit($conversation->last_message, 30) }}</div>
                </div>
                <div class="sidebar-time">
                    @if($conversation->last_message_at)
                        {{ $conversation->last_message_at->shortRelativeToNowDiffForHumans() }}
                    @endif
                </div>
            </a>

            <!-- Other Conversations -->
            @foreach($otherConversations as $otherConv)
                <a href="{{ route('customer.inbox.show', $otherConv) }}" class="sidebar-conversation-item">
                    <div class="sidebar-avatar">
                        @if($otherConv->participant_avatar)
                            <img src="{{ $otherConv->participant_avatar }}" alt="">
                        @else
                            <div class="placeholder">{{ mb_substr($otherConv->participant_name ?? 'م', 0, 1) }}</div>
                        @endif
                        @if(!$otherConv->is_read)
                            <div class="online-dot"></div>
                        @endif
                    </div>
                    <div class="sidebar-info">
                        <div class="sidebar-name">{{ $otherConv->participant_name ?? 'مستخدم' }}</div>
                        <div class="sidebar-preview">{{ \Str::limit($otherConv->last_message, 30) }}</div>
                    </div>
                    <div class="sidebar-time">
                        @if($otherConv->last_message_at)
                            {{ $otherConv->last_message_at->shortRelativeToNowDiffForHumans() }}
                        @endif
                    </div>
                </a>
                @endforeach
        </div>

        <!-- AI Context Panel (InvenGPT v6) -->
        @if(!empty($cart) || !empty($customerData))
        <div class="ai-context-panel">
            <div class="ai-context-header">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                </svg>
                <span>InvenGPT</span>
            </div>

            @if(!empty($cart))
            <div class="ai-section">
                <div class="ai-section-title">🛒 السلة الحالية</div>
                @foreach($cart as $item)
                <div class="cart-item-mini">
                    <div class="cart-item-name">{{ $item['name'] ?? 'منتج' }}</div>
                    <div class="cart-item-meta">
                        <span>{{ $item['quantity'] ?? 1 }}×</span>
                        <span>{{ number_format($item['price'] ?? 0) }} د.ع</span>
                    </div>
                </div>
                @endforeach
                <div class="cart-total">
                    المجموع: {{ number_format(collect($cart)->sum(fn($i) => ($i['price'] ?? 0) * ($i['quantity'] ?? 1))) }} د.ع
                </div>
            </div>
            @endif

            @if(!empty($customerData))
            <div class="ai-section">
                <div class="ai-section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px; display: inline-block; margin-left: 8px; vertical-align: middle;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    بيانات العميل
                </div>
                @if(!empty($customerData['name']))
                <div class="ai-data-item">
                    <span class="label">الاسم:</span>
                    <span class="value">{{ $customerData['name'] }}</span>
                </div>
                @endif
                @if(!empty($customerData['phone']))
                <div class="ai-data-item">
                    <span class="label">الهاتف:</span>
                    <span class="value">{{ $customerData['phone'] }}</span>
                </div>
                @endif
                @if(!empty($customerData['address']))
                <div class="ai-data-item">
                    <span class="label">العنوان:</span>
                    <span class="value">{{ $customerData['address'] }}</span>
                </div>
                @endif
            </div>
            @endif

        </div>
        @endif
    </div>    <!-- Main Chat -->
    <div class="chat-main">
        <!-- Chat Header -->
        <div class="chat-header">
            <a href="{{ route('customer.inbox.index') }}" class="back-btn">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="24" height="24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
            </a>
            <div class="chat-avatar">
                @if($conversation->participant_avatar)
                    <img src="{{ $conversation->participant_avatar }}" alt="{{ $conversation->participant_name }}">
                @else
                    <div class="placeholder">{{ mb_substr($conversation->participant_name ?? 'م', 0, 1) }}</div>
                @endif
                <div class="platform-indicator {{ $conversation->platform }}">
                    @if($conversation->platform === 'instagram')
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069z"/></svg>
                    @elseif($conversation->platform === 'whatsapp')
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    @endif
                </div>
            </div>
            <div class="chat-user-info">
                <div class="chat-user-name">{{ $conversation->participant_name ?? 'مستخدم ' . $conversation->platform }}</div>
                <div class="chat-user-meta">
                    <span class="status-dot"></span>
                    <span>{{ match($conversation->platform) { 'instagram' => 'انستقرام', 'whatsapp' => 'واتساب', default => 'ماسنجر فيسبوك' } }}</span>
                    <span>•</span>
                    <span>{{ $conversation->socialAccount->name ?? '' }}</span>
                </div>
            </div>
            <div class="chat-actions">
                <button type="button" class="chat-action-btn" onclick="refreshParticipantInfo()" title="تحديث بيانات المحادث">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                </button>
                <button type="button" class="chat-action-btn" onclick="refreshMessages()" title="تحديث">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </button>
                <button type="button" class="chat-action-btn" onclick="archiveConversation()" title="أرشفة">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Messages -->
        <div class="messages-area" id="messagesArea">
            @if($conversation->messages->isEmpty())
                <div class="no-messages">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                    </svg>
                    <h3>لا توجد رسائل بعد</h3>
                    <p>ابدأ المحادثة بإرسال رسالة</p>
                </div>
            @else
                @php $lastDate = null; @endphp
                @foreach($conversation->messages as $message)
                    @php
                        $messageDate = $message->created_at->format('Y-m-d');
                        $showDateSeparator = $lastDate !== $messageDate;
                        $lastDate = $messageDate;
                    @endphp

                    @if($showDateSeparator)
                        <div class="date-separator">
                            <span>
                                @if($message->created_at->isToday())
                                    اليوم
                                @elseif($message->created_at->isYesterday())
                                    أمس
                                @else
                                    {{ $message->created_at->translatedFormat('l j F') }}
                                @endif
                            </span>
                        </div>
                    @endif

                    <div class="message-wrapper {{ $message->direction }} {{ $conversation->platform }}" data-message-id="{{ $message->id }}">
                        <div class="message-bubble">
                            @if($message->content)
                                {!! nl2br(e($message->content)) !!}
                            @endif

                            @if($message->attachments && is_array($message->attachments))
                                @foreach($message->attachments as $attachment)
                                    <div class="message-attachment">
                                        @if(($attachment['type'] ?? '') === 'image' && isset($attachment['url']))
                                            <img src="{{ $attachment['url'] }}" alt="صورة المنتج" loading="lazy" onclick="window.open(this.src, '_blank')">
                                            @if(!empty($attachment['product_name']))
                                                <span class="product-label">{{ $attachment['product_name'] }}</span>
                                            @endif
                                        @elseif(($attachment['type'] ?? '') === 'video' && isset($attachment['url']))
                                            <video src="{{ $attachment['url'] }}" controls></video>
                                        @elseif(($attachment['type'] ?? '') === 'sticker')
                                            <span style="font-size: 3rem;">😊</span>
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                        <div class="message-time">
                            @if($message->direction === 'outgoing')
                                <span class="message-status {{ $message->status }}">
                                    @if($message->status === 'read')
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path d="M1 12l5 5L17 6M7 12l5 5L23 6" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    @elseif($message->status === 'delivered')
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path d="M1 12l5 5L17 6M7 12l5 5L23 6" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path d="M5 12l5 5L20 7" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    @endif
                                </span>
                            @endif
                            {{ $message->created_at->format('h:i A') }}
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        <!-- Typing Indicator -->
        <div class="typing-indicator" id="typingIndicator">
            <div class="typing-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <span>يكتب...</span>
        </div>

        <!-- Compose Area -->
        <div class="compose-area">
            <form class="compose-form" id="composeForm" onsubmit="sendMessage(event)">
                @csrf
                <div class="compose-actions">
                    <input type="file" id="imageInput" accept="image/*" style="display: none;" onchange="sendImage(this)">
                    <button type="button" class="compose-action-btn" title="إرفاق صورة" onclick="document.getElementById('imageInput').click()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                    </button>
                    <button type="button" class="compose-action-btn" title="إيموجي">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z" />
                        </svg>
                    </button>
                </div>
                <div class="compose-input-wrapper">
                    <textarea
                        class="compose-input"
                        id="messageInput"
                        placeholder="اكتب رسالتك هنا..."
                        rows="1"
                        onkeydown="handleKeyDown(event)"
                        oninput="autoResize(this)"
                    ></textarea>
                </div>
                <button type="submit" class="send-btn" id="sendBtn" title="إرسال">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                    </svg>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- New Message Toast -->
<div class="new-message-toast" id="newMessageToast" onclick="scrollToBottom()">
    رسالة جديدة ↓
</div>
@endsection

@section('scripts')
<script>
    const conversationId = {{ $conversation->id }};
    const userId = {{ auth()->id() }};
    const platform = '{{ $conversation->platform }}';
    const messagesArea = document.getElementById('messagesArea');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const newMessageToast = document.getElementById('newMessageToast');
    let lastMessageCount = messagesArea.querySelectorAll('.message-wrapper').length;
    let isAtBottom = true;

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        scrollToBottom();
        messageInput.focus();

        // Track scroll position
        messagesArea.addEventListener('scroll', function() {
            const threshold = 100;
            isAtBottom = (messagesArea.scrollHeight - messagesArea.scrollTop - messagesArea.clientHeight) < threshold;
            if (isAtBottom) {
                hideNewMessageToast();
            }
        });
    });

    function scrollToBottom(smooth = false) {
        if (smooth) {
            messagesArea.scrollTo({
                top: messagesArea.scrollHeight,
                behavior: 'smooth'
            });
        } else {
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }
        hideNewMessageToast();
    }

    function showNewMessageToast() {
        newMessageToast.classList.add('show');
    }

    function hideNewMessageToast() {
        newMessageToast.classList.remove('show');
    }

    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }

    function handleKeyDown(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage(event);
        }
    }

    // Send image function
    function sendImage(input) {
        if (!input.files || !input.files[0]) return;

        const file = input.files[0];

        // Validate file size (10MB max)
        if (file.size > 10 * 1024 * 1024) {
            showNotification('حجم الصورة كبير جداً. الحد الأقصى 10 ميجابايت', 'error');
            input.value = '';
            return;
        }

        // Create a preview URL
        const previewUrl = URL.createObjectURL(file);

        // Add image message to UI immediately (optimistic update)
        const tempId = 'temp-img-' + Date.now();
        addImageMessageToUI({
            id: tempId,
            imageUrl: previewUrl,
            direction: 'outgoing',
            formatted_time: formatTime(new Date()),
            status: 'sending',
            platform: platform
        });
        scrollToBottom(true);

        // Create form data
        const formData = new FormData();
        formData.append('image', file);
        formData.append('_token', '{{ csrf_token() }}');

        // Send to server
        fetch('{{ route('customer.inbox.send-image', $conversation) }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            const tempMessage = document.querySelector(`[data-message-id="${tempId}"]`);
            if (data.success) {
                // Update the message with real data
                if (tempMessage) {
                    tempMessage.setAttribute('data-message-id', data.message.id);
                    const statusEl = tempMessage.querySelector('.message-status');
                    if (statusEl) {
                        statusEl.classList.remove('sending');
                        statusEl.classList.add('sent');
                        statusEl.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
                    }
                }
                lastMessageCount++;
                showNotification('تم إرسال الصورة بنجاح', 'success');
            } else {
                // Show error state
                if (tempMessage) {
                    tempMessage.querySelector('.message-bubble').style.opacity = '0.5';
                }
                showNotification(data.error || 'فشل في إرسال الصورة', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('حدث خطأ أثناء إرسال الصورة', 'error');
        })
        .finally(() => {
            // Clear the file input
            input.value = '';
            // Revoke the preview URL to free memory
            URL.revokeObjectURL(previewUrl);
        });
    }

    function addImageMessageToUI(message) {
        // Remove "no messages" state if exists
        const noMessages = messagesArea.querySelector('.no-messages');
        if (noMessages) {
            noMessages.remove();
        }

        const wrapper = document.createElement('div');
        wrapper.className = `message-wrapper ${message.direction} ${message.platform || platform}`;
        wrapper.setAttribute('data-message-id', message.id);
        wrapper.style.animation = 'messageIn 0.3s ease-out';

        const statusIcon = message.direction === 'outgoing'
            ? `<span class="message-status ${message.status || 'sending'}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M5 12l5 5L20 7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
               </span>`
            : '';

        wrapper.innerHTML = `
            <div class="message-bubble">
                <div class="message-attachment">
                    <img src="${message.imageUrl}" alt="صورة" loading="lazy" style="max-width: 100%; max-height: 300px; border-radius: 8px;">
                </div>
            </div>
            <div class="message-time">
                ${statusIcon}
                ${message.formatted_time}
            </div>
        `;

        messagesArea.appendChild(wrapper);
    }

    function sendMessage(event) {
        event.preventDefault();

        const content = messageInput.value.trim();
        if (!content) return;

        sendBtn.disabled = true;
        messageInput.disabled = true;

        // Add message to UI immediately (optimistic update)
        const tempId = 'temp-' + Date.now();
        addMessageToUI({
            id: tempId,
            content: content,
            direction: 'outgoing',
            formatted_time: formatTime(new Date()),
            status: 'sending',
            platform: platform
        });

        // Clear input
        messageInput.value = '';
        messageInput.style.height = 'auto';
        scrollToBottom(true);

        // Send to server
        fetch('{{ route('customer.inbox.send', $conversation) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ content: content })
        })
        .then(response => response.json())
        .then(data => {
            const tempMessage = document.querySelector(`[data-message-id="${tempId}"]`);
            if (data.success) {
                // Update the message with real data
                if (tempMessage) {
                    tempMessage.setAttribute('data-message-id', data.message.id);
                    const statusEl = tempMessage.querySelector('.message-status');
                    if (statusEl) {
                        statusEl.classList.remove('sending');
                        statusEl.classList.add('sent');
                        statusEl.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
                    }
                }
                lastMessageCount++;
            } else {
                // Show error state
                if (tempMessage) {
                    tempMessage.querySelector('.message-bubble').style.opacity = '0.5';
                }
                showNotification(data.error || 'فشل في إرسال الرسالة', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('حدث خطأ أثناء إرسال الرسالة', 'error');
        })
        .finally(() => {
            sendBtn.disabled = false;
            messageInput.disabled = false;
            messageInput.focus();
        });
    }

    function addMessageToUI(message) {
        // Remove "no messages" state if exists
        const noMessages = messagesArea.querySelector('.no-messages');
        if (noMessages) {
            noMessages.remove();
        }

        const wrapper = document.createElement('div');
        wrapper.className = `message-wrapper ${message.direction} ${message.platform || platform}`;
        wrapper.setAttribute('data-message-id', message.id);
        wrapper.style.animation = 'messageIn 0.3s ease-out';

        const statusIcon = message.direction === 'outgoing'
            ? `<span class="message-status ${message.status || 'sending'}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M5 12l5 5L20 7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
               </span>`
            : '';

        // Build attachments HTML if exists
        let attachmentsHtml = '';
        if (message.attachments && Array.isArray(message.attachments)) {
            message.attachments.forEach(attachment => {
                if (attachment.type === 'image' && attachment.url) {
                    const productLabel = attachment.product_name
                        ? `<span class="product-label">${escapeHtml(attachment.product_name)}</span>`
                        : '';
                    attachmentsHtml += `
                        <div class="message-attachment">
                            <img src="${attachment.url}" alt="صورة المنتج" loading="lazy" onclick="window.open(this.src, '_blank')">
                            ${productLabel}
                        </div>
                    `;
                } else if (attachment.type === 'video' && attachment.url) {
                    attachmentsHtml += `
                        <div class="message-attachment">
                            <video src="${attachment.url}" controls></video>
                        </div>
                    `;
                }
            });
        }

        // Build content (only show if not empty)
        const contentHtml = message.content ? escapeHtml(message.content).replace(/\n/g, '<br>') : '';

        wrapper.innerHTML = `
            <div class="message-bubble">
                ${contentHtml}
                ${attachmentsHtml}
            </div>
            <div class="message-time">
                ${statusIcon}
                ${message.formatted_time}
            </div>
        `;

        messagesArea.appendChild(wrapper);
    }

    function formatTime(date) {
        return date.toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit', hour12: true });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function refreshMessages() {
        location.reload();
    }

    function refreshParticipantInfo() {
        showNotification('جاري تحديث بيانات المحادث...', 'info');

        fetch('{{ route('customer.inbox.refresh', $conversation) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('تم تحديث بيانات المحادث بنجاح', 'success');
                // Update the UI with the new name
                if (data.participant_name) {
                    document.querySelectorAll('.chat-user-name, .sidebar-name').forEach(el => {
                        if (el.closest('.active') || el.classList.contains('chat-user-name')) {
                            el.textContent = data.participant_name;
                        }
                    });
                    // Update the avatar placeholder letter
                    document.querySelectorAll('.chat-avatar .placeholder, .sidebar-avatar .placeholder').forEach(el => {
                        if (el.closest('.active') || el.closest('.chat-avatar')) {
                            el.textContent = data.participant_name.charAt(0);
                        }
                    });
                    // Update avatar image if available
                    if (data.participant_avatar) {
                        document.querySelectorAll('.chat-avatar, .sidebar-avatar').forEach(el => {
                            if (el.closest('.active') || el.closest('.chat-header')) {
                                const placeholder = el.querySelector('.placeholder');
                                if (placeholder) {
                                    const img = document.createElement('img');
                                    img.src = data.participant_avatar;
                                    img.alt = data.participant_name;
                                    placeholder.replaceWith(img);
                                }
                            }
                        });
                    }
                }
            } else {
                showNotification(data.error || 'فشل في تحديث بيانات المحادث', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('حدث خطأ أثناء تحديث البيانات', 'error');
        });
    }

    function archiveConversation() {
        if (!confirm('هل أنت متأكد من أرشفة هذه المحادثة؟')) return;

        fetch('{{ route('customer.inbox.archive', $conversation) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('تم أرشفة المحادثة بنجاح', 'success');
                setTimeout(() => {
                    window.location.href = '{{ route('customer.inbox.index') }}';
                }, 1000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('حدث خطأ أثناء الأرشفة', 'error');
        });
    }

    function showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            z-index: 1000;
            animation: slideDown 0.3s ease;
            ${type === 'success' ? 'background: #25D366; color: white;' : ''}
            ${type === 'error' ? 'background: #dc3545; color: white;' : ''}
            ${type === 'info' ? 'background: #0084ff; color: white;' : ''}
        `;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideUp 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Auto-refresh messages every 8 seconds
    let refreshInterval = setInterval(function() {
        fetch('{{ route('customer.inbox.messages', $conversation) }}', {
            headers: {
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages) {
                const newCount = data.messages.length;
                if (newCount > lastMessageCount) {
                    // New messages received
                    const newMessages = data.messages.slice(lastMessageCount);
                    newMessages.forEach(msg => {
                        // Only add if not already in DOM
                        if (!document.querySelector(`[data-message-id="${msg.id}"]`)) {
                            addMessageToUI(msg);
                        }
                    });
                    lastMessageCount = newCount;

                    if (isAtBottom) {
                        scrollToBottom(true);
                    } else {
                        showNewMessageToast();
                    }

                    // Play notification sound for incoming messages
                    const lastNewMsg = newMessages[newMessages.length - 1];
                    if (lastNewMsg && lastNewMsg.direction === 'incoming') {
                        playNotificationSound();
                    }
                }
            }
        })
        .catch(error => console.error('Error checking messages:', error));
    }, 8000);

    function playNotificationSound() {
        // Create a simple beep sound
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            gainNode.gain.value = 0.1;

            oscillator.start();
            oscillator.stop(audioContext.currentTime + 0.1);
        } catch (e) {
            // Audio not supported
        }
    }

    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        clearInterval(refreshInterval);
    });

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideDown {
            from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
        @keyframes slideUp {
            from { opacity: 1; transform: translateX(-50%) translateY(0); }
            to { opacity: 0; transform: translateX(-50%) translateY(-20px); }
        }
    `;
    document.head.appendChild(style);
</script>
@endsection
