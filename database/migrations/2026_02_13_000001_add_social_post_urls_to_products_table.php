<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('facebook_post_url')->nullable()->after('is_active');
            $table->string('instagram_post_url')->nullable()->after('facebook_post_url');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['facebook_post_url', 'instagram_post_url']);
        });
    }
};
