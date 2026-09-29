<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * order_id يبقى اختياري (nullable)، لأن البيع اللي بيتم بره الموقع (البيع اللي بيبلّغ
     * عنه البائع بزرار "تم البيع") مفيهوش طلب (Order) أصلاً. بنسيب type بنفس القيم
     * الموجودة (بنستخدم 'cod_settled' للبيع بره الموقع كمان، وبيتفرّق عن الطلب الحقيقي
     * بكون order_id فاضي)، عشان منحتاجش نلمس الـ check constraint خالص.
     *
     * الطريقة دي بتشتغل زي ما هي على SQLite (محلياً) وPostgres (اللايف) من غير SQL خام.
     */
    public function up(): void
    {
        Schema::table('commission_transactions', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('commission_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
        });

        Schema::table('commission_transactions', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commission_transactions', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('commission_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
        });

        Schema::table('commission_transactions', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }
};
