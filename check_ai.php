<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$setting = \DB::table('ai_settings')->first();
if ($setting) {
    echo "Found settings for user_id: " . $setting->user_id . "\n";
    echo "openai_api_key: " . (!empty($setting->openai_api_key) ? "SET" : "EMPTY") . "\n";
    echo "ai_enabled: " . ($setting->ai_enabled ? "YES" : "NO") . "\n";
} else {
    echo "No records in ai_settings table.\n";
}
