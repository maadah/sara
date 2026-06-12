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
            if (!Schema::hasColumn('ai_settings', 'openai_api_key')) {
                $table->string('openai_api_key')->nullable()->after('groq_model');
            }
            if (!Schema::hasColumn('ai_settings', 'openai_model')) {
                $table->string('openai_model')->nullable()->default('gpt-5-nano')->after('openai_api_key');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn(['openai_api_key', 'openai_model']);
        });
    }
};
