<?php

namespace App\Console\Commands;

use App\Models\CommissionTransaction;
use Illuminate\Console\Command;

class ExpireSaleConfirmations extends Command
{
    protected $signature = 'commissions:expire-confirmations';

    protected $description = 'يحوّل تأكيدات البيع اللي فات ميعادها (24 ساعة) من غير رد المشتري لحالة "منتهية" عشان تتراجع من الإدارة';

    public function handle(): int
    {
        $count = CommissionTransaction::where('buyer_confirmation', 'pending')
            ->where('confirmation_deadline', '<', now())
            ->update(['buyer_confirmation' => 'expired']);

        $this->info("تم تحويل {$count} بيع لحالة منتهي المهلة، وهتظهر في قائمة المراجعة بالإدارة.");

        return self::SUCCESS;
    }
}
