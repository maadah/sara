<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // When false, the product is a service/digital good — quantity is ignored
            // and the AI always treats it as available.
            $table->boolean('manage_stock')->default(true)->after('reserved_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('manage_stock');
        });
    }
};
