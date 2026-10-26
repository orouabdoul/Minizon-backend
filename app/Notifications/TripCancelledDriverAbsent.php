<?php

namespace App\Notifications;

use App\Models\Trip;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TripCancelledDriverAbsent extends Notification
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
        $title = '❌ Trajet annulé — Conducteur absent';
        $body  = "Le trajet {$from} → {$to} a été annulé car le conducteur ne s'est pas présenté. Votre remboursement sera traité sous 24h.";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'      => 'trip_cancelled_driver_absent',
                    'trip_uuid' => $this->trip->uuid,
                ]
            );
        }

        return [
            'type'      => 'trip_cancelled_driver_absent',
            'title'     => $title,
            'body'      => $body,
            'trip_uuid' => $this->trip->uuid,
        ];
    }
}
