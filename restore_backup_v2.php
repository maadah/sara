<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Backup database path
$backupPath = __DIR__ . '/database/database.sqlite.backup_20260112_091149';

if (!file_exists($backupPath)) {
    echo "Backup file not found: $backupPath\n";
    exit(1);
}

// Connect to backup database
$backup = new PDO('sqlite:' . $backupPath);
$backup->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Restoring from backup (v2 - Schema Mapping) ===\n\n";

// Disable foreign key checks
DB::statement('PRAGMA foreign_keys = OFF');

// Get current schema columns
$userColumns = Schema::getColumnListing('users');
$productColumns = Schema::getColumnListing('products');
$aiSettingsColumns = Schema::getColumnListing('ai_settings');

echo "Current users columns: " . implode(', ', $userColumns) . "\n";
echo "Current products columns: " . implode(', ', $productColumns) . "\n\n";

// 1. Restore Users (stores)
echo "Restoring users...\n";
$users = $backup->query("SELECT * FROM users WHERE role = 'customer'")->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($users) . " stores in backup\n";

foreach ($users as $user) {
    try {
        // Check if user already exists
        $existing = DB::table('users')->where('email', $user['email'])->first();
        if (!$existing) {
            // Map backup columns to current schema
            $userData = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => $user['password'],
                'role' => $user['role'],
                'status' => $user['status'] ?? 'active',
                'phone' => $user['phone'] ?? null,
                'created_at' => $user['created_at'],
                'updated_at' => $user['updated_at'],
            ];

            // Map old columns to new columns
            if (in_array('whatsapp', $userColumns)) {
                $userData['whatsapp'] = $user['phone'] ?? null;
            }
            if (in_array('store_address', $userColumns)) {
                $userData['store_address'] = $user['address'] ?? null;
            }

            DB::table('users')->insert($userData);
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
            // Map backup columns to current schema
            $productData = [
                'id' => $prod['id'],
                'user_id' => $prod['user_id'],
                'category_id' => $prod['category_id'] ?? null,
                'name' => $prod['name'],
                'description' => $prod['description'] ?? null,
                'price' => $prod['price'] ?? 0,
                'is_active' => 1,
                'created_at' => $prod['created_at'],
                'updated_at' => $prod['updated_at'],
            ];

            // Map 'stock' to 'quantity' if new schema uses quantity
            if (in_array('quantity', $productColumns)) {
                $productData['quantity'] = $prod['stock'] ?? 0;
            }

            DB::table('products')->insert($productData);
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

$restoredImages = 0;
foreach ($images as $img) {
    try {
        $existing = DB::table('product_images')->where('id', $img['id'])->first();
        if (!$existing) {
            DB::table('product_images')->insert([
                'id' => $img['id'],
                'product_id' => $img['product_id'],
                'image_path' => $img['image_path'],
                'is_primary' => $img['is_primary'] ?? 0,
                'created_at' => $img['created_at'],
                'updated_at' => $img['updated_at'],
            ]);
            $restoredImages++;
        }
    } catch (Exception $e) {
        // Skip errors silently for images
    }
}
echo "  ✓ Restored $restoredImages product images\n";

// 5. Restore AI Settings
echo "\nRestoring AI settings...\n";
$aiSettings = $backup->query("SELECT * FROM ai_settings")->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($aiSettings) . " AI settings in backup\n";

foreach ($aiSettings as $setting) {
    try {
        // Check if user exists in database
        $userExists = DB::table('users')->where('id', $setting['user_id'])->exists();
        if (!$userExists) {
            echo "  - Skipping AI settings for user_id {$setting['user_id']} (user not restored)\n";
            continue;
        }

        $existing = DB::table('ai_settings')->where('user_id', $setting['user_id'])->first();
        if (!$existing) {
            $aiData = [
                'user_id' => $setting['user_id'],
                'ai_enabled' => $setting['ai_enabled'] ?? 1,
                'auto_reply_enabled' => $setting['auto_reply_enabled'] ?? 1,
                'ai_provider' => $setting['ai_provider'] ?? 'groq',
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

            // Add new columns with defaults
            if (in_array('use_assistant_mode', $aiSettingsColumns)) {
                $aiData['use_assistant_mode'] = 0;
            }

            DB::table('ai_settings')->insert($aiData);
            echo "  ✓ Restored AI settings for user_id: {$setting['user_id']}\n";
        }
    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
}

// Re-enable foreign key checks
DB::statement('PRAGMA foreign_keys = ON');

echo "\n=== Restore Complete ===\n";
echo "Users (stores): " . DB::table('users')->where('role', 'customer')->count() . "\n";
echo "Categories: " . DB::table('categories')->count() . "\n";
echo "Products: " . DB::table('products')->count() . "\n";
echo "Product Images: " . DB::table('product_images')->count() . "\n";
echo "AI Settings: " . DB::table('ai_settings')->count() . "\n";
