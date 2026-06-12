<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AiFastReply;
use App\Models\User;

class AiFastRepliesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This will add default fast replies for ALL users (stores)
     * Each user will get their own copy
     */
    public function run(): void
    {
        // Get all customer users (not admin)
        $users = User::where('role', 'customer')->get();
        
        foreach ($users as $user) {
            // Check if user already has fast replies
            $existingCount = AiFastReply::where('user_id', $user->id)->count();
            
            if ($existingCount > 0) {
                $this->command->info("User {$user->name} already has fast replies, skipping...");
                continue;
            }
            
            // Get default fast replies template
            $defaults = $this->getDefaultFastReplies($user->id);
            
            foreach ($defaults as $reply) {
                AiFastReply::create($reply);
            }
            
            $this->command->info("Created " . count($defaults) . " fast replies for user: {$user->name}");
        }
    }
    
    /**
     * Get default fast replies for a user
     * Using the EXISTING table schema (category, reply, trigger_keywords)
     */
    private function getDefaultFastReplies(int $userId): array
    {
        return [
            [
                'user_id' => $userId,
                'category' => 'welcome',
                'reply' => 'هلا وغلا! 👋 أهلاً بيك، شنو اقدر أساعدك به اليوم؟ 😊',
                'trigger_keywords' => ['السلام عليكم', 'مرحبا', 'هلا', 'هاي', 'صباح الخير', 'مساء الخير'],
                'priority' => 10,
                'is_active' => true,
                'usage_count' => 0,
            ],
            [
                'user_id' => $userId,
                'category' => 'goodbye',
                'reply' => 'حياك الله! 🙏 تشرفنا بخدمتك، إذا احتجت أي شي احنا موجودين!',
                'trigger_keywords' => ['الله يحفظك', 'باي', 'مع السلامة', 'بس', 'تمام شكراً'],
                'priority' => 5,
                'is_active' => true,
                'usage_count' => 0,
            ],
            [
                'user_id' => $userId,
                'category' => 'thanks',
                'reply' => 'شكراً لثقتك! 🎉 طلبك راح يوصلك قريباً، نتمنى يعجبك! ❤️',
                'trigger_keywords' => ['اطلب', 'شكراً', 'يسلمو'],
                'priority' => 8,
                'is_active' => true,
                'usage_count' => 0,
            ],
            [
                'user_id' => $userId,
                'category' => 'delivery',
                'reply' => "🚚 نعم يتوفر توصيل لكل أنحاء العراق!\n⏱️ وقت التوصيل: 1-3 أيام عمل\n💰 سعر التوصيل: يعتمد على المنطقة\n\nتحب تطلب الحين؟ 😊",
                'trigger_keywords' => ['توصيل', 'يوصل', 'شحن', 'وقت التوصيل', 'متى يوصل'],
                'priority' => 7,
                'is_active' => true,
                'usage_count' => 0,
            ],
            [
                'user_id' => $userId,
                'category' => 'pricing',
                'reply' => "💰 الأسعار تختلف حسب المنتج\nتقدر تشوف كل المنتجات والأسعار من خلال القائمة\n\nشنو المنتج اللي تبيه؟ 🛍️",
                'trigger_keywords' => ['سعر', 'كم السعر', 'شكد', 'بكم', 'بكام'],
                'priority' => 6,
                'is_active' => true,
                'usage_count' => 0,
            ],
            [
                'user_id' => $userId,
                'category' => 'availability',
                'reply' => "✅ نعم متوفر! ومتجددين باستمرار\nشنو المنتج اللي تدور عليه؟ 🔍",
                'trigger_keywords' => ['موجود', 'متوفر', 'عندكم', 'يتوفر'],
                'priority' => 7,
                'is_active' => true,
                'usage_count' => 0,
            ],
        ];
    }
}
