<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the customer_profiles table.
 *
 * Extended CRM-style profile per store + lead,
 * with lead scoring, tags, preferences, and order history counters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->text('notes')->nullable();
            $table->json('tags')->nullable()->comment('hot, returning, vip, etc.');
            $table->unsignedInteger('lead_score')->default(0);
            $table->unsignedInteger('total_orders')->default(0);
            $table->timestamp('last_order_at')->nullable();
            $table->json('preferences')->nullable()->comment('Browsed categories, liked products');
            $table->timestamps();

            $table->unique(['store_id', 'lead_id']);
            $table->index('lead_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};
