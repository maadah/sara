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
        Schema::create('ai_knowledge_base', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Question and Answer
            $table->text('question'); // The customer question
            $table->text('answer'); // Admin's answer
            
            // Metadata
            $table->string('category')->nullable(); // e.g., 'delivery', 'products', 'payment'
            $table->json('keywords')->nullable(); // Keywords extracted from question
            $table->integer('usage_count')->default(0); // How many times this Q&A was used
            
            // Status
            $table->enum('status', ['active', 'inactive', 'draft'])->default('active');
            $table->boolean('is_verified')->default(false); // Admin verified this answer
            
            // Training
            $table->boolean('use_for_training')->default(true); // Include in AI training
            $table->integer('priority')->default(0); // Higher priority = more important
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('category');
            // Note: fulltext index removed for database compatibility
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_base');
    }
};
