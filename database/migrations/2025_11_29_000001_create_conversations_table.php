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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // The platform user (customer)
            $table->foreignId('social_account_id')->constrained()->onDelete('cascade'); // The FB Page or IG account

            // External participant info (the person messaging the page/account)
            $table->string('participant_id'); // Facebook/Instagram user ID
            $table->string('participant_name')->nullable();
            $table->string('participant_avatar')->nullable();

            // Platform info
            $table->enum('platform', ['facebook', 'instagram'])->default('facebook');
            $table->string('thread_id')->nullable(); // Platform's conversation/thread ID

            // Conversation status
            $table->enum('status', ['active', 'archived', 'spam', 'blocked'])->default('active');
            $table->boolean('is_read')->default(false);
            $table->integer('unread_count')->default(0);

            // Last message preview
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();

            // Metadata
            $table->json('meta_data')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['social_account_id', 'participant_id', 'platform'], 'unique_conversation');
            $table->index(['user_id', 'platform']);
            $table->index(['user_id', 'status']);
            $table->index('last_message_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
