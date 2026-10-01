<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * آلية تأكيد المشتري للبيع اللي بيتم بره الموقع (زرار "تم البيع"):
     * - conversation_id: المحادثة اللي البائع اختارها كدليل على هوية المشتري
     * - buyer_confirmation: pending (مستني رد) / confirmed / rejected / expired (فات الميعاد) / admin_approved / dismissed
     * - confirmation_deadline: بعد 24 ساعة من "تم البيع"، لو محدش رد بتتحول لـ expired
     * - buyer_response_at: تاريخ رد المشتري (أو قرار الإدارة)
     */
    public function up(): void
    {
        Schema::table('commission_transactions', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->after('order_id')
                ->constrained('conversations')->nullOnDelete();
            $table->string('buyer_confirmation')->nullable()->after('status');
            $table->timestamp('confirmation_deadline')->nullable()->after('buyer_confirmation');
            $table->timestamp('buyer_response_at')->nullable()->after('confirmation_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('commission_transactions', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropColumn(['conversation_id', 'buyer_confirmation', 'confirmation_deadline', 'buyer_response_at']);
        });
    }
};
