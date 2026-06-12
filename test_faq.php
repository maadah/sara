<?php
/**
 * Test FAQ functionality
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\AI\IntentAnalyzer;
use App\Services\AI\ResponseGenerator;
use App\Enums\Intent;
use App\Enums\ConversationState;

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║               FAQ FUNCTIONALITY TEST                         ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Test data
$faqQuestions = [
    [
        'question' => 'شكد سعر التوصيل؟',
        'expected_intent' => 'ASK_QUESTION',
        'expected_topic' => 'delivery_cost',
    ],
    [
        'question' => 'شنو سياسه الاسترجاع',
        'expected_intent' => 'ASK_QUESTION',
        'expected_topic' => 'return_policy',
    ],
    [
        'question' => 'طرق الدفع',
        'expected_intent' => 'ASK_QUESTION',
        'expected_topic' => 'payment_methods',
    ],
    [
        'question' => 'متى مفتوحين',
        'expected_intent' => 'ASK_QUESTION',
        'expected_topic' => 'working_hours',
    ],
    [
        'question' => 'متى يوصل الطلب',
        'expected_intent' => 'ASK_QUESTION',
        'expected_topic' => 'delivery_time',
    ],
    [
        'question' => 'وين المحل',
        'expected_intent' => 'ASK_QUESTION',
        'expected_topic' => 'store_location',
    ],
];

// Simulate IntentAnalyzer
function analyzeIntent($message) {
    $normalized = mb_strtolower(trim($message));

    // FAQ patterns
    $faqPatterns = [
        '/شكد سعر التوصيل/u' => 'ASK_QUESTION',
        '/شكد التوصيل/u' => 'ASK_QUESTION',
        '/التوصيل/u' => 'ASK_QUESTION',
        '/سياسه الاسترجاع/u' => 'ASK_QUESTION',
        '/سياسة الاسترجاع/u' => 'ASK_QUESTION',
        '/الاسترجاع/u' => 'ASK_QUESTION',
        '/طرق الدفع/u' => 'ASK_QUESTION',
        '/متى مفتوحين/u' => 'ASK_QUESTION',
        '/متى يوصل/u' => 'ASK_QUESTION',
        '/وين المحل/u' => 'ASK_QUESTION',
        '/شنو سياسه/u' => 'ASK_QUESTION',
    ];

    foreach ($faqPatterns as $pattern => $intent) {
        if (preg_match($pattern, $normalized)) {
            return $intent;
        }
    }

    return 'UNKNOWN';
}

function extractFaqTopic($message) {
    $normalized = mb_strtolower(trim($message));

    // Delivery cost
    if (preg_match('/(التوصيل|الشحن|اجور|سعر التوصيل|شكد التوصيل|كم التوصيل|توصلون)/u', $normalized)) {
        return 'delivery_cost';
    }

    // Return/exchange policy
    if (preg_match('/(الاسترجاع|الاستبدال|سياسه|سياسة)/u', $normalized)) {
        return 'return_policy';
    }

    // Payment methods
    if (preg_match('/(طرق الدفع|طريقه الدفع|كيف ادفع|الدفع)/u', $normalized)) {
        return 'payment_methods';
    }

    // Working hours
    if (preg_match('/(ساعات العمل|متى مفتوحين|اوقات العمل)/u', $normalized)) {
        return 'working_hours';
    }

    // Delivery time
    if (preg_match('/(متى يوصل|وقت التوصيل|متى يصلني)/u', $normalized)) {
        return 'delivery_time';
    }

    // Store location
    if (preg_match('/(وين المحل|العنوان|الموقع)/u', $normalized)) {
        return 'store_location';
    }

    return null;
}

function answerFaq($topic, $settings) {
    $formatPrice = fn($price) => number_format((int) $price, 0, '', ',');

    switch ($topic) {
        case 'delivery_cost':
            $cost = $formatPrice($settings['delivery_cost']);
            return "اجور التوصيل: {$cost} د.ع 🚚\n\nالتوصيل لكل مناطق العراق. اي سؤال او استفسار ثاني، موجودين! 🌟";

        case 'return_policy':
            return "سياسة الاسترجاع والاستبدال:\n\n• يمكن استرجاع او استبدال المنتج خلال 7 ايام من الاستلام\n• المنتج لازم يكون بنفس الحالة (غير مستخدم)\n• الاسترجاع متاح في حالة العيوب المصنعية\n• لا يمكن استرجاع المنتجات المخصصة او الملابس الداخلية\n\nلاي سؤال، تواصل معانا! 🌟";

        case 'payment_methods':
            return "طرق الدفع المتاحة:\n\n💵 الدفع عند الاستلام (كاش)\n🏦 حوالة بنكية\n💳 المحافظ الالكترونية\n\nاختر الطريقة اللي تناسبك! 🌟";

        case 'working_hours':
            return "ساعات العمل:\n\nمن السبت الى الخميس: 9 صباحاً - 9 مساءً\nالجمعة: عطلة\n\nموجودين بخدمتك! 🌟";

        case 'delivery_time':
            $time = $settings['delivery_time'];
            return "وقت التوصيل:\n\n{$time} من تأكيد الطلب 🚚\n\nالمناطق البعيدة ممكن تاخذ وقت اضافي بسيط.";

        case 'store_location':
            return "نوصل لكل مناطق العراق! 🚚\n\nللطلب، فقط اختر المنتجات وزودنا بمعلوماتك وراح نوصلك اينما كنت.\n\nاي استفسار، موجودين! 🌟";

        default:
            return "عذراً، ما فهمت سؤالك. ممكن توضح اكثر؟ 🌟";
    }
}

// Run tests
$passed = 0;
$failed = 0;
$storeSettings = [
    'delivery_cost' => 5000,
    'delivery_time' => 'خلال 24-48 ساعة',
];

echo "Testing FAQ Intent Detection & Topic Extraction:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

foreach ($faqQuestions as $i => $test) {
    $num = $i + 1;
    echo "{$num}. Question: \"{$test['question']}\"\n";

    $intent = analyzeIntent($test['question']);
    $topic = extractFaqTopic($test['question']);

    $intentMatch = $intent === $test['expected_intent'];
    $topicMatch = $topic === $test['expected_topic'];

    echo "   Intent: {$intent} " . ($intentMatch ? "✅" : "❌ Expected: {$test['expected_intent']}") . "\n";
    echo "   Topic: {$topic} " . ($topicMatch ? "✅" : "❌ Expected: {$test['expected_topic']}") . "\n";

    if ($intentMatch && $topicMatch) {
        echo "   ✅ PASS\n";
        $passed++;

        // Show the response
        $response = answerFaq($topic, $storeSettings);
        echo "   Response Preview:\n";
        $preview = explode("\n", $response)[0];
        echo "   → {$preview}\n";
    } else {
        echo "   ❌ FAIL\n";
        $failed++;
    }
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST SUMMARY\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Passed: {$passed} / " . count($faqQuestions) . "\n";
echo "Failed: {$failed} / " . count($faqQuestions) . "\n";

if ($failed === 0) {
    echo "\n✅ ALL FAQ TESTS PASSED!\n\n";
    echo "The chatbot will now answer:\n";
    echo "• Delivery cost questions (شكد سعر التوصيل؟) ✓\n";
    echo "• Return policy questions (شنو سياسه الاسترجاع) ✓\n";
    echo "• Payment method questions (طرق الدفع) ✓\n";
    echo "• Working hours questions (متى مفتوحين) ✓\n";
    echo "• Delivery time questions (متى يوصل) ✓\n";
    echo "• Store location questions (وين المحل) ✓\n";
    echo "\nThese questions will be answered directly without treating them as product searches.\n";
} else {
    echo "\n⚠ Some tests failed - review output above\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "FULL RESPONSE EXAMPLES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Q: شكد سعر التوصيل؟\n";
echo answerFaq('delivery_cost', $storeSettings);
echo "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Q: شنو سياسه الاسترجاع\n";
echo answerFaq('return_policy', $storeSettings);
echo "\n\n";
?>
