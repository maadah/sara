<?php
/**
 * Test: Verify "اريد [product] [quantity]" is recognized as ADD_TO_CART in WAITING_PRODUCT_SELECTION
 */

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  TEST: 'اريد طقم ضوء قطعتين' → ADD_TO_CART Intent            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Simulate the IntentAnalyzer logic
function normalizeMessage(string $message): string
{
    $message = trim($message);
    $message = str_replace(['أ', 'إ', 'آ'], 'ا', $message);
    $message = str_replace('ة', 'ه', $message);
    $message = str_replace('ى', 'ي', $message);
    $message = preg_replace('/[\x{064B}-\x{065F}]/u', '', $message);
    return $message;
}

function extractProductName(string $message): ?string
{
    $msg = mb_strtolower(trim($message));
    $msg = str_replace(['أ', 'إ', 'آ'], 'ا', $msg);
    $msg = str_replace('ة', 'ه', $msg);
    $msg = str_replace('ى', 'ي', $msg);
    $msg = str_replace(['؟', '?'], '', $msg);

    $stripWords = [
        'اريد', 'ابي', 'ابغي', 'بدي', 'اشوف', 'شوفني', 'وريني',
        'شنو', 'شلون', 'هل', 'في', 'اي', 'يا',
        'عندكم', 'عدكم', 'متوفر', 'موجود', 'تبيعون', 'تبيع',
        'السلام', 'سلام', 'مرحبا', 'هلا', 'اهلا',
        'صوره', 'صور', 'سعر', 'بكم', 'شكد',
        'اضف', 'زيد', 'حط', 'ضيف',
        'من', 'على', 'الي', 'مع', 'لو', 'بس', 'بال', 'بقسم',
        'نعم', 'لا', 'تمام', 'اوك', 'اكيد',
        'سمحت', 'ممكن', 'اكو', 'ماكو', 'طيب', 'حسنا', 'ماشي',
        'قسم', 'فئه', 'صنف', 'نوع',
        'قطعتين', 'قطعات', 'قطع', 'قطعه', 'قطعة', // Quantity words
        'وحده', 'وحدة', 'قطعه', 'زوج', 'عدد',
    ];

    $cleaned = $msg;
    foreach ($stripWords as $word) {
        $cleaned = preg_replace('/(?:^|\s)' . preg_quote($word, '/') . '(?:\s|$)/u', ' ', $cleaned);
    }

    $cleaned = preg_replace('/\d+/u', '', $cleaned);
    $cleaned = preg_replace('/\s+/u', ' ', trim($cleaned));

    if (mb_strlen($cleaned) >= 2) {
        return $cleaned;
    }

    return null;
}

function extractQuantity(string $message): ?int
{
    $arabicNumbers = [
        'واحد' => 1, 'وحده' => 1, 'وحدة' => 1, 'قطعه' => 1,
        'اثنين' => 2, 'ثنين' => 2, 'قطعتين' => 2, 'زوج' => 2,
        'ثلاثه' => 3, 'ثلاث' => 3,
        'اربعه' => 4, 'اربع' => 4,
        'خمسه' => 5, 'خمس' => 5,
        'سته' => 6, 'ست' => 6,
        'سبعه' => 7, 'سبع' => 7,
        'ثمانيه' => 8, 'ثمان' => 8,
        'تسعه' => 9, 'تسع' => 9,
        'عشره' => 10, 'عشر' => 10,
    ];

    foreach ($arabicNumbers as $word => $number) {
        if (mb_stripos($message, $word) !== false) {
            return $number;
        }
    }

    $cleaned = preg_replace('/07[3-9]\d{8}/', '', $message);
    if (preg_match('/(\d+)/', $cleaned, $m)) {
        $num = (int)$m[1];
        if ($num >= 1 && $num <= 100) {
            return $num;
        }
    }

    return null;
}

// TEST CASE FROM LIVE CONVERSATION
$userMessage = "اريد طقم ضوء قطعتين";
$state = "WAITING_PRODUCT_SELECTION";

echo "📝 User Message: \"$userMessage\"\n";
echo "🎯 Current State: $state\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$normalized = normalizeMessage($userMessage);
echo "✅ Step 1: Normalize message\n";
echo "   Input:  \"$userMessage\"\n";
echo "   Output: \"$normalized\"\n\n";

// Check if starts with "اريد"
$startsWithAriid = preg_match('/^اريد /u', $normalized);
echo "✅ Step 2: Check if starts with 'اريد' (want)\n";
echo "   Pattern: /^اريد /u\n";
echo "   Match: " . ($startsWithAriid ? "✓ YES" : "✗ NO") . "\n\n";

// Extract product name
$productName = extractProductName($normalized);
echo "✅ Step 3: Extract product name from message\n";
echo "   Removing: 'اريد', 'قطعتين', and other filler words\n";
echo "   Result: \"" . ($productName ?? "NULL") . "\"\n\n";

// Extract quantity
$quantity = extractQuantity($normalized);
echo "✅ Step 4: Extract quantity\n";
echo "   Looking for Arabic numbers: 'قطعتين', 'ثنين', etc.\n";
echo "   Result: " . ($quantity === null ? "NULL" : $quantity) . "\n\n";

// Determine intent
$intent = null;
if ($startsWithAriid && !preg_match('/^اريد هم/u', $normalized)) {
    $intent = "ADD_TO_CART";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "🎯 RESULT:\n";
echo "   Intent:    " . ($intent ?? "UNKNOWN") . "\n";
echo "   Product:   " . ($productName ?? "NULL") . "\n";
echo "   Quantity:  " . ($quantity ?? "1") . "\n";
echo "\n";

if ($intent === "ADD_TO_CART" && $productName && $quantity) {
    echo "✅ SUCCESS! Bot will add \"$productName\" × $quantity to cart\n";
    echo "\n📊 Expected next state: ADDING_TO_CART → CART_REVIEW\n";
} else {
    echo "❌ FAILED - Intent not recognized\n";
}
?>
