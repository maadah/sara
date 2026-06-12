<?php
/**
 * ONE-TIME diagnostic + fixer for shared hosting (no SSH).
 * SECRET KEY is required in the URL: ?key=rehla2026migrate
 * DELETE THIS FILE immediately after use.
 */

$SECRET = 'rehla2026migrate';

if (!isset($_GET['key']) || $_GET['key'] !== $SECRET) {
    http_response_code(403);
    die('Forbidden');
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;background:#1e1e1e;color:#d4d4d4;">';

// Bootstrap Laravel
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// --- FULL LAST ERROR FROM LOG ---
echo "=== ACTUAL EXCEPTION MESSAGE FROM LOG ===\n";
try {
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        // Find all local.ERROR entries and get the last one
        preg_match_all('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \w+\.ERROR:.*?(?=\[\d{4}-\d{2}-\d{2}|\z)/s', $content, $matches);
        if (!empty($matches[0])) {
            $lastError = end($matches[0]);
            // Show first 3000 chars of the last error
            echo substr($lastError, 0, 3000) . "\n";
        } else {
            echo "(no ERROR entries found in log)\n";
        }
    } else {
        echo "(no log file found)\n";
    }
} catch (\Throwable $e) {
    echo "❌ Could not read log: " . $e->getMessage() . "\n";
}

// --- CLEAR CACHES ---
echo "\n=== CLEARING CACHES ===\n";
foreach (['view:clear', 'cache:clear', 'config:clear', 'route:clear'] as $cmd) {
    try {
        $kernel->call($cmd);
        echo "✅ $cmd\n";
        echo $kernel->output();
    } catch (\Throwable $e) {
        echo "⚠️  $cmd failed: " . $e->getMessage() . "\n";
    }
}

// --- CHECK KEY FILES EXIST ---
echo "\n=== KEY FILES CHECK ===\n";
$files = [
    resource_path('views/layouts/customer.blade.php'),
    resource_path('views/customer/leads/index.blade.php'),
    resource_path('views/customer/leads/show.blade.php'),
];
foreach ($files as $f) {
    echo (file_exists($f) ? '✅' : '❌ MISSING') . " $f\n";
}

// --- LIST SQLITE TABLES ---
echo "\n=== SQLITE TABLES ===\n";
try {
    $tables = \Illuminate\Support\Facades\DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    foreach ($tables as $t) {
        echo "  - {$t->name}\n";
    }
} catch (\Throwable $e) {
    echo "❌ " . $e->getMessage() . "\n";
}

echo '</pre>';
echo '<hr><p style="color:red;font-weight:bold;">⚠️ DELETE this file now: public/run_migrate.php</p>';

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;background:#1e1e1e;color:#d4d4d4;">';

// Bootstrap Laravel
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// --- DB INFO ---
try {
    $pdo  = \Illuminate\Support\Facades\DB::getPdo();
    $driver = \Illuminate\Support\Facades\DB::getDriverName();
    $dbName = \Illuminate\Support\Facades\DB::getDatabaseName();
    echo "=== DATABASE CONNECTION ===\n";
    echo "Driver : $driver\n";
    echo "DB Name: $dbName\n\n";
} catch (\Throwable $e) {
    echo "❌ DB Connection failed: " . $e->getMessage() . "\n";
}

// --- EXISTING TABLES ---
try {
    echo "=== EXISTING TABLES ===\n";
    $tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
    foreach ($tables as $t) {
        $t = (array)$t;
        echo "  - " . array_values($t)[0] . "\n";
    }
    echo "\n";
} catch (\Throwable $e) {
    echo "❌ Could not list tables: " . $e->getMessage() . "\n\n";
}

// --- LAST LARAVEL LOG ERROR ---
try {
    echo "=== LAST ERROR IN LARAVEL LOG ===\n";
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $last = array_slice($lines, -60);
        echo implode('', $last);
    } else {
        echo "(no log file found)\n";
    }
    echo "\n";
} catch (\Throwable $e) {
    echo "❌ Could not read log: " . $e->getMessage() . "\n\n";
}

// --- RUN MIGRATIONS ---
echo "=== RUNNING MIGRATIONS ===\n";
try {
    $exitCode = $kernel->call('migrate', ['--force' => true]);
    echo $kernel->output();
    echo "\nExit code: $exitCode\n";
    echo ($exitCode === 0) ? "✅ Done!\n" : "❌ Migration failed.\n";
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo '</pre>';
echo '<hr><p style="color:red;font-weight:bold;">⚠️ DELETE this file now: public/run_migrate.php</p>';
