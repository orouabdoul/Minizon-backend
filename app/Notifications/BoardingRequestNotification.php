<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Envoyée au passager quand le conducteur appuie sur "Embarquer".
 * Le passager voit un plein écran de confirmation dans l'app.
 */
class BoardingRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly ?float  $driverLat = null,
        private readonly ?float  $driverLng = null,
        private readonly string  $expiresAt = '',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $trip  = $this->booking->trip;
        $route = $trip ? "{$trip->departure_city} → {$trip->arrival_city}" : 'votre trajet';

        $title = '🚗 Confirmation d\'embarquement';
        $body  = "Votre conducteur indique que vous êtes à bord pour {$route}. Confirmez-vous ?";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                array_filter([
                    'type'         => 'boarding_request',
                    'booking_uuid' => $this->booking->uuid,
                    'trip_uuid'    => $trip?->uuid,
                    'driver_lat'   => $this->driverLat !== null ? (string) $this->driverLat : null,
                    'driver_lng'   => $this->driverLng !== null ? (string) $this->driverLng : null,
                    'expires_at'   => $this->expiresAt,
                    'sound'        => 'boarding_sound',
                ])
            );
        }

        return [
            'type'         => 'boarding_request',
            'title'        => $title,
            'body'         => $body,
            'booking_uuid' => $this->booking->uuid,
            'trip_uuid'    => $trip?->uuid,
            'expires_at'   => $this->expiresAt,
        ];
    }
}
