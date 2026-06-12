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
        Schema::create('unanswered_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('conversation_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('lead_id')->nullable()->constrained()->onDelete('set null');
            
            // Question Details
            $table->text('question'); // The customer's question
            $table->text('context')->nullable(); // Conversation context
            $table->string('detected_intent')->nullable(); // What AI thought it was
            $table->float('confidence_score')->nullable(); // AI confidence (0-1)
            
            // Admin Response
            $table->text('admin_answer')->nullable(); // Admin's answer
            $table->foreignId('answered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('answered_at')->nullable();
            
            // Status  
            $table->enum('status', ['pending', 'answered', 'ignored', 'added_to_kb'])->default('pending');
            $table->boolean('is_reviewed')->default(false);
            $table->integer('occurrence_count')->default(1); // How many times this question was asked
            
            // Categorization
            $table->string('category')->nullable();
            $table->json('similar_questions')->nullable(); // Array of similar questions
            $table->boolean('needs_urgent_attention')->default(false);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['status', 'is_reviewed']);
            $table->index('conversation_id');
            // Note: fulltext index removed for database compatibility
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unanswered_questions');
    }
};
