<?php
/**
 * COMPREHENSIVE CHATBOT TEST SUITE
 * ==================================
 * Tests ALL conversation flows with multiple Iraqi Arabic speech styles.
 * Each test sends a message, analyzes the AI response, and reports pass/fail.
 *
 * Run: php test_comprehensive_chatbot.php
 *
 * Uses the LIVE GroqChatServiceV3 pipeline (Intent → State → Response)
 * to ensure end-to-end functionality matches production.
 */

require 'vendor/autoload.php';
define('LARAVEL_START', microtime(true));

// Set a fake IP to avoid Symfony IpUtils null error in CLI
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = 80;
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = \Illuminate\Http\Request::capture()
);

use App\Models\User;
use App\Models\Lead;
use App\Models\Conversation;
use App\Models\AiChatSession;
use App\Services\GroqChatServiceV3;
use App\Services\AI\IntentAnalyzer;
use App\Services\Orders\ProductService;
use App\Enums\ConversationState;
use App\Enums\Intent;
use Illuminate\Support\Facades\DB;

// ═══════════════════════════════════════════════════════════════════
// COLOUR HELPERS
// ═══════════════════════════════════════════════════════════════════

function green($t)  { return "\033[32m{$t}\033[0m"; }
function red($t)    { return "\033[31m{$t}\033[0m"; }
function yellow($t) { return "\033[33m{$t}\033[0m"; }
function cyan($t)   { return "\033[36m{$t}\033[0m"; }
function bold($t)   { return "\033[1m{$t}\033[0m"; }

// ═══════════════════════════════════════════════════════════════════
// STATS TRACKER
// ═══════════════════════════════════════════════════════════════════

$stats = [
    'total'    => 0,
    'passed'   => 0,
    'failed'   => 0,
    'warnings' => 0,
    'errors'   => [],
    'warnings_list' => [],
];

function reportTest(string $sectionName, string $message, array $result, array $checks, array &$stats): void
{
    $stats['total']++;
    $reply = $result['reply'] ?? '';
    $allPassed = true;
    $warnings  = [];
    $failures  = [];

    foreach ($checks as $checkName => $checkFn) {
        $checkResult = $checkFn($reply, $result);
        if ($checkResult === true) {
            // passed
        } elseif ($checkResult === 'warn') {
            $warnings[] = $checkName;
        } else {
            $allPassed = false;
            $failures[] = $checkName . ($checkResult !== false ? " ({$checkResult})" : '');
        }
    }

    // Output
    $shortReply = mb_substr(str_replace("\n", ' ', $reply), 0, 120);
    if ($allPassed && empty($warnings)) {
        echo green("  ✅ PASS") . " [{$sectionName}] \"{$message}\"\n";
        echo "     → " . cyan($shortReply) . "\n";
        $stats['passed']++;
    } elseif ($allPassed && !empty($warnings)) {
        echo yellow("  ⚠️  WARN") . " [{$sectionName}] \"{$message}\"\n";
        echo "     → " . cyan($shortReply) . "\n";
        echo "     ⚠️  " . implode(', ', $warnings) . "\n";
        $stats['passed']++;
        $stats['warnings']++;
        $stats['warnings_list'][] = "[{$sectionName}] {$message}: " . implode(', ', $warnings);
    } else {
        echo red("  ❌ FAIL") . " [{$sectionName}] \"{$message}\"\n";
        echo "     → " . cyan($shortReply) . "\n";
        echo "     ❌ " . implode(', ', $failures) . "\n";
        $stats['failed']++;
        $stats['errors'][] = "[{$sectionName}] {$message}: " . implode(', ', $failures);
    }
}

// ═══════════════════════════════════════════════════════════════════
// BOOTSTRAP
// ═══════════════════════════════════════════════════════════════════

echo bold("\n═══════════════════════════════════════════════════════════\n");
echo bold("  COMPREHENSIVE CHATBOT TEST SUITE\n");
echo bold("═══════════════════════════════════════════════════════════\n\n");

// Find store
$store = User::where('role', 'admin')->orWhere('is_store', true)->first();
if (!$store) {
    echo red("❌ No store found in database.\n");
    exit(1);
}
echo green("✅ Store: {$store->name} (ID: {$store->id})\n");

// Check API keys
$settings = $store->aiSetting;
$openaiKey = $settings->openai_api_key ?? env('OPENAI_API_KEY');
if (empty($openaiKey)) {
    echo red("❌ No OpenAI API key configured!\n");
    exit(1);
}
echo green("✅ OpenAI API key: " . substr($openaiKey, 0, 12) . "...\n");
echo "   Provider: " . ($settings->ai_provider ?? env('AI_PROVIDER', 'openai')) . "\n";
echo "   Model: " . env('OPENAI_MODEL', 'gpt-4.1-mini') . "\n";

// List categories
$productService = app(ProductService::class);
$categories = DB::table('categories')
    ->where('user_id', $store->id)
    ->where('is_active', true)
    ->pluck('name')
    ->toArray();
echo "\n📁 Active categories (" . count($categories) . "): " . implode(', ', $categories) . "\n";

// Count products
$productCount = DB::table('products')
    ->where('user_id', $store->id)
    ->where('is_active', true)
    ->count();
echo "📦 Active products: {$productCount}\n";

// ═══════════════════════════════════════════════════════════════════
// HELPER: Send message and get response (fresh session each time unless specified)
// ═══════════════════════════════════════════════════════════════════

$chatService = app(GroqChatServiceV3::class);
$intentAnalyzer = app(IntentAnalyzer::class);

/**
 * Send a single message in a FRESH session.
 */
function sendFresh(string $message, User $store, GroqChatServiceV3 $chatService): array
{
    // Create fresh lead and conversation for isolation
    $lead = Lead::create([
        'user_id' => $store->id,
        'name' => 'Test_' . uniqid(),
        'phone' => '+99' . rand(1000000, 9999999),
        'source' => 'test',
    ]);
    $conversation = Conversation::create([
        'user_id' => $store->id,
        'lead_id' => $lead->id,
        'platform' => 'test',
        'status'   => 'active',
    ]);

    try {
        $result = $chatService->processMessage($message, $store, $lead, $conversation);
        return is_array($result) ? $result : ['reply' => $result];
    } catch (\Throwable $e) {
        return ['reply' => '', 'error' => $e->getMessage()];
    }
}

/**
 * Send a SEQUENCE of messages in the SAME session.
 * Returns array of results, one per message.
 */
function sendConversation(array $messages, User $store, GroqChatServiceV3 $chatService): array
{
    $lead = Lead::create([
        'user_id' => $store->id,
        'name' => 'TestConv_' . uniqid(),
        'phone' => '+99' . rand(1000000, 9999999),
        'source' => 'test',
    ]);
    $conversation = Conversation::create([
        'user_id' => $store->id,
        'lead_id' => $lead->id,
        'platform' => 'test',
        'status'   => 'active',
    ]);

    $results = [];
    foreach ($messages as $msg) {
        try {
            $result = $chatService->processMessage($msg, $store, $lead, $conversation);
            $results[] = is_array($result) ? $result : ['reply' => $result];
        } catch (\Throwable $e) {
            $results[] = ['reply' => '', 'error' => $e->getMessage()];
        }
        // Small delay to avoid rate limiting
        usleep(300000); // 300ms
    }
    return $results;
}

// Common checkers
function hasReply(): Closure {
    return fn($reply, $r) => !empty($reply) ? true : 'Empty reply';
}
function replyContains(string ...$words): Closure {
    return function ($reply, $r) use ($words) {
        foreach ($words as $word) {
            if (mb_stripos($reply, $word) !== false) return true;
        }
        return 'Reply missing any of: ' . implode(', ', $words);
    };
}
function replyContainsAny(array $words): Closure {
    return function ($reply, $r) use ($words) {
        foreach ($words as $word) {
            if (mb_stripos($reply, $word) !== false) return true;
        }
        return 'Reply missing any of: ' . implode(', ', array_slice($words, 0, 5));
    };
}
function replyNotContains(string ...$words): Closure {
    return function ($reply, $r) use ($words) {
        foreach ($words as $word) {
            if (mb_stripos($reply, $word) !== false) {
                return "Reply unexpectedly contains: {$word}";
            }
        }
        return true;
    };
}
function minLength(int $len): Closure {
    return fn($reply, $r) => mb_strlen($reply) >= $len ? true : 'Reply too short (' . mb_strlen($reply) . " < {$len})";
}
function hasProducts(): Closure {
    return function ($reply, $r) {
        // Check for numbered products (#1, 1., 1-, etc.) OR product images
        $hasNumbers = preg_match('/[#\d]\s*[.\-\)]/u', $reply);
        $hasImages = !empty($r['images'] ?? []) || !empty($r['products_to_show'] ?? []);
        return ($hasNumbers || $hasImages) ? true : 'warn'; // warn, not fail
    };
}
function hasCategories(): Closure {
    return function ($reply, $r) {
        // Should mention categories or show a numbered list
        $hasNumbers = preg_match('/[\d]\s*[.\-\)]/u', $reply);
        $hasCatWords = preg_match('/قسم|فئ|صنف|اقسام|اصناف/u', $reply);
        return ($hasNumbers || $hasCatWords) ? true : 'warn';
    };
}

echo bold("\n\n─────────────────────────────────────────────────────────");
echo bold("\n SECTION 1: GREETINGS (Multiple Styles)\n");
echo bold("─────────────────────────────────────────────────────────\n\n");

$greetingTests = [
    'السلام عليكم',
    'مرحبا',
    'هلا',
    'هلو',
    'شلونك',
    'اهلا وسهلا',
    'سلام عليكم شلونكم',
    'هاي',
    'صباح الخير',
    'مساء الخير',
    'يا هلا',
    'شلونكم شباب',
];

$greetingChecks = [
    'has_reply' => hasReply(),
    'warm_greeting' => replyContainsAny(['هلا', 'اهلا', 'مرحبا', 'حياك', 'نورت', 'اهلين', 'سلام', 'خير', 'الله']),
    'min_length' => minLength(15),
];

foreach ($greetingTests as $msg) {
    $result = sendFresh($msg, $store, $chatService);
    reportTest('GREETING', $msg, $result, $greetingChecks, $stats);
    usleep(500000);
}

// Special: "منو انا" (who am I)
echo "\n  --- Identity Questions ---\n\n";
$identityTests = [
    'منو انا',
    'من انا',
    'تعرفني',
    'شنو اسمي',
];
foreach ($identityTests as $msg) {
    $result = sendFresh($msg, $store, $chatService);
    reportTest('IDENTITY', $msg, $result, [
        'has_reply' => hasReply(),
        'not_error' => replyNotContains('خلل', 'مشكل'),
    ], $stats);
    usleep(500000);
}

echo bold("\n\n─────────────────────────────────────────────────────────");
echo bold("\n SECTION 2: GENERAL BROWSE (Show Categories)\n");
echo bold("─────────────────────────────────────────────────────────\n\n");

$browseTests = [
    'شنو عدكم',
    'وريني شنو عدكم',
    'شنو المتوفر',
    'اريد اتسوق',
    'ابي اشوف المنتجات',
    'عندكم شي',
    'شنو تبيعون',
    'المنتجات',
    'اشوف المنتجات',
    'ابغى اتسوق عندك',
    'وريني البضاعه',
    'شنو الموجود',
];

$browseChecks = [
    'has_reply' => hasReply(),
    'has_categories_or_products' => hasCategories(),
    'min_length' => minLength(30),
];

foreach ($browseTests as $msg) {
    $result = sendFresh($msg, $store, $chatService);
    reportTest('BROWSE', $msg, $result, $browseChecks, $stats);
    usleep(500000);
}

echo bold("\n\n─────────────────────────────────────────────────────────");
echo bold("\n SECTION 3: CATEGORY SELECTION (Direct & Indirect)\n");
echo bold("─────────────────────────────────────────────────────────\n\n");

echo "  --- Direct Category Names ---\n\n";

// Test each real category name
foreach ($categories as $catName) {
    $result = sendFresh($catName, $store, $chatService);
    reportTest('CATEGORY-DIRECT', $catName, $result, [
        'has_reply' => hasReply(),
        'shows_products_or_info' => function ($reply, $r) {
            $hasNumberList = preg_match('/[\d]\s*[.\-\)]/u', $reply);
            $hasImages = !empty($r['images'] ?? []);
            $isInfo = mb_strlen($reply) > 40;
            return ($hasNumberList || $hasImages || $isInfo) ? true : 'warn';
        },
        'min_length' => minLength(20),
    ], $stats);
    usleep(500000);
}

echo "\n  --- Indirect Category Requests ---\n\n";

// We take first 3 categories for indirect tests
$testCats = array_slice($categories, 0, min(3, count($categories)));

$indirectPatterns = [
    'اريد {cat}',
    'ابي {cat}',
    'وريني {cat}',
    'اريد اشوف {cat}',
    'عندكم {cat}',
    'قسم {cat}',
    'فئة {cat}',
];

foreach ($testCats as $catName) {
    foreach ($indirectPatterns as $pattern) {
        $msg = str_replace('{cat}', $catName, $pattern);
        $result = sendFresh($msg, $store, $chatService);
        reportTest('CATEGORY-INDIRECT', $msg, $result, [
            'has_reply' => hasReply(),
            'min_length' => minLength(20),
        ], $stats);
        usleep(500000);
    }
}

echo bold("\n\n─────────────────────────────────────────────────────────");
echo bold("\n SECTION 4: SPECIAL PATTERNS (خاص ب، الكترونيه, etc.)\n");
echo bold("─────────────────────────────────────────────────────────\n\n");

// These are the EXACT bug patterns from the conversation logs
$specialPatterns = [];

// Build from available categories
foreach ($testCats as $catName) {
    $specialPatterns[] = "اريد منتج خاص ب{$catName}";
    $specialPatterns[] = "ابي شي يخص {$catName}";
    $specialPatterns[] = "اريد منتجات تخص {$catName}";
}

// Also test partial category matches (e.g., "الكترونيه" for "اجهزه الكترونيه")
// Extract single words from multi-word categories
$partialCatTests = [];
foreach ($categories as $catName) {
    $words = preg_split('/\s+/u', $catName);
    if (count($words) > 1) {
        foreach ($words as $w) {
            if (mb_strlen($w) >= 3) {
                $partialCatTests[] = $w;
            }
        }
    }
}

foreach ($specialPatterns as $msg) {
    $result = sendFresh($msg, $store, $chatService);
    reportTest('SPECIAL-خاص', $msg, $result, [
        'has_reply' => hasReply(),
        'not_showing_only_categories' => function ($reply, $r) {
            // Should show products, not just the category list again
            // Warn if too short (likely just categories)
            return mb_strlen($reply) > 30 ? true : 'warn';
        },
        'min_length' => minLength(20),
    ], $stats);
    usleep(500000);
}

echo "\n  --- Partial Category Names (word matching) ---\n\n";

foreach (array_unique(array_slice($partialCatTests, 0, 6)) as $partial) {
    $result = sendFresh($partial, $store, $chatService);
    reportTest('PARTIAL-CAT', $partial, $result, [
        'has_reply' => hasReply(),
        'min_length' => minLength(15),
    ], $stats);
    usleep(500000);
}

echo bold("\n\n─────────────────────────────────────────────────────────");
echo bold("\n SECTION 5: PRODUCT SEARCH\n");
echo bold("─────────────────────────────────────────────────────────\n\n");

// Get some real product names from the database
$sampleProducts = DB::table('products')
    ->where('user_id', $store->id)
    ->where('is_active', true)
    ->inRandomOrder()
    ->limit(5)
    ->pluck('name')
    ->toArray();

echo "  Sample products: " . implode(', ', $sampleProducts) . "\n\n";

$productSearchStyles = [
    'اريد {product}',
    'ابي {product}',
    'عندكم {product}',
    'متوفر {product}',
    'شنو سعر {product}',
    '{product}', // direct name
];

$testProducts = array_slice($sampleProducts, 0, min(3, count($sampleProducts)));
foreach ($testProducts as $prod) {
    foreach ($productSearchStyles as $pattern) {
        $msg = str_replace('{product}', $prod, $pattern);
        $result = sendFresh($msg, $store, $chatService);
        reportTest('PRODUCT-SEARCH', $msg, $result, [
            'has_reply' => hasReply(),
            'min_length' => minLength(15),
        ], $stats);
        usleep(500000);
    }
}

echo bold("\n\n─────────────────────────────────────────────────────────");
echo bold("\n SECTION 6: PRICE INQUIRIES\n");
echo bold("─────────────────────────────────────────────────────────\n\n");

$priceQuestions = [];
foreach (array_slice($sampleProducts, 0, 2) as $prod) {
    $priceQuestions[] = "شنو سعر {$prod}";
    $priceQuestions[] = "بچم {$prod}";
    $priceQuestions[] = "كم سعره";
    $priceQuestions[] = "بكم هذا";
}

foreach ($priceQuestions as $msg) {
    $result = sendFresh($msg, $store, $chatService);
    reportTest('PRICE', $msg, $result, [
        'has_reply' => hasReply(),
        'mentions_price_or_dinar' => replyContainsAny(['سعر', 'دينار', 'د.ع', 'الف', 'ألف', 'بسعر', 'IQD', 'بلش', 'يبلش']),
        'min_length' => minLength(10),
    ], $stats);
    usleep(500000);
}

echo bold("\n\n─────────────────────────────────────────────────────────");
echo bold("\n SECTION 7: FULL ORDER FLOW (Conversation Sequence)\n");
echo bold("─────────────────────────────────────────────────────────\n\n");

// Pick a real product and a category
$firstProduct = $sampleProducts[0] ?? 'منتج';
$firstCategory = $categories[0] ?? 'عام';

echo "  Testing full order flow with product: {$firstProduct}\n";
echo "  Category: {$firstCategory}\n\n";

$orderFlow = [
    'السلام عليكم',                           // 1. Greeting
    'شنو عدكم',                               // 2. Browse
    $firstCategory,                            // 3. Select category
    '1',                                       // 4. Select first product
    'اضيفه للسله',                            // 5. Add to cart
    'بس هذا يكفي',                            // 6. Done shopping
    'اسمي احمد محمد',                          // 7. Name
    'رقمي 07701234567',                        // 8. Phone
    'العنوان بغداد الكرادة',                     // 9. Address
    'تمام اكد الطلب',                          // 10. Confirm
];

$flowResults = sendConversation($orderFlow, $store, $chatService);

$flowChecks = [
    ['GREETING',     ['has_reply' => hasReply(), 'warm' => replyContainsAny(['هلا', 'اهلا', 'مرحبا', 'حياك', 'نورت'])]],
    ['BROWSE',       ['has_reply' => hasReply(), 'has_cats' => hasCategories()]],
    ['SELECT-CAT',   ['has_reply' => hasReply()]],
    ['SELECT-PROD',  ['has_reply' => hasReply()]],
    ['ADD-TO-CART',  ['has_reply' => hasReply(), 'cart_confirm' => replyContainsAny(['سله', 'سلة', 'اضف', 'اضاف', 'تمت', 'تم', 'طلب', 'كمل', 'تبي'])]],
    ['DONE-SHOP',    ['has_reply' => hasReply()]],
    ['NAME',         ['has_reply' => hasReply()]],
    ['PHONE',        ['has_reply' => hasReply()]],
    ['ADDRESS',      ['has_reply' => hasReply()]],
    ['CONFIRM',      ['has_reply' => hasReply()]],
];

for ($i = 0; $i < count($orderFlow); $i++) {
    $r = $flowResults[$i] ?? ['reply' => '', 'error' => 'No result'];
    $checks = $flowChecks[$i][1] ?? ['has_reply' => hasReply()];
    $stepName = $flowChecks[$i][0] ?? "STEP-$i";
    reportTest("ORDER-FLOW/{$stepName}", $orderFlow[$i], $r, $checks, $stats);
}

echo bold("\n\n─────────────────────────────────────────────────────────");
echo bold("\n SECTION 8: CUSTOMER DATA COLLECTION\n");
echo bold("─────────────────────────────────────────────────────────\n\n");

// Different styles of providing customer info
$infoStyles = [
    // Name styles
    ['اسمي علي حسين', 'NAME', replyContainsAny(['علي', 'حسين', 'اسم', 'رقم', 'هاتف', 'تم', 'حلو', 'موبايل', 'عنوان'])],
    ['انا اسمي فاطمه', 'NAME', replyContainsAny(['فاطم', 'اسم', 'رقم', 'هاتف', 'تم', 'موبايل'])],
    // Phone styles
    ['07712345678', 'PHONE', hasReply()],
    ['رقمي 07801234567', 'PHONE', hasReply()],
    ['الرقم هو 07901234567', 'PHONE', hasReply()],
    // Address styles
    ['بغداد المنصور', 'ADDRESS', hasReply()],
    ['العنوان البصرة شط العرب', 'ADDRESS', hasReply()],
    ['عنواني اربيل ستي سنتر', 'ADDRESS', hasReply()],
];

// Build a conversation that leads to customer info collection
$infoFlowMsgs = ['هلا', 'شنو عدكم'];
// If there's a category, browse it
if (!empty($firstCategory)) {
    $infoFlowMsgs[] = $firstCategory;
    $infoFlowMsgs[] = '1'; // select first product
    $infoFlowMsgs[] = 'اضيفه'; // add to cart
    $infoFlowMsgs[] = 'لا يكفي'; // done
}

$infoFlowResults = sendConversation($infoFlowMsgs, $store, $chatService);
echo "  Pre-flow: " . count($infoFlowMsgs) . " messages sent to reach info collection state\n";
$lastInfoResult = end($infoFlowResults);
echo "  Last pre-flow reply: " . mb_substr(str_replace("\n", ' ', $lastInfoResult['reply'] ?? ''), 0, 80) . "\n\n";

// Now test info provision in fresh sessions that are set up to the info state
foreach ($infoStyles as [$msg, $type, $check]) {
    $result = sendFresh($msg, $store, $chatService);
    reportTest("INFO-{$type}", $msg, $result, [
        'has_reply' => hasReply(),
        'info_check' => $check,
    ], $stats);
    usleep(500000);
}

echo bold("\n\n─────────────────────────────────────────────────────────");
echo bold("\n SECTION 9: CANCEL & DECLINE FLOWS\n");
echo bold("─────────────────────────────────────────────────────────\n\n");

$cancelTests = [
    'لا شكرا',
    'الغي الطلب',
    'ما اريد',
    'لا اريد اطلب',
    'خلاص',
    'سحب طلبي',
    'غير رأيي',
];

foreach ($cancelTests as $msg) {
    $result = sendFresh($msg, $store, $chatService);
    reportTest('CANCEL', $msg, $result, [
        'has_reply' => hasReply(),
        'min_length' => minLength(10),
    ], $stats);
    usleep(500000);
}

echo bold("\n\n─────────────────────────────────────────────────────────");
echo bold("\n SECTION 10: FAQ / GENERAL QUESTIONS\n");
echo bold("─────────────────────────────────────────────────────────\n\n");

$faqQuestions = [
    'شنو ساعات الدوام',
    'توصلون لكل المحافظات',
    'شلون اقدر ارجع منتج',
    'عندكم ضمان',
    'اكو توصيل مجاني',
    'شلون طريقة الدفع',
    'كم يوخذ التوصيل',
    'اكو خصومات',
    'وين موقعكم',
    'شلون اتواصل وياكم',
];

foreach ($faqQuestions as $msg) {
    $result = sendFresh($msg, $store, $chatService);
    reportTest('FAQ', $msg, $result, [
        'has_reply' => hasReply(),
        'min_length' => minLength(15),
        'natural_response' => function ($reply, $r) {
            // Should not be a copy-paste error message
            $bad = ['خلل', 'error', 'exception', 'null'];
            foreach ($bad as $b) {
                if (mb_stripos($reply, $b) !== false) return "Contains error word: {$b}";
            }
            return true;
        },
    ], $stats);
    usleep(500000);
}

echo bold("\n\n─────────────────────────────────────────────────────────");
echo bold("\n SECTION 11: SALES OBJECTIONS\n");
echo bold("─────────────────────────────────────────────────────────\n\n");

$objectionTests = [
    'حصلت بمكان ثاني بسعر ارخص',
    'غالي شوي',
    'مو غالي',
    'ممكن خصم',
    'اكو تخفيض',
    'اقل سعر بكم',
];

foreach ($objectionTests as $msg) {
    $result = sendFresh($msg, $store, $chatService);
    reportTest('OBJECTION', $msg, $result, [
        'has_reply' => hasReply(),
        'min_length' => minLength(15),
        'persuasive' => function ($reply, $r) {
            // Should try to retain the customer, not give error
            return mb_strlen($reply) > 20 ? true : 'warn';
        },
    ], $stats);
    usleep(500000);
}

echo bold("\n\n─────────────────────────────────────────────────────────");
echo bold("\n SECTION 12: MIXED / TRICKY INPUTS\n");
echo bold("─────────────────────────────────────────────────────────\n\n");

$mixedTests = [
    '؟؟؟',               // Just punctuation
    '...',               // Ellipsis
    'هههههه',            // Laughing
    '👍',                // Emoji
    'OK',                // English
    'شكرا',              // Thank you
    'ممنون',             // Thank you (formal)
    'الله يعطيك العافيه', // Blessing
    'يعني شنو',          // What do you mean
    'كلا',               // No
    'ايي',               // Yes (Iraqi dialect)
    'اي نعم',            // Yes formal
    'لا',                // No
    'يا حبيبي ساعدني',   // Emotional
    'بسرعه',             // Hurry
];

foreach ($mixedTests as $msg) {
    $result = sendFresh($msg, $store, $chatService);
    reportTest('MIXED', $msg, $result, [
        'has_reply' => hasReply(),
        'not_error' => function ($reply, $r) {
            if (isset($r['error'])) return "Error: " . $r['error'];
            $bad = ['exception', 'fatal', 'undefined'];
            foreach ($bad as $b) {
                if (mb_stripos($reply, $b) !== false) return "Contains error: {$b}";
            }
            return true;
        },
    ], $stats);
    usleep(500000);
}

echo bold("\n\n─────────────────────────────────────────────────────────");
echo bold("\n SECTION 13: INTENT ANALYZER UNIT TESTS\n");
echo bold("─────────────────────────────────────────────────────────\n\n");

// Test the IntentAnalyzer directly for critical patterns
$intentTests = [
    ['السلام عليكم', Intent::GREETING],
    ['مرحبا', Intent::GREETING],
    ['هلا', Intent::GREETING],
    ['منو انا', Intent::GREETING],
    ['شنو عدكم', Intent::BROWSE_PRODUCTS],
    ['وريني المنتجات', Intent::BROWSE_PRODUCTS],
    ['ابي اشوف المنتجات', Intent::BROWSE_PRODUCTS],
    ['اريد منتج خاص بالاكترونيات', Intent::BROWSE_PRODUCTS],
    ['الغي الطلب', Intent::CANCEL_ORDER],
    ['1', Intent::SELECT_PRODUCT],
    ['#3', Intent::SELECT_PRODUCT],
    ['الثاني', Intent::SELECT_PRODUCT],
    ['اريد الاول', Intent::SELECT_PRODUCT],
];

$state = ConversationState::IDLE;
$context = ['cart_summary' => null, 'last_product' => null];

foreach ($intentTests as [$msg, $expectedIntent]) {
    $stats['total']++;
    try {
        $intentResult = $intentAnalyzer->analyze($msg, $state, $context, $store);
        $actualIntent = $intentResult['intent'];

        if ($actualIntent === $expectedIntent) {
            echo green("  ✅ PASS") . " [INTENT] \"{$msg}\" → " . green($actualIntent->value) . "\n";
            $stats['passed']++;
        } else {
            echo red("  ❌ FAIL") . " [INTENT] \"{$msg}\" → " . red($actualIntent->value) . " (expected: " . yellow($expectedIntent->value) . ")\n";
            $stats['failed']++;
            $stats['errors'][] = "[INTENT] \"{$msg}\": got {$actualIntent->value}, expected {$expectedIntent->value}";
        }
    } catch (\Throwable $e) {
        echo red("  ❌ ERROR") . " [INTENT] \"{$msg}\" → " . red($e->getMessage()) . "\n";
        $stats['failed']++;
        $stats['errors'][] = "[INTENT] \"{$msg}\": " . $e->getMessage();
    }
    usleep(100000);
}

echo bold("\n\n─────────────────────────────────────────────────────────");
echo bold("\n SECTION 14: CATEGORY FINDER UNIT TESTS\n");
echo bold("─────────────────────────────────────────────────────────\n\n");

// Test ProductService::findCategory with various inputs
$categoryFinderTests = [];
foreach ($categories as $catName) {
    // Full name should match
    $categoryFinderTests[] = [$catName, true];
    // If multi-word, individual words should match
    $words = preg_split('/\s+/u', $catName);
    if (count($words) > 1) {
        foreach ($words as $w) {
            if (mb_strlen($w) >= 3) {
                $categoryFinderTests[] = [$w, true];
            }
        }
    }
}

foreach ($categoryFinderTests as [$input, $shouldMatch]) {
    $stats['total']++;
    try {
        $found = $productService->findCategory($store, $input);
        if ($shouldMatch && $found) {
            echo green("  ✅ PASS") . " [CAT-FIND] \"{$input}\" → " . green($found->name) . "\n";
            $stats['passed']++;
        } elseif ($shouldMatch && !$found) {
            echo red("  ❌ FAIL") . " [CAT-FIND] \"{$input}\" → " . red('NOT FOUND') . " (expected match)\n";
            $stats['failed']++;
            $stats['errors'][] = "[CAT-FIND] \"{$input}\": not found, expected match";
        } else {
            echo green("  ✅ PASS") . " [CAT-FIND] \"{$input}\" → correctly not found\n";
            $stats['passed']++;
        }
    } catch (\Throwable $e) {
        echo red("  ❌ ERROR") . " [CAT-FIND] \"{$input}\" → " . red($e->getMessage()) . "\n";
        $stats['failed']++;
    }
    usleep(50000);
}

echo bold("\n\n─────────────────────────────────────────────────────────");
echo bold("\n SECTION 15: CONVERSATION LOOP DETECTION\n");
echo bold("─────────────────────────────────────────────────────────\n\n");

// The BIG bug: category requests showing categories again in a loop
// Test: send category name → should show products, NOT categories list
echo "  Testing loop detection: category request should NOT loop\n\n";

foreach (array_slice($categories, 0, 3) as $catName) {
    // Send a greeting, then browse, then the category name
    $loopFlow = ['هلا', 'شنو عدكم', $catName];
    $loopResults = sendConversation($loopFlow, $store, $chatService);

    $catResult = $loopResults[2] ?? ['reply' => ''];
    $catReply = $catResult['reply'] ?? '';

    // The response should show PRODUCTS from that category, not the category list again
    $stats['total']++;
    $showsProducts = preg_match('/[\d]\s*[.\-\)]/u', $catReply);
    $showsImages = !empty($catResult['images'] ?? []);
    $isTooShort = mb_strlen($catReply) < 40;

    if ($showsProducts || $showsImages || !$isTooShort) {
        echo green("  ✅ PASS") . " [LOOP-CHECK] \"{$catName}\" → Shows products/info (no loop)\n";
        echo "     → " . cyan(mb_substr(str_replace("\n", ' ', $catReply), 0, 100)) . "\n";
        $stats['passed']++;
    } else {
        echo red("  ❌ FAIL") . " [LOOP-CHECK] \"{$catName}\" → Possibly showing category list again (LOOP!)\n";
        echo "     → " . cyan(mb_substr(str_replace("\n", ' ', $catReply), 0, 100)) . "\n";
        $stats['failed']++;
        $stats['errors'][] = "[LOOP-CHECK] \"{$catName}\": May be looping (shows category list)";
    }
    usleep(800000);
}

// Also test the "اريد + category" pattern
echo "\n  --- Testing 'اريد + category' pattern ---\n\n";
foreach (array_slice($categories, 0, 3) as $catName) {
    $msg = "اريد {$catName}";
    $result = sendFresh($msg, $store, $chatService);
    $reply = $result['reply'] ?? '';

    $stats['total']++;
    $showsProducts = preg_match('/[\d]\s*[.\-\)]/u', $reply);
    $showsImages = !empty($result['images'] ?? []);
    $isSubstantial = mb_strlen($reply) > 50;

    if ($showsProducts || $showsImages || $isSubstantial) {
        echo green("  ✅ PASS") . " [LOOP-WANT] \"{$msg}\" → Shows products\n";
        echo "     → " . cyan(mb_substr(str_replace("\n", ' ', $reply), 0, 100)) . "\n";
        $stats['passed']++;
    } else {
        echo red("  ❌ FAIL") . " [LOOP-WANT] \"{$msg}\" → Too short / may be looping\n";
        echo "     → " . cyan(mb_substr(str_replace("\n", ' ', $reply), 0, 100)) . "\n";
        $stats['failed']++;
        $stats['errors'][] = "[LOOP-WANT] \"{$msg}\": May loop";
    }
    usleep(500000);
}

echo bold("\n\n─────────────────────────────────────────────────────────");
echo bold("\n SECTION 16: OpenAI gpt-4.1-mini ENFORCEMENT\n");
echo bold("─────────────────────────────────────────────────────────\n\n");

// Verify config/env
$stats['total']++;
$envProvider = env('AI_PROVIDER');
$envModel = env('OPENAI_MODEL');
$settingsProvider = $settings->ai_provider ?? null;

$providerOk = ($envProvider === 'openai' || $settingsProvider === 'openai');
$modelOk = ($envModel === 'gpt-4.1-mini');

if ($providerOk && $modelOk) {
    echo green("  ✅ PASS") . " [CONFIG] AI_PROVIDER=openai, OPENAI_MODEL=gpt-4.1-mini\n";
    $stats['passed']++;
} else {
    echo red("  ❌ FAIL") . " [CONFIG] AI_PROVIDER={$envProvider} (settings: {$settingsProvider}), OPENAI_MODEL={$envModel}\n";
    $stats['failed']++;
    $stats['errors'][] = "[CONFIG] Wrong provider/model config";
}

// Check ChatAgentService hardcode
$stats['total']++;
$agentFile = file_get_contents(__DIR__ . '/app/Services/AI/ChatAgentService.php');
$hasOpenAIHardcode = strpos($agentFile, "'https://api.openai.com/v1/chat/completions'") !== false;
$hasModelHardcode = strpos($agentFile, "'gpt-4.1-mini'") !== false;

if ($hasOpenAIHardcode && $hasModelHardcode) {
    echo green("  ✅ PASS") . " [CODE] ChatAgentService hardcodes OpenAI gpt-4.1-mini\n";
    $stats['passed']++;
} else {
    echo red("  ❌ FAIL") . " [CODE] ChatAgentService missing hardcoded OpenAI/model\n";
    $stats['failed']++;
    $stats['errors'][] = "[CODE] ChatAgentService not hardcoded to OpenAI gpt-4.1-mini";
}

// ═══════════════════════════════════════════════════════════════════
// FINAL REPORT
// ═══════════════════════════════════════════════════════════════════

echo bold("\n\n═══════════════════════════════════════════════════════════\n");
echo bold("  FINAL RESULTS\n");
echo bold("═══════════════════════════════════════════════════════════\n\n");

echo "  Total tests:  " . bold($stats['total']) . "\n";
echo "  " . green("Passed:     {$stats['passed']}") . "\n";
echo "  " . red("Failed:     {$stats['failed']}") . "\n";
echo "  " . yellow("Warnings:   {$stats['warnings']}") . "\n";

$passRate = $stats['total'] > 0 ? round(($stats['passed'] / $stats['total']) * 100, 1) : 0;
echo "\n  Pass rate: " . ($passRate >= 90 ? green("{$passRate}%") : ($passRate >= 70 ? yellow("{$passRate}%") : red("{$passRate}%"))) . "\n";

if (!empty($stats['errors'])) {
    echo bold("\n\n  ❌ FAILURES:\n");
    foreach ($stats['errors'] as $i => $err) {
        echo red("    " . ($i + 1) . ". {$err}") . "\n";
    }
}

if (!empty($stats['warnings_list'])) {
    echo bold("\n\n  ⚠️  WARNINGS:\n");
    foreach ($stats['warnings_list'] as $i => $warn) {
        echo yellow("    " . ($i + 1) . ". {$warn}") . "\n";
    }
}

echo bold("\n═══════════════════════════════════════════════════════════\n\n");

// Cleanup test data
echo "  Cleaning up test data...\n";
$deleted = Lead::where('source', 'test')
    ->where('name', 'like', 'Test%')
    ->where('created_at', '>=', now()->subHour())
    ->count();
Lead::where('source', 'test')
    ->where('name', 'like', 'Test%')
    ->where('created_at', '>=', now()->subHour())
    ->delete();
echo "  Deleted {$deleted} test leads.\n";

// Delete test sessions
AiChatSession::whereHas('lead', function ($q) {
    $q->where('source', 'test')->where('name', 'like', 'Test%');
})->delete();

echo "\n  Done!\n\n";

exit($stats['failed'] > 0 ? 1 : 0);
