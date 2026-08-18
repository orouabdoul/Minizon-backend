<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewBookingRequest extends Notification
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
        $name      = trim(($profile?->first_name ?? '') . ' ' . ($profile?->last_name ?? '')) ?: 'Un passager';

        $title = '🔔 Nouvelle demande de réservation';
        $body  = "{$name} souhaite réserver {$this->booking->seats_booked} place(s) pour {$trip->departure_city} → {$trip->arrival_city}.";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'         => 'new_booking_request',
                    'booking_uuid' => $this->booking->uuid,
                    'trip_uuid'    => $trip->uuid,
                ]
            );
        }

        return [
            'type'         => 'new_booking_request',
            'title'        => $title,
            'body'         => $body,
            'booking_uuid' => $this->booking->uuid,
            'trip_uuid'    => $trip->uuid,
        ];
    }
}
