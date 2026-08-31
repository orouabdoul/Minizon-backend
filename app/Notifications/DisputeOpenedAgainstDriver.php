<?php

namespace App\Notifications;

use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DisputeOpenedAgainstDriver extends Notification
{
    use Queueable;

    public function __construct(
        private readonly mixed  $dispute,
        private readonly string $tripRoute,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $title = '⚠️ Litige ouvert sur votre trajet';
        $body  = "Un passager a ouvert un litige concernant votre trajet {$this->tripRoute}. Notre équipe va examiner la situation.";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'       => 'dispute_against_driver',
                    'dispute_id' => (string) $this->dispute->id,
                ]
            );
        }

        return [
            'type'       => 'dispute_against_driver',
            'title'      => $title,
            'body'       => $body,
            'dispute_id' => $this->dispute->id,
            'trip_route' => $this->tripRoute,
        ];
    }
}
