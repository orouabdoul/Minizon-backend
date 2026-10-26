<?php

namespace App\Notifications;

use App\Models\Trip;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DriverEnRoute extends Notification
{
    use Queueable;

    public function __construct(private readonly Trip $trip) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $from  = $this->trip->departure_city;
        $to    = $this->trip->arrival_city;
        $title = '🚗 Votre conducteur est en route';
        $body  = "Votre conducteur arrive pour le trajet {$from} → {$to}. Veuillez patienter encore quelques minutes.";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'      => 'driver_en_route',
                    'trip_uuid' => $this->trip->uuid,
                ]
            );
        }

        return [
            'type'      => 'driver_en_route',
            'title'     => $title,
            'body'      => $body,
            'trip_uuid' => $this->trip->uuid,
        ];
    }
}
