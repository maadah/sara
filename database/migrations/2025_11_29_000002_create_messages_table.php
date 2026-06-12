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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Null if from external participant

            // Message identification
            $table->string('external_id')->nullable()->unique(); // Facebook/Instagram message ID

            // Direction and content
            $table->enum('direction', ['incoming', 'outgoing'])->default('incoming');
            $table->text('content')->nullable(); // Text content
            $table->enum('message_type', ['text', 'image', 'video', 'audio', 'file', 'sticker', 'story_mention', 'story_reply', 'share', 'reaction'])->default('text');

            // Attachments
            $table->json('attachments')->nullable(); // Array of attachment URLs/data

            // Status tracking
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Reply reference
            $table->foreignId('reply_to_id')->nullable()->constrained('messages')->onDelete('set null');

            // Metadata from Facebook/Instagram
            $table->json('meta_data')->nullable();

            // Platform timestamp (when it was sent on the platform)
            $table->timestamp('platform_created_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['conversation_id', 'created_at']);
            $table->index('direction');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
