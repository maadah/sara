@extends('layouts.app')

@section('title', 'تسجيل الدخول - سارة')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-gray-50 via-blue-50/30 to-gray-50 relative overflow-x-hidden">
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute top-[-10%] right-[-5%] w-96 h-96 bg-[#00A8E8]/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-[-10%] left-[-5%] w-96 h-96 bg-purple-500/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
        <div class="absolute top-[40%] left-[50%] w-64 h-64 bg-blue-400/5 rounded-full blur-2xl"></div>
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

        <div class="w-full max-w-md mx-auto">
            <div class="relative bg-white/90 backdrop-blur-xl shadow-2xl rounded-3xl border border-gray-100/50 overflow-hidden">
                <div class="h-2 bg-gradient-to-r from-[#00A8E8] via-[#007EA7] to-[#00658B]"></div>
                <div class="absolute top-10 right-10 w-32 h-32 bg-[#00A8E8]/5 rounded-full blur-2xl pointer-events-none"></div>
                <div class="absolute bottom-10 left-10 w-32 h-32 bg-purple-500/5 rounded-full blur-2xl pointer-events-none"></div>

                <div class="relative px-8 py-10 sm:px-10 sm:py-12">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2 text-center">تسجيل الدخول</h2>
                    <p class="text-gray-500 text-center mb-8">أهلاً بعودتك! سجل دخولك للمتابعة</p>

                    @if($errors->any())
                        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm flex items-center gap-2">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm flex items-center gap-2">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif

                    @if(session('warning'))
                        <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-xl text-yellow-700 text-sm flex items-center gap-2">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                            <span>{{ session('warning') }}</span>
                        </div>
                    @endif

                    @if(session('success'))
                        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl text-green-600 text-sm flex items-center gap-2">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span>{{ session('success') }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">البريد الالكتروني</label>
                            <input type="text" name="email" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition-all bg-gray-50/50 @error('email') border-red-300 @enderror" value="{{ old('email') }}" required autofocus>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">الرقم السري</label>
                            <input type="password" name="password" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition-all bg-gray-50/50 @error('password') border-red-300 @enderror" required>
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="remember" class="w-4 h-4 text-[#00A8E8] border-gray-300 rounded focus:ring-[#00A8E8]" {{ old('remember') ? 'checked' : '' }}>
                                <span class="text-sm text-gray-600">تذكرني</span>
                            </label>
                            <a href="#" class="text-sm text-[#00A8E8] hover:text-[#007EA7] font-medium">نسيت كلمة المرور؟</a>
                        </div>

                        <button type="submit" class="w-full py-3.5 bg-gradient-to-r from-[#00A8E8] to-[#007EA7] text-white rounded-xl font-bold text-lg shadow-lg shadow-[#00A8E8]/25 hover:shadow-xl hover:shadow-[#00A8E8]/30 hover:-translate-y-0.5 active:translate-y-0 transition-all">
                            تسجيل الدخول
                        </button>
                    </form>

                    <div class="mt-8 text-center">
                        <span class="text-gray-500">لا تمتلك حساب؟</span>
                        <a href="{{ route('register') }}" class="text-[#00A8E8] hover:text-[#007EA7] font-bold mr-1">إنشاء حساب</a>
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
@endsection