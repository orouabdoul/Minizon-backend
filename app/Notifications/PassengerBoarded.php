<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifie le conducteur qu'un passager a confirmé être à bord.
 * Déclenchée par POST /api/passenger/bookings/{uuid}/pickup-confirm.
 */
class PassengerBoarded extends Notification
{
    use Queueable;

    public function __construct(private readonly Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $passenger = $this->booking->passenger;
        $profile   = $passenger?->profile;
        $name      = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? ''))
            ?: ($passenger?->phone ?? 'Un passager');

        $trip  = $this->booking->trip;
        $route = $trip ? "{$trip->departure_city} → {$trip->arrival_city}" : 'votre trajet';

        $title = '✅ Passager à bord';
        $body  = "{$name} a confirmé être à bord pour {$route}.";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'         => 'passenger_boarded',
                    'booking_uuid' => $this->booking->uuid,
                    'trip_uuid'    => $trip?->uuid,
                ]
            );
        }

        return [
            'type'         => 'passenger_boarded',
            'title'        => $title,
            'body'         => $body,
            'booking_uuid' => $this->booking->uuid,
            'trip_uuid'    => $trip?->uuid,
            'passenger'    => $name,
        ];
    }
}
