<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Trip;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PassengerNoShow extends Notification
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
        $from = $this->trip->departure_city;
        $to   = $this->trip->arrival_city;

        $title = '⚠️ Absence enregistrée';
        $body  = "Vous avez été marqué absent pour le trajet {$from} → {$to}. Votre paiement a été libéré au conducteur en guise d'indemnisation.";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'         => 'passenger_no_show',
                    'trip_uuid'    => $this->trip->uuid,
                    'booking_uuid' => $this->booking->uuid,
                ]
            );
        }

        return [
            'type'         => 'passenger_no_show',
            'title'        => $title,
            'body'         => $body,
            'trip_uuid'    => $this->trip->uuid,
            'booking_uuid' => $this->booking->uuid,
        ];
    }
}
