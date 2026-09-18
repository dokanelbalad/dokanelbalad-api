<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendor_profiles')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories');
            $table->string('title', 200);
            $table->text('description');
            $table->enum('condition', ['new', 'used', 'like_new']);
            $table->decimal('price', 10, 2);
            $table->integer('quantity')->default(1);
            $table->string('governorate', 100);
            $table->enum('status', ['active', 'sold', 'paused', 'rejected'])->default('active');
            $table->integer('views_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('featured_until')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'status']);
            $table->index('governorate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};