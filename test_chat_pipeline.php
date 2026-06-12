<?php

/**
 * Full pipeline diagnostic for the NEW AI Marketing Chatbot.
 * Tests every stage and reports exactly what fails.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AiSetting;
use App\Models\Lead;
use App\Models\User;
use App\Services\Chat\ChatOrchestrator;
use App\Services\Chat\EntityExtractor;
use App\Services\Chat\IntentClassifier;
use App\Services\Chat\SessionManager;
use App\Services\Chat\StoreContextBuilder;
use Illuminate\Support\Facades\Http;

// ─── Counters + helpers (closures) ───────────────────────────────────────────
$pass = 0;
$fail = 0;

$check = function(bool $cond, string $label, string $reason = '') use (&$pass, &$fail): bool {
    if ($cond) { echo "  \u{2705} {$label}\n"; $pass++; }
    else       { echo "  \u{274C} {$label}" . ($reason ? " \u{2014} {$reason}" : '') . "\n"; $fail++; }
    return $cond;
};

$hr   = function(string $title = '') {
    echo "\n" . str_repeat('-', 62) . "\n";
    if ($title) echo "  {$title}\n" . str_repeat('-', 62) . "\n";
};

$info = function(string $text) { echo "     {$text}\n"; };

// =============================================================================
$hr('STAGE 1 -- Config & Language');

$apiKey     = config('chat.openai.api_key', '');
$convModel  = config('chat.models.conversation', '');
$classModel = config('chat.models.classification', '');
$locale     = app()->getLocale();

$check(!empty($apiKey),    'OPENAI_API_KEY is set', 'Missing from .env');
$info("Conversation model  : {$convModel}");
$info("Classification model: {$classModel}");
$info("APP_LOCALE          : {$locale}");

$testKey = __('chat.greeting_default');
$translationOk = $testKey !== 'chat.greeting_default';
$check($translationOk, 'Translation keys resolve',
    $translationOk ? '' : "Got bare key name -- missing lang/{$locale}/chat.php");
if ($translationOk) $info("Sample : " . mb_substr($testKey, 0, 60));

// =============================================================================
$hr('STAGE 2 -- Database');

$store = User::where('role', 'customer')->first();
if (!$store) {
    echo "  No store (role=customer) found -- cannot continue.\n";
    exit(1);
}
$check(true, "Store found: #{$store->id} {$store->name}");

$lead = Lead::where('user_id', $store->id)->first();
if (!$lead) {
    try {
        $lead = Lead::create([
            'user_id'     => $store->id,
            'name'        => 'Test Diagnostic Lead',
            'platform'    => 'web',
            'platform_id' => 'diag-' . time(),
        ]);
        $check(true, "Test lead created: #{$lead->id}");
    } catch (\Throwable $e) {
        $check(false, 'Lead creation failed', $e->getMessage());
        exit(1);
    }
} else {
    $check(true, "Lead found: #{$lead->id} {$lead->name}");
}

$aiSetting = AiSetting::where('user_id', $store->id)->first();
$info($aiSetting ? "AiSetting found" : "No AiSetting -- defaults will be used");

// =============================================================================
$hr('STAGE 3 -- Session Manager');

try {
    $sm      = app(SessionManager::class);
    $session = $sm->loadOrCreate($store->id, $lead->id, null, 'web');
    $check((bool) $session->id, "Session created/loaded (id: {$session->id})");
    $check($session->store_id === $store->id, 'session.store_id matches');
    $check($session->lead_id  === $lead->id,  'session.lead_id matches');
    $info("State: {$session->state->value}");
} catch (\Throwable $e) {
    $check(false, 'SessionManager failed', $e->getMessage());
    exit(1);
}

// =============================================================================
$hr('STAGE 4 -- Store System Prompt');

try {
    $ctx    = app(StoreContextBuilder::class);
    $prompt = $ctx->getSystemPrompt($store->id);
    $check(!empty($prompt), 'System prompt generated (' . strlen($prompt) . ' chars)');
    $info("Preview: " . mb_substr($prompt, 0, 120) . '...');
} catch (\Throwable $e) {
    $check(false, 'StoreContextBuilder failed', $e->getMessage());
}

// =============================================================================
$hr('STAGE 5 -- OpenAI Direct API Call');

try {
    $baseUrl  = config('chat.openai.base_url', 'https://api.openai.com/v1');
    $response = Http::timeout(30)
        ->withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type'  => 'application/json',
        ])
        ->post("{$baseUrl}/chat/completions", [
            'model'      => $convModel,
            'messages'   => [['role' => 'user', 'content' => 'قل مرحبا فقط']],
            'max_tokens' => 20,
        ]);

    if ($response->successful()) {
        $body  = $response->json();
        $reply = $body['choices'][0]['message']['content'] ?? '';
        $check(true, "OpenAI API reachable (HTTP 200)");
        $check(!empty($reply), "Got reply: " . mb_substr($reply, 0, 60));
        $info("Tokens used: " . ($body['usage']['total_tokens'] ?? '?'));
    } else {
        $errBody = $response->json();
        $errMsg  = $errBody['error']['message'] ?? $response->body();
        $check(false, "OpenAI API returned HTTP {$response->status()}", mb_substr($errMsg, 0, 200));
    }
} catch (\Throwable $e) {
    $check(false, 'OpenAI API call exception', $e->getMessage());
}

// =============================================================================
$hr('STAGE 6 -- Intent Classifier');

try {
    $classifier = app(IntentClassifier::class);
    $result     = $classifier->classify('مرحبا ابي اشوف منتجاتكم', []);
    $check(!empty($result['intent']), 'Intent classified: ' . $result['intent']->value);
    $check($result['confidence'] > 0, 'Confidence: ' . $result['confidence']);
} catch (\Throwable $e) {
    $check(false, 'IntentClassifier failed', $e->getMessage());
}

// =============================================================================
$hr('STAGE 7 -- Entity Extractor');

try {
    $extractor = app(EntityExtractor::class);
    $entities  = $extractor->extract('ابي احذية حمراء مقاس 42', 'browsing');
    $check(is_array($entities), 'Entity extraction returned array');
    $info("Entities: " . json_encode($entities, JSON_UNESCAPED_UNICODE));
} catch (\Throwable $e) {
    $check(false, 'EntityExtractor failed', $e->getMessage());
}

// =============================================================================
$hr('STAGE 8 -- Full ChatOrchestrator (E2E)');

foreach (['مرحبا', 'شنو عدكم من منتجات؟'] as $msg) {
    echo "\n     Sending: \"{$msg}\"\n";
    try {
        $orchestrator = app(ChatOrchestrator::class);
        $result       = $orchestrator->processMessage(
            storeId:        $store->id,
            leadId:         $lead->id,
            message:        $msg,
            conversationId: null,
            channel:        'web',
        );

        $reply   = $result['reply'] ?? '';
        $isEmpty = empty(trim($reply));
        $isKey   = str_starts_with($reply, 'chat.');

        $check(!$isEmpty, 'Got non-empty reply', $isEmpty ? 'reply is empty string' : '');
        $check(!$isKey,   'Reply is real text (not bare key)', $isKey ? "Got key: {$reply}" : '');

        if (!$isEmpty && !$isKey) {
            $info("Reply: " . mb_substr($reply, 0, 150));
        }
        $info("Session: #{$result['session_id']} | Images: " . count($result['images']) . " | Products: " . count($result['products']));
    } catch (\Throwable $e) {
        $check(false, 'Orchestrator threw exception', $e->getMessage());
        $info("at " . $e->getFile() . ':' . $e->getLine());
    }
}

// =============================================================================
$hr('SUMMARY');

$total    = $pass + $fail;
$passRate = $total > 0 ? round($pass / $total * 100) : 0;
echo "  Passed: {$pass}  Failed: {$fail}  Total: {$total}  Pass rate: {$passRate}%\n\n";

if ($fail === 0) {
    echo "  ALL CHECKS PASSED -- System is fully working!\n\n";
    exit(0);
} else {
    echo "  Failures detected -- fix the issues above.\n\n";
    exit(1);
}
