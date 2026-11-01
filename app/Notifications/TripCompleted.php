<?php

namespace App\Notifications;

use App\Models\Trip;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TripCompleted extends Notification
{
    use Queueable;

    public function __construct(private readonly Trip $trip, private readonly string $recipientRole = 'passenger') {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        if ($this->recipientRole === 'driver') {
            $title = '✅ Trajet terminé — Validation en cours';
            $body  = "Trajet {$this->trip->departure_city} → {$this->trip->arrival_city} terminé. Les fonds seront libérés sous 24h si aucun litige n'est ouvert.";
        } else {
            $title = '🏁 Vous êtes arrivé(e) !';
            $body  = "Trajet {$this->trip->departure_city} → {$this->trip->arrival_city} terminé. N'oubliez pas de noter votre conducteur.";
        }

        // Pour le passager : retrouver l'UUID de sa réservation pour la navigation côté app
        $bookingUuid = null;
        if ($this->recipientRole === 'passenger') {
            $bookingUuid = \App\Models\Booking::where('trip_id', $this->trip->id)
                ->where('passenger_id', $notifiable->id)
                ->where('status', 'accepted')
                ->value('uuid');
        }

        if ($notifiable->fcm_token) {
            $fcmPayload = [
                'type'      => 'trip_completed',
                'trip_uuid' => $this->trip->uuid,
            ];
            if ($bookingUuid) {
                $fcmPayload['booking_uuid'] = $bookingUuid;
            }
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                $fcmPayload
            );
        }

        $dbPayload = [
            'type'      => 'trip_completed',
            'title'     => $title,
            'body'      => $body,
            'trip_uuid' => $this->trip->uuid,
            'from'      => $this->trip->departure_city,
            'to'        => $this->trip->arrival_city,
        ];
        if ($bookingUuid) {
            $dbPayload['booking_uuid'] = $bookingUuid;
        }

        return $dbPayload;
    }
}
