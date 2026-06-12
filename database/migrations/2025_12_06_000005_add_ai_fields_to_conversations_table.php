<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add lead_id to conversations table
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable()->after('user_id')->constrained()->onDelete('set null');
            $table->boolean('ai_enabled')->default(true)->after('status');
            $table->json('ai_context')->nullable()->after('ai_enabled'); // Store AI conversation context
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
            $table->dropColumn(['lead_id', 'ai_enabled', 'ai_context']);
        });
    }
};
