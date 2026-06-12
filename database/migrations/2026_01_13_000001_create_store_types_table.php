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
        Schema::create('store_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();           // clothing, electronics, food, etc.
            $table->string('display_name', 100);            // اسم العرض بالعربي
            $table->string('display_name_en', 100);         // Display name in English
            $table->json('required_attributes')->nullable(); // ['size', 'color'] for clothing
            $table->json('optional_attributes')->nullable(); // ['material', 'brand']
            $table->text('ai_template')->nullable();         // Extra AI instructions for this type
            $table->text('order_questions')->nullable();     // Questions to ask before order
            $table->boolean('requires_stock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add store_type_id to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('store_type_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['store_type_id']);
            $table->dropColumn('store_type_id');
        });

        Schema::dropIfExists('store_types');
    }
};
