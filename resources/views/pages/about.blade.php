@extends('layouts.public')

@section('title', 'عن المنصة - سارة')

@section('content')
    <!-- Hero Header -->
    <section class="relative pt-32 pb-16 overflow-hidden">
        <div class="absolute top-0 left-0 w-[600px] h-[600px] rounded-full bg-[#00A8E8]/5 blur-[120px] -translate-x-1/2 -translate-y-1/2"></div>
        <div class="absolute bottom-0 right-0 w-[400px] h-[400px] rounded-full bg-purple-500/5 blur-[100px] translate-x-1/3"></div>
        <div class="container mx-auto px-6 relative text-center">
            <span class="inline-flex items-center gap-2 px-4 py-2 bg-[#00A8E8]/10 text-[#00A8E8] rounded-full text-sm font-semibold mb-6 border border-[#00A8E8]/20">
                عن منصة سارة
            </span>
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 leading-tight mb-6">
                المنصة العربية الأذكى <br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#00A8E8] to-[#007EA7]">لإدارة تجارتك الإلكترونية</span>
            </h1>
            <p class="text-xl text-gray-500 max-w-2xl mx-auto leading-relaxed">
                سارة ليست مجرد أداة — هي شريكك الذكي الذي يعمل 24/7 لخدمة عملائك وتنظيم أعمالك وزيادة مبيعاتك.
            </p>
        </div>
    </section>

    <!-- Story Section -->
    <section class="py-16 bg-white">
        <div class="container mx-auto px-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div>
                    <span class="text-[#00A8E8] font-bold text-sm tracking-wider uppercase">قصتنا</span>
                    <h2 class="text-3xl font-bold text-gray-900 mt-2 mb-6">من نحن؟</h2>
                    <p class="text-gray-500 text-lg leading-relaxed mb-4">
                        منصة سارة هي منصة عراقية متكاملة تهدف إلى تسهيل إدارة الأعمال والتجار
                        باستخدام أحدث تقنيات الذكاء الاصطناعي. نسعى لتقديم حلول مبتكرة تساعد الشركات
                        والأفراد على تحسين كفاءة أعمالهم وزيادة مبيعاتهم.
                    </p>
                    <p class="text-gray-500 text-lg leading-relaxed">
                        تأسست المنصة بهدف توفير أدوات ذكية وسهلة الاستخدام لإدارة العمليات التجارية،
                        مع التركيز على تجربة المستخدم العربي واحتياجات السوق المحلي.
                    </p>
                </div>
                <div class="relative">
                    <div class="absolute -inset-4 bg-gradient-to-r from-[#00A8E8]/20 to-purple-500/10 rounded-[2rem] blur-xl"></div>
                    <div class="relative bg-gradient-to-br from-[#00A8E8]/5 to-purple-50 rounded-[2rem] p-8 border border-[#00A8E8]/10">
                        <div class="grid grid-cols-2 gap-6">
                            <div class="bg-white rounded-2xl p-6 shadow-sm text-center">
                                <p class="text-3xl font-bold text-[#00A8E8]">24/7</p>
                                <p class="text-sm text-gray-500 mt-1">خدمة متواصلة</p>
                            </div>
                            <div class="bg-white rounded-2xl p-6 shadow-sm text-center">
                                <p class="text-3xl font-bold text-[#00A8E8]">AI</p>
                                <p class="text-sm text-gray-500 mt-1">ذكاء اصطناعي</p>
                            </div>
                            <div class="bg-white rounded-2xl p-6 shadow-sm text-center">
                                <p class="text-3xl font-bold text-[#00A8E8]">+3</p>
                                <p class="text-sm text-gray-500 mt-1">منصات مدعومة</p>
                            </div>
                            <div class="bg-white rounded-2xl p-6 shadow-sm text-center">
                                <p class="text-3xl font-bold text-[#00A8E8]">100%</p>
                                <p class="text-sm text-gray-500 mt-1">عربي بالكامل</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Vision / Mission / Values -->
    <section class="py-20 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="text-[#00A8E8] font-bold text-sm tracking-wider uppercase">ما يدفعنا</span>
                <h2 class="text-4xl font-bold text-gray-900 mt-2 mb-4">رؤيتنا ورسالتنا</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-8 rounded-3xl border border-gray-100 hover:shadow-xl transition-all group text-center">
                    <div class="w-16 h-16 bg-[#00A8E8]/10 rounded-2xl flex items-center justify-center text-[#00A8E8] mx-auto mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">رؤيتنا</h3>
                    <p class="text-gray-500 leading-relaxed">
                        أن نكون المنصة الرائدة في المنطقة العربية لإدارة الأعمال التجارية،
                        ونساهم في التحول الرقمي من خلال حلول مبتكرة وذكية.
                    </p>
                </div>

                <div class="bg-white p-8 rounded-3xl border border-gray-100 hover:shadow-xl transition-all group text-center">
                    <div class="w-16 h-16 bg-purple-100 rounded-2xl flex items-center justify-center text-purple-600 mx-auto mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" /></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">رسالتنا</h3>
                    <p class="text-gray-500 leading-relaxed">
                        تمكين الشركات والتجار من إدارة أعمالهم بكفاءة عالية من خلال توفير منصة
                        متكاملة وسهلة الاستخدام تجمع بين التقنية الحديثة والبساطة.
                    </p>
                </div>

                <div class="bg-white p-8 rounded-3xl border border-gray-100 hover:shadow-xl transition-all group text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center text-green-600 mx-auto mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" /></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">قيمنا</h3>
                    <p class="text-gray-500 leading-relaxed">
                        الابتكار، الشفافية، الجودة، وخدمة العملاء المتميزة هي القيم التي نلتزم بها
                        في كل ما نقدمه من خدمات وحلول.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us - Numbered Cards -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="text-[#00A8E8] font-bold text-sm tracking-wider uppercase">المزايا</span>
                <h2 class="text-4xl font-bold text-gray-900 mt-2 mb-4">لماذا تختار سارة؟</h2>
                <p class="text-gray-500 text-lg">نقدم لك مزايا فريدة تميزنا عن غيرنا</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto">
                <div class="flex items-start gap-5 bg-gray-50 p-6 rounded-2xl border border-gray-100 hover:shadow-lg transition-all group">
                    <div class="w-12 h-12 bg-gradient-to-br from-[#00A8E8] to-[#007EA7] text-white rounded-xl flex items-center justify-center font-bold text-lg shrink-0 group-hover:scale-110 transition-transform">01</div>
                    <div>
                        <h4 class="font-bold text-gray-900 text-lg mb-1">تصميم عربي أصيل</h4>
                        <p class="text-gray-500 leading-relaxed">واجهة مستخدم مصممة خصيصاً للمستخدم العربي مع دعم كامل للغة العربية والاتجاه من اليمين لليسار.</p>
                    </div>
                </div>

                <div class="flex items-start gap-5 bg-gray-50 p-6 rounded-2xl border border-gray-100 hover:shadow-lg transition-all group">
                    <div class="w-12 h-12 bg-gradient-to-br from-[#00A8E8] to-[#007EA7] text-white rounded-xl flex items-center justify-center font-bold text-lg shrink-0 group-hover:scale-110 transition-transform">02</div>
                    <div>
                        <h4 class="font-bold text-gray-900 text-lg mb-1">تقنيات حديثة</h4>
                        <p class="text-gray-500 leading-relaxed">نستخدم أحدث التقنيات بما فيها الذكاء الاصطناعي لضمان أداء عالي وتجربة سلسة.</p>
                    </div>
                </div>

                <div class="flex items-start gap-5 bg-gray-50 p-6 rounded-2xl border border-gray-100 hover:shadow-lg transition-all group">
                    <div class="w-12 h-12 bg-gradient-to-br from-[#00A8E8] to-[#007EA7] text-white rounded-xl flex items-center justify-center font-bold text-lg shrink-0 group-hover:scale-110 transition-transform">03</div>
                    <div>
                        <h4 class="font-bold text-gray-900 text-lg mb-1">دعم متواصل</h4>
                        <p class="text-gray-500 leading-relaxed">فريق دعم متخصص جاهز لمساعدتك على مدار الساعة بأي استفسار تقني أو تجاري.</p>
                    </div>
                </div>

                <div class="flex items-start gap-5 bg-gray-50 p-6 rounded-2xl border border-gray-100 hover:shadow-lg transition-all group">
                    <div class="w-12 h-12 bg-gradient-to-br from-[#00A8E8] to-[#007EA7] text-white rounded-xl flex items-center justify-center font-bold text-lg shrink-0 group-hover:scale-110 transition-transform">04</div>
                    <div>
                        <h4 class="font-bold text-gray-900 text-lg mb-1">أسعار تنافسية</h4>
                        <p class="text-gray-500 leading-relaxed">خطط اشتراك مرنة تناسب جميع الأحجام والميزانيات، من المشاريع الصغيرة للمؤسسات.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-gradient-to-r from-[#00A8E8] to-[#00658B] text-white">
        <div class="container mx-auto px-6 text-center">
            <h2 class="text-4xl font-bold mb-6">هل أنت مستعد للبدء؟</h2>
            <p class="text-xl mb-8 text-white/80 max-w-2xl mx-auto">انضم إلينا اليوم واستمتع بتجربة إدارة متميزة لتجارتك الإلكترونية.</p>
            <a href="{{ route('register') }}" class="inline-block px-10 py-4 bg-white text-[#00A8E8] rounded-xl font-bold text-lg hover:bg-gray-100 transition-all shadow-xl hover:-translate-y-1">
                سجل مجاناً الآن
            </a>
        </div>
    </section>
@endsection