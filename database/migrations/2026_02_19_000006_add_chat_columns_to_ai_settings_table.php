<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add marketing-chatbot columns to the existing ai_settings table.
 *
 * Only adds columns that do not already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_settings', 'active_promotion')) {
                $table->text('active_promotion')->nullable()->after('delivery_cost');
            }
            if (! Schema::hasColumn('ai_settings', 'wholesale_info')) {
                $table->text('wholesale_info')->nullable()->after('active_promotion');
            }
            if (! Schema::hasColumn('ai_settings', 'out_of_scope_message')) {
                $table->text('out_of_scope_message')->nullable()->after('wholesale_info');
            }
            if (! Schema::hasColumn('ai_settings', 'human_handover_message')) {
                $table->text('human_handover_message')->nullable()->after('out_of_scope_message');
            }
            if (! Schema::hasColumn('ai_settings', 'max_cart_items')) {
                $table->unsignedInteger('max_cart_items')->default(10)->after('human_handover_message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $columns = [
                'active_promotion',
                'wholesale_info',
                'out_of_scope_message',
                'human_handover_message',
                'max_cart_items',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('ai_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
