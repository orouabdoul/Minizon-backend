<?php

namespace App\Notifications;

use App\Models\Trip;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TripStarted extends Notification
{
    use Queueable;

    public function __construct(private readonly Trip $trip) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $title = '🚗 Votre trajet a commencé !';
        $body  = "Le conducteur a démarré le trajet {$this->trip->departure_city} → {$this->trip->arrival_city}. Préparez-vous !";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'      => 'trip_started',
                    'trip_uuid' => $this->trip->uuid,
                ]
            );
        }

        return [
            'type'      => 'trip_started',
            'title'     => $title,
            'body'      => $body,
            'trip_uuid' => $this->trip->uuid,
            'from'      => $this->trip->departure_city,
            'to'        => $this->trip->arrival_city,
        ];
    }
}
