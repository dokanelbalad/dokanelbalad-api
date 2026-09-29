<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\BlockOverdueVendors;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// يحظر تلقائياً أي بائع عليه عمولة متأخرة من 3 أيام وما سددهاش، كل يوم الساعة 3 الفجر
Schedule::command(BlockOverdueVendors::class)->dailyAt('03:00');
