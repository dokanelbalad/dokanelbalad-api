<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->enum('vendor_type', ['shop', 'individual']);
            $table->string('store_name', 150)->nullable();
            $table->text('store_description')->nullable();
            $table->string('store_logo')->nullable();
            $table->string('commercial_register_no', 50)->nullable();
            $table->boolean('is_founding_seller')->default(false);
            $table->decimal('commission_rate', 4, 2)->default(5.00);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->integer('rating_count')->default(0);
            $table->enum('status', ['pending', 'approved', 'suspended'])->default('pending');
            $table->decimal('pending_commission_balance', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_profiles');
    }
};