<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Notifications\TripDepartureReminder;
use Illuminate\Console\Command;

/**
 * Envoie des rappels push aux passagers dont le trajet part dans les 55–65 prochaines minutes.
 * Lancé toutes les 5 minutes par le scheduler.
 * La fenêtre ±5 min garantit qu'un rappel est envoyé exactement une fois par trajet.
 */
class SendDepartureReminders extends Command
{
    protected $signature   = 'minizon:send-departure-reminders';
    protected $description = 'Envoie les rappels FCM 1h avant le départ aux passagers avec booking accepté.';

    public function handle(): void
    {
        // Fenêtre : départs prévus entre 55 et 65 minutes à partir de maintenant
        $from = now()->addMinutes(55);
        $to   = now()->addMinutes(65);

        $bookings = Booking::with(['passenger', 'trip'])
            ->where('status', 'accepted')
            ->whereHas('trip', fn ($q) =>
                $q->whereBetween('departure_time', [$from, $to])
                  ->where('status', 'pending')
            )
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('Aucun rappel à envoyer.');
            return;
        }

        $sent = 0;

        foreach ($bookings as $booking) {
            try {
                $booking->passenger->notify(new TripDepartureReminder($booking->trip));
                $sent++;
            } catch (\Throwable $e) {
                $this->warn("Erreur notification passager #{$booking->passenger_id} : {$e->getMessage()}");
            }
        }

        $this->info("{$sent} rappel(s) de départ envoyé(s).");
    }
}
