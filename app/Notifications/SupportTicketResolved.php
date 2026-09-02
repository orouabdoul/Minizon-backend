<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SupportTicketResolved extends Notification
{
    use Queueable;

    public function __construct(private readonly SupportTicket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $subject = $this->ticket->subject;

        $title = '✅ Votre ticket a été résolu';
        $body  = "Votre demande « {$subject} » a été traitée par notre équipe. Consultez la réponse dans le centre d'aide.";

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'        => 'ticket_resolved',
                    'ticket_uuid' => $this->ticket->uuid,
                ]
            );
        }

        return [
            'type'        => 'ticket_resolved',
            'title'       => $title,
            'body'        => $body,
            'ticket_uuid' => $this->ticket->uuid,
        ];
    }
}
