<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the missed_intents table.
 *
 * Captures messages where intent could not be classified.
 * Useful for training / improving the intent classifier over time.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('missed_intents')) {
            Schema::create('missed_intents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('session_id')->constrained('chat_sessions')->cascadeOnDelete();
                $table->text('raw_message');
                $table->string('detected_state', 40)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['store_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('missed_intents');
    }
};
