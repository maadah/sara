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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('whatsapp')->nullable()->after('phone');
            $table->enum('role', ['admin', 'customer'])->default('customer')->after('whatsapp');
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending')->after('role');
            $table->string('facebook_link')->nullable()->after('status');
            $table->string('instagram_link')->nullable()->after('facebook_link');
            $table->string('store_address')->nullable()->after('instagram_link');
            $table->foreignId('subscription_id')->nullable()->after('store_address')->constrained('subscriptions')->onDelete('set null');
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->dropColumn([
                'phone',
                'whatsapp',
                'role',
                'status',
                'facebook_link',
                'instagram_link',
                'store_address',
                'subscription_id',
                'subscription_expires_at',
            ]);
        });
    }
};
