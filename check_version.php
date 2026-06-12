<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$setting = \DB::table('ai_settings')->first();
if ($setting) {
    echo "service_version: " . ($setting->service_version ?? '3 (default)') . "\n";
    echo "openai_api_key: " . ($setting->openai_api_key ? 'SET' : 'EMPTY') . "\n";
    echo "groq_api_key: " . ($setting->groq_api_key ? 'SET' : 'EMPTY') . "\n";
}
