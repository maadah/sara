<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\AiFastReply;

class AiFastReplySeeder extends Seeder
{
    public function run(): void
    {
        // Get all users with stores
        $users = User::whereNotNull('name')->get();

        foreach ($users as $user) {
            // Check if user already has fast replies
            $existingCount = AiFastReply::where('user_id', $user->id)->count();

            if ($existingCount === 0) {
                AiFastReply::createDefaultsForUser($user->id);
                $this->command->info("Created default fast replies for user: {$user->name}");
            }
        }
    }
}
