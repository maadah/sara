<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Store owner
            $table->foreignId('lead_id')->constrained()->onDelete('cascade');
            $table->foreignId('conversation_id')->nullable()->constrained()->onDelete('set null');

            // Session data
            $table->json('store_context')->nullable(); // Store info, products
            $table->json('messages')->default('[]'); // Chat history
            $table->json('cart')->default('[]'); // Current cart items
            $table->json('customer_data')->default('{}'); // Collected customer info
            $table->json('current_order')->nullable(); // Pending order before confirmation

            // Tracking
            $table->integer('message_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'lead_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_sessions');
    }
};
