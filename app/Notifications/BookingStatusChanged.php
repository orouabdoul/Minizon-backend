<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifie le passager quand son booking change de statut :
 * accepted → "Bonne nouvelle !"
 * rejected → "Désolé…"
 * cancelled → "Annulation"
 */
class BookingStatusChanged extends Notification
{
    use Queueable;

    public function __construct(private readonly Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $trip   = $this->booking->trip;
        $status = $this->booking->status;

        $messages = [
            'accepted' => [
                'title' => '✅ Réservation acceptée !',
                'body'  => "Votre place sur le trajet {$trip->departure_city} → {$trip->arrival_city} a été confirmée.",
            ],
            'rejected' => [
                'title' => '❌ Réservation refusée',
                'body'  => "Le conducteur n'a pas pu confirmer votre place ({$trip->departure_city} → {$trip->arrival_city}).",
            ],
            'cancelled' => [
                'title' => '🚫 Réservation annulée',
                'body'  => "La réservation pour {$trip->departure_city} → {$trip->arrival_city} a été annulée.",
            ],
        ];

        $msg = $messages[$status] ?? ['title' => 'Mise à jour réservation', 'body' => "Statut : {$status}"];

        // Envoi push FCM en parallèle
        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $msg['title'],
                $msg['body'],
                [
                    'type'         => 'booking_status',
                    'booking_uuid' => $this->booking->uuid,
                    'trip_uuid'    => $trip->uuid,
                    'status'       => $status,
                ]
            );
        }

        return [
            'type'         => 'booking_status',
            'title'        => $msg['title'],
            'body'         => $msg['body'],
            'booking_uuid' => $this->booking->uuid,
            'trip_uuid'    => $trip->uuid,
            'status'       => $status,
        ];
    }
}
