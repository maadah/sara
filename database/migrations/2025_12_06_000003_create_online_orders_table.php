<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Store owner
            $table->foreignId('lead_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('conversation_id')->nullable()->constrained()->onDelete('set null');

            // Customer Info (copied from lead or entered by AI)
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_whatsapp')->nullable();
            $table->text('customer_address');
            $table->string('customer_city')->nullable();
            $table->string('customer_area')->nullable();

            // Order Source
            $table->enum('source', ['facebook', 'instagram', 'whatsapp', 'ai_chat', 'manual'])->default('ai_chat');

            // Order Status
            $table->enum('status', [
                'pending',      // Waiting for confirmation
                'confirmed',    // Confirmed by store
                'processing',   // Being prepared
                'shipped',      // On the way
                'delivered',    // Delivered successfully
                'cancelled',    // Cancelled
                'returned'      // Returned
            ])->default('pending');

            // Order Totals
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 10)->default('IQD');

            // Payment
            $table->enum('payment_method', ['cash_on_delivery', 'bank_transfer', 'wallet'])->default('cash_on_delivery');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');

            // Notes
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();

            // Timestamps
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->json('meta_data')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'source']);
            $table->index('order_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_orders');
    }
};
