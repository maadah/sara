<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the chat_messages table.
 *
 * Every inbound/outbound/tool message in a chat session is stored here
 * for full audit trail, analytics, and cost tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('chat_sessions')->cascadeOnDelete();
            $table->string('role', 20)->comment('user|assistant|tool');
            $table->text('content');
            $table->string('intent', 40)->nullable();
            $table->json('entities')->nullable();
            $table->json('tool_calls')->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('session_id');
            $table->index('intent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
