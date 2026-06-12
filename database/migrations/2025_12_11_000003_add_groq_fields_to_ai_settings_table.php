<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            // Groq API configuration
            $table->string('groq_api_key')->nullable()->after('ai_api_url');
            $table->string('groq_model')->default('llama-3.3-70b-versatile')->after('groq_api_key');

            // Session settings
            $table->integer('session_timeout_minutes')->default(30)->after('groq_model');
            $table->integer('max_history_turns')->default(10)->after('session_timeout_minutes');

            // Feature toggles
            $table->boolean('enable_upsell')->default(true)->after('max_history_turns');
            $table->boolean('enable_fast_replies')->default(true)->after('enable_upsell');

            // Store context for AI
            $table->string('working_hours')->nullable()->after('store_policies');
            $table->string('delivery_time')->default('نفس اليوم')->after('working_hours');
            $table->integer('delivery_cost')->default(5000)->after('delivery_time');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn([
                'groq_api_key',
                'groq_model',
                'session_timeout_minutes',
                'max_history_turns',
                'enable_upsell',
                'enable_fast_replies',
                'working_hours',
                'delivery_time',
                'delivery_cost',
            ]);
        });
    }
};
