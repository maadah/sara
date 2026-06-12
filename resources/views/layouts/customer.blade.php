<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'لوحة التحكم - سارة')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Cairo', sans-serif; }
        
        /* تموضع القائمة الجانبية للهاتف بناءً على المسافة النسبية للشاشة */
        @media (max-width: 1023px) {
            #sidebar {
                transition: right 0.4s ease-in-out !important;
                transform: none !important;
            }
            #sidebar[data-sidebar-state="closed"] {
                /* إزاحة حقيقية بالبكسل لتجنب ظهور القائمة عند التصغير (Zoom) */
                right: -350px !important; 
            }
            #sidebar[data-sidebar-state="open"] {
                right: 0 !important; 
            }
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('styles')
</head>
<body class="bg-gray-50 w-full max-w-[100vw] overflow-x-hidden">
    <!-- Mobile Sidebar Overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/40 z-[9998] hidden lg:hidden"></div>

    <!-- Sidebar -->
    <aside id="sidebar" data-sidebar-state="closed" class="fixed top-0 right-0 w-[80%] max-w-[320px] lg:w-[264px] h-full bg-white border-l border-gray-100 flex flex-col shadow-sm z-[9999]">
        <!-- Logo -->
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <a href="{{ route('customer.dashboard') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/1040X1040-png.png') }}" alt="سارة" class="w-10 h-10 object-contain">
                <span class="text-xl font-bold text-gray-800">سارة</span>
            </a>
            <!-- Mobile Close Button -->
            <button id="sidebarClose" class="lg:hidden p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-xl transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
                <a href="{{ route('customer.dashboard') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.dashboard') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
                    <span>الرئيسية</span>
                </a>

                <a href="{{ route('customer.products.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.products*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg>
                    <span>المخزون</span>
                </a>

                @php
                    $pendingOrdersCount = \App\Models\OnlineOrder::where('user_id', auth()->id())->where('status', 'pending')->count();
                @endphp
                <a href="{{ route('customer.online-orders.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.online-orders*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>
                    <span>الطلبات</span>
                    @if($pendingOrdersCount > 0)
                        <span class="mr-auto bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">{{ $pendingOrdersCount }}</span>
                    @endif
                </a>

                <a href="{{ route('customer.categories.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.categories*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                    <span>الفئات</span>
                </a>

                <a href="{{ route('customer.sales.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.sales*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                    <span>المبيعات</span>
                </a>

                <a href="{{ route('customer.reports.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.reports*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
                    <span>التقارير</span>
                </a>

                <div class="pt-3 mt-3 border-t border-gray-100">
                    <p class="px-4 text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">الاشتراك والفوترة</p>
                </div>

                <a href="{{ route('customer.subscription.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.subscription*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>
                    <span>باقة الاشتراك</span>
                </a>


                <div class="pt-3 mt-3 border-t border-gray-100">
                    <p class="px-4 text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">التواصل</p>
                </div>

                <a href="{{ route('customer.inbox.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.inbox*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>
                    <span>صندوق الرسائل</span>
                    @if(auth()->user()->unread_conversations_count > 0)
                        <span class="mr-auto bg-[#00A8E8] text-white text-xs px-2 py-0.5 rounded-full">{{ auth()->user()->unread_conversations_count }}</span>
                    @endif
                </a>

                @php
                    $newLeadsCount = \App\Models\Lead::where('user_id', auth()->id())->where('status', 'new')->count();
                @endphp
                <a href="{{ route('customer.leads.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.leads*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>
                    <span>العملاء</span>
                    @if($newLeadsCount > 0)
                        <span class="mr-auto bg-green-500 text-white text-xs px-2 py-0.5 rounded-full">{{ $newLeadsCount }}</span>
                    @endif
                </a>

                <a href="{{ route('customer.comment-replies.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.comment-replies*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>
                    <span>ردود التعليقات</span>
                </a>

                <a href="{{ route('customer.social-accounts.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.social-accounts*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>
                    <span>الحسابات المرتبطة</span>
                </a>

                <div class="pt-3 mt-3 border-t border-gray-100">
                    <p class="px-4 text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">الأدوات</p>
                </div>

                @php
                    $pendingQuestionsCount = \App\Models\UnansweredQuestion::where('user_id', auth()->id())->where('is_reviewed', false)->count();
                @endphp
                <a href="{{ route('customer.ai-helper.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.ai-helper*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" /></svg>
                    <span>مساعد AI</span>
                    @if($pendingQuestionsCount > 0)
                        <span class="mr-auto bg-amber-500 text-white text-xs px-2 py-0.5 rounded-full">{{ $pendingQuestionsCount }}</span>
                    @endif
                </a>

                <a href="{{ route('customer.inventory.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.inventory*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <span>إدارة المخزون</span>
                </a>

                <a href="{{ route('customer.competitors.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.competitors*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5m.75-9l3-3 2.148 2.148A12.061 12.061 0 0116.5 7.605" /></svg>
                    <span>تحليل المنافسين</span>
                </a>

                <a href="{{ route('customer.team.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.team*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>
                    <span>فريق العمل</span>
                </a>

                <a href="{{ route('customer.pos.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.pos*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" /></svg>
                    <span>نقطة البيع</span>
                </a>

                <div class="pt-3 mt-3 border-t border-gray-100">
                    <p class="px-4 text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">ميزات إضافية</p>
                </div>

                <a href="{{ route('customer.analytics.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.analytics*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z"/></svg>
                    <span>التحليلات</span>
                </a>

                <a href="{{ route('customer.services.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.services*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-5.073-2.683a.75.75 0 010-1.324l5.073-2.683a2.25 2.25 0 012.16 0l5.073 2.683a.75.75 0 010 1.324l-5.073 2.683a2.25 2.25 0 01-2.16 0zM4.5 12.75l6.92 3.654a2.25 2.25 0 002.16 0L20.5 12.75"/></svg>
                    <span>الخدمات</span>
                </a>

                <a href="{{ route('customer.broadcasts.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.broadcasts*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46"/></svg>
                    <span>حملات البث</span>
                </a>

                <a href="{{ route('customer.settings.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('customer.settings*') ? 'bg-[#00A8E8]/10 text-[#00A8E8]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>الإعدادات</span>
                </a>
            </nav>

            <!-- User Profile at Bottom -->
            <div class="p-4 border-t border-gray-100">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded-xl transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                        <span>تسجيل الخروج</span>
                    </button>
                </form>
            </div>
        </aside>

    <div class="flex min-h-screen w-full">
        <!-- Main Content -->
        <main class="flex-1 lg:mr-[264px]">
            <!-- Header -->
            <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-gray-100">
                <div class="flex items-center justify-between px-4 lg:px-8 py-4">
                    <div class="flex items-center gap-3">
                        <!-- Mobile Hamburger Menu -->
                        <button id="sidebarToggle" class="lg:hidden p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-xl transition-all">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                        </button>
                        <h1 class="text-base lg:text-lg font-bold text-gray-900">@yield('page-title', 'لوحة التحكم')</h1>
                    </div>
                    <div class="flex items-center gap-2 lg:gap-4">
                        @php
                            $unreadNotifications = \App\Models\Notification::where('user_id', auth()->id())->unread()->count();
                        @endphp
                        <a href="{{ route('customer.notifications.index') }}" class="relative p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-xl transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
                            @if($unreadNotifications > 0)
                                <span class="absolute -top-1 -left-1 w-5 h-5 bg-red-500 text-white text-[10px] flex items-center justify-center rounded-full">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                            @endif
                        </a>

                        @if(auth()->user()->status === 'approved')
                            <a href="{{ route('customer.subscription.index') }}" class="hidden sm:inline-flex px-3 lg:px-4 py-2 bg-green-50 text-green-700 text-xs lg:text-sm font-medium rounded-xl hover:bg-green-100 transition-all">اشتراكي</a>
                        @else
                            <a href="{{ route('customer.subscription.index') }}" class="hidden sm:inline-flex px-3 lg:px-4 py-2 bg-amber-50 text-amber-700 text-xs lg:text-sm font-medium rounded-xl hover:bg-amber-100 transition-all">الحساب غير مفعل</a>
                        @endif

                        <a href="{{ route('customer.profile') }}" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-xl transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Alerts -->
            <div class="px-4 lg:px-8 pt-4">
                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm flex items-center gap-2">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm flex items-center gap-2">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif
            </div>

            <!-- Content -->
            <div class="p-4 lg:p-8">
                @yield('content')
            </div>
        </main>
    </div>

    @yield('scripts')
    @stack('scripts')

    <script>
        (function () {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            var toggleBtn = document.getElementById('sidebarToggle');
            var closeBtn = document.getElementById('sidebarClose');

            function openSidebar() {
                // Set explicit open state
                sidebar.setAttribute('data-sidebar-state', 'open');
                
                overlay.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }

            function closeSidebar() {
                // Set explicit closed state
                sidebar.setAttribute('data-sidebar-state', 'closed');
                
                overlay.classList.add('hidden');
                document.body.style.overflow = '';
            }

            if (toggleBtn) toggleBtn.addEventListener('click', openSidebar);
            if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
            if (overlay) overlay.addEventListener('click', closeSidebar);

            // Auto-close sidebar on nav link click (mobile)
            sidebar.querySelectorAll('nav a').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (!window.matchMedia('(min-width: 1024px)').matches) closeSidebar();
                });
            });

            // Handle screen size changes robustly without resize event which triggers on mobile zoom
            var mql = window.matchMedia('(min-width: 1024px)');
            mql.addEventListener('change', function(e) {
                // Whether switching to mobile or desktop, ensure mobile overlay and classes are reset
                sidebar.setAttribute('data-sidebar-state', 'closed');
                
                overlay.classList.add('hidden');
                document.body.style.overflow = '';
            });

            // Make tables responsive by injecting data-labels
            document.querySelectorAll('table').forEach(function(table) {
                var headers = [];
                table.querySelectorAll('thead th').forEach(function(th) {
                    headers.push(th.innerText.trim());
                });
                if (headers.length > 0) {
                    table.querySelectorAll('tbody tr').forEach(function(tr) {
                        tr.querySelectorAll('td').forEach(function(td, index) {
                            if (headers[index] && !td.getAttribute('data-label')) {
                                td.setAttribute('data-label', headers[index]);
                            }
                        });
                    });
                }
            });
        })();
    </script>
    
    @auth
        @php
            $u = auth()->user();
            $s = $u->settings ?? [];
            $audioEnabled = $s['sound_notifications_enabled'] ?? true;
            $audioVol = ($s['sound_volume'] ?? 80) / 100;
        @endphp
        <!-- Pusher & Echo via CDN for immediate no-build execution -->
        <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.0/dist/echo.iife.js"></script>
        
        <script>
            window.AppConfig = {
                user_id: {{ $u->id }},
                audioEnabled: @json($audioEnabled),
                audioVol: {{ $audioVol }},
                sounds: {
                    order: "{{ asset('sounds/' . ($s['sound_order'] ?? 'bell.mp3')) }}",
                    message: "{{ asset('sounds/' . ($s['sound_message'] ?? 'pop.mp3')) }}",
                    comment: "{{ asset('sounds/' . ($s['sound_comment'] ?? 'click.mp3')) }}"
                },
                reverb: {
                    host: "{{ config('broadcasting.connections.reverb.options.host', env('REVERB_HOST', 'localhost')) }}",
                    port: {{ config('broadcasting.connections.reverb.options.port', env('REVERB_PORT', 8080)) }},
                    scheme: "{{ config('broadcasting.connections.reverb.options.scheme', env('REVERB_SCHEME', 'http')) }}",
                    appKey: "{{ config('broadcasting.connections.reverb.key', env('REVERB_APP_KEY')) }}"
                }
            };
            
            if (window.AppConfig.user_id && window.AppConfig.reverb.appKey) {
                // Determine ws/wss hosts dynamic to prevent hardcoding localhost on production
                let wsHost = window.AppConfig.reverb.host;
                if (wsHost === 'localhost' || wsHost === '127.0.0.1') {
                    wsHost = window.location.hostname;
                }
            
                window.EchoInstance = new Echo({
                    broadcaster: 'reverb',
                    key: window.AppConfig.reverb.appKey,
                    wsHost: wsHost,
                    wsPort: window.AppConfig.reverb.port,
                    wssPort: window.AppConfig.reverb.port,
                    forceTLS: (window.AppConfig.reverb.scheme === 'https'),
                    enabledTransports: ['ws', 'wss'],
                    authEndpoint: '/broadcasting/auth',
                    auth: {
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    }
                });

                // Audio Context Initialization (Browsers require user gesture)
                let audioCtx = null;
                const initializeAudio = () => {
                    if(!audioCtx) {
                        try {
                            window.AudioContext = window.AudioContext || window.webkitAudioContext;
                            audioCtx = new AudioContext();
                        } catch(e) {}
                    }
                };
                
                // Attach user gesture listeners to unlock Audio
                ['click', 'touchstart', 'keydown'].forEach(evt => 
                    document.addEventListener(evt, initializeAudio, {once:true})
                );

                window.playNotificationSound = function(type) {
                    if (!window.AppConfig.audioEnabled) return;
                    
                    const soundUrl = window.AppConfig.sounds[type];
                    if (!soundUrl) return;
                    
                    const audio = new Audio(soundUrl);
                    audio.volume = window.AppConfig.audioVol;
                    
                    const playPromise = audio.play();
                    if (playPromise !== undefined) {
                        playPromise.catch(err => {
                            console.warn("Autoplay blocked. User needs to interact with the page first.", err);
                        });
                    }
                };
                
                window.showToastNotification = function(title, text) {
                     const container = document.getElementById('audio-toast-container') || (function() {
                         const c = document.createElement('div');
                         c.id = 'audio-toast-container';
                         c.className = 'fixed top-4 left-4 z-[9999] space-y-3'; // Top left for arabic RTL
                         document.body.appendChild(c);
                         return c;
                     })();
                     
                     const toast = document.createElement('div');
                     toast.className = 'bg-white rounded-xl shadow-[0_8px_30px_rgb(0,0,0,0.12)] border-r-4 border-[#00A8E8] p-4 pl-5 -translate-x-[150%] transition-transform duration-500 ease-out flex items-start gap-3 w-80';
                     toast.innerHTML = `
                         <div class="bg-[#00A8E8]/10 text-[#00A8E8] rounded-full p-2 mt-0.5 animate-pulse">
                             <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                         </div>
                         <div class="flex-1 min-w-0">
                             <h4 class="text-sm font-bold text-gray-800 truncate">${title}</h4>
                             <p class="text-xs text-gray-500 mt-1 line-clamp-2">${text}</p>
                         </div>
                         <button onclick="this.parentElement.style.transform='translateX(-150%)'; setTimeout(()=>this.parentElement.remove(), 500)" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                             <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                         </button>
                     `;
                     
                     container.appendChild(toast);
                     
                     requestAnimationFrame(() => {
                         toast.style.transform = 'translateX(0)';
                     });
                     
                     setTimeout(() => {
                         if (toast.parentElement) {
                             toast.style.transform = 'translate-x(-150%)';
                             setTimeout(() => { if(toast.parentElement) toast.remove(); }, 500);
                         }
                     }, 6000);
                }

                // Listen on Private Channel
                window.EchoInstance.private('user.' + window.AppConfig.user_id)
                    .listen('.NewOrderReceived', (event) => {
                        window.playNotificationSound('order');
                        window.showToastNotification('طلب حصري جديد 📦!', event.message || 'لديك طلب جديد، تفقد لوحة التحكم');
                    })
                    .listen('.NewMessageReceived', (event) => {
                        window.playNotificationSound('message');
                        window.showToastNotification('رسالة من ' + (event.senderName || 'عميل') + ' (' + event.platform + ')', event.preview || 'رسالة جديدة 💬');
                    })
                    .listen('.NewCommentReceived', (event) => {
                        window.playNotificationSound('comment');
                        window.showToastNotification('تعليق جديد (' + event.platform + ')', event.message || 'تعليق جديد على منتج 💬');
                    });
            }
        </script>
    @endauth
</body>
</html>
