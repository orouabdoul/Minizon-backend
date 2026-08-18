<?php

namespace App\Notifications;

use App\Models\Dispute;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewDisputeFiled extends Notification
{
    use Queueable;

    public function __construct(private readonly Dispute $dispute) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $booking = $this->dispute->booking ?? null;
        $trip    = $booking?->trip;
        $route   = $trip ? "{$trip->departure_city} → {$trip->arrival_city}" : 'trajet inconnu';

        $title = '⚠️ Nouveau litige ouvert';
        $body  = "Un utilisateur a ouvert un litige pour le {$route}. Intervention requise.";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'       => 'new_dispute',
                    'dispute_id' => $this->dispute->id,
                ]
            );
        }

        return [
            'type'       => 'new_dispute',
            'title'      => $title,
            'body'       => $body,
            'dispute_id' => $this->dispute->id,
        ];
    }
}
