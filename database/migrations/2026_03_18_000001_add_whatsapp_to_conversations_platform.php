<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE conversations MODIFY COLUMN platform ENUM('facebook','instagram','whatsapp') DEFAULT 'facebook'");
        } else {
            // SQLite / others: column is already a text type, just update the default
            Schema::table('conversations', function (Blueprint $table) {
                $table->string('platform')->default('facebook')->change();
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE conversations MODIFY COLUMN platform ENUM('facebook','instagram') DEFAULT 'facebook'");
        } else {
            Schema::table('conversations', function (Blueprint $table) {
                $table->string('platform')->default('facebook')->change();
            });
        }
    }
};
