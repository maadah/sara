@extends('layouts.public')

@section('title', 'سارة - منصة ذكاء اصطناعي لخدمة العملاء')

@section('content')
    <!-- Hero Section -->
    <section class="relative pt-32 pb-20 overflow-hidden">
        <div class="absolute top-0 right-0 -mr-20 -mt-20 w-[500px] h-[500px] rounded-full bg-[#00A8E8]/5 blur-[100px] pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-[500px] h-[500px] rounded-full bg-purple-500/5 blur-[100px] pointer-events-none"></div>

        <div class="container mx-auto px-6 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div class="text-center lg:text-end">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 text-[#00A8E8] rounded-full text-sm font-semibold mb-6 border border-blue-100">
                        <span class="relative flex h-2 w-2">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#00A8E8] opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2 w-2 bg-[#00A8E8]"></span>
                        </span>
                        الجيل الجديد من خدمة العملاء
                    </div>

                    <h1 class="text-5xl lg:text-6xl font-bold text-gray-900 leading-[1.2] mb-6">
                        خدمة عملاء ذكية، <br>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#00A8E8] to-[#00658B]">بدون توقف 24/7</span>
                    </h1>

                    <p class="text-xl text-gray-500 mb-8 leading-relaxed max-w-lg mx-auto lg:mx-0">
                        منصة ذكية تساعدك على إدارة متجرك، متابعة المبيعات، والتواصل مع عملائك عبر فيسبوك وانستغرام وواتساب من مكان واحد بكل سهولة.
                    </p>

                    <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
                        @auth
                            <a href="{{ auth()->user()->role === 'admin' ? route('admin.dashboard') : route('customer.dashboard') }}" class="w-full sm:w-auto px-8 py-4 bg-[#00A8E8] text-white rounded-xl font-bold text-lg shadow-xl shadow-[#00A8E8]/20 hover:bg-[#007EA7] transition-all hover:-translate-y-1 text-center">
                                دخول إلى المنصة
                            </a>
                        @else
                            <a href="{{ route('register') }}" class="w-full sm:w-auto px-8 py-4 bg-[#00A8E8] text-white rounded-xl font-bold text-lg shadow-xl shadow-[#00A8E8]/20 hover:bg-[#007EA7] transition-all hover:-translate-y-1 text-center">
                                جرب المنصة مجاناً
                            </a>
                        @endauth
                        <a href="#features" class="w-full sm:w-auto px-8 py-4 bg-white text-gray-700 border border-gray-200 rounded-xl font-bold text-lg hover:bg-gray-50 transition-all text-center flex items-center justify-center gap-2">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                            </svg>
                            اكتشف المزيد
                        </a>
                    </div>

                    <div class="mt-8 flex items-center justify-center lg:justify-start gap-6 text-sm font-medium text-gray-500">
                        <span class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            سهل الاستخدام
                        </span>
                        <span class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            دعم فني متواصل
                        </span>
                        <span class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            آمن وموثوق
                        </span>
                    </div>
                </div>

                <!-- Hero Chat Mockup -->
                <div class="relative group">
                    <div class="absolute -inset-1 bg-gradient-to-r from-[#00A8E8] to-purple-600 rounded-[2rem] blur opacity-25 group-hover:opacity-50 transition duration-1000 group-hover:duration-200"></div>
                    <div class="relative bg-white rounded-[2rem] shadow-2xl overflow-hidden border border-gray-100">
                        <div class="bg-gray-50 border-b border-gray-100 p-4 flex items-center gap-4">
                            <div class="flex gap-2">
                                <div class="w-3 h-3 rounded-full bg-red-400"></div>
                                <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                                <div class="w-3 h-3 rounded-full bg-green-400"></div>
                            </div>
                            <div class="h-6 w-full max-w-xs bg-gray-200/50 rounded-full mx-auto"></div>
                        </div>
                        <div class="p-6 h-[400px] bg-gray-50 flex flex-col gap-4 overflow-hidden">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-full bg-gray-300 flex-shrink-0"></div>
                                <div class="bg-white p-4 rounded-2xl rounded-tr-none shadow-sm max-w-[80%]">
                                    <p class="text-sm">السلام عليكم، متوفر عندكم المقاس الكبير؟</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3 flex-row-reverse">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-[#00A8E8] to-[#00658B] flex items-center justify-center text-white text-xs font-bold shadow-lg flex-shrink-0">AI</div>
                                <div class="bg-[#00A8E8] text-white p-4 rounded-2xl rounded-tl-none shadow-lg shadow-[#00A8E8]/20 max-w-[80%]">
                                    <p class="text-sm">وعليكم السلام! نعم، متوفر المقاس الكبير باللونين الأسود والأبيض 🤩 تحب تطلب الحين؟</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3 mt-2">
                                <div class="w-10 h-10 rounded-full bg-gray-300 flex-shrink-0"></div>
                                <div class="bg-white p-4 rounded-2xl rounded-tr-none shadow-sm max-w-[80%]">
                                    <p class="text-sm">ايوه باللون الأسود لو سمحت</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Grid -->
    <section id="features" class="py-20 bg-white relative">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="text-[#00A8E8] font-bold tracking-wider uppercase text-sm">المميزات</span>
                <h2 class="text-4xl font-bold text-gray-900 mt-2 mb-4">كل ما تحتاجه لإدارة أعمالك</h2>
                <p class="text-gray-500 text-lg">أدوات قوية مصممة خصيصاً للتجار وأصحاب المشاريع لزيادة الإنتاجية والمبيعات.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="p-8 rounded-3xl bg-gray-50 hover:bg-white hover:shadow-xl transition-all border border-gray-100 group">
                    <div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center text-[#00A8E8] mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">ردود آلية ذكية</h3>
                    <p class="text-gray-500 leading-relaxed">درب الروبوت على منتجاتك وسيقوم بالرد على استفسارات العملاء بدقة وسرعة عبر جميع المنصات.</p>
                </div>

                <div class="p-8 rounded-3xl bg-gray-50 hover:bg-white hover:shadow-xl transition-all border border-gray-100 group">
                    <div class="w-14 h-14 bg-purple-100 rounded-2xl flex items-center justify-center text-purple-600 mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">تجميع المحادثات</h3>
                    <p class="text-gray-500 leading-relaxed">أدر جميع رسائل فيسبوك، انستغرام، وواتساب من مكان واحد دون تشتت أو ضياع.</p>
                </div>

                <div class="p-8 rounded-3xl bg-gray-50 hover:bg-white hover:shadow-xl transition-all border border-gray-100 group">
                    <div class="w-14 h-14 bg-green-100 rounded-2xl flex items-center justify-center text-green-600 mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">تحليلات المبيعات</h3>
                    <p class="text-gray-500 leading-relaxed">لوحة تحكم شاملة توضح لك أداء المبيعات، أكثر المنتجات طلباً، ومعدل الاستجابة.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Section -->
    <section class="py-20 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="text-[#00A8E8] font-bold tracking-wider uppercase text-sm">لماذا نحن</span>
                <h2 class="text-4xl font-bold text-gray-900 mt-2 mb-4">تحويل الفوضى إلى نظام</h2>
                <p class="text-gray-500 text-lg">سارة تساعدك على تنظيم عملك وزيادة إنتاجيتك بأدوات ذكية وسهلة.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="bg-white p-8 rounded-3xl border border-gray-100 hover:shadow-xl transition-all group">
                    <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center text-[#00A8E8] mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" /></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">وفّر على نفسك بذكاء</h3>
                    <p class="text-gray-500 leading-relaxed">ويا سارة، ما تدفع على خدمات ما تحتاجها. كل شي محسوب، واضح، ومصمم الك حسب حجم تجارتك.</p>
                </div>

                <div class="bg-white p-8 rounded-3xl border border-gray-100 hover:shadow-xl transition-all group">
                    <div class="w-14 h-14 bg-purple-50 rounded-2xl flex items-center justify-center text-purple-600 mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">ربط جميع منصات التواصل</h3>
                    <p class="text-gray-500 leading-relaxed">كل رسائلك وطلباتك تتجمع بمكان واحد. إنستغرام، فيسبوك، أو واتساب - نظام واحد يجمع كل شي.</p>
                </div>

                <div class="bg-white p-8 rounded-3xl border border-gray-100 hover:shadow-xl transition-all group">
                    <div class="w-14 h-14 bg-green-50 rounded-2xl flex items-center justify-center text-green-600 mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0116.5 7.605" /></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">تقارير وتحليلات مباشرة</h3>
                    <p class="text-gray-500 leading-relaxed">تقارير مباشرة توضح عدد الطلبات، نسب الإنجاز، والأداء العام. قراراتك مبنية على أرقام.</p>
                </div>

                <div class="bg-white p-8 rounded-3xl border border-gray-100 hover:shadow-xl transition-all group">
                    <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-600 mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">أتمتة ذكية للمهام</h3>
                    <p class="text-gray-500 leading-relaxed">الرد على الرسائل، تثبيت الطلبات، تحديث المخزون - كلها تتم بشكل ذكي يقلل الجهد ويمنع الأخطاء.</p>
                </div>

                <div class="bg-white p-8 rounded-3xl border border-gray-100 hover:shadow-xl transition-all group">
                    <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center text-red-500 mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">سرعة في الإنجاز</h3>
                    <p class="text-gray-500 leading-relaxed">ترد أسرع، تجهز أسرع، وتسلم أسرع. النتيجة؟ رضا الزبون أعلى ومبيعات أكثر.</p>
                </div>

                <div class="bg-white p-8 rounded-3xl border border-gray-100 hover:shadow-xl transition-all group">
                    <div class="w-14 h-14 bg-cyan-50 rounded-2xl flex items-center justify-center text-cyan-600 mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">تتبّع ومراقبة لحظية</h3>
                    <p class="text-gray-500 leading-relaxed">تعرف شنو يصير بتجارتك لحظة بلحظة. كل شي واضح قدامك وأنت مسيطر على تجارتك.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Target Audience Section -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="text-[#00A8E8] font-bold tracking-wider uppercase text-sm">الحلول</span>
                <h2 class="text-4xl font-bold text-gray-900 mt-2 mb-4">مصمم لتجارتك الإلكترونية</h2>
                <p class="text-gray-500 text-lg">أدوات متكاملة تخدم كل جانب من جوانب عملك.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-gradient-to-br from-gray-50 to-blue-50/50 p-6 rounded-2xl border border-gray-100 hover:shadow-lg transition-all text-center">
                    <div class="w-16 h-16 bg-[#00A8E8]/10 rounded-2xl flex items-center justify-center text-[#00A8E8] mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72l1.189-1.19A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72M6.75 18h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .414.336.75.75.75z" /></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">داشبورد موحدة</h3>
                    <p class="text-gray-500 text-sm">واجهة تجمع كل شغلك بمكان واحد من جميع المنصات.</p>
                </div>

                <div class="bg-gradient-to-br from-gray-50 to-purple-50/50 p-6 rounded-2xl border border-gray-100 hover:shadow-lg transition-all text-center">
                    <div class="w-16 h-16 bg-purple-100 rounded-2xl flex items-center justify-center text-purple-600 mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">مساعد ذكي 24/7</h3>
                    <p class="text-gray-500 text-sm">يرد، يشرح، يقنع، يثبت طلب، ويحدث المخزن تلقائياً.</p>
                </div>

                <div class="bg-gradient-to-br from-gray-50 to-green-50/50 p-6 rounded-2xl border border-gray-100 hover:shadow-lg transition-all text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center text-green-600 mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z" /></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">نظام إدارة الطلبات</h3>
                    <p class="text-gray-500 text-sm">كل طلب من أول رسالة إلى باب الزبون بحالة واضحة.</p>
                </div>

                <div class="bg-gradient-to-br from-gray-50 to-orange-50/50 p-6 rounded-2xl border border-gray-100 hover:shadow-lg transition-all text-center">
                    <div class="w-16 h-16 bg-orange-100 rounded-2xl flex items-center justify-center text-orange-600 mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">تجربة احترافية</h3>
                    <p class="text-gray-500 text-sm">الزبون يحس يتعامل ويا مشروع كبير وموثوق ومنظم.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-20 bg-gray-900 text-white">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span class="text-[#00A8E8] font-bold tracking-wider uppercase text-sm">الأسعار</span>
                <h2 class="text-4xl font-bold mt-2 mb-4">باقات الاشتراكات</h2>
                <p class="text-gray-400 text-lg">اختر الباقة المناسبة لك ولأعمالك</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <div class="bg-gray-800/50 backdrop-blur rounded-3xl border border-gray-700/50 p-8 hover:border-[#00A8E8]/30 transition-all">
                    <h3 class="text-xl font-bold mb-2">الباقة الأساسية</h3>
                    <div class="flex items-baseline gap-1 mb-6">
                        <span class="text-4xl font-bold text-[#00A8E8]">$19</span>
                        <span class="text-gray-400">/ شهرياً</span>
                    </div>
                    <ul class="space-y-4 mb-8">
                        <li class="flex items-center gap-3 text-gray-300">
                            <svg class="w-5 h-5 text-[#00A8E8] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            حتى 1000 رسالة شهرياً
                        </li>
                        <li class="flex items-center gap-3 text-gray-300">
                            <svg class="w-5 h-5 text-[#00A8E8] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            ربط منصة واحدة
                        </li>
                        <li class="flex items-center gap-3 text-gray-300">
                            <svg class="w-5 h-5 text-[#00A8E8] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            دعم فني أساسي
                        </li>
                    </ul>
                    <a href="{{ route('register') }}" class="block w-full py-3 text-center border border-[#00A8E8] text-[#00A8E8] rounded-xl font-bold hover:bg-[#00A8E8] hover:text-white transition-all">ابدأ الآن</a>
                </div>

                <div class="relative bg-gradient-to-b from-[#00A8E8]/10 to-gray-800/50 backdrop-blur rounded-3xl border border-[#00A8E8]/30 p-8 scale-105">
                    <div class="absolute -top-4 right-1/2 translate-x-1/2 bg-[#00A8E8] text-white px-4 py-1 rounded-full text-sm font-bold">الأكثر طلباً</div>
                    <h3 class="text-xl font-bold mb-2">الباقة الاحترافية</h3>
                    <div class="flex items-baseline gap-1 mb-6">
                        <span class="text-4xl font-bold text-[#00A8E8]">$49</span>
                        <span class="text-gray-400">/ شهرياً</span>
                    </div>
                    <ul class="space-y-4 mb-8">
                        <li class="flex items-center gap-3 text-gray-300">
                            <svg class="w-5 h-5 text-[#00A8E8] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            رسائل غير محدودة
                        </li>
                        <li class="flex items-center gap-3 text-gray-300">
                            <svg class="w-5 h-5 text-[#00A8E8] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            ربط جميع المنصات
                        </li>
                        <li class="flex items-center gap-3 text-gray-300">
                            <svg class="w-5 h-5 text-[#00A8E8] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            مساعد ذكي متقدم
                        </li>
                        <li class="flex items-center gap-3 text-gray-300">
                            <svg class="w-5 h-5 text-[#00A8E8] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            تقارير وتحليلات
                        </li>
                    </ul>
                    <a href="{{ route('register') }}" class="block w-full py-3 text-center bg-[#00A8E8] text-white rounded-xl font-bold hover:bg-[#007EA7] transition-all shadow-lg shadow-[#00A8E8]/30">ابدأ الآن</a>
                </div>

                <div class="bg-gray-800/50 backdrop-blur rounded-3xl border border-gray-700/50 p-8 hover:border-[#00A8E8]/30 transition-all">
                    <h3 class="text-xl font-bold mb-2">باقة المؤسسات</h3>
                    <div class="flex items-baseline gap-1 mb-6">
                        <span class="text-4xl font-bold text-[#00A8E8]">$99</span>
                        <span class="text-gray-400">/ شهرياً</span>
                    </div>
                    <ul class="space-y-4 mb-8">
                        <li class="flex items-center gap-3 text-gray-300">
                            <svg class="w-5 h-5 text-[#00A8E8] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            كل مميزات الاحترافية
                        </li>
                        <li class="flex items-center gap-3 text-gray-300">
                            <svg class="w-5 h-5 text-[#00A8E8] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            فريق عمل متعدد
                        </li>
                        <li class="flex items-center gap-3 text-gray-300">
                            <svg class="w-5 h-5 text-[#00A8E8] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            دعم فني مخصص
                        </li>
                    </ul>
                    <a href="{{ route('register') }}" class="block w-full py-3 text-center border border-[#00A8E8] text-[#00A8E8] rounded-xl font-bold hover:bg-[#00A8E8] hover:text-white transition-all">ابدأ الآن</a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-gradient-to-r from-[#00A8E8] to-[#00658B] text-white">
        <div class="container mx-auto px-6 text-center">
            <h2 class="text-4xl font-bold mb-6">جاهز تبدأ رحلتك مع سارة؟</h2>
            <p class="text-xl mb-8 text-white/80 max-w-2xl mx-auto">انضم لآلاف التجار الذين يستخدمون سارة لتنظيم أعمالهم وزيادة مبيعاتهم.</p>
            <a href="{{ route('register') }}" class="inline-block px-10 py-4 bg-white text-[#00A8E8] rounded-xl font-bold text-lg hover:bg-gray-100 transition-all shadow-xl hover:-translate-y-1">
                سجل مجاناً الآن
            </a>
        </div>
    </section>
@endsection
