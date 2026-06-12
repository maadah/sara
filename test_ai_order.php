<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Lead;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\User;
use App\Services\AiChatService;
use Illuminate\Support\Facades\Log;

// Get the latest lead
$lead = Lead::latest()->first();
echo "Lead ID: {$lead->id}\n";
echo "Lead Name: {$lead->name}\n";
echo "Lead Phone: {$lead->phone}\n";
echo "Lead City: {$lead->city}\n";
echo "Lead Address: " . ($lead->address ?? 'NULL') . "\n";
echo "Lead Interests: " . (is_array($lead->interests) ? json_encode($lead->interests, JSON_UNESCAPED_UNICODE) : ($lead->interests ?? 'NULL')) . "\n";

// Reset for testing
$lead->address = null;
$lead->interests = null;
$lead->save();
echo "\nLead data reset.\n";

// Get conversation
$conversation = $lead->conversation;
if ($conversation) {
    $context = $conversation->ai_context ?? [];
    $context['collected_data'] = [];
    $conversation->ai_context = $context;
    $conversation->save();
    echo "Conversation context reset.\n";
}

// Now test the product detection manually
$user = User::find($lead->user_id);
$products = Product::where('user_id', $user->id)->where('is_active', true)->get();

echo "\nProducts:\n";
foreach ($products as $product) {
    echo "- {$product->id}: {$product->name} ({$product->price})\n";
}

// Test message (Arabic transliteration)
$testMessages = [
    'ماستر كونتر',
    'Master Counter',
    'تشيرت',
    'بغداد / الكفاح',
];

$transliterations = [
    'master' => ['ماستر', 'ماسطر', 'مستر'],
    'counter' => ['كونتر', 'كاونتر', 'كونترر'],
    'shirt' => ['شيرت', 'تشيرت', 'تيشرت', 'تيشيرت'],
    't-shirt' => ['تيشرت', 'تيشيرت', 'تشيرت'],
];

echo "\n=== Testing Product Detection ===\n";

foreach ($testMessages as $message) {
    echo "\nTesting message: '{$message}'\n";
    $messageLower = mb_strtolower($message);

    foreach ($products as $product) {
        $productNameLower = mb_strtolower($product->name);
        $matched = false;

        // Direct match
        if (mb_strpos($messageLower, $productNameLower) !== false) {
            $matched = true;
            echo "  -> Direct match for: {$product->name}\n";
        }

        // Check transliterations
        if (!$matched) {
            foreach ($transliterations as $english => $arabicVariants) {
                if (mb_stripos($productNameLower, $english) !== false) {
                    foreach ($arabicVariants as $arabic) {
                        if (mb_strpos($messageLower, $arabic) !== false) {
                            $matched = true;
                            echo "  -> Transliteration match for: {$product->name} ('{$english}' matched with '{$arabic}')\n";
                            break 2;
                        }
                    }
                }
            }
        }

        // Also check if product name words appear separately
        if (!$matched) {
            $productWords = preg_split('/\s+/', $productNameLower);
            foreach ($productWords as $word) {
                if (mb_strlen($word) > 3 && mb_strpos($messageLower, $word) !== false) {
                    $matched = true;
                    echo "  -> Word match for: {$product->name} (word: '{$word}')\n";
                    break;
                }
            }
        }

        if (!$matched) {
            echo "  -> No match for: {$product->name}\n";
        }
    }
}

echo "\nTest completed.\n";

// Test address extraction
echo "\n=== Testing Address Extraction ===\n";

$cities = [
    'بغداد', 'البصرة', 'الموصل', 'أربيل', 'اربيل', 'النجف', 'كربلاء',
    'الحلة', 'الكوت', 'الديوانية', 'السماوة', 'الناصرية', 'العمارة',
    'الرمادي', 'تكريت', 'كركوك', 'السليمانية', 'دهوك', 'واسط',
    'ديالى', 'الانبار', 'صلاح الدين', 'ميسان', 'ذي قار', 'المثنى'
];

$testAddresses = [
    'بغداد / الكفاح',
    'البصرة - الزبير',
    'عنواني بغداد شارع الرشيد',
    'منطقة الدورة',
];

foreach ($testAddresses as $message) {
    echo "\nTesting: '{$message}'\n";

    // Check for city/area format like "بغداد / الكفاح" or "بغداد - الكفاح"
    if (preg_match('/([^\n\r\/\-]+)\s*[\/\-]\s*([^\n\r]+)/u', $message, $matches)) {
        $part1 = trim($matches[1]);
        $part2 = trim($matches[2]);

        echo "  -> Pattern matched: '{$part1}' and '{$part2}'\n";

        // Check if first part is a city
        foreach ($cities as $city) {
            if (mb_stripos($part1, $city) !== false || mb_stripos($part2, $city) !== false) {
                $address = $part1 . ' / ' . $part2;
                echo "  -> City detected! Address: '{$address}'\n";
                break;
            }
        }
    } else {
        echo "  -> No pattern match\n";
    }
}

