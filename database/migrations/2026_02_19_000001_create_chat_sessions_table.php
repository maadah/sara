<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the chat_sessions table.
 *
 * Stores one session per store + lead + conversation thread.
 * Cart, customer data, history, and meta are stored as JSON for flexibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('conversation_id')->nullable()->comment('Social media thread ID');
            $table->string('channel', 20)->default('web')->comment('facebook|instagram|web');
            $table->string('state', 40)->default('idle');
            $table->json('cart')->nullable();
            $table->json('customer_data')->nullable();
            $table->json('history')->nullable()->comment('Last 20 message pairs');
            $table->json('meta')->nullable()->comment('tone, browse_count, lead_score, upsell_shown, etc.');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('cart_abandoned_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'lead_id']);
            $table->index(['store_id', 'conversation_id']);
            $table->index('last_activity_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
