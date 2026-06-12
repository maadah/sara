<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            // AI Provider selection (openai or groq)
            if (!Schema::hasColumn('ai_settings', 'ai_provider')) {
                $table->string('ai_provider')->default('openai')->after('ai_enabled');
            }

            // OpenRouter settings (legacy)
            if (!Schema::hasColumn('ai_settings', 'openrouter_api_key')) {
                $table->string('openrouter_api_key')->nullable()->after('ai_provider');
            }

            // Auto-switch model feature
            if (!Schema::hasColumn('ai_settings', 'auto_switch_model')) {
                $table->boolean('auto_switch_model')->default(true)->after('openrouter_api_key');
            }

            // Fallback models
            if (!Schema::hasColumn('ai_settings', 'fallback_models')) {
                $table->text('fallback_models')->nullable()->after('auto_switch_model');
            }

            // Last working model
            if (!Schema::hasColumn('ai_settings', 'last_working_model')) {
                $table->string('last_working_model')->nullable()->after('fallback_models');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $columns = ['ai_provider', 'openrouter_api_key', 'auto_switch_model', 'fallback_models', 'last_working_model'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('ai_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
