<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\AiSetting;
use Illuminate\Console\Command;

class CreateDefaultAiSettings extends Command
{
    protected $signature = 'ai:create-default-settings {userId?}';
    protected $description = 'Create default AI settings for all users or a specific user';

    public function handle()
    {
        $userId = $this->argument('userId');
        
        if ($userId) {
            // Create for specific user
            $user = User::find($userId);
            
            if (!$user) {
                $this->error("User {$userId} not found!");
                return 1;
            }
            
            $this->createSettingsForUser($user);
            $this->info("✅ AI Settings created for user: {$user->name}");
        } else {
            // Create for all users without settings
            $users = User::whereDoesntHave('aiSetting')->get();
            
            if ($users->isEmpty()) {
                $this->info("All users already have AI Settings!");
                return 0;
            }
            
            $this->info("Found {$users->count()} users without AI Settings");
            $this->newLine();
            
            foreach ($users as $user) {
                $this->createSettingsForUser($user);
                $this->info("✅ Created for: {$user->name} (ID: {$user->id})");
            }
            
            $this->newLine();
            $this->info("✅ All done! {$users->count()} AI Settings created");
        }
        
        return 0;
    }
    
    protected function createSettingsForUser(User $user)
    {
        $apiKey = env('GROQ_API_KEY', config('services.groq.api_key'));
        
        AiSetting::create([
            'user_id' => $user->id,
            'ai_enabled' => true,
            'auto_reply_enabled' => true,
            'groq_api_key' => $apiKey,
            'groq_model' => 'llama-3.3-70b-versatile',
            'session_timeout_minutes' => 30,
            'max_history_turns' => 10,
            'enable_upsell' => true,
            'temperature' => 0.3,
        ]);
    }
}
