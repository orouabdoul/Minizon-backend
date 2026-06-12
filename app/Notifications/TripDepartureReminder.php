<?php

namespace App\Notifications;

use App\Models\Trip;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Rappel envoyé aux passagers ~1h avant le départ du trajet.
 * Déclenché par la commande minizon:send-departure-reminders (toutes les 5 min).
 */
class TripDepartureReminder extends Notification
{
    use Queueable;

    public function __construct(private readonly Trip $trip) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $minutesLeft = (int) now()->diffInMinutes($this->trip->departure_time, false);
        $label       = $minutesLeft <= 60 ? "dans environ {$minutesLeft} min" : 'dans 1h';

        $title = '⏰ Départ imminent !';
        $body  = sprintf(
            'Votre trajet %s → %s part %s. Rendez-vous à %s.',
            $this->trip->departure_city,
            $this->trip->arrival_city,
            $label,
            $this->trip->departure_neighborhood
        );

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'      => 'departure_reminder',
                    'trip_uuid' => $this->trip->uuid,
                ]
            );
        }

        return [
            'type'      => 'departure_reminder',
            'title'     => $title,
            'body'      => $body,
            'trip_uuid' => $this->trip->uuid,
            'departure_time' => $this->trip->departure_time,
        ];
    }
}
