@extends('layouts.app')

@section('title', 'إنشاء حساب - سارة')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-gray-50 via-blue-50/30 to-gray-50 relative overflow-x-hidden">
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute top-[-10%] right-[-5%] w-96 h-96 bg-[#00A8E8]/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-[-10%] left-[-5%] w-96 h-96 bg-purple-500/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
    </div>

    <div class="relative min-h-screen flex flex-col items-center justify-center px-4 sm:px-6 lg:px-8 py-12 z-10">
        <div class="mb-8">
            <a href="{{ route('home') }}" class="flex flex-col items-center gap-4 group">
                <div class="relative">
                    <div class="absolute inset-0 bg-[#00A8E8]/20 blur-xl rounded-2xl animate-pulse"></div>
                    <img src="{{ asset('images/1040X1040-png.png') }}" alt="سارة" class="relative w-20 h-20 rounded-2xl shadow-2xl transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300 object-contain">
                </div>
                <h1 class="text-4xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-[#00A8E8] to-[#00658B]">سارة</h1>
            </a>
        </div>

        <div class="w-full max-w-lg mx-auto">
            <div class="relative bg-white/90 backdrop-blur-xl shadow-2xl rounded-3xl border border-gray-100/50 overflow-hidden">
                <div class="h-2 bg-gradient-to-r from-[#00A8E8] via-[#007EA7] to-[#00658B]"></div>
                <div class="absolute top-10 right-10 w-32 h-32 bg-[#00A8E8]/5 rounded-full blur-2xl pointer-events-none"></div>
                <div class="absolute bottom-10 left-10 w-32 h-32 bg-purple-500/5 rounded-full blur-2xl pointer-events-none"></div>

                <div class="relative px-8 py-10 sm:px-10 sm:py-12">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2 text-center">إنشاء حساب جديد</h2>
                    <p class="text-gray-500 text-center mb-8">ابدأ رحلتك مع سارة الآن</p>

                    @if($errors->any())
                        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm flex items-center gap-2">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">اسم التاجر</label>
                            <input type="text" name="name" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition-all bg-gray-50/50 @error('name') border-red-300 @enderror" value="{{ old('name') }}" required>
                            @error('name')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">البريد الالكتروني</label>
                            <input type="email" name="email" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition-all bg-gray-50/50 @error('email') border-red-300 @enderror" value="{{ old('email') }}" required>
                            @error('email')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">رقم الهاتف</label>
                                <input type="tel" name="phone" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition-all bg-gray-50/50 @error('phone') border-red-300 @enderror" value="{{ old('phone') }}" required>
                                @error('phone')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">رقم الواتساب</label>
                                <input type="tel" name="whatsapp" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition-all bg-gray-50/50 @error('whatsapp') border-red-300 @enderror" value="{{ old('whatsapp') }}">
                                @error('whatsapp')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">الرقم السري</label>
                                <input type="password" name="password" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition-all bg-gray-50/50 @error('password') border-red-300 @enderror" required>
                                @error('password')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">تأكيد الرقم السري</label>
                                <input type="password" name="password_confirmation" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition-all bg-gray-50/50" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">رابط الفيس بوك</label>
                                <input type="url" name="facebook_link" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition-all bg-gray-50/50 @error('facebook_link') border-red-300 @enderror" value="{{ old('facebook_link') }}">
                                @error('facebook_link')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">رابط الانستجرام</label>
                                <input type="url" name="instagram_link" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition-all bg-gray-50/50 @error('instagram_link') border-red-300 @enderror" value="{{ old('instagram_link') }}">
                                @error('instagram_link')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">عنوان المخزن</label>
                            <input type="text" name="store_address" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition-all bg-gray-50/50 @error('store_address') border-red-300 @enderror" value="{{ old('store_address') }}">
                            @error('store_address')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الباقة</label>
                            <select name="subscription_id" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition-all bg-gray-50/50 @error('subscription_id') border-red-300 @enderror">
                                <option value="">اختر الباقة</option>
                                @foreach($subscriptions as $subscription)
                                    <option value="{{ $subscription->id }}" {{ old('subscription_id') == $subscription->id ? 'selected' : '' }}>
                                        {{ $subscription->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('subscription_id')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="terms" class="w-4 h-4 text-[#00A8E8] border-gray-300 rounded focus:ring-[#00A8E8]" {{ old('terms') ? 'checked' : '' }} required>
                                <span class="text-sm text-gray-600">أوافق على <a href="{{ route('privacy') }}" class="text-[#00A8E8] hover:text-[#007EA7] font-medium">الشروط والأحكام</a></span>
                            </label>
                            @error('terms')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="w-full py-3.5 bg-gradient-to-r from-[#00A8E8] to-[#007EA7] text-white rounded-xl font-bold text-lg shadow-lg shadow-[#00A8E8]/25 hover:shadow-xl hover:shadow-[#00A8E8]/30 hover:-translate-y-0.5 active:translate-y-0 transition-all">
                            إنشاء حساب
                        </button>
                    </form>

                    <div class="mt-8 text-center">
                        <span class="text-gray-500">تمتلك حساب؟</span>
                        <a href="{{ route('login') }}" class="text-[#00A8E8] hover:text-[#007EA7] font-bold mr-1">تسجيل الدخول</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-10 text-center text-sm text-gray-500">
            <p class="flex items-center justify-center gap-2">
                <svg class="w-4 h-4 text-[#00A8E8]" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span>&copy; {{ date('Y') }} منصة سارة. جميع الحقوق محفوظة.</span>
            </p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // مدة الجلسة الافتراضية في لارفيل 120 دقيقة (إعداد SESSION_LIFETIME)
        // سنقوم بتنبيه المستخدم وتحديث الصفحة بعد 115 دقيقة
        const sessionLifetimeInMinutes = {{ config('session.lifetime', 120) }};
        const timeoutInMilliseconds = (sessionLifetimeInMinutes - 5) * 60 * 1000;

        if (timeoutInMilliseconds > 0) {
            setTimeout(function() {
                alert('عذراً، جلسة التسجيل الخاصة بك على وشك الانتهاء لدواعي أمنية. يرجى إعادة تحميل الصفحة لضمان نجاح التسجيل.');
            }, timeoutInMilliseconds);
        }

        // حفظ البيانات المدخلة في sessionStorage لتجنب فقدانها عند تحديث الصفحة أو أخطاء الجلسة
        const form = document.querySelector('form');
        if (form) {
            const inputs = form.querySelectorAll('input:not([type="password"]):not([type="hidden"]), select');
            
            // استرجاع البيانات المحفوظة
            inputs.forEach(input => {
                const savedValue = sessionStorage.getItem('register_form_' + input.name);
                if (savedValue !== null) {
                    if(input.type === 'checkbox' || input.type === 'radio') {
                        input.checked = savedValue === 'true';
                    } else if (!input.value) { // لا تستبدل القيم المسترجعة من old()
                        input.value = savedValue;
                    }
                }
                
                // الاستماع للتغييرات وحفظها
                input.addEventListener('change', function() {
                    if(input.type === 'checkbox' || input.type === 'radio') {
                        sessionStorage.setItem('register_form_' + input.name, input.checked);
                    } else {
                        sessionStorage.setItem('register_form_' + input.name, input.value);
                    }
                });
            });

            // مسح البيانات المحفوظة عند نجاح الإرسال فقط (يمكنك النقل إلى صفحة النجاح)
            // سنمسحها هنا كإجراء تنظيف بعد التأكد من عدم وجود أخطاء في الـ validation
            @if(!count($errors) && !old())
                inputs.forEach(input => {
                    sessionStorage.removeItem('register_form_' + input.name);
                });
            @endif
        }
    });
</script>
@endsection