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
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('provider'); // facebook, instagram
            $table->string('provider_id'); // The unique ID from the provider
            $table->string('provider_token')->nullable(); // Access token
            $table->string('provider_refresh_token')->nullable(); // Refresh token (if available)
            $table->timestamp('token_expires_at')->nullable(); // Token expiration
            $table->string('name')->nullable(); // Account name/username
            $table->string('email')->nullable(); // Email from provider
            $table->string('avatar')->nullable(); // Profile picture URL
            $table->json('meta_data')->nullable(); // Additional data from provider
            $table->boolean('is_primary')->default(false); // Primary login account
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->index(['user_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
