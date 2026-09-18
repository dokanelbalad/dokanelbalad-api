<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendor_profiles');
            $table->enum('status', ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled', 'disputed'])->default('pending');
            $table->enum('payment_method', ['cod', 'online']);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('commission_rate_applied', 4, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->decimal('total', 10, 2);
            $table->text('shipping_address');
            $table->string('shipping_governorate', 100);
            $table->timestamp('confirmed_by_buyer_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};