<?php
/**
 * Test Chat Integration
 *
 * Quickly tests if the chat system can process messages
 * Run: php test_chat_integration.php
 */

require 'vendor/autoload.php';
define('LARAVEL_START', microtime(true));

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = \Illuminate\Http\Request::capture()
);

use App\Models\User;
use App\Models\Lead;
use App\Models\Conversation;
use App\Services\GroqChatServiceV3;
use Illuminate\Support\Facades\DB;

try {
    echo "═══════════════════════════════════════════════════════════\n";
    echo "CHAT INTEGRATION TEST\n";
    echo "═══════════════════════════════════════════════════════════\n\n";

    // 1. Check API Key
    echo "[1] Checking API Key Configuration...\n";
    $store = User::where('role', 'admin')->orWhere('is_store', true)->first();
    if (!$store) {
        echo "❌ No store found in database\n";
        exit(1);
    }
    echo "✅ Store found: {$store->name} (ID: {$store->id})\n";

    $settings = $store->aiSetting;
    if (!$settings) {
        echo "❌ No AI settings for store\n";
        exit(1);
    }

    $groqKey = $settings->groq_api_key ?? env('GROQ_API_KEY');
    $openaiKey = $settings->openai_api_key ?? env('OPENAI_API_KEY');

    if (empty($groqKey) && empty($openaiKey)) {
        echo "❌ No API keys configured!\n";
        exit(1);
    }

    if ($groqKey) echo "✅ Groq API Key: " . substr($groqKey, 0, 10) . "...\n";
    if ($openaiKey) echo "✅ OpenAI API Key: " . substr($openaiKey, 0, 10) . "...\n";

    // 2. Check or create test lead
    echo "\n[2] Checking Lead...\n";
    $lead = Lead::firstOrCreate(
        ['phone' => '+999999999'],
        ['name' => 'Test Lead', 'user_id' => $store->id]
    );
    echo "✅ Lead: {$lead->name} (ID: {$lead->id})\n";

    // 3. Check conversation
    echo "\n[3] Checking Conversation...\n";
    $conversation = Conversation::firstOrCreate(
        ['lead_id' => $lead->id],
        ['user_id' => $store->id]
    );
    echo "✅ Conversation created (ID: {$conversation->id})\n";

    // 4. Check GroqChatServiceV3 can be instantiated
    echo "\n[4] Testing GroqChatServiceV3 Instantiation...\n";
    $chatService = app(GroqChatServiceV3::class);
    if (!$chatService) {
        echo "❌ Failed to get GroqChatServiceV3 from container\n";
        exit(1);
    }
    echo "✅ GroqChatServiceV3 instantiated successfully\n";

    // 5. Test basic message
    echo "\n[5] Testing processMessage()...\n";
    $testMessage = "السلام عليكم";
    echo "   Sending: '{$testMessage}'\n";

    try {
        $result = $chatService->processMessage($testMessage, $store, $lead, $conversation);

        if (!$result) {
            echo "❌ No response returned\n";
            exit(1);
        }

        if (!is_array($result)) {
            echo "❌ Response is not an array: " . gettype($result) . "\n";
            exit(1);
        }

        echo "✅ Response received!\n";
        echo "   - Reply: " . (isset($result['reply']) ? 'YES (' . mb_strlen($result['reply']) . ' chars)' : 'NO') . "\n";
        echo "   - Images: " . count($result['images'] ?? []) . "\n";
        echo "   - Products: " . count($result['products_to_show'] ?? []) . "\n";

        if (empty($result['reply'])) {
            echo "   ⚠️  WARNING: Reply is empty!\n";
            echo "   Full result: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "   Reply preview: " . mb_substr($result['reply'], 0, 50) . "...\n";
        }

    } catch (\Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "\n";
        echo "   Stack: " . $e->getFile() . ":" . $e->getLine() . "\n";
        exit(1);
    }

    echo "\n═══════════════════════════════════════════════════════════\n";
    echo "✅ ALL TESTS PASSED\n";
    echo "═══════════════════════════════════════════════════════════\n";

} catch (\Exception $e) {
    echo "\n❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
