<?php

use App\Console\Commands\PosBackupCommand;
use Illuminate\Support\Facades\Schedule;

/*
| The VPS crontab only needs one line:
|   * * * * * cd /var/www/zion-pos/server && php artisan schedule:run >> /dev/null 2>&1
| Everything below runs from that.
*/

Schedule::command(PosBackupCommand::class, ['--prune=30'])
    ->dailyAt('01:30')
    ->onOneServer()
    ->emailOutputOnFailure(env('POS_ALERT_EMAIL', 'admin@example.com'));
