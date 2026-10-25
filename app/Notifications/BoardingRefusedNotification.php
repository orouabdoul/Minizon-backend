<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Envoyée au conducteur quand le passager refuse l'embarquement.
 */
class BoardingRefusedNotification extends Notification
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

        $title = '⚠️ Embarquement refusé';
        $body  = "{$name} a refusé la confirmation d'embarquement. Un ticket support a été créé.";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'         => 'boarding_refused',
                    'booking_uuid' => $this->booking->uuid,
                    'trip_uuid'    => $this->booking->trip?->uuid,
                ]
            );
        }

        return [
            'type'         => 'boarding_refused',
            'title'        => $title,
            'body'         => $body,
            'booking_uuid' => $this->booking->uuid,
            'trip_uuid'    => $this->booking->trip?->uuid,
            'passenger'    => $name,
        ];
    }
}
