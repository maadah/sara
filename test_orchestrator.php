<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Chat\ChatOrchestrator;
use App\Models\User;
use App\Models\Lead;

// Get the user we found earlier (id: 2)
$user = User::find(2);
if (!$user) {
    echo "User 2 not found.\n";
    exit;
}

// Get or create a lead
$lead = Lead::where('user_id', $user->id)->first() ?? Lead::create(['user_id' => $user->id, 'name' => 'Test User', 'phone' => '07701234567']);

echo "Testing Orchestrator for Store ID: {$user->id}, Lead ID: {$lead->id}\n";

$orchestrator = app(ChatOrchestrator::class);

try {
    $response = $orchestrator->processMessage(
        $user->id,
        $lead->id,
        "مرحبا، شنو عندكم ملابس؟",
        null,
        'web'
    );

    echo "Response received:\n";
    print_r($response);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
