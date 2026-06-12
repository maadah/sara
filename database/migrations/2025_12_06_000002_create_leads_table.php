<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Store owner
            $table->foreignId('conversation_id')->nullable()->constrained()->onDelete('set null');

            // Customer Info
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('area')->nullable();

            // Lead Source
            $table->enum('source', ['facebook', 'instagram', 'whatsapp', 'manual'])->default('facebook');
            $table->string('platform_user_id')->nullable(); // Facebook/Instagram user ID

            // Lead Status
            $table->enum('status', ['new', 'contacted', 'interested', 'converted', 'lost'])->default('new');
            $table->integer('interest_score')->default(0); // 0-100

            // Analytics
            $table->integer('total_messages')->default(0);
            $table->integer('total_orders')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->timestamp('first_contact_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();

            // Notes
            $table->text('notes')->nullable();
            $table->json('interests')->nullable(); // Products/categories they showed interest in
            $table->json('meta_data')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'source']);
            $table->index('platform_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
