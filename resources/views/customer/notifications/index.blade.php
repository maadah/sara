@extends('layouts.customer')

@section('title', 'الإشعارات - لوحة التحكم')

@section('content')
<div class="page-header-bar">
    <h1 class="page-title">الإشعارات</h1>
    @if($unreadCount > 0)
        <form action="{{ route('customer.notifications.markAllRead') }}" method="POST">
            @csrf
            <button type="submit" class="btn-add-sm" style="background: var(--bg-darker); border: 1px solid var(--border-color);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                تحديد الكل كمقروء
            </button>
        </form>
    @endif
</div>

<div class="notifications-list">
    @forelse($notifications as $notification)
        <form action="{{ route('customer.notifications.markRead', $notification) }}" method="POST" class="notification-form">
            @csrf
            <button type="submit" class="notification-item {{ !$notification->is_read ? 'unread' : '' }}">
                <div class="notification-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                </div>
                <div class="notification-content">
                    <div class="notification-header">
                        <h4>{{ $notification->title }}</h4>
                        <span class="notification-date">{{ $notification->created_at->format('Y/m/d') }}</span>
                    </div>
                    <p>{{ $notification->message }}</p>
                    @if(!$notification->is_read)
                        <span class="unread-dot"></span>
                    @endif
                </div>
            </button>
        </form>
    @empty
        <div class="empty-state-new">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
            </svg>
            <h3>لا توجد إشعارات</h3>
            <p>ستظهر هنا الإشعارات المتعلقة بنشاطك</p>
        </div>
    @endforelse

    @if($notifications->hasPages())
        <div class="pagination-wrapper">
            {{ $notifications->links() }}
        </div>
    @endif
</div>

<style>
    .notifications-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .notification-form {
        display: block;
    }

    .notification-item {
        display: flex;
        gap: 15px;
        padding: 20px;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
        width: 100%;
        text-align: right;
        cursor: pointer;
        font-family: inherit;
    }

    .notification-item:hover {
        border-color: var(--primary-green);
        transform: translateX(5px);
    }

    .notification-item.unread {
        background: rgba(37, 211, 102, 0.05);
        border-color: rgba(37, 211, 102, 0.3);
    }

    .notification-icon {
        width: 50px;
        height: 50px;
        background: var(--primary-green);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .notification-icon svg {
        width: 24px;
        height: 24px;
        color: white;
    }

    .notification-content {
        flex: 1;
        position: relative;
    }

    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
    }

    .notification-header h4 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-light);
        margin: 0;
    }

    .notification-date {
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    .notification-content p {
        font-size: 0.9rem;
        color: var(--text-muted);
        line-height: 1.6;
        margin: 0;
    }

    .unread-dot {
        position: absolute;
        top: 50%;
        left: -25px;
        transform: translateY(-50%);
        width: 10px;
        height: 10px;
        background: var(--primary-green);
        border-radius: 50%;
    }
</style>
@endsection
