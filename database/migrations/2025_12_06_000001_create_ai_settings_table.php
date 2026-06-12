<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // AI API Settings
            $table->string('ai_api_url')->default('http://localhost:5000');
            $table->string('ai_model')->default('models/gemini-2.0-flash-lite');
            $table->boolean('ai_enabled')->default(false);

            // AI Behavior Settings
            $table->text('system_instruction')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.7);
            $table->decimal('top_p', 3, 2)->default(0.95);
            $table->integer('top_k')->default(40);
            $table->integer('max_output_tokens')->default(2048);

            // Auto-reply Settings
            $table->boolean('auto_reply_enabled')->default(true);
            $table->boolean('collect_customer_info')->default(true);
            $table->boolean('can_create_orders')->default(true);

            // Store Context for AI
            $table->text('store_description')->nullable();
            $table->text('store_policies')->nullable();
            $table->text('greeting_message')->nullable();

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
