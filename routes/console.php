<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Notificaciones push de ventanas de examen (MPM-V4 §6.8). En producción
// requiere la entrada de cron de Laravel: `* * * * * php artisan schedule:run`.
Schedule::command('exams:push-due')->everyMinute()->withoutOverlapping();
