<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$setting = \DB::table('ai_settings')->first();
if ($setting) {
    echo "ai_provider: " . ($setting->ai_provider ?? 'not set') . "\n";
}
