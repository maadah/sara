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
        Schema::table('ai_chat_sessions', function (Blueprint $table) {
            // Add explicit state column for V3 state machine
            $table->string('conversation_state', 50)->default('idle')->after('conversation_id');

            // Add cart hash for order deduplication (used by OrderService)
            $table->string('cart_hash', 64)->nullable()->after('cart');

            // Index for finding active sessions
            $table->index(['user_id', 'lead_id', 'conversation_state', 'updated_at'], 'idx_active_sessions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_chat_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_active_sessions');
            $table->dropColumn(['conversation_state', 'cart_hash']);
        });
    }
};
