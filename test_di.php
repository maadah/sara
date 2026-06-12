<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $svc = app(App\Services\GroqChatServiceV3::class);
    echo "OK: " . get_class($svc) . PHP_EOL;

    $agent = app(App\Services\AI\ChatAgentService::class);
    echo "ChatAgent OK: " . get_class($agent) . PHP_EOL;

    echo "DI Container: ✅ All services resolved successfully" . PHP_EOL;
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo "In: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
}
