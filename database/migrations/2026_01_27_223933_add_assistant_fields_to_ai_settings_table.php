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
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->string('openai_assistant_id')->nullable()->after('use_assistant_mode');
            $table->string('openai_vector_store_id')->nullable()->after('openai_assistant_id');
            $table->string('openai_file_id')->nullable()->after('openai_vector_store_id');
            $table->timestamp('assistant_synced_at')->nullable()->after('openai_file_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn([
                'openai_assistant_id',
                'openai_vector_store_id',
                'openai_file_id',
                'assistant_synced_at',
            ]);
        });
    }
};
