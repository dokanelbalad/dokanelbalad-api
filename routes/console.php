<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\BlockOverdueVendors;
use App\Console\Commands\ExpireSaleConfirmations;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// يجمّد تلقائياً أي بائع عليه عمولة متأخرة من 3 أيام وما سددهاش، كل يوم الساعة 3 الفجر
Schedule::command(BlockOverdueVendors::class)->dailyAt('03:00');

// يحوّل تأكيدات البيع اللي فات ميعادها (24 ساعة) لحالة "منتهية" عشان تتراجع من الإدارة
Schedule::command(ExpireSaleConfirmations::class)->hourly();
