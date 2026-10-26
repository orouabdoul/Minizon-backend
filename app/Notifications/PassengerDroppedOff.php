<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Trip;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PassengerDroppedOff extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Trip    $trip,
        private readonly Booking $booking,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $from  = $this->booking->dropoff_city ?? $this->trip->arrival_city;
        $title = '🏁 Vous avez été déposé(e)';
        $body  = "Trajet {$this->trip->departure_city} → {$from} terminé. Les fonds seront libérés dans 1h si aucun litige n'est ouvert.";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'         => 'passenger_dropped_off',
                    'trip_uuid'    => $this->trip->uuid,
                    'booking_uuid' => $this->booking->uuid,
                ]
            );
        }

        return [
            'type'         => 'passenger_dropped_off',
            'title'        => $title,
            'body'         => $body,
            'trip_uuid'    => $this->trip->uuid,
            'booking_uuid' => $this->booking->uuid,
        ];
    }
}
