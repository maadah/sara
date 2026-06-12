<?php
// ONE-TIME DEPLOY SCRIPT — DELETE AFTER USE
// Upload this file to your server's /public/ folder then visit:
// https://rihlaa-ai.com/deploy_fix.php

$secret = 'rehla2026fix';

if (($_GET['key'] ?? '') !== $secret) {
    die('Forbidden');
}

$basePath = dirname(__DIR__);
$php      = PHP_BINARY;

header('Content-Type: text/plain; charset=utf-8');

echo "=== Deploy Fix Script ===\n\n";

$commands = [
    'Config clear'  => "{$php} {$basePath}/artisan config:clear",
    'Cache clear'   => "{$php} {$basePath}/artisan cache:clear",
    'Route clear'   => "{$php} {$basePath}/artisan route:clear",
    'View clear'    => "{$php} {$basePath}/artisan view:clear",
];

foreach ($commands as $label => $cmd) {
    echo "[ {$label} ]\n";
    $output = shell_exec($cmd . ' 2>&1');
    echo $output . "\n";
}

echo "\n=== Done! Delete this file now. ===\n";
echo "DELETE: " . __FILE__ . "\n";
