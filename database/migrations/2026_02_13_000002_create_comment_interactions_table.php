<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');       // store owner
            $table->foreignId('product_id')->constrained()->onDelete('cascade');     // product they asked about
            $table->string('platform');                                               // facebook | instagram
            $table->string('commenter_id');                                           // platform user ID of commenter
            $table->string('commenter_name')->nullable();                             // display name
            $table->string('comment_id')->nullable();                                 // original comment ID from Graph API
            $table->string('post_id')->nullable();                                    // post ID from Graph API
            $table->text('comment_text')->nullable();                                 // what they wrote
            $table->boolean('replied')->default(false);                               // did we auto-reply?
            $table->boolean('dm_sent')->default(false);                               // did we send DM details?
            $table->timestamp('expires_at');                                           // 24h cache expiry
            $table->timestamps();

            $table->index(['commenter_id', 'user_id', 'expires_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_interactions');
    }
};
