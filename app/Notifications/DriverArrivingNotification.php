<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DriverArrivingNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $trip    = $this->booking->trip;
        $origin  = $trip?->departure_neighborhood ?? $trip?->departure_city ?? 'votre point de départ';

        $title = '🚗 Votre conducteur est arrivé !';
        $body  = "Votre conducteur vous attend à {$origin}. Préparez-vous à embarquer.";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'         => 'driver_approaching',
                    'booking_uuid' => $this->booking->uuid,
                    'trip_uuid'    => $trip?->uuid ?? '',
                ]
            );
        }

        return [
            'type'         => 'driver_approaching',
            'title'        => $title,
            'body'         => $body,
            'booking_uuid' => $this->booking->uuid,
            'trip_uuid'    => $trip?->uuid,
        ];
    }
}
