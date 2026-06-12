<?php
$path = 'c:\Users\ENG.THURAYA\Documents\sarav3\resources\views\welcome.blade.php';
$lines = file($path);

$new_content_raw = <<<EOT
                <!-- Chat UI -->
                <div class="px-6 py-12 md:p-8 flex flex-col justify-end h-full w-full bg-gradient-to-br from-[#f8f9fa] to-[#e9ecef] dark:from-[#1a1a19] dark:to-[#121211] overflow-hidden relative" id="chat-hero">
                    <style>
                        .chat-msg {
                            opacity: 0;
                            transform: translateY(20px);
                            animation: fadeInUp 0.5s ease-out forwards;
                        }
                        @keyframes fadeInUp {
                            to {
                                opacity: 1;
                                transform: translateY(0);
                            }
                        }
                        .msg-1 { animation-delay: 0.5s; }
                        .msg-2 { animation-delay: 2.5s; }
                        .msg-3 { animation-delay: 4s; }
                        .typing-indicator {
                            display: inline-flex;
                            align-items: center;
                            gap: 4px;
                            animation: hideTyping 0s 2.5s forwards;
                        }
                        @keyframes hideTyping {
                            to { display: none; width: 0; height: 0; overflow: hidden; }
                        }
                        .dot {
                            width: 6px; height: 6px; border-radius: 50%; background-color: #9CA3AF;
                            animation: bounce 1.4s infinite ease-in-out both;
                        }
                        .dot-1 { animation-delay: -0.32s; }
                        .dot-2 { animation-delay: -0.16s; }
                        @keyframes bounce {
                            0%, 80%, 100% { transform: scale(0); }
                            40% { transform: scale(1); }
                        }
                    </style>

                    <div class="flex flex-col gap-4 w-full h-full justify-center" dir="rtl">
                        <!-- Message 1: User -->
                        <div class="chat-msg msg-1 flex justify-start">
                            <div class="bg-blue-600 text-white text-sm md:text-base px-4 py-3 rounded-2xl rounded-tr-sm shadow-sm max-w-[85%]">
                                السلام عليكم، متوفر عندكم المقاس الكبير؟
                            </div>
                        </div>

                        <!-- Typing Indicator from AI -->
                        <div class="chat-msg msg-1 flex justify-end">
                            <div class="typing-indicator bg-white dark:bg-[#252524] text-gray-800 dark:text-gray-200 px-4 py-3 rounded-2xl rounded-tl-sm shadow-sm max-w-[85%]">
                                <span class="dot dot-1"></span><span class="dot dot-2"></span><span class="dot dot-3"></span>
                            </div>
                        </div>

                        <!-- Message 2: AI -->
                        <div class="chat-msg msg-2 flex justify-end">
                            <div class="bg-white dark:bg-[#252524] text-gray-800 dark:text-gray-200 text-sm md:text-base px-4 py-3 rounded-2xl rounded-tl-sm shadow-sm max-w-[85%] leading-relaxed border border-gray-100 dark:border-gray-800">
                                وعليكم السلام! نعم، متوفر المقاس الكبير باللونين الأسود والأبيض 🤩 تحب تطلب الحين؟
                            </div>
                        </div>

                        <!-- Message 3: User -->
                        <div class="chat-msg msg-3 flex justify-start">
                            <div class="bg-blue-600 text-white text-sm md:text-base px-4 py-3 rounded-2xl rounded-tr-sm shadow-sm max-w-[85%]">
                                ايوه باللون الأسود لو سمحت
                            </div>
                        </div>
                    </div>
                </div>
EOT;

$new_lines = [];
for ($i = 0; $i < 1611; $i++) {
    $new_lines[] = $lines[$i];
}
$new_lines[] = $new_content_raw . "\n";
for ($i = 1968; $i < count($lines); $i++) {
    $new_lines[] = $lines[$i];
}

file_put_contents($path, implode("", $new_lines));
echo "Success\n";
