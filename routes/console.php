<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Las solicitudes también finalizan un intento libre si venció entre dos ejecuciones.
Schedule::command('exams:close-due-free')->everyMinute()->withoutOverlapping();

// Notificaciones push de ventanas de examen (MPM-V3 §6.8). En producción
// requiere la entrada de cron de Laravel: `* * * * * php artisan schedule:run`.
Schedule::command('exams:push-due')->everyMinute()->withoutOverlapping();

// Vigencia de grupos cerrados: aviso a 30 días y eliminación a los 12 meses (RF-GRU-015, CAM-009).
Schedule::command('groups:purge-expired')->dailyAt('03:00')->withoutOverlapping();

// Renovación mensual domiciliada con reintentos de 3 días (RF-SUS-005).
Schedule::command('subscriptions:renew')->dailyAt('02:00')->withoutOverlapping();
