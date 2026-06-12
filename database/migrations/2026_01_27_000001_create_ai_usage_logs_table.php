<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('ai_usage_logs')) {
            Schema::create('ai_usage_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Store owner
                $table->string('provider', 50)->default('openai'); // openai, groq, etc.
                $table->string('model', 100); // gpt-5-nano, gpt-4o, llama-3.3-70b-versatile
                $table->string('request_type', 50)->default('chat'); // chat, order, welcome, confirm, inquiry
                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('output_tokens')->default(0);
                $table->unsignedInteger('total_tokens')->default(0);
                $table->decimal('estimated_cost', 10, 6)->default(0); // Cost in USD
                $table->string('conversation_id')->nullable(); // Link to conversation
                $table->string('lead_id')->nullable(); // Link to lead/customer
                $table->json('metadata')->nullable(); // Additional info (response_time, etc.)
                $table->timestamps();
                
                // Indexes for reporting
                $table->index(['user_id', 'created_at']);
                $table->index(['user_id', 'provider']);
                $table->index(['user_id', 'model']);
                $table->index('created_at');
            });
        }

        // Add new fields to ai_settings table for OpenAI support
        Schema::table('ai_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_settings', 'openai_api_key')) {
                $table->string('openai_api_key')->nullable()->after('groq_model');
            }
            if (!Schema::hasColumn('ai_settings', 'openai_model')) {
                $table->string('openai_model', 100)->default('gpt-5-nano')->after('openai_api_key');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn(['ai_provider', 'openai_api_key', 'openai_model']);
        });
    }
};
