<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_fast_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Category: greeting, thanks, welcome_back, closing, etc.
            $table->string('category', 50);

            // The reply text (Arabic)
            $table->text('reply');

            // Keywords that trigger this reply (comma-separated or JSON)
            $table->json('trigger_keywords')->nullable();

            // Priority (higher = more likely to be selected)
            $table->integer('priority')->default(1);

            // Whether this is active
            $table->boolean('is_active')->default(true);

            // Usage stats
            $table->integer('usage_count')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'category']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_fast_replies');
    }
};
