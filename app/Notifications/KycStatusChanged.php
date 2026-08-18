<?php

namespace App\Notifications;

use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class KycStatusChanged extends Notification
{
    use Queueable;

    public function __construct(private readonly string $status) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $messages = [
            'approved' => [
                'title' => '🎉 Compte vérifié !',
                'body'  => 'Votre dossier KYC a été validé. Vous pouvez maintenant utiliser toutes les fonctionnalités de Minizon.',
            ],
            'rejected' => [
                'title' => '❌ Dossier KYC rejeté',
                'body'  => "Votre dossier n'a pas pu être validé. Veuillez soumettre des documents conformes ou contacter le support.",
            ],
        ];

        $msg = $messages[$this->status] ?? [
            'title' => 'Mise à jour de votre dossier',
            'body'  => "Statut de votre dossier : {$this->status}.",
        ];

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $msg['title'],
                $msg['body'],
                [
                    'type'   => 'kyc_status',
                    'status' => $this->status,
                ]
            );
        }

        return [
            'type'   => 'kyc_status',
            'title'  => $msg['title'],
            'body'   => $msg['body'],
            'status' => $this->status,
        ];
    }
}
