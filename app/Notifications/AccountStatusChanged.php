<?php

namespace App\Notifications;

use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AccountStatusChanged extends Notification
{
    use Queueable;

    public function __construct(private readonly bool $isSuspended) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        if ($this->isSuspended) {
            $title = '🚫 Compte suspendu';
            $body  = 'Votre compte a été suspendu par un administrateur. Contactez le support pour plus d\'informations.';
        } else {
            $title = '✅ Compte réactivé';
            $body  = 'La suspension de votre compte a été levée. Vous pouvez à nouveau utiliser Minizon.';
        }

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                [
                    'type'        => 'account_status',
                    'is_suspended' => $this->isSuspended,
                ]
            );
        }

        return [
            'type'         => 'account_status',
            'title'        => $title,
            'body'         => $body,
            'is_suspended' => $this->isSuspended,
        ];
    }
}
