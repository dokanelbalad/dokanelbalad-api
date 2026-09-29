<?php

namespace App\Console\Commands;

use App\Models\CommissionTransaction;
use App\Models\VendorProfile;
use Illuminate\Console\Command;

class BlockOverdueVendors extends Command
{
    protected $signature = 'commissions:block-overdue';

    protected $description = 'يحظر البائعين اللي عليهم عمولة مستحقة من أكتر من 3 أيام وما اتسددتش';

    // مهلة السماح بالأيام قبل الحظر
    const GRACE_DAYS = 3;

    public function handle(): int
    {
        $cutoff = now()->subDays(self::GRACE_DAYS);

        // أي بائع عنده معاملة عمولة (من طلب أو من بيع خارج الموقع) لسه مش متحصّلة
        // ومرّ عليها أكتر من مهلة السماح
        $vendorIds = CommissionTransaction::where('type', 'cod_settled')
            ->where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->distinct()
            ->pluck('vendor_id');

        $blocked = VendorProfile::whereIn('id', $vendorIds)
            ->where('status', 'approved')
            ->where('pending_commission_balance', '>', 0)
            ->update(['status' => 'blocked']);

        $this->info("تم حظر {$blocked} بائع بسبب عمولات متأخرة أكتر من " . self::GRACE_DAYS . ' أيام.');

        return self::SUCCESS;
    }
}
