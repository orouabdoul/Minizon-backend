<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Libération automatique des fonds en escrow — toutes les minutes
Schedule::command('minizon:release-funds')->everyMinute();

// Rappels de départ push FCM — vérification toutes les 5 min
// Notifie les passagers dont le trajet part dans 55–65 min
Schedule::command('minizon:send-departure-reminders')->everyFiveMinutes();
