<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * نظام تجميد الحساب الموحّد (بائع أو مشتري):
     * - frozen_reason: سبب التجميد الحالي - phone_sharing / commission_overdue / admin_manual / فاضي (مش مجمّد)
     * - frozen_until: تاريخ انتهاء التجميد التلقائي. فاضي (null) = تجميد مفتوح لحد ما يترفع يدوي/بالسداد
     * - phone_violation_strikes: عداد محاولات مشاركة رقم تليفون في المحادثات (1، 2 تحذير - 3 منع - 4 تجميد)
     * - phone_freeze_count: كام مرة اتجمد الحساب بسبب الأرقام (المرة التانية = غلق نهائي)
     * - commission_freeze_count: كام مرة اتجمد الحساب بسبب عمولة متأخرة (المرة التانية = غلق نهائي)
     * - permanently_banned: غلق نهائي، مراجعة إدارية يدوية بس
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('frozen_reason')->nullable()->after('is_active');
            $table->timestamp('frozen_until')->nullable()->after('frozen_reason');
            $table->unsignedTinyInteger('phone_violation_strikes')->default(0)->after('frozen_until');
            $table->unsignedTinyInteger('phone_freeze_count')->default(0)->after('phone_violation_strikes');
            $table->unsignedTinyInteger('commission_freeze_count')->default(0)->after('phone_freeze_count');
            $table->boolean('permanently_banned')->default(false)->after('commission_freeze_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'frozen_reason',
                'frozen_until',
                'phone_violation_strikes',
                'phone_freeze_count',
                'commission_freeze_count',
                'permanently_banned',
            ]);
        });
    }
};
