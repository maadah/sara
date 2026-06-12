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
        // Add cart_hash if not exists
        if (!Schema::hasColumn('online_orders', 'cart_hash')) {
            Schema::table('online_orders', function (Blueprint $table) {
                $table->string('cart_hash', 64)->nullable()->after('status');
            });
        }

        // Source column may already exist from previous migrations
        if (!Schema::hasColumn('online_orders', 'source')) {
            Schema::table('online_orders', function (Blueprint $table) {
                $table->string('source', 50)->nullable();
            });
        }

        // Add index for duplicate checking
        Schema::table('online_orders', function (Blueprint $table) {
            // Use raw SQL to check if index exists (SQLite doesn't support checking via Schema)
            try {
                $table->index(['lead_id', 'cart_hash', 'created_at'], 'idx_order_dedup');
            } catch (\Exception $e) {
                // Index may already exist, ignore
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropIndex('idx_order_dedup');
            $table->dropColumn(['cart_hash', 'source']);
        });
    }
};
