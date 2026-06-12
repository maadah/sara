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
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('attribute_key', 50);       // size, color, material, etc.
            $table->string('attribute_value', 255);    // L, Red, Cotton, etc.
            $table->decimal('price_modifier', 12, 2)->default(0); // Extra price for this variant
            $table->integer('stock_quantity')->nullable(); // Stock for this specific variant
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'attribute_key']);
            $table->index(['attribute_key', 'attribute_value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
    }
};
