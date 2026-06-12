<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add rich demographic / profiling columns to customer_profiles.
 *
 * New fields collected by the AI during natural conversation:
 *   age, gender, budget_min, budget_max,
 *   occupation, marital_status, interests (JSON), social_platform.
 *
 * All columns are nullable; they accumulate over multiple sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_profiles', 'age')) {
                $table->unsignedTinyInteger('age')->nullable()->after('city')
                      ->comment('Customer age in years');
            }
            if (! Schema::hasColumn('customer_profiles', 'gender')) {
                $table->string('gender', 10)->nullable()->after('age')
                      ->comment('male | female | other');
            }
            if (! Schema::hasColumn('customer_profiles', 'budget_min')) {
                $table->unsignedInteger('budget_min')->nullable()->after('gender')
                      ->comment('Minimum stated budget in IQD');
            }
            if (! Schema::hasColumn('customer_profiles', 'budget_max')) {
                $table->unsignedInteger('budget_max')->nullable()->after('budget_min')
                      ->comment('Maximum stated budget in IQD');
            }
            if (! Schema::hasColumn('customer_profiles', 'occupation')) {
                $table->string('occupation', 120)->nullable()->after('budget_max')
                      ->comment('Job / profession stated by the customer');
            }
            if (! Schema::hasColumn('customer_profiles', 'marital_status')) {
                $table->string('marital_status', 20)->nullable()->after('occupation')
                      ->comment('single | married | divorced | other');
            }
            if (! Schema::hasColumn('customer_profiles', 'interests')) {
                $table->json('interests')->nullable()->after('marital_status')
                      ->comment('Array of interest keywords collected during chat');
            }
            if (! Schema::hasColumn('customer_profiles', 'social_platform')) {
                $table->string('social_platform', 30)->nullable()->after('interests')
                      ->comment('facebook | instagram | whatsapp | web');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $cols = [
                'age', 'gender', 'budget_min', 'budget_max',
                'occupation', 'marital_status', 'interests', 'social_platform',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('customer_profiles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
