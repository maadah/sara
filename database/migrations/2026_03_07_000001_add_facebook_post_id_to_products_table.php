<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Stores the page-scoped Graph API object ID (e.g. "122103146919287716")
            // so webhook post_id matching works even when it differs from the fbid in the URL.
            $table->string('facebook_post_id')->nullable()->after('facebook_post_url');
            $table->string('instagram_post_id')->nullable()->after('instagram_post_url');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['facebook_post_id', 'instagram_post_id']);
        });
    }
};
