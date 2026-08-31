<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PassengerCancelledBooking extends Notification
{
    use Queueable;

    public function __construct(private readonly Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $trip      = $this->booking->trip;
        $passenger = $this->booking->passenger;
        $profile   = $passenger?->profile;

        $passengerName = $profile
            ? trim("{$profile->first_name} {$profile->last_name}")
            : ($passenger?->phone ?? 'Un passager');

        $route = $trip ? "{$trip->departure_city} → {$trip->arrival_city}" : 'votre trajet';
        $seats = $this->booking->seats_booked;

        $title = '🚫 Réservation annulée';
        $body  = "{$passengerName} a annulé sa réservation ({$seats} place(s)) sur {$route}.";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'         => 'passenger_cancelled',
                    'booking_uuid' => $this->booking->uuid,
                    'trip_uuid'    => $trip?->uuid ?? '',
                ]
            );
        }

        return [
            'type'         => 'passenger_cancelled',
            'title'        => $title,
            'body'         => $body,
            'booking_uuid' => $this->booking->uuid,
            'trip_uuid'    => $trip?->uuid,
            'seats'        => $seats,
        ];
    }
}
