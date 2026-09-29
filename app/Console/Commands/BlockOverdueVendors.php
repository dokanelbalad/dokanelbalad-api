<?php

namespace App\Console\Commands;

use App\Models\CommissionTransaction;
use App\Models\VendorProfile;
use Illuminate\Console\Command;

class BlockOverdueVendors extends Command
{
    protected $signature = 'commissions:block-overdue';

    protected $description = 'يجمّد حساب أي بائع عليه عمولة مستحقة من أكتر من 3 أيام وما اتسددتش';

    // مهلة السماح بالأيام قبل التجميد
    const GRACE_DAYS = 3;

    public function handle(): int
    {
        $cutoff = now()->subDays(self::GRACE_DAYS);

        // أي بائع عنده معاملة عمولة (من طلب أو من بيع خارج الموقع اتبلّغ عنه) لسه مش متحصّلة
        // ومرّ عليها أكتر من مهلة السماح
        $vendorIds = CommissionTransaction::where('type', 'cod_settled')
            ->where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->distinct()
            ->pluck('vendor_id');

        $vendors = VendorProfile::with('user')
            ->whereIn('id', $vendorIds)
            ->where('pending_commission_balance', '>', 0)
            ->get();

        $frozen = 0;

        foreach ($vendors as $vendor) {
            $user = $vendor->user;

            // مفيش حساب مرتبط، أو الحساب مجمّد أو مغلق أصلاً - سيبه زي ما هو
            if (! $user || $user->isFrozen()) {
                continue;
            }

            $freezeCount = $user->commission_freeze_count + 1;
            $banned = $freezeCount >= 2; // المرة التانية اللي الحساب بيتجمد فيها بسبب العمولة = غلق نهائي

            $user->update([
                'commission_freeze_count' => $freezeCount,
                'permanently_banned' => $banned,
                'frozen_reason' => 'commission_overdue',
                'frozen_until' => null, // مفتوح لحد السداد الكامل، مش بيتفك بمرور وقت
            ]);

            $frozen++;
        }

        $this->info("تم تجميد {$frozen} حساب بسبب عمولات متأخرة أكتر من " . self::GRACE_DAYS . ' أيام.');

        return self::SUCCESS;
    }
}
