<!DOCTYPE html>
<html lang="ar" dir="rtl" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'سارة - منصة ذكاء اصطناعي لخدمة العملاء'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Cairo', sans-serif; }
    </style>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="bg-gray-50 font-sans antialiased text-gray-600">
    <!-- Navbar -->
    <nav class="fixed w-full z-50 transition-all duration-300 bg-white/80 backdrop-blur-md border-b border-gray-100" id="mainNav">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <a href="<?php echo e(route('home')); ?>" class="flex items-center gap-2">
                    <img src="<?php echo e(asset('images/1040X1040-png.png')); ?>" alt="سارة" class="w-10 h-10 object-contain">
                    <span class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-700">سارة</span>
                </a>

                <button class="md:hidden p-2 rounded-lg hover:bg-gray-100 transition-colors" id="mobileMenuBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <!-- Desktop Nav Links -->
                <div class="hidden lg:flex items-center space-x-8 space-x-reverse flex-1 justify-center">
                    <a href="<?php echo e(route('home')); ?>" class="font-semibold text-sm <?php echo e(request()->routeIs('home') ? 'text-[#00A8E8]' : 'text-gray-700'); ?> hover:text-[#00A8E8] transition-all">الرئيسية</a>
                    <a href="<?php echo e(route('about')); ?>" class="font-semibold text-sm <?php echo e(request()->routeIs('about') ? 'text-[#00A8E8]' : 'text-gray-700'); ?> hover:text-[#00A8E8] transition-all">عن المنصة</a>
                    <a href="<?php echo e(route('contact')); ?>" class="font-semibold text-sm <?php echo e(request()->routeIs('contact') ? 'text-[#00A8E8]' : 'text-gray-700'); ?> hover:text-[#00A8E8] transition-all">تواصل معنا</a>
                    <a href="<?php echo e(route('privacy')); ?>" class="font-semibold text-sm <?php echo e(request()->routeIs('privacy') ? 'text-[#00A8E8]' : 'text-gray-700'); ?> hover:text-[#00A8E8] transition-all">الشروط والأحكام</a>
                </div>

                <!-- Auth Actions (Desktop) -->
                <div class="hidden lg:flex items-center gap-4">
                    <?php if(auth()->guard()->check()): ?>
                        <?php if(auth()->user()->role === 'admin'): ?>
                            <a href="<?php echo e(route('admin.dashboard')); ?>" class="px-6 py-2.5 bg-[#00A8E8] text-white rounded-xl font-bold hover:bg-[#007EA7] transition-all shadow-lg shadow-[#00A8E8]/20">لوحة التحكم</a>
                        <?php else: ?>
                            <a href="<?php echo e(route('customer.dashboard')); ?>" class="px-6 py-2.5 bg-[#00A8E8] text-white rounded-xl font-bold hover:bg-[#007EA7] transition-all shadow-lg shadow-[#00A8E8]/20">لوحة التحكم</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="<?php echo e(route('login')); ?>" class="font-bold text-[#00A8E8] hover:text-[#007EA7] transition-all px-4">تسجيل الدخول</a>
                        <a href="<?php echo e(route('register')); ?>" class="bg-[#00A8E8] text-white px-6 py-2.5 rounded-xl font-bold hover:bg-[#007EA7] transition-all shadow-lg hover:shadow-[#00A8E8]/30 hover:-translate-y-0.5 active:translate-y-0">
                            ابدأ مجاناً
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="md:hidden hidden mt-4 pb-4 border-t border-gray-100 pt-4" id="mobileMenu">
                <div class="flex flex-col space-y-3">
                    <a href="<?php echo e(route('home')); ?>" class="font-medium <?php echo e(request()->routeIs('home') ? 'text-[#00A8E8]' : 'text-gray-600'); ?> hover:text-[#00A8E8] py-2">الرئيسية</a>
                    <a href="<?php echo e(route('about')); ?>" class="font-medium <?php echo e(request()->routeIs('about') ? 'text-[#00A8E8]' : 'text-gray-600'); ?> hover:text-[#00A8E8] py-2">عن المنصة</a>
                    <a href="<?php echo e(route('contact')); ?>" class="font-medium <?php echo e(request()->routeIs('contact') ? 'text-[#00A8E8]' : 'text-gray-600'); ?> hover:text-[#00A8E8] py-2">تواصل معنا</a>
                    <a href="<?php echo e(route('privacy')); ?>" class="font-medium <?php echo e(request()->routeIs('privacy') ? 'text-[#00A8E8]' : 'text-gray-600'); ?> hover:text-[#00A8E8] py-2">الشروط والأحكام</a>
                    <div class="flex flex-col gap-3 pt-3 border-t border-gray-100">
                        <?php if(auth()->guard()->check()): ?>
                            <?php if(auth()->user()->role === 'admin'): ?>
                                <a href="<?php echo e(route('admin.dashboard')); ?>" class="bg-[#00A8E8] text-white px-6 py-2.5 rounded-xl font-bold text-center">لوحة التحكم</a>
                            <?php else: ?>
                                <a href="<?php echo e(route('customer.dashboard')); ?>" class="bg-[#00A8E8] text-white px-6 py-2.5 rounded-xl font-bold text-center">لوحة التحكم</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="<?php echo e(route('login')); ?>" class="text-gray-700 font-medium text-center py-2">تسجيل الدخول</a>
                            <a href="<?php echo e(route('register')); ?>" class="bg-[#00A8E8] text-white px-6 py-2.5 rounded-xl font-bold text-center">ابدأ مجاناً</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main>
        <?php echo $__env->yieldContent('content'); ?>
    </main>

    <footer class="bg-gray-900 text-white pt-20 pb-10">
        <div class="container mx-auto px-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12 border-b border-gray-800 pb-12">
                <div>
                    <div class="flex items-center gap-2 mb-6">
                        <img src="<?php echo e(asset('images/1040X1040-png.png')); ?>" alt="سارة" class="w-10 h-10 object-contain">
                        <span class="text-2xl font-bold">سارة</span>
                    </div>
                    <p class="text-gray-400 leading-relaxed">منصة سارة تعمل لمساعدة أصحاب الأعمال والشركات على زيادة المبيعات من خلال حلول مبتكرة تعتمد على الذكاء الاصطناعي والتواصل الفعال.</p>
                </div>
                <div>
                    <h4 class="font-bold mb-6 text-lg">الصفحات</h4>
                    <ul class="space-y-4 text-gray-400">
                        <li><a href="<?php echo e(route('home')); ?>" class="hover:text-white transition-colors">الرئيسية</a></li>
                        <li><a href="<?php echo e(route('about')); ?>" class="hover:text-white transition-colors">عن المنصة</a></li>
                        <li><a href="<?php echo e(route('contact')); ?>" class="hover:text-white transition-colors">تواصل معنا</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold mb-6 text-lg">قانوني</h4>
                    <ul class="space-y-4 text-gray-400">
                        <li><a href="<?php echo e(route('privacy')); ?>" class="hover:text-white transition-colors">سياسة الخصوصية</a></li>
                        <li><a href="<?php echo e(route('privacy')); ?>" class="hover:text-white transition-colors">شروط الاستخدام</a></li>
                        <li><a href="<?php echo e(route('privacy')); ?>" class="hover:text-white transition-colors">الشروط والأحكام</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold mb-6 text-lg">التواصل معنا</h4>
                    <ul class="space-y-4 text-gray-400">
                        <li class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-[#00A8E8]">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>
                            info@saraa.tech
                        </li>
                        <li class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-[#00A8E8]">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                            </svg>
                            +964 7812003005
                        </li>
                    </ul>
                </div>
            </div>
            <div class="pt-8 text-center text-gray-500 text-sm">
                &copy; <?php echo e(date('Y')); ?> منصة سارة. جميع الحقوق محفوظة.
            </div>
        </div>
    </footer>

    <script>
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        mobileMenuBtn.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
    </script>
    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\Users\ENG.THURAYA\Documents\sarav3\resources\views/layouts/public.blade.php ENDPATH**/ ?>