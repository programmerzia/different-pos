<?php

declare(strict_types=1);

use App\Actions\Backup\RunScheduledBackup;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Nightly installation backup (config/backup.php): recorded in `backups` and run on the queue.
| `backup:monitor` raises UnhealthyBackupWasFound when the newest archive is older than a day.
*/
Schedule::call(fn () => app(RunScheduledBackup::class)->handle())
    ->dailyAt((string) config('backup.schedule.daily_at', '02:00'))
    ->timezone((string) config('backup.schedule.timezone', 'Asia/Dhaka'))
    ->name('backup:nightly')
    ->withoutOverlapping();
Schedule::command('backup:monitor')->dailyAt('08:00')->timezone((string) config('backup.schedule.timezone', 'Asia/Dhaka'));
