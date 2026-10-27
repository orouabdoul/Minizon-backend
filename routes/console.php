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

// Purge des comptes sans profil complété après 20 min — toutes les minutes
Schedule::command('minizon:purge-incomplete-users')->everyMinute();

// Alertes expiration documents conducteur/véhicule — chaque matin à 8h
Schedule::command('minizon:check-document-expiry')->dailyAt('08:00');

// Lever les suspensions auto expirées — chaque matin à 8h01
Schedule::command('minizon:lift-expired-suspensions')->dailyAt('08:01');
