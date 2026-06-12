@extends('layouts.customer')

@section('title', 'الإعدادات')

@section('content')
<div class="max-w-4xl mx-auto space-y-8">
    <div>
        <h2 class="text-3xl font-bold text-gray-800">الإعدادات</h2>
        <p class="text-gray-500 mt-1">إعدادات المتجر والإشعارات</p>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-green-800">
            {{ session('success') }}
        </div>
    @endif

    <form action="{{ route('customer.settings.update') }}" method="POST" class="space-y-8">
        @csrf
        @method('PUT')

        {{-- Store Info --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-[#00A8E8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                معلومات المتجر
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">اسم المتجر</label>
                    <input type="text" name="store_name" value="{{ old('store_name', $settings['store_name'] ?? $user->name) }}"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">رقم الهاتف</label>
                    <input type="text" name="store_phone" value="{{ old('store_phone', $settings['store_phone'] ?? '') }}"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">وصف المتجر</label>
                    <textarea name="store_description" rows="3"
                              class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all">{{ old('store_description', $settings['store_description'] ?? '') }}</textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">العنوان</label>
                    <input type="text" name="store_address" value="{{ old('store_address', $settings['store_address'] ?? '') }}"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all">
                </div>
            </div>
        </div>

        {{-- Regional Settings --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-[#00A8E8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                الإعدادات الإقليمية
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">العملة</label>
                    <select name="currency" class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all">
                        <option value="IQD" {{ ($settings['currency'] ?? 'IQD') === 'IQD' ? 'selected' : '' }}>دينار عراقي (IQD)</option>
                        <option value="USD" {{ ($settings['currency'] ?? '') === 'USD' ? 'selected' : '' }}>دولار أمريكي (USD)</option>
                        <option value="SAR" {{ ($settings['currency'] ?? '') === 'SAR' ? 'selected' : '' }}>ريال سعودي (SAR)</option>
                        <option value="AED" {{ ($settings['currency'] ?? '') === 'AED' ? 'selected' : '' }}>درهم إماراتي (AED)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">اللغة</label>
                    <select name="language" class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all">
                        <option value="ar" {{ ($settings['language'] ?? 'ar') === 'ar' ? 'selected' : '' }}>العربية</option>
                        <option value="en" {{ ($settings['language'] ?? '') === 'en' ? 'selected' : '' }}>English</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">المنطقة الزمنية</label>
                    <select name="timezone" class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all">
                        <option value="Asia/Baghdad" {{ ($settings['timezone'] ?? 'Asia/Baghdad') === 'Asia/Baghdad' ? 'selected' : '' }}>بغداد (GMT+3)</option>
                        <option value="Asia/Riyadh" {{ ($settings['timezone'] ?? '') === 'Asia/Riyadh' ? 'selected' : '' }}>الرياض (GMT+3)</option>
                        <option value="Asia/Dubai" {{ ($settings['timezone'] ?? '') === 'Asia/Dubai' ? 'selected' : '' }}>دبي (GMT+4)</option>
                        <option value="Africa/Cairo" {{ ($settings['timezone'] ?? '') === 'Africa/Cairo' ? 'selected' : '' }}>القاهرة (GMT+2)</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Notification Settings --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-[#00A8E8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                إعدادات الإشعارات
            </h3>
            <div class="space-y-4">
                <label class="flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors">
                    <div>
                        <p class="font-medium text-gray-800">إشعارات الطلبات</p>
                        <p class="text-xs text-gray-500">تلقي إشعار عند استلام طلب جديد</p>
                    </div>
                    <input type="hidden" name="order_notifications" value="0">
                    <input type="checkbox" name="order_notifications" value="1"
                           {{ ($settings['order_notifications'] ?? true) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-[#00A8E8] focus:ring-[#00A8E8] w-5 h-5">
                </label>
                <label class="flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors">
                    <div>
                        <p class="font-medium text-gray-800">إشعارات الرسائل</p>
                        <p class="text-xs text-gray-500">تلقي إشعار عند وصول رسالة جديدة</p>
                    </div>
                    <input type="hidden" name="message_notifications" value="0">
                    <input type="checkbox" name="message_notifications" value="1"
                           {{ ($settings['message_notifications'] ?? true) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-[#00A8E8] focus:ring-[#00A8E8] w-5 h-5">
                </label>
                <label class="flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors">
                    <div>
                        <p class="font-medium text-gray-800">تنبيه نقص المخزون</p>
                        <p class="text-xs text-gray-500">تلقي إشعار عندما ينخفض المخزون</p>
                    </div>
                    <input type="hidden" name="low_stock_notifications" value="0">
                    <input type="checkbox" name="low_stock_notifications" value="1"
                           {{ ($settings['low_stock_notifications'] ?? true) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-[#00A8E8] focus:ring-[#00A8E8] w-5 h-5">
                </label>
            </div>

            <hr class="my-6 border-gray-100">

            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h4 class="text-md font-bold text-gray-800">التنبيهات الصوتية المباشرة</h4>
                    <p class="text-xs text-gray-500">سماع رنين فوري عند فتح النظام في المتصفح أو الهاتف</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="sound_notifications_enabled" value="0">
                    <input type="checkbox" name="sound_notifications_enabled" value="1" class="sr-only peer" {{ ($settings['sound_notifications_enabled'] ?? true) ? 'checked' : '' }}>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[#00A8E8]/30 rounded-full peer peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#00A8E8]"></div>
                </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">نغمة الطلبات</label>
                    <select name="sound_order" class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all">
                        <option value="bell.mp3" {{ ($settings['sound_order'] ?? 'bell.mp3') === 'bell.mp3' ? 'selected' : '' }}>جرس (Bell)</option>
                        <option value="chime.mp3" {{ ($settings['sound_order'] ?? '') === 'chime.mp3' ? 'selected' : '' }}>رنين (Chime)</option>
                        <option value="notification.mp3" {{ ($settings['sound_order'] ?? '') === 'notification.mp3' ? 'selected' : '' }}>إشعار (Notification)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">نغمة الرسائل</label>
                    <select name="sound_message" class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all">
                        <option value="pop.mp3" {{ ($settings['sound_message'] ?? 'pop.mp3') === 'pop.mp3' ? 'selected' : '' }}>فقاعة (Pop)</option>
                        <option value="message.mp3" {{ ($settings['sound_message'] ?? '') === 'message.mp3' ? 'selected' : '' }}>رسالة (Message)</option>
                        <option value="notification.mp3" {{ ($settings['sound_message'] ?? '') === 'notification.mp3' ? 'selected' : '' }}>إشعار (Notification)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">نغمة التعليقات</label>
                    <select name="sound_comment" class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-[#00A8E8] focus:ring-2 focus:ring-[#00A8E8]/20 transition-all">
                        <option value="click.mp3" {{ ($settings['sound_comment'] ?? 'click.mp3') === 'click.mp3' ? 'selected' : '' }}>نقر (Click)</option>
                        <option value="pop.mp3" {{ ($settings['sound_comment'] ?? '') === 'pop.mp3' ? 'selected' : '' }}>فقاعة (Pop)</option>
                        <option value="notification.mp3" {{ ($settings['sound_comment'] ?? '') === 'notification.mp3' ? 'selected' : '' }}>إشعار (Notification)</option>
                    </select>
                </div>
            </div>

            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">مستوى الصوت للمنصة</label>
                <input type="range" name="sound_volume" min="0" max="100" value="{{ $settings['sound_volume'] ?? 80 }}" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
            </div>
        </div>

        {{-- Save --}}
        <div class="flex justify-end">
            <button type="submit" class="bg-[#00A8E8] text-white px-10 py-3 rounded-xl font-bold hover:bg-[#00658B] transition-all shadow-lg">
                حفظ الإعدادات
            </button>
        </div>
    </form>
</div>
@endsection
