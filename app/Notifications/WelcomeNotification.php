<?php

namespace App\Notifications;

use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $role) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $isDriver = $this->role === 'driver';

        $title = $isDriver
            ? '🎉 Bienvenue sur Minizon !'
            : '🎉 Bienvenue sur Minizon !';

        $body = $isDriver
            ? 'Votre dossier est soumis. Notre équipe vérifie vos documents sous 24–48h. Vous serez notifié dès validation.'
            : 'Votre compte est créé. Recherchez un trajet et réservez votre place en quelques secondes !';

        if ($notifiable->fcm_token) {
            app(FcmService::class)->send(
                $notifiable->fcm_token,
                $title,
                $body,
                ['type' => 'welcome', 'role' => $this->role]
            );
        }

        return [
            'type'  => 'welcome',
            'title' => $title,
            'body'  => $body,
            'role'  => $this->role,
        ];
    }
}
