<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('buyer_commission_rate_applied', 4, 2)->default(0)->after('commission_amount');
            $table->decimal('buyer_commission_amount', 10, 2)->default(0)->after('buyer_commission_rate_applied');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['buyer_commission_rate_applied', 'buyer_commission_amount']);
        });
    }
};