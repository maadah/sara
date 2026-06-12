@extends('layouts.public')

@section('title', 'سياسة الخصوصية - سارة')

@section('content')
    <!-- Hero Header -->
    <section class="relative pt-32 pb-16 overflow-hidden bg-white border-b border-gray-100">
        <div class="absolute top-0 right-0 w-64 h-64 bg-[#00A8E8]/5 rounded-full blur-[100px] -mr-32 -mt-32"></div>
        <div class="container mx-auto px-6 relative text-center">
            <div class="flex items-center justify-center gap-2 mb-6">
                <div class="w-12 h-12 bg-gradient-to-br from-[#00A8E8] to-[#007EA7] rounded-2xl flex items-center justify-center text-white font-bold text-2xl shadow-xl">S</div>
                <span class="text-3xl font-extrabold text-gray-900">سارة</span>
            </div>
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">سياسة الخصوصية</h1>
            <p class="text-gray-500 max-w-2xl mx-auto text-lg">تاريخ التحديث الأخير: {{ date('Y/m/d') }}</p>
        </div>
    </section>

    <!-- Content -->
    <section class="py-16">
        <div class="container mx-auto px-6">
            <div class="max-w-4xl mx-auto bg-white rounded-[2.5rem] shadow-2xl shadow-[#00A8E8]/5 border border-gray-100 p-8 md:p-16">

                <!-- Intro -->
                <div class="bg-[#00A8E8]/5 border-r-4 border-[#00A8E8] p-6 rounded-2xl mb-12">
                    <p class="text-gray-700 text-lg leading-relaxed font-medium">
                        تلتزم منصة "سارة" بحماية خصوصية بياناتكم وبيانات عملائكم. توضح هذه السياسة كيفية جمعنا واستخدامنا وحمايتنا للمعلومات التي نحصل عليها من خلال خدماتنا، بما في ذلك تكاملنا مع منصات Meta (فيسبوك، إنستغرام، واتساب).
                    </p>
                </div>

                <div class="space-y-12">

                    <!-- Section 1 -->
                    <section>
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-10 h-10 bg-[#00A8E8]/10 rounded-xl flex items-center justify-center text-[#00A8E8]">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900">1. المعلومات التي نجمعها</h2>
                        </div>
                        <ul class="space-y-4 pr-14 text-gray-600 text-lg">
                            <li class="flex items-start gap-3">
                                <span class="w-2 h-2 rounded-full bg-[#00A8E8] mt-2.5 shrink-0"></span>
                                <span><strong>معلومات الحساب:</strong> الاسم، البريد الإلكتروني، واسم النشاط التجاري عند التسجيل.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-2 h-2 rounded-full bg-[#00A8E8] mt-2.5 shrink-0"></span>
                                <span><strong>بيانات منصات التواصل:</strong> المعرفات الفريدة (IDs)، محتوى الرسائل، والوسائط المرسلة، وأسماء العملاء المستلمة عبر واجهات برمجة تطبيقات Meta الرسمية.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-2 h-2 rounded-full bg-[#00A8E8] mt-2.5 shrink-0"></span>
                                <span><strong>البيانات التقنية:</strong> عنوان IP، نوع المتصفح، ومعلومات الجهاز لضمان أداء النظام وحمايته.</span>
                            </li>
                        </ul>
                    </section>

                    <hr class="border-gray-100">

                    <!-- Section 2 -->
                    <section>
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center text-purple-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900">2. كيف نستخدم معلوماتكم</h2>
                        </div>
                        <div class="pr-14 space-y-4 text-gray-600 text-lg leading-relaxed">
                            <p>نحن نعالج بياناتكم فقط للأغراض التالية:</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100">
                                    <span class="font-bold text-gray-900 block mb-1">تقديم الخدمة:</span>
                                    إدارة صندوق الوارد الموحد وتسهيل التواصل الفوري مع عملائكم.
                                </div>
                                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100">
                                    <span class="font-bold text-gray-900 block mb-1">الذكاء الاصطناعي:</span>
                                    تحليل محتوى الرسائل للتعرف على النوايا وتقديم ردود مؤتمتة.
                                </div>
                                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100">
                                    <span class="font-bold text-gray-900 block mb-1">إدارة العملاء (CRM):</span>
                                    تصنيف العملاء بناءً على تفاعلاتهم لتحسين استراتيجيات المبيعات.
                                </div>
                                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100">
                                    <span class="font-bold text-gray-900 block mb-1">التواصل:</span>
                                    إرسال تنبيهات هامة حول حسابكم أو تحديثات أمنية ضرورية.
                                </div>
                            </div>
                        </div>
                    </section>

                    <hr class="border-gray-100">

                    <!-- Section 3 -->
                    <section>
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center text-green-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900">3. حماية ومشاركة البيانات</h2>
                        </div>
                        <div class="pr-14 text-gray-600 text-lg leading-relaxed space-y-4">
                            <p>نحن نولي أمن البيانات أقصى أهمية:</p>
                            <p><strong>عدم البيع:</strong> نحن لا نبيع أو نؤجر بياناتكم أو بيانات عملائكم لأطراف ثالثة لأغراض تسويقية أبداً.</p>
                            <p><strong>المزودون المعتمدون:</strong> تتم مشاركة البيانات التقنية فقط مع مزودي الخدمة السحابية والذكاء الاصطناعي لغرض المعالجة التشغيلية فقط، مع ضمان الالتزام بمعايير التشفير.</p>
                        </div>
                    </section>

                    <hr class="border-gray-100">

                    <!-- Section 4 -->
                    <section>
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center text-red-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900">4. الاحتفاظ بالبيانات وحذفها</h2>
                        </div>
                        <div class="pr-14 text-gray-600 text-lg leading-relaxed">
                            <p class="mb-4">نحتفظ بالبيانات فقط للمدة اللازمة لتقديم الخدمة. يحق لكم طلب حذف بياناتكم في أي وقت.</p>
                            <div class="bg-red-50 p-6 rounded-2xl border border-red-100 text-red-900">
                                <strong>طلب الحذف:</strong> يمكنكم تقديم طلب حذف البيانات عبر البريد الإلكتروني <span class="font-bold">privacy@saraa.tech</span>. سيتم تنفيذ طلبات الحذف خلال 14 يوم عمل بحد أقصى.
                            </div>
                        </div>
                    </section>

                    <hr class="border-gray-100">

                    <!-- Section 5 -->
                    <section>
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-amber-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900">5. ملفات تعريف الارتباط</h2>
                        </div>
                        <div class="pr-14 text-gray-600 text-lg leading-relaxed space-y-3">
                            <p>نستخدم ملفات تعريف الارتباط لتحسين تجربتك، بما في ذلك:</p>
                            <ul class="space-y-2">
                                <li class="flex items-start gap-3"><span class="w-2 h-2 rounded-full bg-amber-500 mt-2.5 shrink-0"></span>تذكر تفضيلاتك وإعداداتك</li>
                                <li class="flex items-start gap-3"><span class="w-2 h-2 rounded-full bg-amber-500 mt-2.5 shrink-0"></span>الحفاظ على تسجيل دخولك</li>
                                <li class="flex items-start gap-3"><span class="w-2 h-2 rounded-full bg-amber-500 mt-2.5 shrink-0"></span>فهم كيفية استخدامك للمنصة</li>
                                <li class="flex items-start gap-3"><span class="w-2 h-2 rounded-full bg-amber-500 mt-2.5 shrink-0"></span>تحسين أداء الموقع</li>
                            </ul>
                        </div>
                    </section>

                    <hr class="border-gray-100">

                    <!-- Section 6 -->
                    <section>
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-10 h-10 bg-[#00A8E8]/10 rounded-xl flex items-center justify-center text-[#00A8E8]">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900">6. الالتزام بسياسات Meta</h2>
                        </div>
                        <div class="pr-14 text-gray-600 text-lg leading-relaxed">
                            <p class="mb-3">تعمل منصة "سارة" باستخدام واجهات برمجة تطبيقات Meta الرسمية وتلتزم بـ:</p>
                            <ul class="space-y-2">
                                <li class="flex items-start gap-3"><span class="w-2 h-2 rounded-full bg-[#00A8E8] mt-2.5 shrink-0"></span>شروط خدمة منصة Meta (Meta Platform Terms).</li>
                                <li class="flex items-start gap-3"><span class="w-2 h-2 rounded-full bg-[#00A8E8] mt-2.5 shrink-0"></span>سياسة استخدام بيانات المطورين (Developer Data Use Policy).</li>
                                <li class="flex items-start gap-3"><span class="w-2 h-2 rounded-full bg-[#00A8E8] mt-2.5 shrink-0"></span>نحن لا نقوم بجمع أو تخزين بيانات تتجاوز ما هو ضروري لتوفير الوظائف المعلن عنها.</li>
                            </ul>
                        </div>
                    </section>

                    <hr class="border-gray-100">

                    <!-- Section 7 -->
                    <section>
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center text-indigo-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900">7. حقوقكم</h2>
                        </div>
                        <div class="pr-14 text-gray-600 text-lg leading-relaxed">
                            <p class="mb-3">لديكم الحق في:</p>
                            <ul class="space-y-2">
                                <li class="flex items-start gap-3"><span class="w-2 h-2 rounded-full bg-indigo-500 mt-2.5 shrink-0"></span><strong>الوصول:</strong> طلب نسخة من بياناتكم الشخصية</li>
                                <li class="flex items-start gap-3"><span class="w-2 h-2 rounded-full bg-indigo-500 mt-2.5 shrink-0"></span><strong>التصحيح:</strong> تعديل أي بيانات غير دقيقة</li>
                                <li class="flex items-start gap-3"><span class="w-2 h-2 rounded-full bg-indigo-500 mt-2.5 shrink-0"></span><strong>الحذف:</strong> طلب حذف بياناتكم الشخصية</li>
                                <li class="flex items-start gap-3"><span class="w-2 h-2 rounded-full bg-indigo-500 mt-2.5 shrink-0"></span><strong>الاعتراض:</strong> الاعتراض على معالجة بياناتكم لأغراض معينة</li>
                                <li class="flex items-start gap-3"><span class="w-2 h-2 rounded-full bg-indigo-500 mt-2.5 shrink-0"></span><strong>النقل:</strong> طلب نقل بياناتكم إلى جهة أخرى</li>
                            </ul>
                        </div>
                    </section>

                    <hr class="border-gray-100">

                    <!-- Contact Footer -->
                    <div class="text-center pt-10">
                        <p class="text-gray-500 mb-2">هل لديك أي استفسارات؟</p>
                        <a href="mailto:privacy@saraa.tech" class="text-2xl font-bold text-[#00A8E8] hover:text-[#007EA7] transition-colors">privacy@saraa.tech</a>
                    </div>
                </div>
            </div>

            <div class="mt-12 text-center">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-gray-500 hover:text-[#00A8E8] transition-colors font-bold">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5m7 7l-7-7 7-7"/></svg>
                    العودة للصفحة الرئيسية
                </a>
            </div>
        </div>
    </section>
@endsection