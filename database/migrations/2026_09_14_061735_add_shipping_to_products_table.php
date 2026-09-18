<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('shipping_fee', 8, 2)->default(0)->after('price');
            $table->enum('shipping_paid_by', ['vendor', 'buyer'])->default('buyer')->after('shipping_fee');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['shipping_fee', 'shipping_paid_by']);
        });
    }
};