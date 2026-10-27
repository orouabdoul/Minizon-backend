<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DriverAutoSuspended extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $reason,
        private readonly string $suspendedUntil,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return (new FcmMessage)
            ->setTitle('Compte suspendu 7 jours')
            ->setBody("Votre compte a été suspendu jusqu'au {$this->suspendedUntil} en raison de : {$this->reason}. Contactez le support pour toute question.")
            ->setData([
                'type'           => 'driver_auto_suspended',
                'reason'         => $this->reason,
                'suspended_until'=> $this->suspendedUntil,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'driver_auto_suspended',
            'reason'          => $this->reason,
            'suspended_until' => $this->suspendedUntil,
        ];
    }
}
