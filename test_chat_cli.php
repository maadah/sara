<?php
/**
 * Simpler Chat Integration Test - Console Only
 *
 * Run: php test_chat_cli.php
 */

require 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Lead;
use App\Models\Conversation;
use App\Services\GroqChatServiceV3;
use Illuminate\Support\Facades\Log;

try {
    echo "═══════════════════════════════════════════════════════════\n";
    echo "CHAT INTEGRATION TEST (CLI)\n";
    echo "═══════════════════════════════════════════════════════════\n\n";

    // 1. Check API Key
    echo "[1] Loading Store...\n";
    $store = User::where('role', 'admin')->orWhere('is_store', true)->first();
    if (!$store) {
        $stores = User::where('is_store', true)->count();
        echo "❌ No admin/store user found (found {$stores} store users total)\n";

        // List all users
        $allUsers = User::select('id', 'name', 'role', 'is_store')->limit(5)->get();
        echo "\nFirst 5 users:\n";
        foreach ($allUsers as $u) {
            echo "  - [{$u->id}] {$u->name} (role={$u->role}, is_store={$u->is_store})\n";
        }

        // Try to use any store
        $store = User::where('is_store', true)->first();
        if (!$store) {
            exit("No store found at all!\n");
        }
    }
    echo "✅ Store: {$store->name} (ID: {$store->id})\n";

    // 2. Check API settings
    echo "\n[2] Checking API Keys...\n";
    $settings = $store->aiSetting;
    if ($settings) {
        $groqKey = $settings->groq_api_key;
        $openaiKey = $settings->openai_api_key;
        echo "   Groq: " . (empty($groqKey) ? "❌ MISSING" : "✅ " . substr($groqKey, 0, 10) . "...") . "\n";
        echo "   OpenAI: " . (empty($openaiKey) ? "❌ MISSING" : "✅ " . substr($openaiKey, 0, 10) . "...") . "\n";
    } else {
        echo "   ❌ No AI settings found for store\n";
    }

    // 3. Get or create test lead
    echo "\n[3] Creating Test Lead...\n";
    $lead = Lead::firstOrCreate(
        ['phone' => '+999999999999'],
        ['name' => 'CLI Test', 'user_id' => $store->id]
    );
    echo "✅ Lead: {$lead->name} (ID: {$lead->id})\n";

    // 4. Get or create conversation
    echo "\n[4] Creating Test Conversation...\n";
    $conversation = Conversation::firstOrCreate(
        ['lead_id' => $lead->id],
        ['user_id' => $store->id]
    );
    echo "✅ Conversation (ID: {$conversation->id})\n";

    // 5. Check if container can resolve the service
    echo "\n[5] Resolving GroqChatServiceV3...\n";
    try {
        $chatService = app(GroqChatServiceV3::class);
        echo "✅ Service resolved\n";
    } catch (\Throwable $e) {
        echo "❌ Failed to resolve: " . $e->getMessage() . "\n";
        echo "   Class: " . get_class($e) . "\n";
        exit(1);
    }

    // 6. Test message
    echo "\n[6] Sending Test Message...\n";
    $message = "السلام عليكم";
    echo "   Message: '{$message}'\n";
    echo "   Store ID: {$store->id}, Lead ID: {$lead->id}\n";

    $result = $chatService->processMessage($message, $store, $lead, $conversation);

    echo "   Result keys: " . implode(', ', array_keys($result)) . "\n";

    if (empty($result['reply'])) {
        echo "   ⚠️  EMPTY REPLY!\n";
        var_dump($result);
    } else {
        echo "   ✅ Reply: " . mb_substr($result['reply'], 0, 60) . "...\n";
    }

    echo "\n═══════════════════════════════════════════════════════════\n";
    echo "✅ TEST COMPLETE\n";
    echo "═══════════════════════════════════════════════════════════\n";

} catch (\Throwable $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nFile: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nTrace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
