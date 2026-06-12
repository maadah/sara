<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add store contact / location columns to ai_settings.
 *
 * contact_phone : the store's own phone number — returned by get_store_info
 *                 so the AI never confuses it with the customer's phone.
 * store_location: physical address / Google Maps link of the store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_settings', 'contact_phone')) {
                $table->string('contact_phone', 30)->nullable()->after('delivery_cost')
                      ->comment('Store contact phone shown to customers on request');
            }
            if (! Schema::hasColumn('ai_settings', 'store_location')) {
                $table->text('store_location')->nullable()->after('contact_phone')
                      ->comment('Physical address or Maps link of the store');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            foreach (['contact_phone', 'store_location'] as $col) {
                if (Schema::hasColumn('ai_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
