<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Backup database path
$backupPath = __DIR__ . '/database/database.sqlite.backup_20260112_091149';

if (!file_exists($backupPath)) {
    echo "Backup file not found: $backupPath\n";
    exit(1);
}

// Connect to backup database
$backup = new PDO('sqlite:' . $backupPath);
$backup->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Restoring from backup ===\n\n";

// 1. Restore Users (stores)
echo "Restoring users...\n";
$users = $backup->query("SELECT * FROM users WHERE role = 'customer'")->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($users) . " stores in backup\n";

foreach ($users as $user) {
    try {
        // Check if user already exists
        $existing = DB::table('users')->where('email', $user['email'])->first();
        if (!$existing) {
            DB::table('users')->insert([
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => $user['password'],
                'role' => $user['role'],
                'status' => $user['status'] ?? 'active',
                'store_name' => $user['store_name'] ?? null,
                'phone' => $user['phone'] ?? null,
                'address' => $user['address'] ?? null,
                'facebook_page_id' => $user['facebook_page_id'] ?? null,
                'facebook_page_access_token' => $user['facebook_page_access_token'] ?? null,
                'instagram_account_id' => $user['instagram_account_id'] ?? null,
                'whatsapp_phone_number_id' => $user['whatsapp_phone_number_id'] ?? null,
                'whatsapp_business_account_id' => $user['whatsapp_business_account_id'] ?? null,
                'created_at' => $user['created_at'],
                'updated_at' => $user['updated_at'],
            ]);
            echo "  ✓ Restored user: {$user['name']} ({$user['email']})\n";
        } else {
            echo "  - User already exists: {$user['email']}\n";
        }
    } catch (Exception $e) {
        echo "  ✗ Error restoring user {$user['email']}: " . $e->getMessage() . "\n";
    }
}

// 2. Restore Categories
echo "\nRestoring categories...\n";
$categories = $backup->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($categories) . " categories in backup\n";

foreach ($categories as $cat) {
    try {
        $existing = DB::table('categories')->where('id', $cat['id'])->first();
        if (!$existing) {
            DB::table('categories')->insert([
                'id' => $cat['id'],
                'user_id' => $cat['user_id'],
                'name' => $cat['name'],
                'description' => $cat['description'] ?? null,
                'image' => $cat['image'] ?? null,
                'created_at' => $cat['created_at'],
                'updated_at' => $cat['updated_at'],
            ]);
            echo "  ✓ Restored category: {$cat['name']}\n";
        }
    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
}

// 3. Restore Products
echo "\nRestoring products...\n";
$products = $backup->query("SELECT * FROM products")->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($products) . " products in backup\n";

foreach ($products as $prod) {
    try {
        $existing = DB::table('products')->where('id', $prod['id'])->first();
        if (!$existing) {
            DB::table('products')->insert([
                'id' => $prod['id'],
                'user_id' => $prod['user_id'],
                'category_id' => $prod['category_id'] ?? null,
                'name' => $prod['name'],
                'description' => $prod['description'] ?? null,
                'price' => $prod['price'],
                'stock' => $prod['stock'] ?? 0,
                'sku' => $prod['sku'] ?? null,
                'barcode' => $prod['barcode'] ?? null,
                'image' => $prod['image'] ?? null,
                'currency' => $prod['currency'] ?? 'IQD',
                'created_at' => $prod['created_at'],
                'updated_at' => $prod['updated_at'],
            ]);
            echo "  ✓ Restored product: {$prod['name']}\n";
        }
    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
}

// 4. Restore Product Images
echo "\nRestoring product images...\n";
$images = $backup->query("SELECT * FROM product_images")->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($images) . " product images in backup\n";

foreach ($images as $img) {
    try {
        $existing = DB::table('product_images')->where('id', $img['id'])->first();
        if (!$existing) {
            DB::table('product_images')->insert([
                'id' => $img['id'],
                'product_id' => $img['product_id'],
                'image_path' => $img['image_path'],
                'is_primary' => $img['is_primary'] ?? 0,
                'sort_order' => $img['sort_order'] ?? 0,
                'created_at' => $img['created_at'],
                'updated_at' => $img['updated_at'],
            ]);
        }
    } catch (Exception $e) {
        // Ignore
    }
}
echo "  ✓ Product images restored\n";

// 5. Restore AI Settings
echo "\nRestoring AI settings...\n";
$aiSettings = $backup->query("SELECT * FROM ai_settings")->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($aiSettings) . " AI settings in backup\n";

foreach ($aiSettings as $setting) {
    try {
        $existing = DB::table('ai_settings')->where('user_id', $setting['user_id'])->first();
        if (!$existing) {
            $data = [
                'user_id' => $setting['user_id'],
                'ai_enabled' => $setting['ai_enabled'] ?? 0,
                'auto_reply_enabled' => $setting['auto_reply_enabled'] ?? 1,
                'ai_provider' => $setting['ai_provider'] ?? 'openai',
                'openai_api_key' => $setting['openai_api_key'] ?? null,
                'openai_model' => $setting['openai_model'] ?? 'gpt-4o-mini',
                'groq_api_key' => $setting['groq_api_key'] ?? null,
                'groq_model' => $setting['groq_model'] ?? 'llama-3.3-70b-versatile',
                'store_description' => $setting['store_description'] ?? null,
                'store_policies' => $setting['store_policies'] ?? null,
                'greeting_message' => $setting['greeting_message'] ?? null,
                'system_instruction' => $setting['system_instruction'] ?? null,
                'created_at' => $setting['created_at'],
                'updated_at' => $setting['updated_at'],
            ];
            DB::table('ai_settings')->insert($data);
            echo "  ✓ Restored AI settings for user_id: {$setting['user_id']}\n";
        }
    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Restore Complete ===\n";
echo "Users: " . DB::table('users')->where('role', 'customer')->count() . "\n";
echo "Categories: " . DB::table('categories')->count() . "\n";
echo "Products: " . DB::table('products')->count() . "\n";
echo "AI Settings: " . DB::table('ai_settings')->count() . "\n";
