<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the cart_abandonments table.
 *
 * Snapshot of carts that were abandoned (no order after N hours).
 * Used for remarketing and analytics.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cart_abandonments')) {
            Schema::create('cart_abandonments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('session_id')->constrained('chat_sessions')->cascadeOnDelete();
                $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->foreignId('store_id')->constrained('users')->cascadeOnDelete();
                $table->json('cart_snapshot');
                $table->unsignedInteger('cart_total')->default(0);
                $table->timestamp('abandoned_at');

                $table->index(['store_id', 'abandoned_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_abandonments');
    }
};
