<?php
/**
 * Chat Integration Test - Using Existing Data
 *
 * Run: php test_chat_existing.php
 */

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Lead;
use App\Models\Conversation;
use App\Models\AiChatSession;
use App\Services\GroqChatServiceV3;

echo "═══════════════════════════════════════════════════════════\n";
echo "CHAT TEST - Using Existing Data\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// 1. Check stores
echo "[1] Checking Stores...\n";
$store = User::where('is_store', true)->firstOrFail();
echo "✅ Store: {$store->name} (ID: {$store->id})\n";

// 2. Check API keys
echo "\n[2] Checking API Configuration...\n";
$settings = $store->aiSetting;
if ($settings) {
    $groqKey = $settings->groq_api_key ?? env('GROQ_API_KEY');
    $openaiKey = $settings->openai_api_key ?? env('OPENAI_API_KEY');

    if (empty($groqKey)) {
        echo "⚠️  Groq API Key: MISSING (no store setting, env: " . (empty(env('GROQ_API_KEY')) ? "MISSING" : "✅") . ")\n";
    } else {
        echo "✅ Groq Key: " . substr($groqKey, 0, 10) . "...\n";
    }

    if (empty($openaiKey)) {
        echo "⚠️  OpenAI Key: MISSING (no store setting, env: " . (empty(env('OPENAI_API_KEY')) ? "MISSING" : "✅") . ")\n";
    } else {
        echo "✅ OpenAI Key: " . substr($openaiKey, 0, 10) . "...\n";
    }
} else {
    echo "⚠️  No AI settings for store\n";
}

// 3. Check environment
echo "\n[3] Environment Variables...\n";
echo "   APP_ENV: " . env('APP_ENV') . "\n";
echo "   GROQ_API_KEY: " . (empty(env('GROQ_API_KEY')) ? "❌ MISSING" : "✅ SET") . "\n";
echo "   OPENAI_API_KEY: " . (empty(env('OPENAI_API_KEY')) ? "❌ MISSING" : "✅ SET") . "\n";

// 4. Get existing conversation
echo "\n[4] Finding Test Conversation...\n";
$conversation = Conversation::where('user_id', $store->id)->first();
if (!$conversation) {
    echo "❌ No conversations found for this store\n";
    echo "   Creating sample conversation...\n";
    $lead = Lead::firstOrCreate(
        ['phone' => '+999888777'],
        ['name' => 'Test Lead', 'user_id' => $store->id]
    );
    $conversation = Conversation::create([
        'lead_id' => $lead->id,
        'user_id' => $store->id,
        'social_account_id' => 1,  // Required field
    ]);
    echo "   ✅ Created conversation {$conversation->id}\n";
} else {
    echo "✅ Found conversation {$conversation->id}\n";
}

$lead = $conversation->lead;
echo "   Lead: {$lead->name} (ID: {$lead->id})\n";

// 5. Try resolving service
echo "\n[5] Resolving GroqChatServiceV3...\n";
try {
    $chatService = app(GroqChatServiceV3::class);
    echo "✅ Service resolved successfully\n";
} catch (\Exception $e) {
    echo "❌ Failed to resolve service:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    exit(1);
}

// 6. Test a simple message
echo "\n[6] Testing Message Processing...\n";
$testMessage = "السلام عليكم";
echo "   Message: '$testMessage'\n";
echo "   Waiting for response (this calls the LLM)...\n";

try {
    $startTime = microtime(true);
    $result = $chatService->processMessage($testMessage, $store, $lead, $conversation);
    $duration = round((microtime(true) - $startTime) * 1000);

    echo "   ✅ Response received in {$duration}ms\n";

    if (!is_array($result)) {
        echo "   ❌ Response is not array: " . gettype($result) . "\n";
        var_dump($result);
    } else {
        echo "   Keys: " . implode(', ', array_keys($result)) . "\n";

        if (isset($result['reply'])) {
            if (empty($result['reply'])) {
                echo "   ⚠️  REPLY IS EMPTY\n";
            } else {
                echo "   ✅ Reply (" . mb_strlen($result['reply']) . " chars): \n";
                echo "      " . mb_substr($result['reply'], 0, 80) . "...\n";
            }
        }

        echo "   - Images: " . count($result['images'] ?? []) . "\n";
        echo "   - Products: " . count($result['products_to_show'] ?? []) . "\n";
        echo "   - Actions: " . count($result['actions'] ?? []) . "\n";
    }

} catch (\Exception $e) {
    echo "   ❌ Exception during processing:\n";
    echo "      " . $e->getMessage() . "\n";
    echo "      File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    if ($e->getPrevious()) {
        echo "      Caused by: " . $e->getPrevious()->getMessage() . "\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "TEST COMPLETE\n";
echo "═══════════════════════════════════════════════════════════\n";
