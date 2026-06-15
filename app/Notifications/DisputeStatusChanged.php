<?php

namespace App\Notifications;

use App\Models\Dispute;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DisputeStatusChanged extends Notification
{
    use Queueable;

    public function __construct(private readonly Dispute $dispute) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $messages = [
            'investigating'          => ['Litige en cours d\'investigation', 'Un administrateur examine votre litige.'],
            'resolved_refunded'      => ['Litige résolu — Remboursement', 'Votre paiement vous a été remboursé suite à la résolution du litige.'],
            'resolved_paid_to_driver' => ['Litige résolu — Paiement validé', 'Le litige a été résolu en faveur du conducteur.'],
        ];

        [$title, $body] = $messages[$this->dispute->status] ?? ['Litige mis à jour', 'Le statut de votre litige a changé.'];

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                ['type' => 'dispute_update', 'dispute_id' => (string) $this->dispute->id]
            );
        }

        return [
            'type'       => 'dispute_update',
            'dispute_id' => $this->dispute->id,
            'status'     => $this->dispute->status,
            'title'      => $title,
            'body'       => $body,
        ];
    }
}
