<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // External platforms that use our Facebook app as a proxy
        Schema::create('proxy_platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // Platform display name
            $table->string('domain');                      // e.g. app.example.com
            $table->string('api_key', 64)->unique();       // For API auth
            $table->string('api_secret', 128);             // HMAC secret for signing
            $table->string('webhook_url');                  // Where we forward webhooks
            $table->string('oauth_callback_url');           // Where we redirect after OAuth
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();           // Extra config
            $table->timestamps();
        });

        // Social accounts linked via external platforms (through our OAuth proxy)
        Schema::create('proxy_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proxy_platform_id')->constrained('proxy_platforms')->onDelete('cascade');
            $table->string('external_user_id');            // The user ID on the external platform
            $table->string('provider');                     // facebook_page, instagram
            $table->string('provider_id');                  // Page/IG account ID
            $table->text('provider_token')->nullable();     // Page access token (encrypted)
            $table->string('name')->nullable();             // Page/Account name
            $table->string('avatar')->nullable();
            $table->json('meta_data')->nullable();
            $table->json('granted_permissions')->nullable();
            $table->timestamps();

            $table->unique(['proxy_platform_id', 'provider', 'provider_id']);
            $table->index(['provider', 'provider_id']);
            $table->index(['proxy_platform_id', 'external_user_id']);
        });

        // Audit log for proxy API calls
        Schema::create('proxy_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proxy_platform_id')->constrained('proxy_platforms')->onDelete('cascade');
            $table->string('action', 50);                  // send_message, send_image, etc.
            $table->string('provider_id')->nullable();      // Page/IG ID
            $table->string('status', 20);                   // success, error
            $table->text('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxy_api_logs');
        Schema::dropIfExists('proxy_social_accounts');
        Schema::dropIfExists('proxy_platforms');
    }
};
