<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\SocialAccount;
use App\Models\AiSetting;

$user = User::first();
if ($user) {
    echo "User ID: {$user->id}, Email: {$user->email}\n";
    $aiSettings = $user->aiSetting;
    if ($aiSettings) {
        echo "AI Enabled: " . ($aiSettings->ai_enabled ? "Yes" : "No") . "\n";
        echo "Auto Reply Enabled: " . ($aiSettings->auto_reply_enabled ? "Yes" : "No") . "\n";
        echo "OpenAI Key: " . (!empty($aiSettings->openai_api_key) ? "Set" : "Not Set") . "\n";
    } else {
        echo "No AI Settings found for user.\n";
    }

    $socialAccount = SocialAccount::where('user_id', $user->id)->get();
    echo "Social Accounts count: " . $socialAccount->count() . "\n";
    foreach ($socialAccount as $sa) {
        echo "- Provider: {$sa->provider}, ID: {$sa->provider_id}, Token: " . (!empty($sa->provider_token) ? "Set" : "Not Set") . "\n";
    }
} else {
    echo "No users found in database.\n";
}
