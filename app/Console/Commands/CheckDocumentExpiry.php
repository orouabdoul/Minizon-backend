<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Models\Vehicle;
use App\Notifications\DocumentExpiringSoon;
use Illuminate\Console\Command;

/**
 * Vérifie chaque matin les documents arrivant à expiration.
 * Envoie une alerte FCM au conducteur à J-30, J-15 et J-7.
 */
class CheckDocumentExpiry extends Command
{
    protected $signature   = 'minizon:check-document-expiry';
    protected $description = 'Envoie les alertes FCM pour les documents conducteur/véhicule qui expirent bientôt.';

    private const ALERT_DAYS = [30, 15, 7];

    public function handle(): void
    {
        $sent = 0;

        foreach (self::ALERT_DAYS as $days) {
            $target = now()->addDays($days)->toDateString();

            // ── Permis de conduire ──────────────────────────────────────────
            Profile::with('user')
                ->whereDate('license_expiry_date', $target)
                ->whereNotNull('license_expiry_date')
                ->get()
                ->each(function (Profile $profile) use ($days, $target, &$sent) {
                    try {
                        $profile->user->notify(new DocumentExpiringSoon(
                            documentLabel: 'Permis de conduire',
                            daysLeft: $days,
                            expiryDate: $target,
                        ));
                        $sent++;
                    } catch (\Throwable $e) {
                        $this->warn("Erreur permis user#{$profile->user_id} : {$e->getMessage()}");
                    }
                });

            // ── Assurance véhicule ──────────────────────────────────────────
            Vehicle::with('user')
                ->whereDate('insurance_expiry_date', $target)
                ->whereNotNull('insurance_expiry_date')
                ->get()
                ->each(function (Vehicle $vehicle) use ($days, $target, &$sent) {
                    try {
                        $vehicle->user->notify(new DocumentExpiringSoon(
                            documentLabel: 'Assurance véhicule',
                            daysLeft: $days,
                            expiryDate: $target,
                        ));
                        $sent++;
                    } catch (\Throwable $e) {
                        $this->warn("Erreur assurance véhicule#{$vehicle->id} : {$e->getMessage()}");
                    }
                });

            // ── Visite technique ────────────────────────────────────────────
            Vehicle::with('user')
                ->whereDate('technical_inspection_expiry_date', $target)
                ->whereNotNull('technical_inspection_expiry_date')
                ->get()
                ->each(function (Vehicle $vehicle) use ($days, $target, &$sent) {
                    try {
                        $vehicle->user->notify(new DocumentExpiringSoon(
                            documentLabel: 'Visite technique',
                            daysLeft: $days,
                            expiryDate: $target,
                        ));
                        $sent++;
                    } catch (\Throwable $e) {
                        $this->warn("Erreur visite véhicule#{$vehicle->id} : {$e->getMessage()}");
                    }
                });
        }

        $this->info("{$sent} alerte(s) d'expiration envoyée(s).");
    }
}
