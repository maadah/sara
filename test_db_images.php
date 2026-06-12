<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$products = \App\Models\ProductImage::pluck('image_path')->take(5);
echo "Products:\n";
print_r($products->toArray());

$services = \App\Models\Service::pluck('portfolio')->take(5);
echo "\nServices:\n";
print_r($services->toArray());
