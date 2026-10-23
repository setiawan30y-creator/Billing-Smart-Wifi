<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduler ALMARA Billing — jalankan `php artisan schedule:work` (dev) atau cron `schedule:run` tiap menit (produksi).
| @2026-Alifiandra
*/
Schedule::command('billing:generate-invoices')->dailyAt('00:30')->withoutOverlapping()->onOneServer();
Schedule::command('billing:send-reminders')->dailyAt('09:00')->withoutOverlapping()->onOneServer();
Schedule::command('saas:snapshot-usage')->dailyAt('23:50')->withoutOverlapping()->onOneServer();
Schedule::command('monitoring:collect')->everyMinute()->withoutOverlapping(10)->onOneServer();
Schedule::command('monitoring:prune')->dailyAt('03:10')->onOneServer();
Schedule::command('almara:heartbeat')->everyMinute();
Schedule::command('backups:run --prune')->weeklyOn(0, '02:00')->withoutOverlapping()->onOneServer();
